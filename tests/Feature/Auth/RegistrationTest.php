<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\SchoolRegistrationApproved;
use App\Notifications\SchoolRegistrationSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $this->get('/register')->assertStatus(200);
    }

    public function test_school_head_registration_notifies_division()
    {
        Notification::fake();

        $division = User::factory()->create([
            'role' => 'division',
            'status' => 'active',
        ]);

        $this->post('/register', [
            'name' => 'Maria Santos',
            'email' => 'head@example.com',
            'role' => 'school_head',
            'school_name' => 'Rizal Elementary School',
            'school_code' => '654321',
            'position' => 'School Head',
            'password' => 'password',
        ])->assertRedirect(route('register.pending', absolute: false));

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'email' => 'head@example.com',
            'role' => 'school_head',
            'status' => 'pending',
        ]);

        Notification::assertSentTo($division, SchoolRegistrationSubmitted::class);
    }

    public function test_encoder_cannot_register_before_school_head_is_active()
    {
        $this->from('/register')->post('/register', [
            'name' => 'Ana Reyes',
            'email' => 'teacher@example.com',
            'role' => 'school',
            'school_name' => 'Rizal Elementary School',
            'school_code' => '654321',
            'position' => 'Teacher I',
            'password' => 'password',
        ])->assertRedirect('/register')->assertSessionHasErrors('school_code');
    }

    public function test_school_head_can_accept_an_encoder_registration()
    {
        Notification::fake();

        $head = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_code' => '654321',
            'school_name' => 'Rizal Elementary School',
        ]);

        $this->post('/register', [
            'name' => 'Ana Reyes',
            'email' => 'teacher@example.com',
            'role' => 'school',
            'school_name' => 'Rizal Elementary School',
            'school_code' => '654321',
            'position' => 'Teacher I',
            'password' => 'password',
        ])->assertRedirect(route('register.pending', absolute: false));

        Notification::assertSentTo($head, SchoolRegistrationSubmitted::class);

        $encoder = User::query()->where('email', 'teacher@example.com')->first();

        $this->actingAs($head)
            ->post(route('school.encoders.accept', $encoder))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $encoder->id,
            'status' => 'active',
            'role' => 'school',
        ]);

        Notification::assertSentTo($encoder->fresh(), SchoolRegistrationApproved::class);

        $this->post('/logout');

        $this->post('/login', [
            'email' => 'teacher@example.com',
            'password' => 'password',
        ])->assertRedirect(route('school.dashboard', absolute: false));
    }

    public function test_division_admin_can_accept_a_school_head_registration()
    {
        Notification::fake();

        $division = User::factory()->create([
            'role' => 'division',
            'status' => 'active',
        ]);

        $head = User::factory()->create([
            'role' => 'school_head',
            'status' => 'pending',
            'school_name' => 'Rizal Elementary School',
            'school_code' => '654321',
        ]);

        $this->actingAs($division)
            ->post(route('division.registrations.accept', $head))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $head->id,
            'status' => 'active',
        ]);

        Notification::assertSentTo($head->fresh(), SchoolRegistrationApproved::class);
    }

    public function test_school_head_cannot_reuse_another_school_id_or_name()
    {
        User::factory()->create([
            'role' => 'school_head',
            'status' => 'pending',
            'school_name' => 'Rizal Elementary School',
            'school_code' => '654321',
        ]);

        $this->from('/register')->post('/register', [
            'name' => 'Pedro Cruz',
            'email' => 'other.head@example.com',
            'role' => 'school_head',
            'school_name' => 'Bonifacio Elementary School',
            'school_code' => '654321',
            'position' => 'School Head',
            'password' => 'password',
        ])->assertRedirect('/register')->assertSessionHasErrors('school_code');

        $this->from('/register')->post('/register', [
            'name' => 'Pedro Cruz',
            'email' => 'other.head@example.com',
            'role' => 'school_head',
            'school_name' => 'Rizal Elementary School',
            'school_code' => '111222',
            'position' => 'Principal I',
            'password' => 'password',
        ])->assertRedirect('/register')->assertSessionHasErrors('school_name');
    }

    public function test_encoder_must_use_the_registered_school_name()
    {
        User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_name' => 'Rizal Elementary School',
            'school_code' => '654321',
        ]);

        $this->from('/register')->post('/register', [
            'name' => 'Ana Reyes',
            'email' => 'teacher@example.com',
            'role' => 'school',
            'school_name' => 'SPED Training',
            'school_code' => '654321',
            'position' => 'Teacher I',
            'password' => 'password',
        ])->assertRedirect('/register')->assertSessionHasErrors('school_name');
    }

    public function test_position_must_come_from_the_defined_list()
    {
        $this->from('/register')->post('/register', [
            'name' => 'Maria Santos',
            'email' => 'head@example.com',
            'role' => 'school_head',
            'school_name' => 'Rizal Elementary School',
            'school_code' => '654321',
            'position' => 'Teacher / SGC coordinator',
            'password' => 'password',
        ])->assertRedirect('/register')->assertSessionHasErrors('position');
    }
}
