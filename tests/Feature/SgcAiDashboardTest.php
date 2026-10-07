<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\User;
use App\Support\AssessmentEngine;
use App\Support\SgcAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SgcAiDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_division_dashboard_embeds_ai_recommendation(): void
    {
        $division = User::factory()->create([
            'role' => 'division',
            'office' => 'SGOD / SGC Focal',
            'position' => 'Education Program Supervisor',
            'school_name' => null,
            'school_code' => null,
        ]);

        $this->actingAs($division)
            ->get('/division')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('division/Overview')
                ->where('sgc.ai.role', 'division')
                ->has('sgc.ai.hint')
                ->has('sgc.ai.graphs')
                ->has('sgc.ai.ideas')
                ->has('sgc.ai.prompts')
                ->where('sgc.ai.uses.0', 'ideas'));
    }

    public function test_super_dashboard_embeds_ai_recommendation(): void
    {
        $super = User::factory()->create([
            'role' => 'super',
            'school_name' => null,
            'school_code' => null,
        ]);

        $this->actingAs($super)
            ->get('/super')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('super/Overview')
                ->where('sgc.ai.role', 'super')
                ->has('sgc.ai.hint')
                ->has('sgc.ai.graphs'));
    }

    public function test_division_can_ask_ai_for_ideas_without_openai(): void
    {
        $division = User::factory()->create([
            'role' => 'division',
            'school_name' => null,
            'school_code' => null,
        ]);

        $this->actingAs($division)
            ->postJson('/ai/chat', ['message' => 'Generate TA ideas'])
            ->assertOk()
            ->assertJsonStructure(['reply']);

        $reply = SgcAi::chat($division, 'Generate TA ideas');
        $this->assertNotSame('', $reply);
    }

    public function test_guest_cannot_use_ai_chat(): void
    {
        $this->postJson('/ai/chat', ['message' => 'Interpret this dashboard'])
            ->assertUnauthorized();
    }

    public function test_division_ai_explains_the_review_page_not_the_dashboard(): void
    {
        Cycle::query()->create([
            'name' => '2026 SGC Functionality Assessment',
            'level' => 'Public Elementary',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(20),
            'status' => 'open',
        ]);

        $division = User::factory()->create([
            'role' => 'division',
            'school_name' => null,
            'school_code' => null,
        ]);
        $head = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_name' => 'Sample Elementary School',
            'school_code' => '123456',
        ]);
        $assessment = AssessmentEngine::forSchool($head);
        $assessment->update(['status' => 'returned']);
        $assessment->movs()->create([
            'indicator_code' => 'FI3',
            'code' => 'FI3A',
            'title' => 'SGC Resolution',
            'kind' => 'minimum',
            'original_name' => 'FI3A-Minimum-Resolution.pdf',
            'path' => 'movs/fi3a.pdf',
            'status' => 'returned',
            'return_reason' => 'Minutes do not show 50%+1 quorum',
        ]);

        $path = '/division/queue/'.$assessment->id;

        $this->actingAs($division)
            ->get($path)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sgc.ai.page.key', 'division.review')
                ->where('sgc.ai.prompts.0', 'Explain this page'));

        $reply = SgcAi::chat($division, 'can you explain this page?', [], $path);
        $this->assertStringContainsString('MOV validation', $reply);
        $this->assertStringContainsString('Sample Elementary School', $reply);
        $this->assertStringContainsString('FI3A', $reply);
        $this->assertStringNotContainsStringIgnoringCase('compliance', $reply);

        $http = $this->actingAs($division)
            ->postJson('/ai/chat', [
                'message' => 'can you explain this page?',
                'path' => $path,
            ])
            ->assertOk();

        $this->assertStringContainsString('Sample Elementary School', (string) $http->json('reply'));
    }
}
