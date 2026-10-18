<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Models\User;
use App\Notifications\PacketSubmitted;
use App\Support\DriveMovFolders;
use App\Support\DriveWorkStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ArchiveSchoolPacket implements ShouldQueue
{
    use Queueable;

    public int $timeout = 80;

    public int $tries = 3;

    public function __construct(public int $userId, public int $assessmentId) {}

    public static function cacheKey(int $assessmentId): string
    {
        return 'drive-packet:'.$assessmentId;
    }

    public function handle(): void
    {
        $key = self::cacheKey($this->assessmentId);
        $current = DriveWorkStatus::get($key);
        if (($current['state'] ?? null) === 'queued') {
            $total = Assessment::query()->find($this->assessmentId)?->movs()->whereNotNull('path')->count() ?? 0;
            DriveWorkStatus::put($key, 'running', 'Sending files to Google Drive…', 0, $total);
        }

        $passes = 0;

        while ($passes < 80) {
            $passes++;
            $outcome = $this->step();
            if ($outcome !== 'again') {
                return;
            }

            if (config('queue.default') !== 'sync') {
                self::dispatch($this->userId, $this->assessmentId);

                return;
            }
        }

        DriveWorkStatus::put(
            self::cacheKey($this->assessmentId),
            'failed',
            'The packet was not sent. Google Drive did not take every file. Reconnect Drive and submit again.',
        );
    }

    public function failed(?Throwable $exception): void
    {
        $key = self::cacheKey($this->assessmentId);
        $assessment = Assessment::query()->find($this->assessmentId);
        if ($assessment?->status === 'submitted') {
            DriveWorkStatus::put($key, 'done', 'Packet submitted to Division. Files are in the school Google Drive folder.');

            return;
        }

        $current = DriveWorkStatus::get($key);
        if (! in_array($current['state'] ?? null, ['queued', 'running'], true)) {
            return;
        }

        DriveWorkStatus::put($key, 'failed', 'The packet was not sent. Google Drive did not take every file. Reconnect Drive and submit again.');
    }

    private function step(): string
    {
        $key = self::cacheKey($this->assessmentId);
        $user = User::query()->find($this->userId);
        $assessment = Assessment::query()->find($this->assessmentId);

        if (! $user || ! $assessment) {
            DriveWorkStatus::put($key, 'failed', 'The packet was not sent. Google Drive did not take every file. Reconnect Drive and submit again.');

            return 'stop';
        }

        $archived = DriveMovFolders::archivePacket($user, $assessment, 40);
        if ($archived === 'offline') {
            DriveWorkStatus::put($key, 'failed', 'Connect Google Drive, then submit again. The packet was not sent. Files stay on this server until Drive can store them.');

            return 'stop';
        }

        if ($archived === 'failed') {
            DriveWorkStatus::put($key, 'failed', 'The packet was not sent. Google Drive did not take every file. Reconnect Drive and submit again.');

            return 'stop';
        }

        if ($archived === 'partial') {
            $left = $assessment->movs()->whereNotNull('path')->count();
            $total = DriveWorkStatus::get($key)['total'] ?? $left;
            $done = max(0, $total - $left);
            DriveWorkStatus::put($key, 'running', "Sending files to Google Drive… {$done} of {$total}.", $done, $total);

            return 'again';
        }

        $assessment->refresh();
        if ($assessment->status !== 'submitted') {
            $assessment->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            $divisionAdmins = User::query()->where('role', 'division')->where('status', 'active')->get();
            if ($divisionAdmins->isNotEmpty()) {
                Notification::send($divisionAdmins, new PacketSubmitted($assessment->load('user')));
            }
        }

        $total = DriveWorkStatus::get($key)['total'] ?? $assessment->movs()->count();
        DriveWorkStatus::put($key, 'done', 'Packet submitted to Division. Files are in the school Google Drive folder.', $total, $total);

        return 'stop';
    }
}
