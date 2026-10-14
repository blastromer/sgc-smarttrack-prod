<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EncoderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_head_can_edit_change_password_archive_and_restore_an_encoder(): void
    {
        $head = $this->schoolHead();
        $encoder = $this->schoolEncoder();

        $this->actingAs($head)
            ->put(route('school.encoders.update', $encoder), [
                'name' => 'Ana Reyes',
                'email' => 'ana.updated@deped.gov.ph',
                'position' => 'Teacher II',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $encoder->id,
            'email' => 'ana.updated@deped.gov.ph',
            'position' => 'Teacher II',
        ]);

        $this->actingAs($head)
            ->put(route('school.encoders.password', $encoder), [
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('new-password-1', (string) $encoder->fresh()->password));

        $this->actingAs($head)
            ->post(route('school.encoders.archive', $encoder))
            ->assertRedirect();

        $this->assertSame('archived', $encoder->fresh()->status);

        $this->post('/logout');

        $this->post('/login', [
            'email' => 'ana.updated@deped.gov.ph',
            'password' => 'new-password-1',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($head)
            ->post(route('school.encoders.restore', $encoder))
            ->assertRedirect();

        $this->assertSame('active', $encoder->fresh()->status);
    }

    public function test_school_head_can_keep_an_older_encoder_position(): void
    {
        $head = $this->schoolHead();
        $encoder = $this->schoolEncoder(['position' => 'Teacher / SGC coordinator']);

        $this->actingAs($head)
            ->put(route('school.encoders.update', $encoder), [
                'name' => 'Ana Reyes',
                'email' => 'ana.reyes@deped.gov.ph',
                'position' => 'Teacher / SGC coordinator',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_school_head_cannot_manage_another_schools_encoder(): void
    {
        $head = $this->schoolHead();
        $encoder = $this->schoolEncoder(['school_code' => '999999', 'email' => 'other@deped.gov.ph']);

        $this->actingAs($head)
            ->put(route('school.encoders.update', $encoder), [
                'name' => 'Ana Reyes',
                'email' => 'other@deped.gov.ph',
                'position' => 'Teacher I',
            ])
            ->assertNotFound();
    }

    private function schoolHead(): User
    {
        return User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_name' => 'Rizal Elementary School',
            'school_code' => '654321',
        ]);
    }

    private function schoolEncoder(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'school',
            'status' => 'active',
            'name' => 'Ana Reyes',
            'email' => 'ana.reyes@deped.gov.ph',
            'school_name' => 'Rizal Elementary School',
            'school_code' => '654321',
            'position' => 'Teacher I',
        ], $overrides));
    }
}
