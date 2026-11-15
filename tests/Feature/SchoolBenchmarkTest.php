<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\User;
use App\Support\AssessmentEngine;
use App\Support\SchoolBenchmark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_can_open_benchmarking_and_see_named_peers(): void
    {
        $cycle = Cycle::query()->create([
            'name' => '2026 SGC Functionality Assessment',
            'level' => 'Public Elementary and Secondary',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(20),
            'status' => 'open',
        ]);

        $ours = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_name' => 'Cadiz Central School',
            'school_code' => '111111',
            'position' => 'School Head',
        ]);
        $peer = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_name' => 'Cadiz North Elementary',
            'school_code' => '222222',
            'position' => 'School Head',
        ]);

        $ourPacket = AssessmentEngine::forSchool($ours);
        $peerPacket = AssessmentEngine::forSchool($peer);
        $this->assertNotNull($ourPacket);
        $this->assertNotNull($peerPacket);

        foreach (['FI1', 'FI2', 'FI3', 'FI4', 'FI5', 'FI6'] as $code) {
            $ourPacket->indicators()->where('code', $code)->update(['answer' => 'yes']);
        }
        foreach (['FI1', 'FI2', 'FI3', 'FI4', 'FI5', 'FI6', 'FI7', 'FI8', 'FI9'] as $code) {
            $peerPacket->indicators()->where('code', $code)->update(['answer' => 'yes']);
        }

        $this->actingAs($ours)
            ->get('/school/benchmarking')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('school/Benchmarking')
                ->where('empty', false)
                ->where('total', 2)
                ->where('me.name', 'Cadiz Central School')
                ->where('me.rank', 2)
                ->where('benchmark.highest', 75)
                ->has('schools', 2)
                ->where('schools.0.name', 'Cadiz North Elementary')
                ->where('schools.1.is_self', true)
                ->missing('schools.0.movs')
                ->etc());

        $page = SchoolBenchmark::page($ours, $cycle->id, 'fis');
        $this->assertSame('Cadiz North Elementary', $page['schools'][0]['name']);
        $this->assertArrayNotHasKey('path', $page['schools'][0]);
        $this->assertArrayNotHasKey('drive_file_id', $page['schools'][0]);
    }

    public function test_dashboard_includes_benchmark_summary_card_props(): void
    {
        Cycle::query()->create([
            'name' => '2026 SGC Functionality Assessment',
            'level' => 'Public Elementary and Secondary',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(20),
            'status' => 'open',
        ]);

        $head = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_name' => 'Cadiz Central School',
            'school_code' => '111111',
            'position' => 'School Head',
        ]);
        AssessmentEngine::forSchool($head);

        $this->actingAs($head)
            ->get('/school')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('school/Dashboard')
                ->has('benchmark')
                ->where('benchmark.href', '/school/benchmarking')
                ->etc());
    }

    public function test_guest_and_division_cannot_use_school_benchmarking_route(): void
    {
        $this->get('/school/benchmarking')->assertRedirect();

        $division = User::factory()->create([
            'role' => 'division',
            'school_name' => null,
            'school_code' => null,
        ]);

        $this->actingAs($division)
            ->get('/school/benchmarking')
            ->assertRedirect();
    }
}
