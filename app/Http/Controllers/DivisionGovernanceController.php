<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\CoachingSession;
use App\Models\User;
use App\Notifications\CoachingScheduled;
use App\Support\AssessmentEngine;
use App\Support\GovernanceImprovementPlan;
use App\Support\GovernanceRecommendation;
use App\Support\PortalMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class DivisionGovernanceController extends Controller
{
    public function storePlan(string $code): RedirectResponse
    {
        $head = PortalMetrics::schoolHeadFor($code);
        $schoolCode = $head->packetSchoolCode();
        $cycle = AssessmentEngine::currentCycle();
        abort_unless($cycle, 422, 'No open SGC FAT cycle.');

        $assessment = Assessment::query()
            ->with(['indicators', 'movs'])
            ->where('school_code', $schoolCode)
            ->where('cycle_id', $cycle->id)
            ->latest('id')
            ->first();

        $plan = GovernanceImprovementPlan::generate(
            request()->user(),
            $schoolCode,
            $head->school_name ?: 'This school',
            $assessment,
            $cycle,
        );

        $session = GovernanceImprovementPlan::upcomingSession($schoolCode, $cycle->id);
        if ($session && $session->plan_id !== $plan->id) {
            $session->update(['plan_id' => $plan->id]);
        }

        $status = 'School Governance Improvement Plan issued for '.($head->school_name ?: 'this school').'.';
        $previous = (string) url()->previous();
        if (str_contains($previous, '/division/ta')) {
            $query = ['plan' => $schoolCode];
            parse_str((string) parse_url($previous, PHP_URL_QUERY), $prior);
            if (! empty($prior['cycle'])) {
                $query['cycle'] = $prior['cycle'];
            }

            return redirect()->to('/division/ta?'.http_build_query($query))->with('status', $status);
        }

        return back()->with('status', $status);
    }

    public function storeCoaching(Request $request, string $code): RedirectResponse
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'mode' => ['required', 'in:onsite,online'],
            'venue' => ['nullable', 'string', 'max:160'],
            'focus' => ['nullable', 'string', 'max:240'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $head = PortalMetrics::schoolHeadFor($code);
        $schoolCode = $head->packetSchoolCode();
        $cycle = AssessmentEngine::currentCycle();
        abort_unless($cycle, 422, 'No open SGC FAT cycle.');

        $plan = GovernanceImprovementPlan::latest($schoolCode, $cycle->id);
        $focus = trim((string) ($data['focus'] ?? ''));
        if ($focus === '') {
            $assessment = Assessment::query()
                ->with(['indicators', 'movs'])
                ->where('school_code', $schoolCode)
                ->where('cycle_id', $cycle->id)
                ->latest('id')
                ->first();
            $summary = GovernanceRecommendation::summaryFor($assessment);
            $focus = $plan?->actions[0]['activity'] ?? ($summary['top_action'] ?? 'Governance technical assistance');
        }

        $existing = GovernanceImprovementPlan::upcomingSession($schoolCode, $cycle->id);
        $payload = [
            'plan_id' => $plan?->id,
            'school_code' => $schoolCode,
            'cycle_id' => $cycle->id,
            'scheduled_at' => Carbon::parse($data['scheduled_at']),
            'mode' => $data['mode'],
            'venue' => $data['venue'] ?: ($data['mode'] === 'online' ? 'Online' : $head->school_name),
            'focus' => $focus,
            'notes' => $data['notes'] ?: null,
            'status' => 'scheduled',
            'created_by' => $request->user()->id,
        ];

        if ($existing) {
            $existing->update($payload);
            $session = $existing->fresh();
            $rescheduled = true;
        } else {
            $session = CoachingSession::query()->create($payload);
            $rescheduled = false;
        }

        $recipients = User::schoolTeam($head->school_code);
        if ($recipients->isEmpty()) {
            $recipients = collect([$head])->filter(fn (User $user) => $user->status === 'active');
        }

        Notification::send(
            $recipients,
            new CoachingScheduled($session, $head->school_name ?: 'Your school', $rescheduled),
        );

        return back()->with(
            'status',
            ($rescheduled ? 'Coaching rescheduled for ' : 'Coaching scheduled for ').($head->school_name ?: 'this school').'.',
        );
    }
}
