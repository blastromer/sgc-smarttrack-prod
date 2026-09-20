<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Cycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FatSubmissionTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * @return array{0: User, 1: User}
     */
    private function schoolPair(): array
    {
        $encoder = User::factory()->create([
            'role' => 'school',
            'status' => 'active',
            'school_code' => '654321',
        ]);
        $head = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_code' => '654321',
            'position' => 'School Head',
        ]);

        return [$encoder, $head];
    }

    public function test_encoder_prepares_packet_and_school_head_submits()
    {
        Storage::fake('local');
        $this->openCycle();
        [$encoder, $head] = $this->schoolPair();

        $this->actingAs($encoder)->post('/school/assessment', [
            'code' => 'FI1',
            'answer' => 'yes',
        ])->assertRedirect();

        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'FI1A',
            'file' => UploadedFile::fake()->create('FI1A.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'Validity',
            'file' => UploadedFile::fake()->create('Validity.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        foreach (['FI2', 'FI3', 'FI4', 'FI5', 'FI6', 'FI7', 'FI8', 'FI9', 'FI10', 'FI11', 'FI12'] as $code) {
            $this->actingAs($encoder)->post('/school/assessment', [
                'code' => $code,
                'answer' => 'no',
            ]);
        }

        $this->actingAs($encoder)->post('/school/submit/qa')->assertForbidden();
        $this->actingAs($encoder)->post('/school/submit')->assertForbidden();

        $this->actingAs($head)->post('/school/submit/qa')->assertRedirect();
        $this->actingAs($head)->post('/school/submit')->assertRedirect();

        $this->actingAs($encoder)->post('/school/assessment', [
            'code' => 'FI1',
            'answer' => 'no',
        ])->assertForbidden();

        $this->assertDatabaseHas('assessments', [
            'school_code' => '654321',
            'status' => 'submitted',
        ]);
    }

    public function test_returned_mov_must_be_replaced_before_resubmit()
    {
        Storage::fake('local');
        $this->openCycle();
        [$encoder, $head] = $this->schoolPair();
        $division = User::factory()->create(['role' => 'division', 'status' => 'active']);

        $this->actingAs($encoder)->post('/school/assessment', ['code' => 'FI1', 'answer' => 'yes']);
        foreach (['FI2', 'FI3', 'FI4', 'FI5', 'FI6', 'FI7', 'FI8', 'FI9', 'FI10', 'FI11', 'FI12'] as $code) {
            $this->actingAs($encoder)->post('/school/assessment', ['code' => $code, 'answer' => 'no']);
        }
        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'FI1A',
            'file' => UploadedFile::fake()->create('FI1A.pdf', 80, 'application/pdf'),
        ]);
        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'Validity',
            'file' => UploadedFile::fake()->create('Validity.pdf', 20, 'application/pdf'),
        ]);
        $this->actingAs($head)->post('/school/submit/qa');
        $this->actingAs($head)->post('/school/submit');

        $assessment = Assessment::query()->where('school_code', '654321')->first();
        $mov = $assessment->movs()->where('code', 'FI1A')->first();
        $this->actingAs($division)->post(route('division.movs.return', $mov), [
            'reason' => 'Minutes do not show 50%+1 quorum',
        ])->assertRedirect();

        $this->actingAs($head)->post('/school/submit')->assertForbidden();

        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'FI1A',
            'file' => UploadedFile::fake()->create('FI1A-fixed.pdf', 90, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($head)->post('/school/submit/qa')->assertRedirect();
        $this->actingAs($head)->post('/school/submit')->assertRedirect();

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('movs', [
            'id' => $mov->id,
            'status' => 'uploaded',
        ]);
    }

    public function test_division_marks_functional_when_ten_yes_movs_are_valid()
    {
        Storage::fake('local');
        $this->openCycle();
        [$encoder, $head] = $this->schoolPair();
        $division = User::factory()->create(['role' => 'division', 'status' => 'active']);

        foreach (['FI1', 'FI2', 'FI3', 'FI4', 'FI5', 'FI6', 'FI7', 'FI8', 'FI9', 'FI10'] as $code) {
            $this->actingAs($encoder)->post('/school/assessment', ['code' => $code, 'answer' => 'yes']);
        }
        foreach (['FI11', 'FI12'] as $code) {
            $this->actingAs($encoder)->post('/school/assessment', ['code' => $code, 'answer' => 'no']);
        }

        $codes = ['FI1A', 'FI2A', 'FI3A', 'FI4A', 'FI5A', 'FI6A', 'FI7A', 'FI8A', 'FI9A', 'FI10A', 'Validity'];
        foreach ($codes as $code) {
            $this->actingAs($encoder)->post('/school/movs', [
                'code' => $code,
                'file' => UploadedFile::fake()->create($code.'.pdf', 40, 'application/pdf'),
            ]);
        }

        $this->actingAs($head)->post('/school/submit/qa');
        $this->actingAs($head)->post('/school/submit');

        $assessment = Assessment::query()->where('school_code', '654321')->first();
        foreach ($assessment->movs as $mov) {
            $this->actingAs($division)->post(route('division.movs.accept', $mov));
        }

        $this->actingAs($division)->post(route('division.review.complete', $assessment))->assertRedirect();

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'validated',
            'result' => 'functional',
        ]);
    }

    public function test_encoder_requests_removal_and_school_head_removes_unapproved_file()
    {
        Storage::fake('local');
        $this->openCycle();
        [$encoder, $head] = $this->schoolPair();

        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'Validity',
            'file' => UploadedFile::fake()->create('Validity.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $assessment = Assessment::query()->where('school_code', '654321')->first();
        $mov = $assessment->movs()->where('code', 'Validity')->first();

        $this->actingAs($encoder)
            ->post(route('school.movs.remove', $mov))
            ->assertForbidden();

        $this->actingAs($encoder)
            ->post(route('school.movs.request-removal', $mov))
            ->assertRedirect();

        $this->assertNotNull($mov->fresh()->removal_requested_at);

        $this->actingAs($head)
            ->post(route('school.movs.remove', $mov))
            ->assertRedirect();

        $this->assertDatabaseHas('movs', [
            'id' => $mov->id,
            'path' => null,
            'status' => 'draft',
        ]);
    }

    public function test_school_head_can_withdraw_unapproved_packet()
    {
        Storage::fake('local');
        $this->openCycle();
        [$encoder, $head] = $this->schoolPair();

        $this->actingAs($encoder)->post('/school/assessment', ['code' => 'FI1', 'answer' => 'yes']);
        foreach (['FI2', 'FI3', 'FI4', 'FI5', 'FI6', 'FI7', 'FI8', 'FI9', 'FI10', 'FI11', 'FI12'] as $code) {
            $this->actingAs($encoder)->post('/school/assessment', ['code' => $code, 'answer' => 'no']);
        }
        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'FI1A',
            'file' => UploadedFile::fake()->create('FI1A.pdf', 80, 'application/pdf'),
        ]);
        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'Validity',
            'file' => UploadedFile::fake()->create('Validity.pdf', 20, 'application/pdf'),
        ]);
        $this->actingAs($head)->post('/school/submit/qa');
        $this->actingAs($head)->post('/school/submit');

        $this->actingAs($encoder)->post('/school/submit/withdraw')->assertForbidden();
        $this->actingAs($head)->post('/school/submit/withdraw')->assertRedirect();

        $this->assertDatabaseHas('assessments', [
            'school_code' => '654321',
            'status' => 'in_progress',
        ]);
    }
}
