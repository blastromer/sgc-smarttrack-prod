<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\User;
use App\Support\AssessmentEngine;
use App\Support\GovernanceRecommendation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GovernanceRecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.key', '');
    }

    public function test_division_school_dossier_includes_governance_recommendations_without_openai(): void
    {
        $this->openCycle();

        $division = User::factory()->create(['role' => 'division', 'status' => 'active']);
        $head = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_code' => '654321',
            'school_name' => 'Rizal Elementary School',
        ]);

        $assessment = AssessmentEngine::forSchool($head);
        $this->assertNotNull($assessment);
        $assessment->indicators()->where('code', 'FI3')->update(['answer' => 'no']);
        $assessment->indicators()->where('code', 'FI7')->update(['answer' => 'no']);

        $this->actingAs($division)
            ->get('/division/schools/654321')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('division/School')
                ->has('recommendations')
                ->where('recommendations.live', false)
                ->where('recommendations.ta_flagged', true)
                ->where('recommendations.priority', 'high')
                ->has('recommendations.items')
                ->has('recommendations.summary'));
    }

    public function test_division_overview_and_schools_list_expose_ta_priority(): void
    {
        $this->openCycle();

        $division = User::factory()->create(['role' => 'division', 'status' => 'active']);
        $head = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_code' => '654321',
            'school_name' => 'Rizal Elementary School',
        ]);

        AssessmentEngine::forSchool($head);

        $this->actingAs($division)
            ->get('/division')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('division/Overview')
                ->has('ta_preview')
                ->where('ta_preview.0.name', 'Rizal Elementary School'));

        $this->actingAs($division)
            ->get('/division/ta')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('division/Ta')
                ->has('schools')
                ->where('schools.0.name', 'Rizal Elementary School')
                ->has('kpis')
                ->has('breakdown', 3)
                ->has('trend'));

        $this->actingAs($division)
            ->get('/division/schools')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('division/Schools')
                ->has('schools', 1)
                ->where('schools.0.ta_flagged', true)
                ->has('schools.0.ta_action')
                ->has('schools.0.ta_priority'));
    }

    public function test_catalog_recommendations_cover_unmet_fis_without_openai(): void
    {
        $this->openCycle();

        $head = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_code' => '777001',
            'school_name' => 'Sample High School',
        ]);
        $assessment = AssessmentEngine::forSchool($head);
        $assessment->indicators()->where('code', 'FI12')->update(['answer' => 'no']);

        $bundle = GovernanceRecommendation::forSchool($assessment, 'Sample High School', false);

        $this->assertFalse($bundle['live']);
        $this->assertTrue($bundle['ta_flagged']);
        $this->assertNotSame('', $bundle['summary']);
        $this->assertNotEmpty($bundle['items']);
        $this->assertTrue(
            collect($bundle['items'])->contains(fn (array $item) => str_contains($item['focus'], 'FI12') || str_contains($item['title'], 'FI12'))
        );
    }

    public function test_priority_queue_ranks_ta_flagged_schools(): void
    {
        $this->openCycle();

        User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_code' => '111111',
            'school_name' => 'Alpha School',
        ]);
        $beta = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_code' => '222222',
            'school_name' => 'Beta School',
        ]);

        $packet = AssessmentEngine::forSchool($beta);
        $packet->indicators()->update(['answer' => 'yes']);
        $packet->indicators()->whereIn('code', ['FI11', 'FI12'])->update(['answer' => 'no']);

        $queue = GovernanceRecommendation::priorityQueue(null, 10);

        $this->assertNotEmpty($queue);
        $this->assertTrue(collect($queue)->contains(fn (array $row) => $row['school_code'] === '111111' || $row['code'] === '111111'));
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
