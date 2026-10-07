<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Cycle;
use App\Models\User;
use App\Support\AssessmentEngine;
use App\Support\FatCatalog;
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
            'level' => 'Public Elementary and Secondary',
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

    /**
     * @param  list<string>  $yesCodes
     */
    private function encodeIndicators(User $encoder, array $yesCodes): Assessment
    {
        foreach (FatCatalog::indicators() as $indicator) {
            $this->actingAs($encoder)->post('/school/assessment', [
                'code' => $indicator['code'],
                'answer' => in_array($indicator['code'], $yesCodes, true) ? 'yes' : 'no',
            ]);
        }

        return Assessment::query()->where('school_code', '654321')->firstOrFail();
    }

    private function uploadRequiredMovs(User $encoder, Assessment $assessment): void
    {
        $assessment->load(['indicators', 'movs']);
        foreach (AssessmentEngine::requiredSlots($assessment) as $slot) {
            $this->actingAs($encoder)->post('/school/movs', [
                'code' => $slot['code'],
                'file' => UploadedFile::fake()->create($slot['code'].'.pdf', 40, 'application/pdf'),
            ]);
        }
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

        $assessment = Assessment::query()->where('school_code', '654321')->first();
        $this->uploadRequiredMovs($encoder, $assessment);

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

    public function test_division_must_accept_every_uploaded_mov_before_complete()
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
            'file' => UploadedFile::fake()->create('FI1A.pdf', 40, 'application/pdf'),
        ]);
        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'Validity',
            'file' => UploadedFile::fake()->create('Validity.pdf', 20, 'application/pdf'),
        ]);
        $this->actingAs($head)->post('/school/submit/qa');
        $this->actingAs($head)->post('/school/submit');

        $assessment = Assessment::query()->where('school_code', '654321')->first();
        $first = $assessment->movs()->where('code', 'Validity')->first();
        $second = $assessment->movs()->where('code', 'FI1A')->first();

        $this->actingAs($division)->post(route('division.movs.accept', $first))->assertRedirect();
        $this->actingAs($division)->post(route('division.review.complete', $assessment))->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'under_review',
        ]);

        $this->actingAs($division)->post(route('division.movs.accept', $second))->assertRedirect();
        $this->actingAs($division)->post(route('division.review.complete', $assessment))->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'validated',
            'result' => 'not_yet',
        ]);
    }

    public function test_division_can_accept_remaining_mov_after_packet_was_closed_early()
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
            'file' => UploadedFile::fake()->create('FI1A.pdf', 40, 'application/pdf'),
        ]);
        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'Validity',
            'file' => UploadedFile::fake()->create('Validity.pdf', 20, 'application/pdf'),
        ]);
        $this->actingAs($head)->post('/school/submit/qa');
        $this->actingAs($head)->post('/school/submit');

        $assessment = Assessment::query()->where('school_code', '654321')->first();
        $first = $assessment->movs()->where('code', 'Validity')->first();
        $second = $assessment->movs()->where('code', 'FI1A')->first();
        $this->actingAs($division)->post(route('division.movs.accept', $first));
        $assessment->update(['status' => 'validated', 'result' => 'not_yet']);

        $this->actingAs($division)->post(route('division.movs.accept', $second))->assertRedirect();
        $this->assertDatabaseHas('movs', ['id' => $second->id, 'status' => 'valid']);
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'status' => 'under_review']);
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

    public function test_school_head_cannot_withdraw_after_division_accepts_a_mov()
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
        $mov = $assessment->movs()->where('code', 'Validity')->first();
        $this->actingAs($division)->post(route('division.movs.accept', $mov))->assertRedirect();

        $this->actingAs($head)
            ->from('/school/submit')
            ->post('/school/submit/withdraw')
            ->assertRedirect('/school/submit')
            ->assertSessionHas('status', 'Cannot withdraw after Division has accepted a MOV.');

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'under_review',
        ]);
    }

    public function test_primary_yes_on_fi6_requires_both_minimum_movs()
    {
        Storage::fake('local');
        $this->openCycle();
        [$encoder, $head] = $this->schoolPair();

        $assessment = $this->encodeIndicators($encoder, ['FI6']);
        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'FI6A',
            'file' => UploadedFile::fake()->create('FI6A.pdf', 40, 'application/pdf'),
        ]);
        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'Validity',
            'file' => UploadedFile::fake()->create('Validity.pdf', 20, 'application/pdf'),
        ]);

        $this->actingAs($head)->post('/school/submit/qa')->assertForbidden();

        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'FI6A-2',
            'file' => UploadedFile::fake()->create('FI6A-2.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $this->actingAs($head)->post('/school/submit/qa')->assertRedirect();
        $this->assertNotNull($assessment->fresh()->qa_certified_at);
    }

    public function test_assessment_page_recommends_minimum_mov_to_submit()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $this->actingAs($encoder)
            ->get('/school/assessment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('school/Assessment')
                ->has('indicators', 12)
                ->where('indicators.0.code', 'FI1')
                ->where('indicators.0.ai.template', 'SGC Notice of Meeting')
                ->where('indicators.0.ai.submit.0', 'FI1A · Notice of meeting (at least 1 of 4 Regular Meetings)')
                ->where('indicators.0.templates.0.key', 'notice-sgc'));

        $this->actingAs($encoder)->post('/school/assessment', ['code' => 'FI1', 'answer' => 'yes']);

        $this->actingAs($encoder)
            ->get('/school/assessment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('indicators.0.ai.missing.0', 'FI1A · Notice of meeting (at least 1 of 4 Regular Meetings)'));
    }

    public function test_assessment_card_shows_minimum_slot_file_after_upload()
    {
        Storage::fake('local');
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $this->actingAs($encoder)
            ->get('/school/assessment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('indicators.0.minimum.0.code', 'FI1A')
                ->where('indicators.0.minimum.0.has_file', false)
                ->where('indicators.0.minimum.0.can_replace', false)
                ->where('indicators.0.minimum.0.templates.0.key', 'notice-sgc')
                ->where('indicators.0.minimum.0.templates.0.previewable', true));

        $this->actingAs($encoder)->post('/school/assessment', ['code' => 'FI1', 'answer' => 'yes']);

        $this->actingAs($encoder)
            ->get('/school/assessment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('indicators.0.minimum.0.can_replace', true)
                ->where('indicators.0.minimum.0.file', null)
                ->where('indicators.0.status.badge', 'Incomplete'));

        $this->actingAs($encoder)
            ->from('/school/assessment')
            ->post('/school/movs', [
                'code' => 'FI1A',
                'file' => UploadedFile::fake()->create('notice-q1.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect('/school/assessment');

        $this->actingAs($encoder)
            ->get('/school/assessment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('indicators.0.status.badge', 'Complete')
                ->where('indicators.0.minimum.0.has_file', true)
                ->where('indicators.0.minimum.0.file', 'notice-q1.pdf')
                ->where('indicators.0.minimum.0.size', '40 KB')
                ->where('indicators.0.minimum.0.status', 'uploaded')
                ->where('indicators.0.minimum.0.badge', 'Attached (draft)'));

        $this->actingAs($encoder)->post('/school/assessment', ['code' => 'FI2', 'answer' => 'no']);

        $this->actingAs($encoder)
            ->get('/school/assessment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('indicators.1.status.badge', 'Complete'));
    }

    public function test_school_dashboard_shares_packet_ai_assistance()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $this->actingAs($encoder)
            ->get('/school')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('school/Dashboard')
                ->where('sgc.ai.template', 'Official SGC MOV template')
                ->where('sgc.ai.href', '/school/assessment')
                ->has('sgc.ai.unencoded', 12));

        $this->actingAs($encoder)->post('/school/assessment', ['code' => 'FI1', 'answer' => 'yes']);

        $this->actingAs($encoder)
            ->get('/school')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sgc.ai.href', '/school/assessment')
                ->where('sgc.ai.missing.0', 'FI1A · Notice of meeting (at least 1 of 4 Regular Meetings)'));
    }

    public function test_encoder_can_chat_with_ai_assistance()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $response = $this->actingAs($encoder)
            ->postJson('/school/ai/chat', ['message' => 'What should I encode first?'])
            ->assertOk();

        $this->assertIsString($response->json('reply'));
        $this->assertStringContainsString('Encode remaining primary FIs first', $response->json('reply'));
    }

    public function test_encoder_can_ask_for_a_walkthrough()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $reply = $this->actingAs($encoder)
            ->postJson('/school/ai/chat', ['message' => 'Would you like me to walk you through?'])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('walkthrough', strtolower($reply));
        $this->assertStringContainsString('My assessment', $reply);
        $this->assertStringContainsString('I will not encode Yes or No', $reply);
    }

    public function test_encoder_can_open_the_mov_template_library()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $this->actingAs($encoder)
            ->get('/school/templates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('school/Templates')
                ->has('groups')
                ->where('groups.0.files.0.key', 'notice-sgc'));
    }

    public function test_encoder_can_download_official_mov_template()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $this->actingAs($encoder)
            ->get('/school/templates/notice-sgc')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_filled_template_stamps_school_identity()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();
        $encoder->update(['school_name' => 'Rizal Elementary School']);

        $response = $this->actingAs($encoder)
            ->get('/school/templates/notice-sgc')
            ->assertOk();

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($xml);
        $this->assertStringContainsString('Rizal Elementary School', $xml);
        $this->assertStringContainsString('654321', $xml);
        $this->assertStringContainsString('auto-filled school data', $xml);
    }

    public function test_encoder_can_save_form_data_used_on_filled_templates()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $this->actingAs($encoder)
            ->post('/school/form-data', [
                'region' => 'Region VI',
                'division' => 'SDO Cadiz City',
                'school_name' => 'Rizal Elementary School',
                'school_address' => 'Rizal St., Cadiz City',
                'school_year' => '2026-2027',
                'co_chair_elected' => 'Maria Santos',
                'secretary_name' => 'Juan Cruz',
                'venue' => 'SGC Office',
                'meeting_subject' => 'First Regular Meeting',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('school_form_profiles', [
            'school_code' => '654321',
            'region' => 'Region VI',
            'secretary_name' => 'Juan Cruz',
        ]);

        $response = $this->actingAs($encoder)
            ->get('/school/templates/notice-sgc')
            ->assertOk();

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $header = ($zip->getFromName('word/header1.xml') ?: '')
            .($zip->getFromName('word/header2.xml') ?: '')
            .($zip->getFromName('word/header3.xml') ?: '');
        $body = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        $this->assertStringContainsString('Region VI', $header);
        $this->assertStringContainsString('Rizal Elementary School', $header);
        $this->assertStringContainsString('S/Y 2026-2027', $header);
        $this->assertStringNotContainsString('[REGION]', $header);
        $this->assertStringContainsString('Maria Santos', $body);
        $this->assertStringContainsString('Juan Cruz', $body);
        $this->assertStringContainsString('SGC Office', $body);
        $this->assertStringContainsString('First Regular Meeting', $body);
    }

    public function test_blank_template_download_skips_school_stamp()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();
        $encoder->update(['school_name' => 'Rizal Elementary School']);

        $response = $this->actingAs($encoder)
            ->get('/school/templates/notice-sgc?blank=1')
            ->assertOk();

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($xml);
        $this->assertStringNotContainsString('auto-filled school data', $xml);
    }

    public function test_uploading_a_minimum_mov_encodes_yes_for_that_fi()
    {
        Storage::fake('local');
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $this->actingAs($encoder)
            ->post('/school/movs', [
                'code' => 'FI2A',
                'file' => UploadedFile::fake()->create('minutes-spt.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect();

        $assessment = Assessment::query()->where('school_code', '654321')->firstOrFail();
        $this->assertSame('yes', $assessment->indicators()->where('code', 'FI2')->value('answer'));
        $this->assertTrue($assessment->movs()->where('code', 'FI2A')->first()?->hasFile());
    }

    public function test_a_no_answer_does_not_create_a_minimum_mov_slot()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $this->actingAs($encoder)->post('/school/assessment', ['code' => 'FI3', 'answer' => 'no']);

        $assessment = Assessment::query()->where('school_code', '654321')->firstOrFail();
        $this->assertSame('no', $assessment->indicators()->where('code', 'FI3')->value('answer'));
        $this->assertNull($assessment->movs()->where('code', 'FI3A')->first());
    }

    public function test_encoder_can_preview_official_docx_template()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $html = $this->actingAs($encoder)
            ->getJson('/school/templates/notice-sgc/preview')
            ->assertOk()
            ->assertJsonPath('key', 'notice-sgc')
            ->assertJsonPath('previewable', true)
            ->assertJsonPath('ext', 'docx')
            ->json('html');

        $this->assertIsString($html);
        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('<script', strtolower($html));
    }

    public function test_pptx_template_preview_stays_download_only()
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $this->actingAs($encoder)
            ->getJson('/school/templates/membership-cert-sgc/preview')
            ->assertOk()
            ->assertJsonPath('key', 'membership-cert-sgc')
            ->assertJsonPath('previewable', false)
            ->assertJsonPath('html', null);
    }

    public function test_encoder_can_reuse_an_uploaded_mov_on_a_matching_slot()
    {
        Storage::fake('local');
        $this->openCycle();
        [$encoder] = $this->schoolPair();

        $this->actingAs($encoder)->post('/school/assessment', ['code' => 'FI2', 'answer' => 'yes']);
        $this->actingAs($encoder)->post('/school/assessment', ['code' => 'FI4', 'answer' => 'yes']);

        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'FI2A',
            'file' => UploadedFile::fake()->create('minutes-spt.pdf', 40, 'application/pdf'),
        ]);

        $source = Assessment::query()->where('school_code', '654321')->firstOrFail()
            ->movs()->where('code', 'FI2A')->firstOrFail();

        $this->actingAs($encoder)
            ->post('/school/movs/reuse', [
                'code' => 'FI4A',
                'source_mov_id' => $source->id,
            ])
            ->assertRedirect();

        $copied = Assessment::query()->where('school_code', '654321')->firstOrFail()
            ->movs()->where('code', 'FI4A')->firstOrFail();

        $this->assertTrue($copied->hasFile());
        $this->assertSame('minutes-spt.pdf', $copied->original_name);
        $this->assertNotSame($source->path, $copied->path);
    }
}
