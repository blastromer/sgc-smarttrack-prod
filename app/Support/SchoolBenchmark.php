<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\Cycle;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Division-wide SGC FAT benchmarking for school staff.
 * Exposes named peer status/progress (FIs, maturity, packet status) — never MOV files or contacts.
 */
class SchoolBenchmark
{
    public const METRICS = [
        'fis' => 'FIs met',
        'progress' => 'Assessment progress',
    ];

    /**
     * Compact dashboard teaser for the signed-in school.
     *
     * @return array<string, mixed>|null
     */
    public static function summaryFor(User $user, ?int $cycleId = null): ?array
    {
        $page = self::page($user, $cycleId, 'fis');
        if (($page['total'] ?? 0) < 1 || ! ($page['me'] ?? null)) {
            return null;
        }

        return [
            'value' => $page['me']['value_label'],
            'metric' => $page['metric_label'],
            'average' => $page['benchmark']['average_label'],
            'difference_points' => $page['me']['difference_points'],
            'rank' => $page['me']['rank'],
            'total' => $page['total'],
            'percentile' => $page['me']['percentile'],
            'standing' => $page['me']['standing'],
            'href' => '/school/benchmarking',
        ];
    }

    /**
     * Full benchmarking page payload.
     *
     * @return array<string, mixed>
     */
    public static function page(User $user, ?int $cycleId = null, string $metric = 'fis', ?string $compareCode = null): array
    {
        $metric = array_key_exists($metric, self::METRICS) ? $metric : 'fis';
        $cycles = Cycle::query()->orderByDesc('opens_at')->get(['id', 'name', 'status']);
        $cycle = $cycles->firstWhere('id', $cycleId)
            ?? $cycles->firstWhere('status', 'open')
            ?? $cycles->first();

        $schools = self::schoolRows($cycle?->id, $metric);
        $total = $schools->count();
        $selfCode = $user->packetSchoolCode();
        $me = $schools->firstWhere('code', $selfCode);

        if ($total === 0) {
            return self::emptyPage($cycles, $cycle, $metric);
        }

        $values = $schools->pluck('value')->sort()->values();
        $average = round((float) $values->avg(), 1);
        $median = self::median($values);
        $highest = (float) $values->max();
        $lowest = (float) $values->min();
        $top = $schools->first();

        $ranked = self::assignRanks($schools)->map(function (array $row) use ($selfCode, $average, $total) {
            $row['is_self'] = $row['code'] === $selfCode;
            if ($row['is_self']) {
                $row = self::enrichSelf($row, $average, $total);
            }

            return $row;
        });
        $me = $ranked->firstWhere('is_self', true);

        $compareCode = $compareCode ?: self::defaultCompareCode($ranked, $selfCode);
        $compare = $ranked->firstWhere('code', $compareCode);
        if ($compare && $compare['code'] === $selfCode) {
            $compare = $ranked->first(fn (array $row) => $row['code'] !== $selfCode);
        }

        return [
            'title' => 'Benchmarking',
            'subtitle' => 'Compare your school’s SGC FAT progress against the division and other schools.',
            'chip' => $cycle?->name,
            'cycles' => $cycles->map(fn (Cycle $item) => ['id' => $item->id, 'name' => $item->name])->values(),
            'cycle_id' => $cycle?->id,
            'metrics' => collect(self::METRICS)->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
            ])->values(),
            'metric' => $metric,
            'metric_label' => self::METRICS[$metric],
            'total' => $total,
            'me' => $me,
            'benchmark' => [
                'average' => $average,
                'average_label' => self::formatValue($average, $metric),
                'median' => $median,
                'median_label' => self::formatValue($median, $metric),
                'highest' => $highest,
                'highest_label' => self::formatValue($highest, $metric),
                'lowest' => $lowest,
                'lowest_label' => self::formatValue($lowest, $metric),
            ],
            'comparison_bars' => [
                [
                    'label' => 'Top school',
                    'value' => self::formatValue((float) ($top['value'] ?? 0), $metric),
                    'height' => (int) round((float) ($top['value'] ?? 0)),
                    'tone' => null,
                ],
                [
                    'label' => 'Our school',
                    'value' => self::formatValue((float) ($me['value'] ?? 0), $metric),
                    'height' => (int) round((float) ($me['value'] ?? 0)),
                    'tone' => 'ok',
                ],
                [
                    'label' => 'Division average',
                    'value' => self::formatValue($average, $metric),
                    'height' => (int) round($average),
                    'tone' => 'warn',
                ],
            ],
            'distribution' => self::distribution($ranked, $metric, $me['value'] ?? null),
            'takeaways' => self::takeaways($me, $total, $average),
            'schools' => $ranked->values(),
            'compare' => [
                'options' => $ranked->map(fn (array $row) => [
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'is_self' => $row['is_self'],
                ])->values(),
                'left' => $me ? self::compareCard($me, $metric) : null,
                'right' => $compare ? self::compareCard($compare, $metric) : null,
                'right_code' => $compare['code'] ?? null,
                'rows' => $me && $compare ? self::compareRows($me, $compare, $metric) : [],
            ],
            'empty' => false,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function schoolRows(?int $cycleId, string $metric): Collection
    {
        $heads = User::query()
            ->where('role', 'school_head')
            ->where('status', 'active')
            ->orderBy('school_name')
            ->get(['id', 'name', 'school_name', 'school_code']);

        $codes = $heads->map(fn (User $head) => $head->packetSchoolCode())->filter()->values();
        $packets = Assessment::query()
            ->whereIn('school_code', $codes->isEmpty() ? ['__none__'] : $codes)
            ->when($cycleId, fn ($query) => $query->where('cycle_id', $cycleId))
            ->with(['indicators', 'movs'])
            ->withCount([
                'indicators as yes_count' => fn ($query) => $query->where('answer', 'yes'),
                'indicators as encoded_count' => fn ($query) => $query->whereNotNull('answer'),
            ])
            ->withCount(['movs as mov_files' => fn ($query) => $query->where(function ($query) {
                $query->whereNotNull('path')->orWhereNotNull('drive_file_id');
            })])
            ->get()
            ->keyBy('school_code');

        return $heads->map(function (User $head) use ($packets, $metric) {
            $code = $head->packetSchoolCode();
            $packet = $packets->get($code);
            $yes = (int) ($packet?->yes_count ?? 0);
            $encoded = (int) ($packet?->encoded_count ?? 0);
            $movs = (int) ($packet?->mov_files ?? 0);
            $fisPct = round(100 * $yes / 12, 1);
            $progressPct = round(100 * $encoded / 12, 1);
            $value = $metric === 'progress' ? $progressPct : $fisPct;
            $status = self::bucket($packet, $yes);

            return [
                'code' => $code,
                'name' => $head->school_name ?: 'Unnamed school',
                'school_code' => $head->school_code ?: '—',
                'yes_count' => $yes,
                'encoded_count' => $encoded,
                'mov_files' => $movs,
                'fis_pct' => $fisPct,
                'progress' => (int) round($progressPct),
                'value' => $value,
                'value_label' => self::formatValue($value, $metric),
                'status' => $status,
                'status_label' => self::statusLabel($status),
                'maturity_level' => SgcMaturity::levelFor($packet),
                'result' => $packet?->result,
                'functional' => ($packet?->result === 'functional') || $yes >= 10,
                'is_self' => false,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $schools
     * @return Collection<int, array<string, mixed>>
     */
    private static function assignRanks(Collection $schools): Collection
    {
        $sorted = $schools->sort(function (array $a, array $b) {
            $cmp = $b['value'] <=> $a['value'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp($a['name'], $b['name']);
        })->values();

        $total = $sorted->count();
        $rank = 0;
        $seen = 0;
        $previous = null;
        $out = [];

        foreach ($sorted as $row) {
            $seen++;
            if ($previous === null || $row['value'] !== $previous) {
                $rank = $seen;
                $previous = $row['value'];
            }
            $row['rank'] = $rank;
            $row['percentile'] = $total <= 1 ? 100 : (int) round(100 * ($total - $rank) / max($total - 1, 1));
            $row['is_self'] = false;
            $out[] = $row;
        }

        return collect($out);
    }

    /**
     * @param  array<string, mixed>  $me
     * @return array<string, mixed>
     */
    private static function enrichSelf(array $me, float $average, int $total): array
    {
        $diff = round((float) $me['value'] - $average, 1);
        $me['is_self'] = true;
        $me['difference_points'] = $diff;
        $me['standing'] = $diff > 0 ? 'Above average' : ($diff < 0 ? 'Below average' : 'At average');
        $me['total'] = $total;

        return $me;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $schools
     */
    private static function defaultCompareCode(Collection $schools, string $selfCode): ?string
    {
        $self = $schools->firstWhere('code', $selfCode);
        if (! $self) {
            return $schools->first()['code'] ?? null;
        }

        $peer = $schools
            ->filter(fn (array $row) => $row['code'] !== $selfCode)
            ->sortBy(fn (array $row) => abs($row['value'] - $self['value']))
            ->first();

        return $peer['code'] ?? null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $schools
     * @return list<array{label: string, value: string, height: number, tone: ?string, highlight?: bool}>
     */
    private static function distribution(Collection $schools, string $metric, ?float $selfValue): array
    {
        $bands = [
            ['label' => '0–24%', 'min' => 0, 'max' => 24.999],
            ['label' => '25–49%', 'min' => 25, 'max' => 49.999],
            ['label' => '50–74%', 'min' => 50, 'max' => 74.999],
            ['label' => '75–91%', 'min' => 75, 'max' => 91.999],
            ['label' => '92–100%', 'min' => 92, 'max' => 100],
        ];
        $maxCount = 1;
        $counts = [];
        foreach ($bands as $band) {
            $count = $schools->filter(fn (array $row) => $row['value'] >= $band['min'] && $row['value'] <= $band['max'])->count();
            $counts[] = $count;
            $maxCount = max($maxCount, $count);
        }

        return collect($bands)->map(function (array $band, int $index) use ($counts, $maxCount, $selfValue) {
            $count = $counts[$index];
            $highlight = $selfValue !== null && $selfValue >= $band['min'] && $selfValue <= $band['max'];

            return [
                'label' => $band['label'],
                'value' => (string) $count,
                'height' => (int) round(100 * $count / $maxCount),
                'tone' => $highlight ? 'ok' : null,
                'highlight' => $highlight,
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>|null  $me
     * @return list<string>
     */
    private static function takeaways(?array $me, int $total, float $average): array
    {
        if (! $me) {
            return ['Your school is not yet listed among active School Heads for this cycle.'];
        }

        $diff = (float) $me['difference_points'];
        $lines = [];
        if ($diff > 0) {
            $lines[] = abs($diff).' percentage points above the division average.';
        } elseif ($diff < 0) {
            $lines[] = abs($diff).' percentage points below the division average.';
        } else {
            $lines[] = 'Exactly at the division average ('.$average.'%).';
        }

        $lines[] = 'Ranked #'.$me['rank'].' out of '.$total.' schools ('.$me['percentile'].'th percentile).';

        if ($me['percentile'] >= 75) {
            $lines[] = 'Within the top 25% of schools in the division.';
        } elseif ($me['percentile'] >= 50) {
            $lines[] = 'Performing above at least half of schools.';
        } else {
            $lines[] = 'Below the division median band — prioritize remaining FIs and MOVs.';
        }

        if ($me['functional']) {
            $lines[] = 'At or above the Functional bar (10 of 12 Yes), subject to Division validation.';
        } else {
            $need = max(0, 10 - (int) $me['yes_count']);
            $lines[] = 'Need '.$need.' more Yes FI'.($need === 1 ? '' : 's').' to reach the Functional bar (10/12).';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function compareCard(array $row, string $metric): array
    {
        return [
            'code' => $row['code'],
            'name' => $row['name'],
            'is_self' => (bool) $row['is_self'],
            'value_label' => self::formatValue((float) $row['value'], $metric),
            'rank' => $row['rank'],
            'percentile' => $row['percentile'],
            'status_label' => $row['status_label'],
            'maturity_level' => $row['maturity_level'],
            'yes_count' => $row['yes_count'],
            'progress' => $row['progress'],
            'mov_files' => $row['mov_files'],
            'functional' => $row['functional'],
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return list<array{label: string, left: string, right: string, difference: string, tone: ?string}>
     */
    private static function compareRows(array $left, array $right, string $metric): array
    {
        $diffPts = round((float) $left['value'] - (float) $right['value'], 1);
        $rankDiff = (int) $right['rank'] - (int) $left['rank'];
        $pctDiff = (int) $left['percentile'] - (int) $right['percentile'];

        return [
            [
                'label' => self::METRICS[$metric],
                'left' => self::formatValue((float) $left['value'], $metric),
                'right' => self::formatValue((float) $right['value'], $metric),
                'difference' => ($diffPts > 0 ? '+' : '').$diffPts.' pp',
                'tone' => $diffPts > 0 ? 'ok' : ($diffPts < 0 ? 'bad' : null),
            ],
            [
                'label' => 'FIs met',
                'left' => $left['yes_count'].' / 12',
                'right' => $right['yes_count'].' / 12',
                'difference' => (($left['yes_count'] - $right['yes_count']) > 0 ? '+' : '').($left['yes_count'] - $right['yes_count']),
                'tone' => $left['yes_count'] > $right['yes_count'] ? 'ok' : ($left['yes_count'] < $right['yes_count'] ? 'bad' : null),
            ],
            [
                'label' => 'Assessment progress',
                'left' => $left['progress'].'%',
                'right' => $right['progress'].'%',
                'difference' => (($left['progress'] - $right['progress']) > 0 ? '+' : '').($left['progress'] - $right['progress']).' pp',
                'tone' => $left['progress'] > $right['progress'] ? 'ok' : ($left['progress'] < $right['progress'] ? 'bad' : null),
            ],
            [
                'label' => 'MOV files uploaded',
                'left' => (string) $left['mov_files'],
                'right' => (string) $right['mov_files'],
                'difference' => (($left['mov_files'] - $right['mov_files']) > 0 ? '+' : '').($left['mov_files'] - $right['mov_files']),
                'tone' => null,
            ],
            [
                'label' => 'Maturity level',
                'left' => 'Level '.$left['maturity_level'],
                'right' => 'Level '.$right['maturity_level'],
                'difference' => (($left['maturity_level'] - $right['maturity_level']) > 0 ? '+' : '').($left['maturity_level'] - $right['maturity_level']),
                'tone' => null,
            ],
            [
                'label' => 'Division rank',
                'left' => '#'.$left['rank'],
                'right' => '#'.$right['rank'],
                'difference' => ($rankDiff > 0 ? '+' : '').$rankDiff.' places',
                'tone' => $rankDiff > 0 ? 'ok' : ($rankDiff < 0 ? 'bad' : null),
            ],
            [
                'label' => 'Percentile',
                'left' => $left['percentile'].'th',
                'right' => $right['percentile'].'th',
                'difference' => ($pctDiff > 0 ? '+' : '').$pctDiff,
                'tone' => $pctDiff > 0 ? 'ok' : ($pctDiff < 0 ? 'bad' : null),
            ],
        ];
    }

    /**
     * @param  Collection<int, float|int>  $values
     */
    private static function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();
        if ($count === 0) {
            return 0.0;
        }
        $mid = intdiv($count, 2);
        if ($count % 2 === 1) {
            return round((float) $sorted[$mid], 1);
        }

        return round(((float) $sorted[$mid - 1] + (float) $sorted[$mid]) / 2, 1);
    }

    private static function formatValue(float $value, string $metric): string
    {
        $rounded = round($value, 1);
        if (abs($rounded - round($rounded)) < 0.05) {
            return ((int) round($rounded)).'%';
        }

        return rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.').'%';
    }

    private static function bucket(?Assessment $packet, int $yes): string
    {
        if (! $packet) {
            return 'not_started';
        }

        return match ($packet->status) {
            'validated' => 'validated',
            'submitted', 'under_review' => 'for_validation',
            'returned' => 'returned',
            default => $yes > 0 || (int) ($packet->encoded_count ?? 0) > 0 ? 'in_progress' : 'not_started',
        };
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'validated' => 'Validated',
            'for_validation' => 'For validation',
            'returned' => 'Returned',
            'in_progress' => 'In progress',
            default => 'Not started',
        };
    }

    /**
     * @param  Collection<int, Cycle>  $cycles
     * @return array<string, mixed>
     */
    private static function emptyPage(Collection $cycles, ?Cycle $cycle, string $metric): array
    {
        return [
            'title' => 'Benchmarking',
            'subtitle' => 'Compare your school’s SGC FAT progress against the division and other schools.',
            'chip' => $cycle?->name,
            'cycles' => $cycles->map(fn (Cycle $item) => ['id' => $item->id, 'name' => $item->name])->values(),
            'cycle_id' => $cycle?->id,
            'metrics' => collect(self::METRICS)->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
            ])->values(),
            'metric' => $metric,
            'metric_label' => self::METRICS[$metric],
            'total' => 0,
            'me' => null,
            'benchmark' => null,
            'comparison_bars' => [],
            'distribution' => [],
            'takeaways' => [],
            'schools' => [],
            'compare' => [
                'options' => [],
                'left' => null,
                'right' => null,
                'right_code' => null,
                'rows' => [],
            ],
            'empty' => true,
        ];
    }
}
