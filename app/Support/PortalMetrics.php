<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\Cycle;
use App\Models\IndicatorAnswer;
use App\Models\Mov;
use App\Models\SchoolFormProfile;
use App\Models\User;

class PortalMetrics
{
    /**
     * @return array<string, mixed>
     */
    public static function divisionOverview(User $user): array
    {
        $cycle = self::openCycle();
        $stats = self::stats();

        return [
            'title' => 'Division dashboard',
            'subtitle' => trim($user->name.' · '.($user->position ?: 'Division Admin').' · '.($user->office ?: 'Division')),
            'chip' => self::deadlineChip($cycle),
            'kpis' => [
                self::kpi('Schools', (string) $stats['schools'], 'Accepted School Heads'),
                self::kpi('Submitted', (string) $stats['submitted'], $stats['schools'] ? $stats['compliance'].'% of schools' : 'No packets yet'),
                self::kpi('Overdue', (string) $stats['overdue'], 'No submission yet', $stats['overdue'] ? 'bad' : null),
                self::kpi('Functional', (string) $stats['functional'], '≥ 10 of 12 FIs'),
                self::kpi('Queue', (string) $stats['queue'], 'Awaiting validation', $stats['queue'] ? 'warn' : null),
                self::kpi('Returned', (string) $stats['returned_movs'], 'Invalid MOVs', $stats['returned_movs'] ? 'bad' : null),
                self::kpi('TA flagged', (string) $stats['ta_flagged'], 'Below 10/12', $stats['ta_flagged'] ? 'warn' : null),
                self::kpi('Avg FIs met', $stats['avg_fis'], 'Division mean'),
            ],
            'charts' => [
                [
                    'title' => 'Weakest indicators',
                    'hint' => $stats['schools'] ? 'Share of accepted schools with Yes' : 'No school packets yet',
                    'bars' => $stats['weakest'],
                ],
                [
                    'title' => 'Submission funnel',
                    'hint' => $stats['schools'].' accepted schools',
                    'bars' => $stats['funnel'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function superOverview(): array
    {
        $cycle = self::openCycle();
        $stats = self::stats();

        return [
            'title' => 'System overview',
            'subtitle' => 'Live counts · SGC Functionality Assessment',
            'chip' => $cycle?->name ?: 'No open cycle',
            'kpis' => [
                self::kpi('Schools in cycle', (string) $stats['schools'], 'Accepted School Heads'),
                self::kpi('Functional SGCs', (string) $stats['functional'], '≥ 10 of 12 FIs'),
                self::kpi('Submission rate', $stats['compliance'].'%', $stats['submitted'].' of '.$stats['schools'].' submitted'),
                self::kpi('Overdue', (string) $stats['overdue'], 'No submission yet', $stats['overdue'] ? 'bad' : null),
                self::kpi('Validated', (string) $stats['validated'], 'Division QA complete'),
                self::kpi('Under review', (string) $stats['queue'], 'In validation queue', $stats['queue'] ? 'warn' : null),
                self::kpi('Returned MOVs', (string) $stats['returned_movs'], 'Awaiting school resubmit', $stats['returned_movs'] ? 'bad' : null),
                self::kpi('Admin accounts', (string) $stats['admins'], $stats['admin_hint']),
            ],
            'charts' => [
                [
                    'title' => 'Indicator performance',
                    'hint' => $stats['schools'] ? 'Share of schools meeting each FI' : 'No school packets yet',
                    'bars' => $stats['weakest'],
                ],
                [
                    'title' => 'Compliance funnel',
                    'hint' => $stats['schools'].' accepted schools in cycle',
                    'bars' => $stats['funnel'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function superDivisions(): array
    {
        $stats = self::stats();
        $division = User::query()->where('role', 'division')->where('status', 'active')->first();
        $divisionName = $division?->office ?: ($division?->name ?: 'No Division Admin yet');

        return [
            'title' => 'Divisions',
            'subtitle' => 'Counts come from registered schools, not sample data.',
            'kpis' => [
                self::kpi('Division Admins', (string) $stats['division_admins'], 'Created by Super Admin'),
                self::kpi('Schools mapped', (string) $stats['schools'], 'Accepted School Heads'),
                self::kpi('Functional', (string) $stats['functional'], '≥ 10 of 12 FIs'),
            ],
            'headers' => ['Division', 'Schools', 'Submission', 'Functional', 'Status'],
            'rows' => $division ? [[
                $divisionName,
                (string) $stats['schools'],
                $stats['compliance'].'%',
                (string) $stats['functional'],
                self::badge($stats['schools'] ? 'Live' : 'Awaiting schools', $stats['schools'] ? 'ok' : 'warn'),
            ]] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function superCycles(): array
    {
        $stats = self::stats();
        $rows = Cycle::query()->orderByDesc('opens_at')->get()->map(function (Cycle $cycle) use ($stats) {
            $tone = $cycle->status === 'open' ? 'ok' : 'warn';

            return [
                $cycle->name,
                $cycle->level,
                $cycle->opens_at->format('M j, Y'),
                $cycle->deadline_at->format('M j, Y'),
                (string) $stats['schools'],
                self::badge(ucfirst($cycle->status), $tone),
            ];
        })->all();

        return [
            'title' => 'Assessment cycles',
            'subtitle' => 'Official SGC FAT cycles stored in this system.',
            'kpis' => [
                self::kpi('Open cycles', (string) Cycle::query()->where('status', 'open')->count()),
                self::kpi('Days remaining', self::daysRemaining(self::openCycle())),
                self::kpi('Schools', (string) $stats['schools']),
            ],
            'headers' => ['Cycle', 'Level', 'Opens', 'Deadline', 'Schools', 'Status'],
            'rows' => $rows,
            'empty_text' => 'No assessment cycle yet.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function divisionSchools(): array
    {
        $stats = self::stats();
        $heads = User::query()
            ->where('role', 'school_head')
            ->orderBy('school_name')
            ->get();

        $packets = Assessment::query()
            ->withCount(['indicators as yes_count' => fn ($query) => $query->where('answer', 'yes')])
            ->withCount(['movs as mov_files' => fn ($query) => $query->whereNotNull('path')])
            ->get()
            ->keyBy('school_code');

        $schools = $heads->map(function (User $head) use ($packets) {
            $code = $head->packetSchoolCode();
            $packet = $packets->get($head->school_code) ?? $packets->get($code);
            $yes = $packet?->yes_count ?? 0;
            $result = match (true) {
                $head->status === 'pending' => self::badge('Pending', 'warn'),
                $packet?->result === 'functional' => self::badge('Functional', 'ok'),
                $packet?->submitted_at => self::badge('Submitted', 'warn'),
                $packet => self::badge('In progress', 'warn'),
                default => self::badge('No data', 'bad'),
            };

            return [
                'code' => $code,
                'name' => $head->school_name ?: 'Unnamed school',
                'school_code' => $head->school_code ?: '—',
                'yes' => $yes.'/12',
                'movs' => (string) ($packet?->mov_files ?? 0),
                'result' => $result,
            ];
        })->values();

        return [
            'title' => 'Schools',
            'subtitle' => 'Open a school to see its team and the current FAT packet.',
            'kpis' => [
                self::kpi('Listed', (string) $heads->count()),
                self::kpi('Functional', (string) $stats['functional']),
                self::kpi('Not yet', (string) max($heads->count() - $stats['functional'], 0), null, $heads->count() ? 'warn' : null),
            ],
            'schools' => $schools,
            'empty_text' => 'No School Heads have registered yet.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function divisionSchool(string $code): array
    {
        $heads = User::query()
            ->where('role', 'school_head')
            ->where(function ($query) use ($code) {
                $query->where('school_code', $code);
                if (preg_match('/^user-(\d+)$/', $code, $match)) {
                    $query->orWhere('id', (int) $match[1]);
                }
            })
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        abort_if($heads->isEmpty(), 404);

        $head = $heads->first();
        $schoolCode = $head->packetSchoolCode();
        $profile = SchoolFormProfile::query()->firstOrNew(['school_code' => $schoolCode]);
        $form = $profile->toForm($head);
        $encoders = User::query()
            ->where('role', 'school')
            ->where('school_code', $head->school_code)
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        $assessment = Assessment::query()
            ->with(['cycle', 'indicators', 'movs'])
            ->where('school_code', $schoolCode)
            ->latest('id')
            ->first();

        $snap = $assessment ? AssessmentEngine::snapshot($assessment) : null;
        $yes = $snap['yes_count'] ?? 0;
        $encoded = $snap['encoded'] ?? 0;
        $result = match (true) {
            $head->status === 'pending' => self::badge('Pending', 'warn'),
            ($assessment?->result) === 'functional' => self::badge('Functional', 'ok'),
            $assessment?->submitted_at => self::badge($assessment->status === 'validated' ? 'Validated' : 'Submitted', 'warn'),
            $assessment => self::badge('In progress', 'warn'),
            default => self::badge('No packet', 'bad'),
        };

        return [
            'title' => $head->school_name ?: 'School',
            'subtitle' => 'School ID '.($head->school_code ?: '—').' · current cycle packet',
            'kpis' => [
                self::kpi('FIs met', $yes.'/12', $encoded.' encoded'),
                self::kpi('Encoders', (string) $encoders->where('status', 'active')->count(), $encoders->where('status', 'pending')->count().' pending'),
                self::kpi('MOVs', (string) ($assessment?->movs->filter->hasFile()->count() ?? 0), $assessment?->status ?: 'No packet'),
            ],
            'school' => [
                'name' => $form['school_name'] ?: ($head->school_name ?: '—'),
                'school_code' => $head->school_code ?: '—',
                'region' => $form['region'] ?: '—',
                'division' => $form['division'] ?: '—',
                'address' => $form['school_address'] ?: '—',
                'school_year' => $form['school_year'] ?: '—',
                'contact' => $form['contact'] ?: '—',
                'email' => $form['email'] ?: '—',
                'co_chair_elected' => $form['co_chair_elected'] ?: '—',
                'co_chair_designated' => $form['co_chair_designated'] ?: '—',
                'secretary' => $form['secretary_name'] ?: '—',
            ],
            'heads' => $heads->map(fn (User $user) => self::person($user))->values(),
            'encoders' => $encoders->map(fn (User $user) => self::person($user))->values(),
            'packet' => $assessment ? [
                'id' => $assessment->id,
                'cycle' => $assessment->cycle?->name ?: '—',
                'status' => $assessment->status,
                'result' => $result,
                'encoded' => $encoded.'/12',
                'yes' => $yes.'/12',
                'qa' => $assessment->qa_certified_at?->format('M j, Y g:i A') ?: 'Not certified',
                'submitted' => $assessment->submitted_at?->format('M j, Y g:i A') ?: 'Not submitted',
                'validated' => $assessment->validated_at?->format('M j, Y g:i A') ?: '—',
                'can_review' => $assessment->canReviewMovs(),
                'indicators' => FatCatalog::sortIndicators($assessment->indicators)->map(fn ($indicator) => [
                    'code' => $indicator->code,
                    'title' => $indicator->title,
                    'answer' => $indicator->answer,
                ])->values(),
                'movs' => $assessment->movs->map(fn (Mov $mov) => [
                    'id' => $mov->id,
                    'code' => $mov->code,
                    'title' => $mov->title,
                    'file' => $mov->original_name,
                    'status' => $mov->status,
                    'has_file' => $mov->hasFile(),
                ])->values(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function divisionAlerts(): array
    {
        $stats = self::stats();
        $rows = [];

        $returned = Mov::query()
            ->with('assessment.user')
            ->where('status', 'returned')
            ->latest()
            ->limit(20)
            ->get();

        foreach ($returned as $mov) {
            $rows[] = [
                $mov->updated_at?->format('M j, H:i') ?: '—',
                $mov->assessment?->user?->school_name ?: 'School',
                $mov->code.' MOV returned',
                'In-app',
                self::badge('Open', 'warn'),
            ];
        }

        $overdueHeads = User::query()
            ->where('role', 'school_head')
            ->where('status', 'active')
            ->orderBy('school_name')
            ->get()
            ->filter(function (User $head) {
                return ! Assessment::query()
                    ->where('school_code', $head->school_code)
                    ->whereNotNull('submitted_at')
                    ->exists();
            });

        foreach ($overdueHeads as $head) {
            $rows[] = [
                now()->format('M j'),
                $head->school_name ?: 'Unnamed school',
                'No submission yet',
                'Dashboard',
                self::badge('Pending', 'bad'),
            ];
        }

        return [
            'title' => 'Alerts',
            'subtitle' => 'Returned MOVs and schools with no submission.',
            'kpis' => [
                self::kpi('Overdue schools', (string) $stats['overdue'], null, $stats['overdue'] ? 'bad' : null),
                self::kpi('Returned MOVs', (string) $stats['returned_movs'], null, $stats['returned_movs'] ? 'bad' : null),
                self::kpi('Open alerts', (string) count($rows)),
            ],
            'headers' => ['When', 'School', 'Trigger', 'Channel', 'Status'],
            'rows' => $rows,
            'empty_text' => 'No alerts yet.',
        ];
    }

    /**
     * Compact live counts for dashboard AI (ideas, graph reading, analytics).
     *
     * @return array<string, mixed>
     */
    public static function analyticsSnapshot(): array
    {
        $stats = self::stats();
        $cycle = self::openCycle();

        return [
            'cycle' => $cycle?->name,
            'deadline' => self::deadlineChip($cycle),
            'schools' => $stats['schools'],
            'submitted' => $stats['submitted'],
            'overdue' => $stats['overdue'],
            'functional' => $stats['functional'],
            'queue' => $stats['queue'],
            'validated' => $stats['validated'],
            'returned_movs' => $stats['returned_movs'],
            'ta_flagged' => $stats['ta_flagged'],
            'avg_fis' => $stats['avg_fis'],
            'compliance' => $stats['compliance'],
            'admins' => $stats['admins'],
            'division_admins' => $stats['division_admins'],
            'weakest' => $stats['weakest'],
            'funnel' => $stats['funnel'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function stats(): array
    {
        $heads = User::query()->where('role', 'school_head')->where('status', 'active')->get();
        $schools = $heads->count();
        $codes = $heads->pluck('school_code')->filter()->values();

        $packets = Assessment::query()
            ->when($codes->isNotEmpty(), fn ($query) => $query->whereIn('school_code', $codes))
            ->when($codes->isEmpty(), fn ($query) => $query->whereRaw('0 = 1'))
            ->withCount(['indicators as yes_count' => fn ($query) => $query->where('answer', 'yes')])
            ->get();

        $submitted = $packets->filter(fn (Assessment $assessment) => $assessment->submitted_at !== null)->count();
        $queue = $packets->whereIn('status', ['submitted', 'under_review'])->count();
        $validated = $packets->where('status', 'validated')->count();
        $functional = $packets->where('result', 'functional')->count();
        $overdue = $schools - $submitted;
        $returnedMovs = Mov::query()->where('status', 'returned')->count();
        $taFlagged = $packets->filter(fn (Assessment $assessment) => $assessment->yes_count < 10)->count();
        $avg = $packets->count() ? round($packets->avg('yes_count'), 1) : 0;
        $compliance = $schools ? (int) round(100 * $submitted / $schools) : 0;

        $superCount = User::query()->where('role', 'super')->count();
        $divisionCount = User::query()->where('role', 'division')->count();

        $funnelCounts = [
            'Not started' => 0,
            'In progress' => 0,
            'Submitted' => 0,
            'In review' => 0,
            'Validated' => 0,
        ];

        $byCode = $packets->keyBy('school_code');
        foreach ($heads as $head) {
            $packet = $byCode->get($head->school_code);
            if (! $packet) {
                $funnelCounts['Not started']++;

                continue;
            }

            match ($packet->status) {
                'validated' => $funnelCounts['Validated']++,
                'under_review' => $funnelCounts['In review']++,
                'submitted' => $funnelCounts['Submitted']++,
                'returned' => $funnelCounts['In progress']++,
                default => $packet->yes_count > 0
                    ? $funnelCounts['In progress']++
                    : $funnelCounts['Not started']++,
            };
        }

        $scale = max($schools, 1);

        return [
            'schools' => $schools,
            'submitted' => $submitted,
            'overdue' => max($overdue, 0),
            'functional' => $functional,
            'queue' => $queue,
            'validated' => $validated,
            'returned_movs' => $returnedMovs,
            'ta_flagged' => $taFlagged,
            'avg_fis' => (string) $avg,
            'compliance' => $compliance,
            'admins' => $superCount + $divisionCount,
            'division_admins' => $divisionCount,
            'admin_hint' => $superCount.' Super · '.$divisionCount.' Division',
            'weakest' => self::weakestBars($schools),
            'funnel' => collect($funnelCounts)->map(fn (int $count, string $label) => self::bar(
                $label,
                (string) $count,
                (int) round(100 * $count / $scale),
                $count === 0 ? null : ($label === 'Validated' ? null : 'warn')
            ))->values()->all(),
        ];
    }

    /**
     * @return list<array{label: string, value: string, height: int, tone: ?string}>
     */
    private static function weakestBars(int $schools): array
    {
        $scale = max($schools, 1);
        $bars = [];

        foreach (FatCatalog::indicators() as $indicator) {
            $yes = IndicatorAnswer::query()
                ->where('code', $indicator['code'])
                ->where('answer', 'yes')
                ->count();
            $pct = (int) round(100 * $yes / $scale);
            $tone = $pct >= 70 ? null : ($pct >= 50 ? 'warn' : 'bad');
            if ($schools === 0) {
                $tone = null;
            }
            $bars[] = self::bar($indicator['code'], $pct.'%', $pct, $tone) + ['pct' => $pct];
        }

        usort($bars, fn ($a, $b) => $a['pct'] <=> $b['pct']);
        $bars = array_slice($bars, 0, 5);

        return array_map(function (array $bar) {
            unset($bar['pct']);

            return $bar;
        }, $bars);
    }

    private static function openCycle(): ?Cycle
    {
        return Cycle::query()->where('status', 'open')->orderByDesc('opens_at')->first();
    }

    private static function deadlineChip(?Cycle $cycle): ?string
    {
        if (! $cycle) {
            return 'No open cycle';
        }

        $days = (int) now()->startOfDay()->diffInDays($cycle->deadline_at->copy()->startOfDay(), false);
        if ($days < 0) {
            return 'Deadline passed';
        }

        return 'Deadline in '.$days.' days';
    }

    private static function daysRemaining(?Cycle $cycle): string
    {
        if (! $cycle) {
            return '—';
        }

        $days = (int) now()->startOfDay()->diffInDays($cycle->deadline_at->copy()->startOfDay(), false);

        return (string) max($days, 0);
    }

    /**
     * @return array{label: string, value: string, hint: ?string, tone: ?string}
     */
    private static function kpi(string $label, string $value, ?string $hint = null, ?string $tone = null): array
    {
        return compact('label', 'value', 'hint', 'tone');
    }

    /**
     * @return array{label: string, value: string, height: int, tone: ?string}
     */
    private static function bar(string $label, string $value, int $height, ?string $tone = null): array
    {
        return compact('label', 'value', 'height', 'tone');
    }

    /**
     * @return array{badge: string, tone: string}
     */
    private static function badge(string $badge, string $tone): array
    {
        return compact('badge', 'tone');
    }

    /**
     * @return array{name: string, email: string, position: string, status: array{badge: string, tone: string}}
     */
    private static function person(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'position' => $user->position ?: '—',
            'status' => $user->status === 'active'
                ? self::badge('Active', 'ok')
                : self::badge(ucfirst((string) $user->status), 'warn'),
        ];
    }
}
