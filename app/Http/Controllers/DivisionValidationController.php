<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Mov;
use App\Models\User;
use App\Notifications\MovReturned;
use App\Notifications\PacketValidated;
use App\Support\AssessmentEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DivisionValidationController extends Controller
{
    public function queue(): Response
    {
        $rows = Assessment::query()
            ->with('user')
            ->whereIn('status', ['submitted', 'under_review', 'returned', 'validated'])
            ->latest('submitted_at')
            ->get()
            ->map(function (Assessment $assessment) {
                $yes = $assessment->indicators()->where('answer', 'yes')->count();
                $badge = match ($assessment->status) {
                    'validated' => ['badge' => $assessment->result === 'functional' ? 'Validated' : 'Not yet', 'tone' => $assessment->result === 'functional' ? 'ok' : 'warn'],
                    'returned' => ['badge' => 'Returned', 'tone' => 'bad'],
                    'under_review' => ['badge' => 'In review', 'tone' => 'warn'],
                    default => ['badge' => 'Needs validation', 'tone' => 'warn'],
                };

                return [
                    'id' => $assessment->id,
                    'school' => $assessment->user?->school_name,
                    'school_code' => $assessment->user?->school_code,
                    'score' => $yes.'/12',
                    'status' => $badge,
                    'submitted' => $assessment->submitted_at?->format('M j'),
                ];
            });

        $awaiting = $rows->filter(fn ($row) => $row['status']['badge'] === 'Needs validation')->count();
        $returned = $rows->filter(fn ($row) => $row['status']['badge'] === 'Returned')->count();
        $validated = $rows->filter(fn ($row) => $row['status']['badge'] === 'Validated')->count();

        return Inertia::render('division/Queue', [
            'title' => 'Validation queue',
            'subtitle' => 'Review MOVs against SGC FAT Volume 2 rules. Return invalid files so the school can replace only those files.',
            'kpis' => [
                ['label' => 'Awaiting review', 'value' => (string) $awaiting, 'hint' => null, 'tone' => 'warn'],
                ['label' => 'Returned', 'value' => (string) $returned, 'hint' => null, 'tone' => $returned ? 'bad' : null],
                ['label' => 'Validated', 'value' => (string) $validated, 'hint' => null, 'tone' => null],
            ],
            'packets' => $rows,
        ]);
    }

    public function show(Assessment $assessment): Response
    {
        $assessment->load(['user', 'indicators', 'movs', 'cycle']);
        $snap = AssessmentEngine::snapshot($assessment);

        return Inertia::render('division/Review', [
            'title' => $assessment->user?->school_name ?: 'School packet',
            'subtitle' => 'Accept valid MOVs or return invalid files with a reason.',
            'assessment_id' => $assessment->id,
            'status' => $assessment->status,
            'result' => $assessment->result,
            'yes_count' => $snap['yes_count'],
            'movs' => $assessment->movs->map(fn (Mov $mov) => [
                'id' => $mov->id,
                'code' => $mov->code,
                'title' => $mov->title,
                'kind' => $mov->kind,
                'file' => $mov->original_name,
                'status' => $mov->status,
                'reason' => $mov->return_reason,
            ]),
            'can_complete' => in_array($assessment->status, ['submitted', 'under_review'], true),
        ]);
    }

    public function acceptMov(Request $request, Mov $mov): RedirectResponse
    {
        abort_unless(in_array($mov->assessment->status, ['submitted', 'under_review', 'returned'], true), 403);

        $mov->update(['status' => 'valid', 'return_reason' => null]);
        $mov->assessment->update(['status' => 'under_review']);

        return back()->with('status', $mov->code.' marked valid.');
    }

    public function returnMov(Request $request, Mov $mov): RedirectResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $mov->update([
            'status' => 'returned',
            'return_reason' => $data['reason'],
        ]);

        $assessment = $mov->assessment;
        $assessment->update([
            'status' => 'returned',
            'qa_certified_at' => null,
            'result' => null,
        ]);

        $assessment->user?->notify(new MovReturned($mov));
        User::schoolTeam($assessment->school_code)
            ->reject(fn (User $member) => $member->id === $assessment->user_id)
            ->each(fn (User $member) => $member->notify(new MovReturned($mov)));

        return back()->with('status', $mov->code.' returned to the school. They must replace that file, certify QA, and resubmit.');
    }

    public function complete(Assessment $assessment): RedirectResponse
    {
        abort_unless(in_array($assessment->status, ['submitted', 'under_review'], true), 403);
        abort_if($assessment->movs()->where('status', 'returned')->exists(), 403, 'Return is still open.');

        $assessment->load('movs');

        $yesValid = $assessment->indicators()
            ->where('answer', 'yes')
            ->get()
            ->filter(function ($indicator) use ($assessment) {
                $mov = $assessment->movs->firstWhere('indicator_code', $indicator->code);

                return $mov && $mov->status === 'valid';
            })
            ->count();

        $functional = $yesValid >= 10;

        $assessment->update([
            'status' => 'validated',
            'result' => $functional ? 'functional' : 'not_yet',
            'validated_at' => now(),
        ]);

        $assessment->user?->notify(new PacketValidated($assessment->fresh()));
        User::schoolTeam($assessment->school_code)
            ->reject(fn (User $member) => $member->id === $assessment->user_id)
            ->each(fn (User $member) => $member->notify(new PacketValidated($assessment->fresh())));

        return back()->with('status', $functional ? 'Marked Functional (10/12).' : 'Validated: not yet functional.');
    }
}
