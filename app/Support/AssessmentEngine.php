<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\Cycle;
use App\Models\IndicatorAnswer;
use App\Models\Mov;
use App\Models\User;
use Illuminate\Support\Collection;

class AssessmentEngine
{
    public static function currentCycle(): ?Cycle
    {
        return Cycle::query()->where('status', 'open')->latest('id')->first();
    }

    public static function forSchool(User $user): ?Assessment
    {
        $cycle = self::currentCycle();
        if (! $cycle) {
            return null;
        }

        return Assessment::query()->firstOrCreate(
            [
                'cycle_id' => $cycle->id,
                'school_code' => $user->packetSchoolCode(),
            ],
            [
                'user_id' => $user->id,
                'status' => 'in_progress',
            ]
        );
    }

    public static function flowFor(User $user): ?array
    {
        $assessment = self::forSchool($user);
        if (! $assessment) {
            return [
                'banner' => 'No open SGC FAT cycle',
                'path' => 'Path: Encode → MOVs → School Head QA → Submit to Division → Validation → Functional (10/12).',
                'steps' => [
                    ['n' => '1', 'label' => 'Encode', 'hint' => 'Locked', 'href' => '/school/assessment', 'state' => 'lock'],
                    ['n' => '2', 'label' => 'MOVs', 'hint' => 'Locked', 'href' => '/school/movs', 'state' => 'lock'],
                    ['n' => '3', 'label' => 'School Head QA', 'hint' => 'Locked', 'href' => '/school/submit', 'state' => 'lock'],
                    ['n' => '4', 'label' => 'Submit', 'hint' => 'Locked', 'href' => '/school/submit', 'state' => 'lock'],
                    ['n' => '5', 'label' => 'Division', 'hint' => 'Locked', 'href' => '/school/submit', 'state' => 'lock'],
                    ['n' => '6', 'label' => 'Result', 'hint' => 'Not yet', 'href' => '/school/submit', 'state' => 'lock'],
                ],
            ];
        }

        return self::snapshot($assessment)['flow'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(Assessment $assessment): array
    {
        $assessment->load(['indicators', 'movs', 'cycle', 'user']);
        $indicators = $assessment->indicators->sortBy('code')->values();
        $encoded = $indicators->whereNotNull('answer')->count();
        $yesCount = $indicators->where('answer', 'yes')->count();
        $returnedMovs = $assessment->movs->where('status', 'returned');
        $missingMovs = self::missingRequiredMovs($assessment);
        $validity = $assessment->movs->firstWhere('kind', 'validity');
        $cycleOpen = $assessment->cycle?->isOpen() ?? false;
        $qaDone = $assessment->qa_certified_at !== null;
        $inDivision = in_array($assessment->status, ['submitted', 'under_review'], true);
        $validated = $assessment->status === 'validated';
        $returned = $assessment->status === 'returned' || $returnedMovs->isNotEmpty();

        $encodeDone = $encoded === 12;
        $movsClear = $returnedMovs->isEmpty() && $missingMovs->isEmpty();
        $validityReady = $validity && $validity->status !== 'returned' && $validity->hasFile();
        $canQa = $encodeDone && $movsClear && $validityReady && ! $inDivision && ! $validated;
        $canSubmit = $canQa && $qaDone;

        $encodeState = $encodeDone ? 'done' : 'now';
        $movState = $encodeDone && ! $movsClear ? 'now' : ($movsClear && $encoded > 0 ? 'done' : ($encodeDone ? 'now' : 'lock'));
        if ($returnedMovs->isNotEmpty()) {
            $movState = 'now';
        }
        $qaState = $qaDone ? 'done' : ($canQa ? 'now' : 'lock');
        $submitState = $inDivision || $validated ? 'done' : ($canSubmit ? 'now' : 'lock');
        $divisionState = $validated ? 'done' : ($inDivision ? 'now' : ($returned ? 'warn' : 'lock'));
        $resultState = $validated ? 'done' : 'lock';

        $banner = match (true) {
            $validated => $assessment->result === 'functional'
                ? 'Result: Functional SGC (10 of 12 FIs validated).'
                : 'Result: Not yet functional.',
            $inDivision => 'Packet is with Division for MOV validation.',
            $returnedMovs->isNotEmpty() => 'Replace returned or invalid MOVs, then School Head QA, then resubmit.',
            $canSubmit => 'School Head QA is done. Submit the packet to SDO Cadiz City.',
            $canQa => 'Encode and MOVs are clear. School Head can certify QA.',
            ! $encodeDone => 'Encode all 12 functionality indicators first.',
            default => 'Upload missing MOVs and replace any returned files.',
        };

        return [
            'assessment' => $assessment,
            'encoded' => $encoded,
            'yes_count' => $yesCount,
            'returned_count' => $returnedMovs->count(),
            'missing_count' => $missingMovs->count(),
            'validity' => $validity,
            'cycle_open' => $cycleOpen,
            'qa_done' => $qaDone,
            'can_qa' => $canQa && ! $qaDone,
            'can_submit' => $canSubmit,
            'movs_clear' => $movsClear,
            'validity_ready' => $validityReady,
            'returned' => $returned,
            'flow' => [
                'banner' => $banner,
                'path' => 'Path: Encode → MOVs → School Head QA → Submit to Division → Validation → Functional (10/12).',
                'steps' => [
                    ['n' => '1', 'label' => 'Encode', 'hint' => $encoded.' / 12 FIs', 'href' => '/school/assessment', 'state' => $encodeState],
                    ['n' => '2', 'label' => 'MOVs', 'hint' => $returnedMovs->isNotEmpty() ? $returnedMovs->count().' returned' : ($missingMovs->isNotEmpty() ? $missingMovs->count().' missing' : 'Ready'), 'href' => '/school/movs', 'state' => $movState],
                    ['n' => '3', 'label' => 'School Head QA', 'hint' => $qaDone ? 'Certified' : ($canQa ? 'Ready' : 'Locked'), 'href' => '/school/submit', 'state' => $qaState],
                    ['n' => '4', 'label' => 'Submit', 'hint' => $inDivision || $validated ? 'Sent' : ($canSubmit ? 'Ready' : 'Blocked'), 'href' => '/school/submit', 'state' => $submitState],
                    ['n' => '5', 'label' => 'Division', 'hint' => $validated ? 'Done' : ($inDivision ? 'In review' : ($returned ? 'Returned' : 'Waiting')), 'href' => '/school/submit', 'state' => $divisionState],
                    ['n' => '6', 'label' => 'Result', 'hint' => $validated ? ($assessment->result === 'functional' ? 'Functional' : 'Not yet') : 'Not yet', 'href' => '/school/submit', 'state' => $resultState],
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, array{code: string, title: string, indicator_code: ?string, kind: string}>
     */
    public static function requiredSlots(Assessment $assessment): Collection
    {
        $slots = collect();

        foreach ($assessment->indicators as $indicator) {
            if ($indicator->answer !== 'yes') {
                continue;
            }
            $meta = FatCatalog::indicator($indicator->code);
            if (! $meta) {
                continue;
            }
            $slots->push([
                'code' => $meta['mov_code'],
                'title' => $meta['mov_title'],
                'indicator_code' => $indicator->code,
                'kind' => 'minimum',
            ]);
        }

        $slots->push([
            'code' => 'Validity',
            'title' => 'Validity Form',
            'indicator_code' => null,
            'kind' => 'validity',
        ]);

        return $slots;
    }

    public static function missingRequiredMovs(Assessment $assessment): Collection
    {
        return self::requiredSlots($assessment)->filter(function (array $slot) use ($assessment) {
            $mov = $assessment->movs->firstWhere('code', $slot['code']);

            return ! $mov
                || $mov->status === 'draft'
                || $mov->status === 'returned'
                || (! $mov->hasFile() && $mov->status !== 'valid');
        })->values();
    }

    public static function ensureRequiredMovs(Assessment $assessment): void
    {
        foreach (self::requiredSlots($assessment) as $slot) {
            Mov::query()->firstOrCreate(
                [
                    'assessment_id' => $assessment->id,
                    'code' => $slot['code'],
                ],
                [
                    'indicator_code' => $slot['indicator_code'],
                    'title' => $slot['title'],
                    'kind' => $slot['kind'],
                    'status' => 'draft',
                ]
            );
        }
    }
}
