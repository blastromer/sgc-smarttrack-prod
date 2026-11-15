<?php

namespace Tests\Feature;

use App\Models\CoachingSession;
use App\Models\Cycle;
use App\Models\ImprovementPlan;
use App\Models\User;
use App\Notifications\CoachingScheduled;
use App\Support\AssessmentEngine;
use App\Support\GovernanceImprovementPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GovernanceImprovementPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.key', '');
        Notification::fake();
    }

    public function test_division_can_issue_catalog_plan_without_openai(): void
    {
        $this->openCycle();
        [$division, $head] = $this->divisionAndHead();
        $assessment = AssessmentEngine::forSchool($head);
        $assessment->indicators()->where('code', 'FI12')->update(['answer' => 'no']);

        $this->actingAs($division)
            ->post('/division/schools/654321/improvement-plan')
            ->assertRedirect();

        $this->assertDatabaseHas('governance_improvement_plans', [
            'school_code' => '654321',
            'status' => 'issued',
            'model' => 'catalog',
        ]);

        $plan = ImprovementPlan::query()->first();
        $this->assertNotNull($plan);
        $this->assertNotSame('', $plan->summary);
        $this->assertTrue(
            collect($plan->actions)->contains(fn (array $row) => ($row['focus'] ?? '') === 'FI12' || str_contains($row['activity'] ?? '', 'FI12'))
        );
    }

    public function test_openai_plan_keeps_catalog_focuses_and_drops_invented_fis(): void
    {
        $this->openCycle();
        [$division, $head] = $this->divisionAndHead();
        $assessment = AssessmentEngine::forSchool($head);
        $assessment->indicators()->where('code', 'FI12')->update(['answer' => 'no']);

        Config::set('services.openai.key', 'test-key');
        Config::set('services.openai.model', 'gpt-5.5');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'summary' => 'Polished SGIP for Rizal Elementary School.',
                            'objectives' => ['Coach the SGC on SIP/AIP review.'],
                            'actions' => [
                                [
                                    'focus' => 'FI12',
                                    'area' => 'Feedback Mechanisms',
                                    'activity' => 'Facilitate an SGC review of SIP/AIP quality.',
                                    'output' => 'Resolution with improvement recommendations.',
                                    'timeframe' => 'Within 30 days',
                                    'persons' => 'School Head and SGC officers',
                                    'materials' => 'SIP, AIP, minutes template',
                                ],
                                [
                                    'focus' => 'FI99',
                                    'area' => 'Invented',
                                    'activity' => 'Do not keep this indicator.',
                                    'output' => 'None',
                                    'timeframe' => 'Now',
                                    'persons' => 'Nobody',
                                    'materials' => 'None',
                                ],
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($division)
            ->post('/division/schools/654321/improvement-plan')
            ->assertRedirect();

        $plan = ImprovementPlan::query()->first();
        $this->assertNotNull($plan);
        $this->assertSame('gpt-5.5', $plan->model);
        $this->assertSame('Polished SGIP for Rizal Elementary School.', $plan->summary);
        $focuses = collect($plan->actions)->pluck('focus');
        $this->assertTrue($focuses->contains('FI12'));
        $this->assertFalse($focuses->contains('FI99'));
    }

    public function test_scheduling_coaching_notifies_school_head(): void
    {
        $this->openCycle();
        [$division, $head] = $this->divisionAndHead();
        AssessmentEngine::forSchool($head);

        $this->actingAs($division)
            ->post('/division/schools/654321/improvement-plan')
            ->assertRedirect();

        $this->actingAs($division)
            ->from('/division/ta')
            ->post('/division/schools/654321/coaching', [
                'scheduled_at' => now()->addDay()->format('Y-m-d\\TH:i'),
                'mode' => 'onsite',
                'venue' => 'Rizal Elementary School',
                'focus' => 'FI12 SIP review',
                'notes' => 'Bring minutes templates.',
            ])
            ->assertRedirect('/division/ta');

        $this->assertDatabaseHas('coaching_sessions', [
            'school_code' => '654321',
            'status' => 'scheduled',
            'mode' => 'onsite',
        ]);

        Notification::assertSentTo($head, CoachingScheduled::class);

        $this->actingAs($head)
            ->get('/school')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('school/Dashboard')
                ->has('coaching.when'));
    }

    public function test_school_can_read_plan_but_cannot_generate_or_schedule(): void
    {
        $this->openCycle();
        [$division, $head] = $this->divisionAndHead();
        AssessmentEngine::forSchool($head);

        $this->actingAs($division)->post('/division/schools/654321/improvement-plan');

        $this->actingAs($head)
            ->get('/school/improvement-plan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('school/ImprovementPlan')
                ->where('plan.status', 'issued')
                ->has('plan.actions'));

        $this->actingAs($head)
            ->post('/division/schools/654321/improvement-plan')
            ->assertRedirect('/school');

        $this->actingAs($head)
            ->post('/division/schools/654321/coaching', [
                'scheduled_at' => now()->addDay()->toDateTimeString(),
                'mode' => 'online',
            ])
            ->assertRedirect('/school');

        $this->assertSame(0, CoachingSession::query()->count());
    }

    public function test_dossier_and_ta_queue_expose_issued_plan(): void
    {
        $this->openCycle();
        [$division, $head] = $this->divisionAndHead();
        AssessmentEngine::forSchool($head);
        $this->actingAs($division)->post('/division/schools/654321/improvement-plan');

        $this->actingAs($division)
            ->get('/division/schools/654321')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('division/School')
                ->has('improvement_plan')
                ->where('improvement_plan.status', 'issued'));

        $this->actingAs($division)
            ->from('/division/ta')
            ->post('/division/schools/654321/improvement-plan')
            ->assertRedirect('/division/ta?plan=654321');

        $this->actingAs($division)
            ->get('/division/ta?plan=654321')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('division/Ta')
                ->where('schools.0.has_plan', true)
                ->has('schools.0.plan.summary')
                ->has('schools.0.plan.actions'));
    }

    public function test_catalog_generate_helper_stores_versioned_plan(): void
    {
        $cycle = $this->openCycle();
        [, $head] = $this->divisionAndHead();
        $assessment = AssessmentEngine::forSchool($head);
        $actor = User::factory()->create(['role' => 'division', 'status' => 'active']);

        $first = GovernanceImprovementPlan::generate($actor, '654321', 'Rizal Elementary School', $assessment, $cycle);
        $second = GovernanceImprovementPlan::generate($actor, '654321', 'Rizal Elementary School', $assessment, $cycle);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame('issued', $second->status);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function divisionAndHead(): array
    {
        $division = User::factory()->create(['role' => 'division', 'status' => 'active']);
        $head = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_code' => '654321',
            'school_name' => 'Rizal Elementary School',
        ]);

        return [$division, $head];
    }

    private function openCycle(): Cycle
    {
        return Cycle::query()->create([
            'name' => '2026 SGC Functionality Assessment',
            'level' => 'Public Elementary',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(20),
            'status' => 'open',
        ]);
    }
}
