<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_open_school_pages()
    {
        Cycle::query()->create([
            'name' => '2026 SGC Functionality Assessment',
            'level' => 'Public Elementary',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(20),
            'status' => 'open',
        ]);

        $user = User::factory()->create(['role' => 'school']);

        $this->actingAs($user)->get('/school')->assertOk();
        $this->actingAs($user)->get('/school/assessment')->assertOk();
        $this->actingAs($user)->get('/school/movs')->assertOk();
        $this->actingAs($user)->get('/school/submit')->assertOk();
        $this->actingAs($user)->get('/school/notifications')->assertOk();
        $this->actingAs($user)->get('/account')->assertOk()->assertSee('Account');
        $this->actingAs($user)->get('/docs')->assertOk();
        $this->actingAs($user)->get('/help')->assertOk();
    }

    public function test_school_head_can_open_school_pages()
    {
        Cycle::query()->create([
            'name' => '2026 SGC Functionality Assessment',
            'level' => 'Public Elementary',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(20),
            'status' => 'open',
        ]);

        $user = User::factory()->create(['role' => 'school_head']);

        $this->actingAs($user)->get('/school')->assertOk();
        $this->actingAs($user)->get('/school/submit')->assertOk();
        $this->actingAs($user)->get('/school/encoders')->assertOk();
    }

    public function test_school_admin_can_update_configuration()
    {
        $user = User::factory()->create([
            'role' => 'school',
            'school_name' => 'school12345',
            'school_code' => null,
        ]);

        $this->actingAs($user)
            ->from('/school')
            ->patch('/settings/account', [
                'name' => $user->name,
                'email' => $user->email,
                'school_name' => 'Rizal Elementary School',
                'school_code' => '123456',
                'position' => 'Teacher I',
            ])
            ->assertRedirect('/school');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'school_name' => 'Rizal Elementary School',
            'school_code' => '123456',
        ]);
    }

    public function test_division_admin_cannot_open_super_pages()
    {
        $user = User::factory()->create(['role' => 'division']);

        $this->actingAs($user)
            ->get('/super')
            ->assertRedirect(route('division.overview', absolute: false));
    }

    public function test_super_admin_can_open_super_overview()
    {
        $user = User::factory()->create(['role' => 'super']);

        $this->actingAs($user)->get('/super')->assertOk();
        $this->actingAs($user)->get('/super/users')->assertOk();
    }

    public function test_division_admin_can_open_registrations()
    {
        $user = User::factory()->create(['role' => 'division']);

        $this->actingAs($user)->get('/division/registrations')->assertOk();
        $this->actingAs($user)
            ->get('/division')
            ->assertOk()
            ->assertDontSee('186')
            ->assertDontSee('76% compliance');
        $this->actingAs($user)->get('/division/schools')->assertOk()->assertSee('No School Heads have registered yet.');
    }

    public function test_super_admin_can_reset_school_and_fat_data()
    {
        $super = User::factory()->create(['role' => 'super']);
        $division = User::factory()->create(['role' => 'division', 'email' => 'division@example.com']);
        $head = User::factory()->create(['role' => 'school_head', 'email' => 'head@example.com']);

        $this->actingAs($super)
            ->from('/super/users')
            ->post('/super/reset', ['confirm' => 'RESET FAT DATA'])
            ->assertRedirect('/super/users');

        $this->assertDatabaseHas('users', ['id' => $super->id, 'role' => 'super']);
        $this->assertDatabaseHas('users', ['id' => $division->id, 'role' => 'division']);
        $this->assertDatabaseMissing('users', ['id' => $head->id]);
    }

    public function test_division_admin_cannot_reset_data()
    {
        $division = User::factory()->create(['role' => 'division']);
        $head = User::factory()->create(['role' => 'school_head']);

        $this->actingAs($division)
            ->post('/super/reset', ['confirm' => 'RESET FAT DATA'])
            ->assertRedirect(route('division.overview', absolute: false));

        $this->assertDatabaseHas('users', ['id' => $head->id]);
    }
}
