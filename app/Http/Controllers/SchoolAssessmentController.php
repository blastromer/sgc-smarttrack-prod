<?php

namespace App\Http\Controllers;

use App\Models\Mov;
use App\Models\User;
use App\Notifications\MovRemovalRequested;
use App\Notifications\PacketSubmitted;
use App\Support\AssessmentEngine;
use App\Support\FatCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolAssessmentController extends Controller
{
    public function dashboard(): Response
    {
        $assessment = AssessmentEngine::forSchool(request()->user());
        if (! $assessment) {
            return Inertia::render('school/Dashboard', $this->emptyDashboard());
        }

        $snap = AssessmentEngine::snapshot($assessment);
        $indicators = $assessment->indicators->sortBy('code')->values();
        $bars = $indicators->map(function ($indicator) use ($assessment) {
            $mov = $assessment->movs->firstWhere('indicator_code', $indicator->code);
            $state = match (true) {
                $mov?->status === 'returned' => ['value' => 'R', 'height' => 40, 'tone' => 'bad'],
                $indicator->answer === 'yes' => ['value' => 'Y', 'height' => 100, 'tone' => null],
                $indicator->answer === 'no' => ['value' => 'N', 'height' => 20, 'tone' => 'warn'],
                default => ['value' => '—', 'height' => 8, 'tone' => 'bad'],
            };

            return [
                'label' => $indicator->code,
                'value' => $state['value'],
                'height' => $state['height'],
                'tone' => $state['tone'],
            ];
        });

        $need = max(0, 10 - $snap['yes_count']);
        $width = (int) round(($snap['encoded'] / 12) * 100);
        $actions = [];
        if ($snap['encoded'] < 12) {
            $actions[] = '1. Encode remaining functionality indicators.';
        }
        if ($snap['returned_count'] > 0) {
            $actions[] = '2. Replace returned MOVs on MOV files.';
        } elseif ($snap['missing_count'] > 0) {
            $actions[] = '2. Upload missing Minimum MOVs.';
        }
        if ($snap['can_qa']) {
            $actions[] = '3. School Head QA, then submit to Division.';
        } elseif ($snap['can_submit']) {
            $actions[] = '3. Submit the packet to SDO Cadiz City.';
        }

        return Inertia::render('school/Dashboard', [
            'title' => 'School dashboard',
            'subtitle' => ($assessment->user->school_name ?: 'School').' · School ID '.($assessment->user->school_code ?: '—'),
            'chip' => $assessment->cycle?->deadline_at?->diffForHumans(),
            'kpis' => [
                ['label' => 'Indicators met', 'value' => $snap['yes_count'].' / 12', 'hint' => 'Need 10 to be functional', 'tone' => $snap['yes_count'] >= 10 ? null : 'warn'],
                ['label' => 'MOVs uploaded', 'value' => (string) $assessment->movs->filter->hasFile()->count(), 'hint' => 'Minimum + validity', 'tone' => null],
                ['label' => 'Returned', 'value' => (string) $snap['returned_count'], 'hint' => $snap['returned_count'] ? 'Replace flagged files' : 'None', 'tone' => $snap['returned_count'] ? 'bad' : null],
                ['label' => 'Self-score', 'value' => $assessment->result ? ucfirst(str_replace('_', ' ', $assessment->result)) : 'Not yet', 'hint' => $width.'% encoded', 'tone' => $assessment->result === 'functional' ? null : 'warn'],
            ],
            'charts' => [
                [
                    'title' => 'Indicator status',
                    'hint' => $snap['yes_count'].' yes · '.$snap['returned_count'].' returned · '.(12 - $snap['encoded']).' not started',
                    'bars' => $bars,
                ],
            ],
            'progress' => [
                'width' => $width,
                'text' => $width.'% encoded · need '.$need.' more Yes FIs for functional (10/12)',
                'actions' => $actions ?: ['Open Submit to see the rest of the path.'],
            ],
        ]);
    }

    public function assessment(): Response
    {
        $assessment = AssessmentEngine::forSchool(request()->user());
        if (! $assessment) {
            return Inertia::render('school/Assessment', [
                'title' => 'My assessment',
                'subtitle' => 'No open cycle',
                'kpis' => [],
                'indicators' => [],
            ]);
        }

        $snap = AssessmentEngine::snapshot($assessment);
        $rows = $assessment->indicators->sortBy('code')->map(function ($indicator) use ($assessment) {
            $mov = $assessment->movs->firstWhere('indicator_code', $indicator->code);
            $status = match (true) {
                $mov?->status === 'returned' => ['badge' => 'Returned', 'tone' => 'bad'],
                $indicator->answer === 'yes' && $mov?->hasFile() => ['badge' => 'Complete', 'tone' => 'ok'],
                $indicator->answer !== null => ['badge' => 'In progress', 'tone' => 'warn'],
                default => ['badge' => 'Not started', 'tone' => 'warn'],
            };

            return [
                'code' => $indicator->code,
                'title' => $indicator->title,
                'answer' => $indicator->answer,
                'mov' => $mov?->status === 'returned' ? $mov->code.' returned' : ($mov?->hasFile() ? 'Minimum uploaded' : ($indicator->answer === 'yes' ? 'Missing' : 'None')),
                'status' => $status,
                'locked' => $assessment->answersLocked(),
            ];
        })->values();

        return Inertia::render('school/Assessment', [
            'title' => 'My assessment',
            'subtitle' => $assessment->cycle?->name.' · public elementary',
            'kpis' => [
                ['label' => 'Complete', 'value' => (string) $snap['encoded'], 'hint' => null, 'tone' => null],
                ['label' => 'Returned', 'value' => (string) $snap['returned_count'], 'hint' => null, 'tone' => $snap['returned_count'] ? 'bad' : null],
                ['label' => 'Not started', 'value' => (string) (12 - $snap['encoded']), 'hint' => null, 'tone' => (12 - $snap['encoded']) ? 'warn' : null],
            ],
            'indicators' => $rows,
        ]);
    }

    public function encode(Request $request): RedirectResponse
    {
        $assessment = AssessmentEngine::forSchool($request->user());
        abort_unless($assessment, 404);
        abort_if($assessment->answersLocked(), 403, 'Answers are locked after submit. Replace returned MOVs only.');
        abort_unless($request->user()->isSchoolStaff(), 403);

        $data = $request->validate([
            'code' => 'required|string',
            'answer' => 'required|in:yes,no',
        ]);

        abort_unless(FatCatalog::indicator($data['code']), 404);

        $assessment->indicators()->where('code', $data['code'])->update([
            'answer' => $data['answer'],
        ]);

        $assessment->update(['qa_certified_at' => null, 'status' => 'in_progress', 'result' => null]);
        AssessmentEngine::ensureRequiredMovs($assessment->fresh());

        return back()->with('status', $data['code'].' saved.');
    }

    public function movs(): Response
    {
        $assessment = AssessmentEngine::forSchool(request()->user());
        if (! $assessment) {
            return Inertia::render('school/Movs', [
                'title' => 'MOV files',
                'subtitle' => 'No open cycle',
                'kpis' => [],
                'slots' => [],
                'is_school_head' => request()->user()->isSchoolHead(),
                'can_withdraw' => false,
            ]);
        }
        AssessmentEngine::ensureRequiredMovs($assessment);
        $assessment->load('movs');

        $slots = AssessmentEngine::requiredSlots($assessment)->map(function (array $slot) use ($assessment) {
            $mov = $assessment->movs->firstWhere('code', $slot['code']);

            return [
                'id' => $mov?->id,
                'code' => $slot['code'],
                'title' => $slot['title'],
                'indicator_code' => $slot['indicator_code'],
                'kind' => $slot['kind'] === 'validity' ? 'Required' : 'Minimum',
                'file' => $mov?->original_name,
                'size' => $mov && $mov->size ? $this->humanSize($mov->size) : '—',
                'status' => $mov?->status ?? 'draft',
                'reason' => $mov?->return_reason,
                'can_replace' => $mov ? $assessment->canReplaceMov($mov) : ! $assessment->answersLocked(),
                'can_remove' => $mov ? $assessment->canRemoveMov($mov) && request()->user()->isSchoolHead() : false,
                'can_request_remove' => $mov ? $assessment->canRemoveMov($mov) && request()->user()->isEncoder() : false,
                'removal_requested' => (bool) $mov?->removal_requested_at,
            ];
        })->values();

        $returned = $slots->where('status', 'returned')->count();
        $valid = $slots->whereIn('status', ['valid', 'uploaded'])->count();

        return Inertia::render('school/Movs', [
            'title' => 'MOV files',
            'subtitle' => 'Means of Verification for this cycle. If Division returns a file, replace only that file and resubmit.',
            'kpis' => [
                ['label' => 'Files', 'value' => (string) $slots->count(), 'hint' => null, 'tone' => null],
                ['label' => 'Valid', 'value' => (string) $valid, 'hint' => null, 'tone' => null],
                ['label' => 'Returned', 'value' => (string) $returned, 'hint' => $returned ? 'Replace then QA + resubmit' : null, 'tone' => $returned ? 'bad' : null],
            ],
            'slots' => $slots,
            'is_school_head' => request()->user()->isSchoolHead(),
            'can_withdraw' => $assessment->canWithdraw() && request()->user()->isSchoolHead(),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $assessment = AssessmentEngine::forSchool($request->user());
        abort_unless($assessment, 404);

        $data = $request->validate([
            'code' => 'required|string',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);

        AssessmentEngine::ensureRequiredMovs($assessment);
        $mov = $assessment->movs()->where('code', $data['code'])->firstOrFail();

        abort_unless($assessment->canReplaceMov($mov), 403, 'Only returned or unsubmitted files can be replaced.');

        $file = $data['file'];
        if ($mov->path) {
            Storage::disk('local')->delete($mov->path);
        }

        $path = $file->store('movs/'.$assessment->id, 'local');

        $mov->update([
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'status' => 'uploaded',
            'return_reason' => null,
            'removal_requested_at' => null,
        ]);

        $assessment->update(['qa_certified_at' => null]);
        if ($assessment->status === 'returned') {
            $assessment->update(['status' => 'in_progress']);
        }

        $encoded = $assessment->fresh()->indicators()->whereNotNull('answer')->count();
        $message = $encoded === 12
            ? ($request->user()->isSchoolHead()
                ? $mov->code.' uploaded. Certify School Head QA before submit.'
                : $mov->code.' uploaded. Ask the School Head to certify QA, then submit.')
            : $mov->code.' uploaded. Encode all 12 indicators before School Head QA.';

        return back()->with('status', $message);
    }

    public function requestRemoval(Mov $mov): RedirectResponse
    {
        $user = request()->user();
        $assessment = $mov->assessment;
        abort_unless($user->isEncoder() && $assessment->school_code === $user->packetSchoolCode(), 403);
        abort_unless($assessment->canRemoveMov($mov), 403, 'This file cannot be removed. Division already accepted it, or the packet is locked.');

        $mov->update(['removal_requested_at' => now()]);

        User::query()
            ->where('role', 'school_head')
            ->where('status', 'active')
            ->where('school_code', $user->school_code)
            ->get()
            ->each(fn (User $head) => $head->notify(new MovRemovalRequested($mov->fresh(), $user)));

        return back()->with('status', $mov->code.' removal requested. The School Head can remove this file.');
    }

    public function remove(Mov $mov): RedirectResponse
    {
        $user = request()->user();
        $assessment = $mov->assessment;
        abort_unless($user->isSchoolHead() && $assessment->school_code === $user->packetSchoolCode(), 403);
        abort_unless($assessment->canRemoveMov($mov), 403, 'This file cannot be removed. Division already accepted it, or withdraw the packet first.');

        if ($mov->path) {
            Storage::disk('local')->delete($mov->path);
        }

        $mov->update([
            'original_name' => null,
            'path' => null,
            'mime' => null,
            'size' => 0,
            'status' => 'draft',
            'return_reason' => null,
            'removal_requested_at' => null,
        ]);

        $assessment->update(['qa_certified_at' => null]);

        return back()->with('status', $mov->code.' removed. Upload a replacement if this indicator still needs a MOV.');
    }

    public function withdraw(): RedirectResponse
    {
        $user = request()->user();
        $assessment = AssessmentEngine::forSchool($user);
        abort_unless($assessment, 404);
        abort_unless($user->isSchoolHead(), 403, 'Only the School Head can withdraw the packet.');
        abort_unless($assessment->canWithdraw(), 403, 'Cannot withdraw after Division has accepted a MOV.');

        $assessment->update([
            'status' => 'in_progress',
            'submitted_at' => null,
            'qa_certified_at' => null,
            'result' => null,
        ]);

        return back()->with('status', 'Packet withdrawn from Division. You can remove or replace files, then QA and submit again.');
    }

    public function download(Mov $mov): StreamedResponse
    {
        $user = request()->user();
        abort_unless(
            ($user->isSchoolStaff() && $mov->assessment->school_code === $user->packetSchoolCode())
            || $user->role === 'division'
            || $user->role === 'super',
            403
        );
        abort_unless($mov->path && Storage::disk('local')->exists($mov->path), 404);

        return Storage::disk('local')->download($mov->path, $mov->original_name ?: $mov->code.'.pdf');
    }

    public function submitPage(): Response
    {
        $assessment = AssessmentEngine::forSchool(request()->user());
        if (! $assessment) {
            return Inertia::render('school/Submit', [
                'title' => 'Submit to Division',
                'subtitle' => 'No open cycle',
                'checks' => [],
                'after' => [],
                'packet' => [],
                'can_qa' => false,
                'can_submit' => false,
                'qa_done' => false,
                'returned' => false,
            'locked' => true,
            'status' => 'none',
            'is_school_head' => request()->user()->isSchoolHead(),
            'can_withdraw' => false,
            ]);
        }
        $snap = AssessmentEngine::snapshot($assessment);
        $user = $assessment->user;
        $requestUser = request()->user();

        $checks = [
            ['mark' => $snap['cycle_open'] ? '✓' : '!', 'tone' => $snap['cycle_open'] ? 'ok' : 'bad', 'title' => 'Cycle is open', 'hint' => 'Deadline '.$assessment->cycle?->deadline_at?->toFormattedDateString()],
            ['mark' => $snap['encoded'] === 12 ? '✓' : (string) $snap['encoded'], 'tone' => $snap['encoded'] === 12 ? 'ok' : 'warn', 'title' => 'Encode 12 functionality indicators', 'hint' => $snap['encoded'].' of 12 encoded'],
            ['mark' => $snap['returned_count'] ? '!' : ($snap['missing_count'] ? (string) $snap['missing_count'] : '✓'), 'tone' => ($snap['returned_count'] || $snap['missing_count']) ? ($snap['returned_count'] ? 'bad' : 'warn') : 'ok', 'title' => $snap['returned_count'] ? 'Replace returned or invalid MOVs' : 'Minimum MOVs complete', 'hint' => $snap['returned_count'] ? $snap['returned_count'].' returned — replace the flagged file only' : ($snap['missing_count'] ? $snap['missing_count'].' missing' : 'No returned files')],
            ['mark' => $snap['validity_ready'] ? '✓' : '○', 'tone' => $snap['validity_ready'] ? 'ok' : 'warn', 'title' => 'Finalize Validity Form', 'hint' => $snap['validity_ready'] ? 'Uploaded' : 'Still missing or returned'],
            ['mark' => $snap['qa_done'] ? '✓' : '3', 'tone' => $snap['qa_done'] ? 'ok' : ($snap['can_qa'] ? 'warn' : 'todo'), 'title' => 'School Head QA', 'hint' => $snap['qa_done'] ? 'Certified' : ($requestUser->isSchoolHead() ? 'Certify answers before send' : 'Waiting for School Head')],
            ['mark' => in_array($assessment->status, ['submitted', 'under_review', 'validated'], true) ? '✓' : '4', 'tone' => in_array($assessment->status, ['submitted', 'under_review', 'validated'], true) ? 'ok' : 'todo', 'title' => 'Submit to SDO Cadiz City', 'hint' => 'Locks the school packet for validation'],
            ['mark' => in_array($assessment->status, ['under_review', 'validated'], true) ? '✓' : '5', 'tone' => $assessment->status === 'validated' ? 'ok' : 'todo', 'title' => 'Division review', 'hint' => 'Composite Team checks MOVs against FAT Volume 2'],
            ['mark' => $assessment->status === 'validated' ? '✓' : '6', 'tone' => $assessment->result === 'functional' ? 'ok' : 'todo', 'title' => 'Result', 'hint' => 'Functional if 10 of 12 FIs are validated'],
        ];

        return Inertia::render('school/Submit', [
            'title' => 'Submit to Division',
            'subtitle' => ($user->school_name ?: 'School').' · '.$assessment->cycle?->name,
            'chip' => $assessment->cycle?->deadline_at?->diffForHumans(),
            'checks' => $checks,
            'after' => [
                '1. The school packet is time-stamped and locked.',
                '2. It appears in the Division validation queue.',
                '3. A validator accepts or returns each Minimum MOV.',
                '4. If returned or invalid: replace only the flagged file, certify School Head QA again, then resubmit.',
                '5. When 10 of 12 FIs pass, the SGC is marked Functional.',
            ],
            'packet' => [
                'School ID '.($user->school_code ?: '—').' · '.($user->school_name ?: 'School'),
                'Self-score: '.($assessment->result ?: 'not yet').' · '.$snap['yes_count'].' of 12 Yes',
                'Returned MOVs: '.$snap['returned_count'].' · missing: '.$snap['missing_count'],
                'Destination: SGC Focal, SDO Cadiz City',
            ],
            'can_qa' => $snap['can_qa'] && $requestUser->isSchoolHead(),
            'can_submit' => $snap['can_submit'] && $requestUser->isSchoolHead(),
            'qa_done' => $snap['qa_done'],
            'returned' => $snap['returned_count'] > 0,
            'locked' => $assessment->isLocked(),
            'status' => $assessment->status,
            'is_school_head' => $requestUser->isSchoolHead(),
            'can_withdraw' => $assessment->canWithdraw() && $requestUser->isSchoolHead(),
        ]);
    }

    public function certifyQa(): RedirectResponse
    {
        $user = request()->user();
        $assessment = AssessmentEngine::forSchool($user);
        abort_unless($assessment, 404);
        abort_unless($user->isSchoolHead(), 403, 'Only the School Head can certify QA.');
        $snap = AssessmentEngine::snapshot($assessment);
        abort_unless($snap['can_qa'], 403, 'Finish encode and replace returned MOVs first.');

        $assessment->update(['qa_certified_at' => now()]);

        return back()->with('status', 'School Head QA certified. You can submit to Division.');
    }

    public function submit(): RedirectResponse
    {
        $user = request()->user();
        $assessment = AssessmentEngine::forSchool($user);
        abort_unless($assessment, 404);
        abort_unless($user->isSchoolHead(), 403, 'Only the School Head can submit to Division.');
        $snap = AssessmentEngine::snapshot($assessment);
        abort_unless($snap['can_submit'], 403, 'Submit stays locked until returned MOVs are replaced and School Head QA is done.');

        $assessment->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $divisionAdmins = User::query()->where('role', 'division')->where('status', 'active')->get();
        if ($divisionAdmins->isNotEmpty()) {
            Notification::send($divisionAdmins, new PacketSubmitted($assessment->load('user')));
        }

        return back()->with('status', 'Packet submitted to SDO Cadiz City.');
    }

    public function notifications(): Response
    {
        $user = request()->user();
        $items = $user->notifications()->latest()->limit(20)->get();

        return Inertia::render('school/Notifications', [
            'title' => 'Notifications',
            'subtitle' => 'School Head and designated encoder both receive these.',
            'kpis' => [
                ['label' => 'Unread', 'value' => (string) $user->unreadNotifications()->count(), 'hint' => null, 'tone' => $user->unreadNotifications()->count() ? 'warn' : null],
                ['label' => 'This cycle', 'value' => (string) $items->count(), 'hint' => null, 'tone' => null],
            ],
            'headers' => ['Date', 'Message', 'Type'],
            'rows' => $items->map(fn ($notification) => [
                $notification->created_at?->format('M j'),
                $notification->data['detail'] ?? $notification->data['title'] ?? 'Notice',
                [
                    'badge' => str_contains(strtolower($notification->data['title'] ?? ''), 'returned') ? 'Action needed' : 'Info',
                    'tone' => str_contains(strtolower($notification->data['title'] ?? ''), 'returned') ? 'bad' : 'ok',
                ],
            ])->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboard(): array
    {
        return [
            'title' => 'School dashboard',
            'subtitle' => 'No open SGC FAT cycle yet',
            'kpis' => [],
            'charts' => [],
            'progress' => ['width' => 0, 'text' => 'Wait for Super Admin to open a cycle.', 'actions' => []],
        ];
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
