<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\Mov;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_file_opens_an_attached_word_document_in_the_reader(): void
    {
        Storage::fake('local');
        $this->openCycle();
        $user = User::factory()->create(['role' => 'school', 'school_code' => '654321']);

        $this->actingAs($user)->post('/school/assessment', [
            'code' => 'FI1',
            'answer' => 'yes',
        ]);

        $this->actingAs($user)->post('/school/movs', [
            'code' => 'FI1A',
            'file' => UploadedFile::fake()->createWithContent(
                'SGC Notice of Meeting.docx',
                (string) file_get_contents(base_path('resources/sgc-templates/notice-sgc.docx')),
            ),
        ])->assertRedirect();

        $mov = Mov::query()->where('code', 'FI1A')->firstOrFail();

        $this->actingAs($user)
            ->getJson('/movs/'.$mov->id.'/preview')
            ->assertOk()
            ->assertJsonPath('kind', 'docx')
            ->assertJsonPath('label', 'SGC Notice of Meeting.docx');

        $html = (string) $this->actingAs($user)->getJson('/movs/'.$mov->id.'/preview')->json('html');
        $this->assertNotSame('', trim(strip_tags($html)));
    }

    public function test_view_file_opens_an_attached_pdf_inline(): void
    {
        Storage::fake('local');
        $this->openCycle();
        $user = User::factory()->create(['role' => 'school', 'school_code' => '654321']);

        $this->actingAs($user)->post('/school/assessment', [
            'code' => 'FI1',
            'answer' => 'yes',
        ]);
        $this->actingAs($user)->post('/school/movs', [
            'code' => 'FI1A',
            'file' => UploadedFile::fake()->create('minutes.pdf', 40, 'application/pdf'),
        ]);

        $mov = Mov::query()->where('code', 'FI1A')->firstOrFail();

        $this->actingAs($user)
            ->getJson('/movs/'.$mov->id.'/preview')
            ->assertOk()
            ->assertJsonPath('kind', 'pdf')
            ->assertJsonPath('url', route('movs.inline', $mov));

        $this->actingAs($user)
            ->get('/movs/'.$mov->id.'/inline')
            ->assertOk();
    }

    public function test_another_school_cannot_preview_the_file(): void
    {
        Storage::fake('local');
        $this->openCycle();
        $user = User::factory()->create(['role' => 'school', 'school_code' => '654321']);
        $other = User::factory()->create(['role' => 'school', 'school_code' => '999999']);

        $this->actingAs($user)->post('/school/assessment', [
            'code' => 'FI1',
            'answer' => 'yes',
        ]);
        $this->actingAs($user)->post('/school/movs', [
            'code' => 'FI1A',
            'file' => UploadedFile::fake()->create('minutes.pdf', 40, 'application/pdf'),
        ]);

        $mov = Mov::query()->where('code', 'FI1A')->firstOrFail();

        $this->actingAs($other)->getJson('/movs/'.$mov->id.'/preview')->assertForbidden();
    }

    public function test_division_can_open_a_submitted_file_for_review(): void
    {
        Storage::fake('local');
        $this->openCycle();
        $user = User::factory()->create(['role' => 'school', 'school_code' => '654321']);
        $division = User::factory()->create(['role' => 'division']);

        $this->actingAs($user)->post('/school/assessment', [
            'code' => 'FI1',
            'answer' => 'yes',
        ]);
        $this->actingAs($user)->post('/school/movs', [
            'code' => 'FI1A',
            'file' => UploadedFile::fake()->createWithContent(
                'SGC Notice of Meeting.docx',
                (string) file_get_contents(base_path('resources/sgc-templates/notice-sgc.docx')),
            ),
        ]);

        $mov = Mov::query()->where('code', 'FI1A')->firstOrFail();

        $this->actingAs($division)
            ->getJson('/movs/'.$mov->id.'/preview')
            ->assertOk()
            ->assertJsonPath('kind', 'docx')
            ->assertJsonPath('label', 'SGC Notice of Meeting.docx');
    }

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
}
