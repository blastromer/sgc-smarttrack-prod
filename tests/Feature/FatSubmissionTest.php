<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Cycle;
use App\Models\User;
use App\Support\AssessmentEngine;
use App\Support\DriveMovFolders;
use App\Support\FatCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FatSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'ya29.test']);
            }
            if (str_contains($url, 'upload/drive')) {
                return Http::response(['id' => 'drive-'.bin2hex(random_bytes(4))]);
            }
            if (str_contains($url, 'api.openai.com')) {
                $body = $request->body();
                if (str_contains($body, '"kind":"resolution"') || str_contains($body, '\"kind\":\"resolution\"')) {
                    $whereas = [];
                    for ($i = 1; $i <= 9; $i++) {
                        $whereas[] = 'Resolution whereas clause '.$i;
                    }

                    return Http::response([
                        'choices' => [[
                            'message' => [
                                'content' => json_encode([
                                    'subject' => 'CONTINUOUS SIP AND AIP IMPLEMENTATION',
                                    'whereas' => $whereas,
                                    'resolved' => [
                                        'Adopt continuous SIP monitoring for the school year',
                                        'Endorse quarterly progress reporting to stakeholders',
                                    ],
                                    'day' => '15',
                                    'month' => 'November',
                                    'year' => '2026',
                                ]),
                            ],
                        ]],
                    ]);
                }

                if (str_contains($body, '"kind":"concept"') || str_contains($body, '\"kind\":\"concept\"')) {
                    return Http::response([
                        'choices' => [[
                            'message' => [
                                'content' => json_encode([
                                    'title' => 'Project Learning Continuity Kits',
                                    'supervisor' => 'School Head',
                                    'organization' => 'School Governance Council',
                                    'start_date' => 'August 1, 2026',
                                    'end_date' => 'March 30, 2027',
                                    'background' => 'This concept note proposes a school-community support activity for SY 2026-2027.',
                                    'objectives' => [
                                        'Strengthen stakeholder coordination',
                                        'Align SIP and AIP priorities',
                                        'Mobilize partners for learning continuity',
                                    ],
                                    'activities' => [
                                        'Conduct planning workshop with SGC officers',
                                        'Prepare learner support kits',
                                        'Monitor distribution during suspensions',
                                    ],
                                    'accomplishments' => [
                                        'Approved concept note and activity calendar',
                                        'Assembled continuity kits for priority learners',
                                        'Documented partner commitments',
                                    ],
                                    'indicators' => [
                                        'Concept note endorsed by SGC officers',
                                        'Kits ready within 24 hours of suspension',
                                        'Partner support logged each quarter',
                                    ],
                                    'budget' => [
                                        ['item' => 'Printing of modules', 'quantity' => '100', 'unit_price' => '10', 'total' => '1000'],
                                        ['item' => 'Bond paper', 'quantity' => '20', 'unit_price' => '250', 'total' => '5000'],
                                        ['item' => 'Transport support', 'quantity' => '1', 'unit_price' => '3000', 'total' => '3000'],
                                    ],
                                ]),
                            ],
                        ]],
                    ]);
                }

                if (str_contains($body, '"kind":"action_plan"') || str_contains($body, '\"kind\":\"action_plan\"')) {
                    $rows = [];
                    for ($i = 1; $i <= 5; $i++) {
                        $rows[] = [
                            'area' => 'Governance area '.$i,
                            'need' => 'Prioritized need '.$i,
                            'objective' => 'Objective '.$i,
                            'activities' => 'Activity '.$i,
                            'outputs' => 'Expected output '.$i,
                            'timeframe' => 'Q'.$i.' SY 2026-2027',
                            'persons' => 'SGC Officers',
                            'materials' => 'Advocacy kit '.$i,
                        ];
                    }

                    return Http::response([
                        'choices' => [[
                            'message' => [
                                'content' => json_encode(['rows' => $rows]),
                            ],
                        ]],
                    ]);
                }

                if (str_contains($body, '"kind":"documentation"') || str_contains($body, '\"kind\":\"documentation\"')) {
                    return Http::response([
                        'choices' => [[
                            'message' => [
                                'content' => json_encode([
                                    'replacements' => [
                                        '[INSERT PROJECT TITLE]' => 'SY 2026-2027 SGC Regular Meeting and SIP/AIP Alignment Documentation Report',
                                        '[Insert project overview, including the date, venue, attendees, & leading organization]' => 'On 6 November 2026 at the school conference room, SGC officers and stakeholders reviewed SIP and AIP priorities.',
                                        'Obj1' => 'Strengthen stakeholder coordination for school programs',
                                        'Obj2' => 'Align SIP and AIP monitoring actions',
                                        'Obj3' => 'Document agreements for quarterly follow-through',
                                        'Obj4' => 'Mobilize partners for learning continuity support',
                                        '[Insert description of the activities conducted with photo documentation]' => 'The meeting opened with registration, roll call, and a review of previous agreements.',
                                        '[INSERT PHOTO DOCUMENTATION/S]' => 'Photo documentation of attendance and deliberation is attached.',
                                        '[Insert description of the status quo with or without photo documentation]' => 'Current SIP actions are underway with partner support still being mobilized.',
                                        '[Insert description of accomplishments/Issues/ Concerns with photo documentation]' => 'Agreements on monitoring roles were confirmed; remaining concern is partner scheduling.',
                                    ],
                                ]),
                            ],
                        ]],
                    ]);
                }

                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'replacements' => [
                                    '[Insert brief description of the purpose of the meeting]' => 'Discuss the first regular meeting of the School Governance Council.',
                                    '[INSERT VENUE/MODE]' => 'School conference room',
                                    '[INSERT SUBJECT]' => 'First regular SGC meeting',
                                    '[INSERT DATE]' => 'October 26, 2026, 9:00 AM',
                                    'Agenda #1' => 'Call to order and quorum',
                                    'Agenda #2' => 'Reading of the previous minutes',
                                    'Agenda #3' => 'School improvement matters',
                                    'Agendan #4' => 'Other matters and adjournment',
                                ],
                            ]),
                        ],
                    ]],
                ]);
            }
            if ($request->method() === 'GET') {
                return Http::response(['files' => [['id' => 'folder-found']]]);
            }

            return Http::response(['id' => 'folder-made']);
        });
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
        Cache::forever(DriveMovFolders::tokenCacheKey($head->id), 'refresh-token');
        Cache::forever(DriveMovFolders::scopeCacheKey($head->id), DriveMovFolders::WRITE_SCOPE);

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
                ->where('indicators.0.minimum.0.can_replace', true)
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
        $this->assertStringContainsString('Encode remaining primary FIs, starting with FI1', $response->json('reply'));
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

    public function test_template_download_allows_a_slash_in_the_label_and_school_name(): void
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();
        $encoder->update(['school_name' => 'North/Negros College']);

        $this->actingAs($encoder)
            ->get('/school/templates/concept-note-sgc')
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

    public function test_auto_fill_updates_the_open_notice_preview(): void
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();
        config(['services.openai.key' => 'test-key']);

        $html = $this->actingAs($encoder)
            ->postJson('/school/templates/notice-sgc/autofill')
            ->assertOk()
            ->json('html');

        $this->assertIsString($html);
        $this->assertStringContainsString('Discuss the first regular meeting of the School Governance Council.', $html);
        $this->assertStringNotContainsString('[Insert brief description of the purpose of the meeting]', $html);
        $sent = collect(Http::recorded())->contains(function (array $pair) {
            return str_contains($pair[0]->url(), 'api.openai.com')
                && str_contains($pair[0]->body(), 'San Guillermo Elementary School');
        });
        $this->assertTrue($sent);
        $this->assertStringNotContainsString('<script', strtolower($html));
    }

    public function test_auto_fill_updates_the_open_resolution_preview(): void
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();
        config(['services.openai.key' => 'test-key']);

        $response = $this->actingAs($encoder)
            ->postJson('/school/templates/resolution-sgc/autofill')
            ->assertOk();

        $html = $response->json('html');
        $this->assertIsString($html);
        $this->assertStringContainsString('CONTINUOUS SIP AND AIP IMPLEMENTATION', $html);
        $this->assertStringContainsString('Resolution whereas clause 1', $html);
        $this->assertStringContainsString('Resolution whereas clause 9', $html);
        $this->assertStringContainsString('Adopt continuous SIP monitoring for the school year', $html);
        $this->assertStringNotContainsString('[State the Subject]', $html);
        $this->assertStringNotContainsString('[State details]', $html);
        $this->assertStringContainsString('resolution subject, WHEREAS clauses', (string) $response->json('note'));
        $this->assertStringNotContainsString('<script', strtolower($html));
    }

    public function test_auto_fill_updates_documentation_overview_and_objectives(): void
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();
        config(['services.openai.key' => 'test-key']);

        $response = $this->actingAs($encoder)
            ->postJson('/school/templates/documentation-sgc/autofill')
            ->assertOk();

        $html = $response->json('html');
        $this->assertIsString($html);
        $this->assertStringContainsString('On 6 November 2026 at the school conference room', $html);
        $this->assertStringContainsString('Strengthen stakeholder coordination for school programs', $html);
        $this->assertStringContainsString('Mobilize partners for learning continuity support', $html);
        $this->assertStringNotContainsString('[Insert project overview, including the date, venue, attendees, & leading organization]', $html);
        $this->assertStringNotContainsString('Obj1', $html);
        $this->assertStringNotContainsString('Obj4', $html);
        $this->assertStringContainsString('overview, objectives', (string) $response->json('note'));
        $this->assertStringNotContainsString('<script', strtolower($html));
    }

    public function test_auto_fill_updates_the_open_action_plan_preview(): void
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();
        config(['services.openai.key' => 'test-key']);

        $response = $this->actingAs($encoder)
            ->postJson('/school/templates/action-plan-sgc/autofill')
            ->assertOk();

        $html = $response->json('html');
        $this->assertIsString($html);
        $this->assertStringContainsString('Governance area 1', $html);
        $this->assertStringContainsString('Prioritized need 5', $html);
        $this->assertStringContainsString('Expected output 3', $html);
        $this->assertStringContainsString('Advocacy kit 2', $html);
        $this->assertStringContainsString('action / advocacy plan rows', (string) $response->json('note'));
        $this->assertStringNotContainsString('<script', strtolower($html));
    }

    public function test_auto_fill_updates_the_open_concept_note_preview(): void
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();
        config(['services.openai.key' => 'test-key']);

        $response = $this->actingAs($encoder)
            ->postJson('/school/templates/concept-note-sgc/autofill')
            ->assertOk();

        $html = $response->json('html');
        $this->assertIsString($html);
        $this->assertStringContainsString('Project Learning Continuity Kits', $html);
        $this->assertStringContainsString('August 1, 2026', $html);
        $this->assertStringContainsString('Strengthen stakeholder coordination', $html);
        $this->assertStringContainsString('Conduct planning workshop with SGC officers', $html);
        $this->assertStringContainsString('Printing of modules', $html);
        $this->assertStringContainsString('9,000', $html);
        $this->assertStringNotContainsString('[Insert exhaustive background', $html);
        $this->assertStringNotContainsString('Obj1', $html);
        $this->assertStringContainsString('project details, background, objectives', (string) $response->json('note'));
        $this->assertStringNotContainsString('<script', strtolower($html));
    }

    public function test_auto_fill_updates_the_open_org_chart_preview(): void
    {
        $this->openCycle();
        [$encoder] = $this->schoolPair();
        \App\Models\SchoolFormProfile::query()->create([
            'school_code' => '654321',
            'school_name' => 'Sample ES',
            'school_head_name' => 'Ana Principal',
            'co_chair_designated' => 'Ben Designated',
            'co_chair_elected' => 'Cara Elected',
            'secretary_name' => 'Dina Secretary',
        ]);

        $response = $this->actingAs($encoder)
            ->postJson('/school/templates/org-chart-sgc/autofill')
            ->assertOk();

        $html = $response->json('html');
        $this->assertIsString($html);
        $this->assertStringContainsString('flex-wrap', $html);
        $this->assertMatchesRegularExpression('/Ana Principal<br(?:\s*\/)?>School Principal/', $html);
        $this->assertMatchesRegularExpression('/Ben Designated<br(?:\s*\/)?>Designated Co-Chairperson/', $html);
        $this->assertMatchesRegularExpression('/Cara Elected<br(?:\s*\/)?>Elected Co-Chairperson/', $html);
        $this->assertMatchesRegularExpression('/Dina Secretary<br(?:\s*\/)?>Elected Secretary/', $html);
        $this->assertStringContainsString('Officers from Form data', (string) $response->json('note'));
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

    public function test_encoder_generates_a_completed_mov_form(): void
    {
        Storage::fake('local');
        $this->openCycle();
        [$encoder] = $this->schoolPair();
        config(['services.openai.key' => null]);

        $this->actingAs($encoder)->post('/school/assessment', [
            'code' => 'FI1',
            'answer' => 'yes',
        ])->assertRedirect();

        $this->actingAs($encoder)->post('/school/movs/generate', [
            'code' => 'FI1A',
            'template' => 'membership-cert-sgc',
        ])->assertNotFound();

        $this->actingAs($encoder)->post('/school/movs/generate', [
            'code' => 'FI1A',
            'template' => 'notice-sgc',
        ])->assertRedirect();

        $mov = Assessment::query()->where('school_code', '654321')->firstOrFail()
            ->movs()->where('code', 'FI1A')->firstOrFail();

        $this->assertSame('uploaded', $mov->status);
        $this->assertStringContainsString('SGC Notice of Meeting', (string) $mov->original_name);
        $this->assertTrue(Storage::disk('local')->exists((string) $mov->path));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path((string) $mov->path)) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertIsString($xml);
        $this->assertStringNotContainsString('Recommended contents', $xml);
    }

    public function test_generate_puts_ai_wording_into_the_notice_blanks(): void
    {
        Storage::fake('local');
        $this->openCycle();
        [$encoder] = $this->schoolPair();
        config(['services.openai.key' => 'test-key']);

        $this->actingAs($encoder)->post('/school/assessment', [
            'code' => 'FI1',
            'answer' => 'yes',
        ])->assertRedirect();

        $response = $this->actingAs($encoder)->post('/school/movs/generate', [
            'code' => 'FI1A',
            'template' => 'notice-sgc',
        ]);
        $response->assertRedirect();

        $mov = Assessment::query()->where('school_code', '654321')->firstOrFail()
            ->movs()->where('code', 'FI1A')->firstOrFail();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path((string) $mov->path)) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($xml);
        $this->assertStringContainsString('Discuss the first regular meeting of the School Governance Council.', $xml);
        $this->assertStringContainsString('Call to order and quorum', $xml);
        $this->assertStringNotContainsString('[Insert brief description of the purpose of the meeting]', $xml);
        $this->assertStringNotContainsString('Agenda #1', $xml);
    }
}
