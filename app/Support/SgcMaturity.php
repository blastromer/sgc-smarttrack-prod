<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\Mov;
use App\Models\SchoolFormProfile;

class SgcMaturity
{
    /**
     * Shared maturity level for one school packet.
     * A level is reached when every process step for that level is done.
     * Level I is establishment only: structure, members, and initial operations.
     * Encoding FI1–FI12 and Division validation stay on Level II and Level III.
     * This is not the 10-of-12 functional count, and it does not assign indicators to a level.
     */
    public static function levelFor(?Assessment $assessment = null): int
    {
        if (! $assessment) {
            return 0;
        }

        $assessment->loadMissing(['indicators', 'movs']);
        $profile = SchoolFormProfile::query()->firstOrNew([
            'school_code' => $assessment->school_code,
        ]);
        $checks = self::checks($profile, $assessment, [
            'encoded' => $assessment->indicators->whereNotNull('answer')->count(),
            'missing_count' => AssessmentEngine::missingRequiredMovs($assessment)->count(),
            'returned_count' => $assessment->movs->where('status', 'returned')->count(),
            'qa_done' => $assessment->qa_certified_at !== null,
        ]);

        $level = 0;
        foreach ([
            1 => ['structure', 'members', 'operations'],
            2 => ['structure', 'baseline', 'stakeholders', 'encode', 'submit'],
            3 => ['improvement', 'partnerships', 'encode', 'submit'],
        ] as $reached => $keys) {
            foreach ($keys as $key) {
                if (($checks[$key]['state'] ?? '') !== 'done') {
                    return $level;
                }
            }
            $level = $reached;
        }

        return $level;
    }

    /**
     * @return list<array{level: int, name: string, title: string, meaning: string}>
     */
    public static function levels(): array
    {
        return [
            [
                'level' => 0,
                'name' => 'Level 0',
                'title' => 'Not yet organized',
                'meaning' => 'The council has not yet established its structure and basic operations.',
            ],
            [
                'level' => 1,
                'name' => 'Level I',
                'title' => 'Being Organized',
                'meaning' => 'The council has a structure, members, and basic operations.',
            ],
            [
                'level' => 2,
                'name' => 'Level II',
                'title' => 'Fully Functional',
                'meaning' => 'Meets baseline indicators and connects the school with its stakeholders.',
            ],
            [
                'level' => 3,
                'name' => 'Level III',
                'title' => 'Championing and Advocating',
                'meaning' => 'Leads school improvement and community partnerships.',
            ],
        ];
    }

    /**
     * Schools grouped into the four maturity levels. Empty levels stay visible.
     *
     * @param  list<array{name: string, school_code: string, view_href: string|null, maturity_level: int}>  $schools
     * @return list<array{level: int, name: string, title: string, meaning: string, count: int, schools: list<array{name: string, school_code: string, view_href: string|null}>}>
     */
    public static function matrix(array $schools): array
    {
        $buckets = [0 => [], 1 => [], 2 => [], 3 => []];

        foreach ($schools as $school) {
            $level = in_array($school['maturity_level'], [0, 1, 2, 3], true) ? $school['maturity_level'] : 0;
            $buckets[$level][] = [
                'name' => $school['name'],
                'school_code' => $school['school_code'],
                'view_href' => $school['view_href'],
            ];
        }

        return array_map(function (array $definition) use ($buckets) {
            $rows = $buckets[$definition['level']];

            return $definition + [
                'count' => count($rows),
                'schools' => $rows,
            ];
        }, self::levels());
    }

