<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\DriveMovFolders;
use App\Support\DriveWorkStatus;
use App\Support\GoogleDriveClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CreateSchoolDriveFolders implements ShouldQueue
{
    use Queueable;

    public int $timeout = 80;

    public int $tries = 3;

    public function __construct(public int $userId, public string $schoolCode) {}

    public static function cacheKey(string $schoolCode): string
    {
        return 'drive-folders:'.$schoolCode;
    }

    public function handle(GoogleDriveClient $drive): void
    {
        $current = DriveWorkStatus::get(self::cacheKey($this->schoolCode));
        if (($current['state'] ?? null) === 'queued') {
            DriveWorkStatus::put(self::cacheKey($this->schoolCode), 'running', 'Creating school folders…');
        }

        $passes = 0;

        while ($passes < 80) {
            $passes++;
            $outcome = $this->step($drive);
            if ($outcome !== 'again') {
                return;
            }

            if (config('queue.default') !== 'sync') {
                self::dispatch($this->userId, $this->schoolCode);

                return;
            }
        }

        DriveWorkStatus::put(
            self::cacheKey($this->schoolCode),
            'failed',
            'Google Drive could not finish the school folders. Try again.',
        );
    }

    public function failed(?Throwable $exception): void
    {
        $current = DriveWorkStatus::get(self::cacheKey($this->schoolCode));
        if (($current['state'] ?? null) !== 'running') {
            return;
        }

        DriveWorkStatus::put(
            self::cacheKey($this->schoolCode),
            'failed',
            'Google Drive could not create the school folders. Try again.',
        );
    }

    private function step(GoogleDriveClient $drive): string
    {
        $user = User::query()->find($this->userId);
        $key = self::cacheKey($this->schoolCode);

        if (! $user || ! DriveMovFolders::writable($user->id)) {
            DriveWorkStatus::put($key, 'failed', 'Reconnect Drive so SmartTrack can create school folders.');

            return 'stop';
        }

        $placement = DriveMovFolders::placement($user);
        if (! $placement) {
            DriveWorkStatus::put($key, 'failed', 'Add the school name on Form data first. Folders are created as school year / school name inside each MOV folder.');

            return 'stop';
        }

        $result = $drive->ensureWithin(
            (string) Cache::get(DriveMovFolders::tokenCacheKey($user->id)),
            $user->packetSchoolCode(),
            $placement['year'],
            $placement['school'],
            40,
        );

        if ($result['error'] === 'scope') {
            Cache::forget(DriveMovFolders::scopeCacheKey($user->id));
            DriveWorkStatus::put($key, 'failed', 'Reconnect Drive so SmartTrack can create school folders.');

            return 'stop';
        }

        if ($result['error']) {
            DriveWorkStatus::put($key, 'failed', 'Google Drive could not create the school folders. Try again.');

            return 'stop';
        }

        if ($result['pending'] > 0) {
            $done = $result['created'] + $result['ready'];
            $total = $done + $result['pending'];
            DriveWorkStatus::put($key, 'running', "Creating school folders… {$done} of {$total}.", $done, $total);

            return 'again';
        }

        $finished = $result['created'] + $result['ready'];
        DriveWorkStatus::put(
            $key,
            'done',
            "School folders are ready: {$placement['year']} / {$placement['school']}. Created {$result['created']}, already there {$result['ready']}.",
            $finished,
            $finished,
        );

        return 'stop';
    }
}
