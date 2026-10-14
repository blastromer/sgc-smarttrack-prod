<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\Mov;
use App\Models\SchoolFormProfile;
use App\Models\User;
use App\Support\DriveMovFolders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleDriveTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_page_lists_drive_folders(): void
    {
        $this->openCycle();
        $user = User::factory()->create(['role' => 'school']);

        $this->actingAs($user)
            ->get('/school/templates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('school/Templates')
                ->where('drive.enabled', true)
                ->where('drive.connected', false)
                ->where('drive.writable', false)
                ->has('drive.folders', 44)
                ->where('drive.folders.0.key', 'FI1A')
                ->where('drive.folders.0.url', route('google.drive.open', ['key' => 'FI1A'])));
    }

    public function test_assessment_minimum_slot_includes_drive_url(): void
    {
        $this->openCycle();
        $user = User::factory()->create(['role' => 'school', 'school_code' => '654321']);

        $this->actingAs($user)->post('/school/assessment', [
            'code' => 'FI1',
            'answer' => 'yes',
        ]);

        $this->actingAs($user)
            ->get('/school/assessment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('indicators.0.minimum.0.code', 'FI1A')
                ->where('indicators.0.minimum.0.drive_url', DriveMovFolders::ensureUrlForSlot('FI1A')));
    }

    public function test_submit_page_includes_validity_drive_folder(): void
    {
        $this->openCycle();
        $user = User::factory()->create(['role' => 'school']);

        $this->actingAs($user)
            ->get('/school/submit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('drive_url', DriveMovFolders::ensureUrlForSlot('Validity')));
    }

    public function test_school_staff_is_sent_to_google_oauth(): void
    {
        $user = User::factory()->create(['role' => 'school']);

        $this->actingAs($user)
            ->get('/auth/google/redirect')
            ->assertRedirect();

        $location = (string) $this->actingAs($user)->get('/auth/google/redirect')->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertStringContainsString('client_id=', $location);
        $this->assertStringContainsString(urlencode('http://127.0.0.1/auth/google/callback'), $location);
        $this->assertStringContainsString(urlencode(DriveMovFolders::WRITE_SCOPE), $location);
        $this->assertStringNotContainsString('readonly', $location);
    }

    public function test_division_cannot_connect_drive(): void
    {
        $user = User::factory()->create(['role' => 'division']);

        $this->actingAs($user)->get('/auth/google/redirect')->assertRedirect();
    }

    public function test_expired_oauth_state_does_not_connect(): void
    {
        $this->get('/auth/google/callback?code=fake&state=missing')
            ->assertRedirect(rtrim((string) config('app.url'), '/').'/school/templates');
    }

    public function test_disconnect_clears_cached_token(): void
    {
        $user = User::factory()->create(['role' => 'school']);
        Cache::forever(DriveMovFolders::tokenCacheKey($user->id), 'refresh-token');

        $this->actingAs($user)->post('/auth/google/disconnect')->assertRedirect();
        $this->assertFalse(DriveMovFolders::connected($user->id));
    }

    public function test_opening_a_folder_without_a_drive_connection_keeps_the_library_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/school/drive/FI1B')
            ->assertRedirect(DriveMovFolders::copyUrl('1PRnGPcv1tgBAboqKTZaLXSNOwMvx3S4z'));
    }

    public function test_opening_a_folder_goes_to_the_school_directory_when_it_already_exists(): void
    {
        $user = User::factory()->create([
            'school_name' => 'Cadiz Elementary School',
            'school_code' => '654321',
        ]);
        SchoolFormProfile::query()->create([
            'school_code' => '654321',
            'school_name' => 'Cadiz Elementary School',
            'school_year' => '2026-2027',
        ]);
        $parentId = DriveMovFolders::find('FI1B')['id'];
        Cache::forever(
            DriveMovFolders::schoolFolderCacheKey('654321', '2026-2027', 'Cadiz Elementary School', $parentId),
            'school-folder-id',
        );

        $this->actingAs($user)
            ->get('/school/drive/FI1B')
            ->assertRedirect('https://drive.google.com/drive/folders/school-folder-id');
    }

    public function test_opening_a_folder_creates_the_school_directory_when_missing(): void
    {
        $user = $this->schoolReadyToCreateFolders();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'ya29.test']);
            }
            if ($request->method() === 'GET') {
                return Http::response(['files' => []]);
            }

            $name = $request->data()['name'] ?? '';

            return Http::response(['id' => $name === '2026-2027' ? 'year-id' : 'school-id']);
        });

        $this->actingAs($user)
            ->get('/school/drive/FI1B')
            ->assertRedirect('https://drive.google.com/drive/folders/school-id');

        Http::assertSent(fn ($request) => $request->method() === 'POST' && ($request->data()['name'] ?? null) === 'Cadiz Elementary School');
    }

    public function test_opening_a_folder_reuses_an_existing_school_directory(): void
    {
        $user = $this->schoolReadyToCreateFolders();
        $lookups = 0;
        Http::fake(function ($request) use (&$lookups) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'ya29.test']);
            }
            $lookups++;

            return Http::response(['files' => [['id' => $lookups === 1 ? 'year-id' : 'school-id']]]);
        });

        $this->actingAs($user)
            ->get('/school/drive/FI1B')
            ->assertRedirect('https://drive.google.com/drive/folders/school-id');

        Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), 'drive/v3/files'));
    }

    public function test_manual_create_reports_existing_school_folders(): void
    {
        $user = $this->schoolReadyToCreateFolders();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'ya29.test']);
            }

            return Http::response(['files' => [['id' => 'existing-folder']]]);
        });

        $this->actingAs($user)
            ->from('/school/templates')
            ->post('/school/drive/folders')
            ->assertRedirect('/school/templates')
            ->assertSessionHas('status', 'School folders are ready: 2026-2027 / Cadiz Elementary School. Created 0, already there 44.');
    }

    public function test_uploading_a_draft_saves_a_copy_in_the_school_drive_folder(): void
    {
        Storage::fake('local');
        $this->openCycle();
        $user = $this->schoolReadyToCreateFolders();
        $head = User::factory()->create([
            'role' => 'school_head',
            'school_name' => 'Cadiz Elementary School',
            'school_code' => '654321',
            'position' => 'School Head',
        ]);
        $parentId = DriveMovFolders::find('FI1A')['id'];
        Cache::forever(
            DriveMovFolders::schoolFolderCacheKey('654321', '2026-2027', 'Cadiz Elementary School', $parentId),
            'school-folder-id',
        );
        $uploads = 0;
        Http::fake(function ($request) use (&$uploads) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'ya29.test']);
            }
            if (str_contains($request->url(), 'upload/drive')) {
                $uploads++;

                return Http::response(['id' => 'draft-file-'.$uploads]);
            }

            return Http::response([]);
        });

        $this->actingAs($user)->post('/school/assessment', [
            'code' => 'FI1',
            'answer' => 'yes',
        ]);

        $this->actingAs($user)
            ->post('/school/movs', [
                'code' => 'FI1A',
                'file' => UploadedFile::fake()->create('notice.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($status) => str_contains((string) $status, 'Saved to the school Google Drive folder for review.'));

        $mov = Mov::query()->where('code', 'FI1A')->firstOrFail();
        $this->assertSame('draft-file-1', $mov->drive_file_id);

        $this->actingAs($user)
            ->post('/school/movs', [
                'code' => 'FI1A',
                'file' => UploadedFile::fake()->create('notice-revised.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertSame('draft-file-2', $mov->fresh()->drive_file_id);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), 'draft-file-1'));

        $this->actingAs($head)
            ->post('/school/movs/'.$mov->id.'/remove')
            ->assertRedirect();

        $this->assertNull($mov->fresh()->drive_file_id);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), 'draft-file-2'));
    }

    public function test_submit_moves_files_to_drive_and_clears_the_server_copy(): void
    {
        Storage::fake('local');
        $this->openCycle();
        $encoder = $this->schoolReadyToCreateFolders();
        $head = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_name' => 'Cadiz Elementary School',
            'school_code' => '654321',
            'position' => 'School Head',
        ]);
        $division = User::factory()->create(['role' => 'division', 'status' => 'active']);
        $uploads = 0;
        Http::fake(function ($request) use (&$uploads) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'ya29.test']);
            }
            if (str_contains($url, 'alt=media')) {
                return Http::response('%PDF-1.4 submitted');
            }
            if (str_contains($url, 'upload/drive')) {
                $uploads++;

                return Http::response(['id' => 'submitted-file-'.$uploads]);
            }
            if ($request->method() === 'GET') {
                return Http::response(['files' => [['id' => 'school-folder-id']]]);
            }

            return Http::response(['id' => 'school-folder-id']);
        });

        $this->preparePacket($encoder, $head);

        $this->actingAs($head)
            ->post('/school/submit')
            ->assertRedirect()
            ->assertSessionHas('status', 'Packet submitted to Division. Files are in the school Google Drive folder.');

        $mov = Mov::query()->where('code', 'FI1A')->firstOrFail();
        $this->assertNull($mov->path);
        $this->assertNotNull($mov->drive_file_id);
        $this->assertTrue($mov->hasFile());
        $this->assertSame([], Storage::disk('local')->allFiles('movs'));

        $inline = $this->actingAs($division)->get('/movs/'.$mov->id.'/inline');
        $inline->assertOk();
        $this->assertStringContainsString('%PDF-1.4 submitted', $inline->streamedContent());
    }

    public function test_division_opens_the_google_drive_copy_and_sees_it_on_the_review(): void
    {
        Storage::fake('local');
        $this->openCycle();
        $encoder = $this->schoolReadyToCreateFolders();
        $division = User::factory()->create(['role' => 'division', 'status' => 'active']);
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'ya29.test']);
            }
            if (str_contains($url, 'alt=media')) {
                return Http::response('DRIVE-COPY');
            }
            if (str_contains($url, 'upload/drive')) {
                return Http::response(['id' => 'drive-copy-1']);
            }
            if ($request->method() === 'GET') {
                return Http::response(['files' => [['id' => 'school-folder-id']]]);
            }

            return Http::response(['id' => 'school-folder-id']);
        });

        $this->actingAs($encoder)->post('/school/assessment', ['code' => 'FI1', 'answer' => 'yes']);
        $this->actingAs($encoder)->post('/school/movs', [
            'code' => 'FI1A',
            'file' => UploadedFile::fake()->createWithContent('notice.pdf', 'LOCAL-COPY'),
        ]);

        $mov = Mov::query()->where('code', 'FI1A')->firstOrFail();
        $this->assertNotNull($mov->path);
        $this->assertSame('drive-copy-1', $mov->drive_file_id);

        $this->actingAs($division)
            ->get('/division/queue/'.$mov->assessment_id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('movs', function ($movs) {
                $row = collect($movs)->firstWhere('code', 'FI1A');

                return is_array($row) && $row['on_drive'] === true && $row['file'] === 'notice.pdf';
            }));

        $this->actingAs($division)
            ->getJson('/movs/'.$mov->id.'/preview')
            ->assertOk()
            ->assertJsonPath('note', 'Opened from the school Google Drive folder.')
            ->assertJsonPath('kind', 'pdf');

        $inline = $this->actingAs($division)->get('/movs/'.$mov->id.'/inline');
        $inline->assertOk();
        $this->assertStringContainsString('DRIVE-COPY', $inline->streamedContent());
        $this->assertStringNotContainsString('LOCAL-COPY', $inline->streamedContent());
    }

    public function test_submit_stays_on_the_server_until_drive_is_connected(): void
    {
        Storage::fake('local');
        $this->openCycle();
        $encoder = User::factory()->create([
            'role' => 'school',
            'status' => 'active',
            'school_name' => 'Cadiz Elementary School',
            'school_code' => '654321',
        ]);
        $head = User::factory()->create([
            'role' => 'school_head',
            'status' => 'active',
            'school_name' => 'Cadiz Elementary School',
            'school_code' => '654321',
            'position' => 'School Head',
        ]);

        $this->preparePacket($encoder, $head);

        $this->actingAs($head)
            ->from('/school/submit')
            ->post('/school/submit')
            ->assertRedirect('/school/submit')
            ->assertSessionHas('status', fn ($status) => str_contains((string) $status, 'Connect Google Drive'));

        $this->assertDatabaseMissing('assessments', [
            'school_code' => '654321',
            'status' => 'submitted',
        ]);
        $mov = Mov::query()->where('code', 'FI1A')->firstOrFail();
        $this->assertNotNull($mov->path);
        Storage::disk('local')->assertExists($mov->path);
    }

    public function test_create_asks_for_a_school_name_first(): void
    {
        $user = User::factory()->create(['school_name' => '', 'school_code' => '654321']);
        Cache::forever(DriveMovFolders::tokenCacheKey($user->id), 'refresh-token');
        Cache::forever(DriveMovFolders::scopeCacheKey($user->id), DriveMovFolders::WRITE_SCOPE);

        $this->actingAs($user)
            ->from('/school/templates')
            ->post('/school/drive/folders')
            ->assertRedirect('/school/templates')
            ->assertSessionHas('status');
    }

    private function preparePacket(User $encoder, User $head): void
    {
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
    }

    private function schoolReadyToCreateFolders(): User
    {
        $user = User::factory()->create([
            'school_name' => 'Cadiz Elementary School',
            'school_code' => '654321',
        ]);
        SchoolFormProfile::query()->create([
            'school_code' => '654321',
            'school_name' => 'Cadiz Elementary School',
            'school_year' => '2026-2027',
        ]);
        Cache::forever(DriveMovFolders::tokenCacheKey($user->id), 'refresh-token');
        Cache::forever(DriveMovFolders::scopeCacheKey($user->id), DriveMovFolders::WRITE_SCOPE);

        return $user;
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