    /**
     * Whether each process step has its own records.
     * This does not assign indicators to a maturity level.
     *
     * @param  array<string, mixed>|null  $snap
     * @return array<string, array{state: string, detail: string}>
     */
    public static function checks(SchoolFormProfile $profile, ?Assessment $assessment, ?array $snap): array
    {
        $officers = $profile->exists && self::profileFilled($profile, ['co_chair_elected', 'co_chair_designated', 'secretary_name', 'school_head_name']);
        $meeting = $profile->exists && self::profileFilled($profile, ['meeting_subject', 'meeting_datetime', 'venue', 'meeting_purpose']);

        $encoded = 0;
        $missing = 0;
        $returned = 0;
        $qa = false;
        $status = '';
        $has = fn (string $kind): bool => false;
        if ($assessment && $snap) {
            $encoded = (int) $snap['encoded'];
            $missing = (int) $snap['missing_count'];
            $returned = (int) $snap['returned_count'];
            $qa = (bool) $snap['qa_done'];
            $status = (string) $assessment->status;
            $files = $assessment->movs->filter(fn (Mov $mov) => $mov->hasFile() && $mov->status !== 'returned');
            $has = fn (string $kind): bool => $files->contains(
                fn (Mov $mov) => in_array($kind, MovTemplates::kindsForSlot($mov->code), true)
            );
        }

        $org = $has('org_chart');
        $members = $has('members');
        $notice = $has('notice');
        $minutes = $has('minutes');
        $records = $notice || $minutes;
        $encodeDone = $encoded === 12 && $missing === 0 && $returned === 0;
        $validated = $status === 'validated';
        $withDivision = in_array($status, ['submitted', 'under_review'], true);
        $hasSlot = function (string $code) use ($assessment): bool {
            if (! $assessment) {
                return false;
            }
            $mov = $assessment->movs->firstWhere('code', $code);

            return $mov && $mov->hasFile() && $mov->status !== 'returned';
        };
        $stakeholderMinutes = $hasSlot('FI4C');
        $stakeholderReport = $hasSlot('FI6B');

        return [
            'structure' => self::stepState(
                $officers && $org,
                $officers || $org,
                $officers && $org
                    ? 'Officers are saved and the organizational chart is uploaded.'
                    : ($officers ? 'Officers are saved. The organizational chart is not uploaded yet.' : 'Officers are not saved yet.')
            ),
            'members' => self::stepState(
                $members,
                false,
                $members ? 'A member list or membership certificate is uploaded.' : 'No member list or membership certificate is uploaded yet.'
            ),
            'operations' => self::stepState(
                $meeting && $records,
                $meeting || $records,
                $meeting && $records
                    ? 'Meeting details are saved and a notice or minutes file is uploaded.'
                    : ($meeting ? 'Meeting details are saved. The notice or minutes are not uploaded yet.' : ($records ? 'A notice or minutes file is uploaded. The meeting details are not saved yet.' : 'Meeting details are not saved yet.'))
            ),
            'encode' => self::stepState($encodeDone, $encoded > 0 || $missing > 0, self::encodeDetail($encoded, $missing, $returned)),
            'baseline' => self::stepState(
                $stakeholderMinutes && $stakeholderReport,
                $stakeholderMinutes || $stakeholderReport,
                self::baselineDetail($stakeholderMinutes, $stakeholderReport)
            ),
            'stakeholders' => self::stepState(
                $minutes,
                $notice,
                $minutes ? 'Meeting minutes are uploaded.' : ($notice ? 'A notice is uploaded. Minutes are not uploaded yet.' : 'No stakeholder meeting record is uploaded yet.')
            ),
            'improvement' => self::stepState($has('action_plan'), false, $has('action_plan') ? 'An action plan is uploaded.' : 'No action plan is uploaded yet.'),
            'partnerships' => self::stepState($has('concept'), false, $has('concept') ? 'A concept note is uploaded.' : 'No concept note is uploaded yet.'),
            'submit' => self::submitState($validated, $withDivision, $qa, $status === 'returned'),
        ];
    }

    /**
     * @param  list<string>  $fields
     */
    private static function profileFilled(SchoolFormProfile $profile, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! filled($profile->{$field})) {
                return false;
            }
        }

        return true;
    }

    private static function baselineDetail(bool $minutes, bool $report): string
    {
        $minutesLabel = 'Minutes of Meetings with different school stakeholders';
        $reportLabel = 'stakeholder monitoring report';
        if ($minutes && $report) {
            return $minutesLabel.' and the '.$reportLabel.' are uploaded.';
        }
        if (! $minutes && ! $report) {
            return 'Upload '.$minutesLabel.' and the '.$reportLabel.'.';
        }

        return 'Upload '.($minutes ? 'the '.$reportLabel : $minutesLabel).'.';
    }

    private static function encodeDetail(int $encoded, int $missing, int $returned): string
    {
        if ($encoded === 12 && $missing === 0 && $returned === 0) {
            return 'All 12 indicators are encoded and the minimum MOVs are uploaded.';
        }
        if ($returned > 0) {
            return $returned.' MOV'.($returned === 1 ? ' is' : 's are').' still returned.';
        }
        $text = $encoded.' of 12 indicators encoded';
        if ($missing > 0) {
            $text .= ', '.$missing.' minimum MOV'.($missing === 1 ? '' : 's').' still missing';
        }

        return $text.'.';
    }

    /**
     * @return array{state: string, detail: string}
     */
    private static function submitState(bool $validated, bool $withDivision, bool $qa, bool $returned): array
    {
        if ($validated) {
            return self::stepState(true, false, 'Division validated the packet.');
        }
        if ($withDivision) {
            return self::stepState(false, true, 'The packet is with Division. Validation is not finished.');
        }
        if ($returned) {
            return self::stepState(false, true, 'Division returned the packet. Replace the files, then certify QA and resubmit.');
        }
        if ($qa) {
            return self::stepState(false, true, 'School Head QA is certified. The packet is not submitted yet.');
        }

        return self::stepState(false, false, 'School Head QA is not certified yet.');
    }

    /**
     * @return array{state: string, detail: string}
     */
    private static function stepState(bool $done, bool $progress, string $detail): array
    {
        return [
            'state' => $done ? 'done' : ($progress ? 'progress' : 'open'),
            'detail' => $detail,
        ];
    }
}
