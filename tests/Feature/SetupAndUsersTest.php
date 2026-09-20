<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupAndUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_app_redirects_home_to_super_admin_setup()
    {
        $this->get('/')->assertRedirect(route('setup', absolute: false));
        $this->get('/setup')->assertOk();
    }

    public function test_first_super_admin_can_be_created_then_creates_division_admin()
    {
        $this->post('/setup', [
            'name' => 'Romer Necesario',
            'email' => 'romer.necesario@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertRedirect(route('super.users', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'romer.necesario@example.com',
            'role' => 'super',
            'status' => 'active',
        ]);

        $this->post('/super/users', [
            'name' => 'Jovel J. Oberio',
            'email' => 'jovel.oberio@example.com',
            'office' => 'SGOD / SGC Focal',
            'position' => 'Education Program Supervisor',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'jovel.oberio@example.com',
            'role' => 'division',
            'status' => 'active',
            'position' => 'Education Program Supervisor',
        ]);

        $this->post('/logout');
        $this->get('/setup')->assertRedirect(route('login', absolute: false));
    }

    public function test_division_admin_cannot_create_users()
    {
        $division = User::factory()->create(['role' => 'division']);

        $this->actingAs($division)
            ->post('/super/users', [
                'name' => 'Other',
                'email' => 'other@example.com',
                'office' => 'SGOD',
                'position' => 'Education Program Supervisor',
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ])
            ->assertRedirect(route('division.overview', absolute: false));

        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
    }
}
