<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\SchoolFormProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Division TA recommendations grounded in each school's FAT gaps.
 * Deterministic catalog first; optional OpenAI polish. Never invents Yes/No or Functional.
 */
class GovernanceRecommendation
{
    public const MAX_ITEMS = 6;

    /**
     * Full recommendation payload for one school dossier.
     *
     * @return array{
     *   live: bool,
     *   priority: string,
     *   summary: string,
     *   needs: list<string>,
     *   items: list<array{priority: string, focus: string, domain: string, title: string, action: string, why: string}>,
     *   yes: int,
     *   need_for_functional: int,
     *   maturity_level: int,
     *   ta_flagged: bool
     * }
     */
    public static function forSchool(?Assessment $assessment, string $schoolName = 'This school', bool $polish = true): array
    {
        $items = self::buildItems($assessment);
        $yes = 0;
        $maturity = 0;
        if ($assessment) {
            $assessment->loadMissing(['indicators', 'movs']);
            $yes = $assessment->indicators->where('answer', 'yes')->count();
            $maturity = SgcMaturity::levelFor($assessment);
        }

        $need = max(0, 10 - $yes);
        $priority = self::overallPriority($items, $yes, $assessment);
        $needs = collect($items)->pluck('title')->unique()->values()->take(5)->all();
        $summary = self::summaryText($schoolName, $yes, $need, $maturity, $items, $assessment);

        $bundle = [
            'live' => EncoderRecommendation::enabled(),
            'priority' => $priority,
            'summary' => $summary,
            'needs' => $needs,
            'items' => array_slice($items, 0, self::MAX_ITEMS),
            'yes' => $yes,
            'need_for_functional' => $need,
            'maturity_level' => $maturity,
            'ta_flagged' => $yes < 10,
        ];

        if ($polish && $bundle['items'] !== []) {
            return self::polishBundle($bundle, $assessment?->school_code);
        }

        return $bundle;
    }

    /**
     * Compact row for Schools list / Overview TA queue.
     *
     * @return array{priority: string, top_action: string|null, focus: string|null, ta_flagged: bool, need_for_functional: int}|null
     */
    public static function summaryFor(?Assessment $assessment): ?array
    {
        if (! $assessment) {
            return [
                'priority' => 'medium',
                'top_action' => 'Help the school start encoding the 12 functionality indicators.',
                'focus' => 'submission',
                'ta_flagged' => true,
                'need_for_functional' => 10,
            ];
        }

        $bundle = self::forSchool($assessment, 'School', false);
        $top = $bundle['items'][0] ?? null;

        return [
            'priority' => $bundle['priority'],
            'top_action' => $top['action'] ?? ($bundle['ta_flagged'] ? 'Coach the school toward 10 of 12 Yes FIs.' : null),
            'focus' => $top['focus'] ?? null,
            'ta_flagged' => $bundle['ta_flagged'],
            'need_for_functional' => $bundle['need_for_functional'],
        ];
    }

    /**
     * Division-wide TA priority list (highest need first).
     *
     * @return list<array{
     *   code: string,
     *   name: string,
     *   school_code: string,
     *   yes: int,
     *   need_for_functional: int,
     *   priority: string,
     *   top_action: string,
     *   focus: string|null,
     *   maturity_level: int,
     *   status: string,
     *   href: string
     * }>
     */
    public static function priorityQueue(?int $cycleId = null, int $limit = 8): array
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
            ->get()
            ->keyBy('school_code');

        $rows = $heads->map(function (User $head) use ($packets) {
            $code = $head->packetSchoolCode();
            $packet = $packets->get($code);
            $summary = self::summaryFor($packet);
            $yes = $packet ? $packet->indicators->where('answer', 'yes')->count() : 0;
            $status = $packet?->status ?? 'not_started';

            return [
                'code' => $code,
                'name' => $head->school_name ?: 'Unnamed school',
                'school_code' => $head->school_code ?: '—',
                'yes' => $yes,
                'need_for_functional' => (int) ($summary['need_for_functional'] ?? max(0, 10 - $yes)),
                'priority' => (string) ($summary['priority'] ?? 'medium'),
                'top_action' => (string) ($summary['top_action'] ?? 'Review this school’s packet.'),
                'focus' => $summary['focus'] ?? null,
                'maturity_level' => SgcMaturity::levelFor($packet),
                'status' => $status,
                'href' => '/division/schools/'.$code,
                'ta_flagged' => (bool) ($summary['ta_flagged'] ?? true),
                '_rank' => self::priorityRank((string) ($summary['priority'] ?? 'medium')),
            ];
        })
            ->filter(fn (array $row) => $row['ta_flagged'] || $row['priority'] !== 'none')
            ->sort(function (array $a, array $b) {
                $cmp = $a['_rank'] <=> $b['_rank'];
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = $b['need_for_functional'] <=> $a['need_for_functional'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcasecmp($a['name'], $b['name']);
            })
            ->take($limit)
            ->map(function (array $row) {
                unset($row['_rank'], $row['ta_flagged']);

                return $row;
            })
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @return list<array{priority: string, focus: string, domain: string, title: string, action: string, why: string}>
     */
    private static function buildItems(?Assessment $assessment): array
    {
        if (! $assessment) {
            return [[
                'priority' => 'medium',
                'focus' => 'submission',
                'domain' => 'Assessment readiness',
                'title' => 'No FAT packet yet',
                'action' => 'Contact the School Head to open the cycle packet and begin encoding FI1–FI12.',
                'why' => 'This school has no assessment packet in the current cycle.',
            ]];
        }

        $assessment->loadMissing(['indicators', 'movs']);
        $snap = AssessmentEngine::snapshot($assessment);
        $profile = SchoolFormProfile::query()->firstOrNew(['school_code' => $assessment->school_code]);
        $checks = SgcMaturity::checks($profile, $assessment, $snap);
        $items = [];

        $returned = $assessment->movs->where('status', 'returned');
        if ($returned->isNotEmpty()) {
            $codes = $returned->pluck('code')->take(4)->implode(', ');
            $items[] = [
                'priority' => 'high',
                'focus' => 'mov',
                'domain' => 'Means of Verification',
                'title' => 'Returned MOVs need coaching',
                'action' => 'Walk the school through correcting returned files ('.$codes.'), then recertify QA and resubmit.',
                'why' => $returned->count().' MOV'.($returned->count() === 1 ? ' is' : 's are').' returned and block Functional validation.',
            ];
        }

        if (! $assessment->submitted_at && ($snap['encoded'] ?? 0) < 12) {
            $items[] = [
                'priority' => 'high',
                'focus' => 'submission',
                'domain' => 'Assessment readiness',
                'title' => 'Incomplete encoding',
                'action' => 'Provide encoding TA so the school finishes all 12 Yes/No answers before the deadline.',
                'why' => ($snap['encoded'] ?? 0).' of 12 FIs encoded; packet is not yet with Division.',
            ];
        } elseif (! $assessment->submitted_at && ($snap['missing_count'] ?? 0) > 0) {
            $items[] = [
                'priority' => 'high',
                'focus' => 'mov',
                'domain' => 'Means of Verification',
                'title' => 'Missing Minimum MOVs',
                'action' => 'Coach the school to upload the missing Minimum MOV slots and Validity Form, then submit.',
                'why' => ($snap['missing_count'] ?? 0).' required MOV slot'.(($snap['missing_count'] ?? 0) === 1 ? '' : 's').' still missing.',
            ];
        }

        $indicators = FatCatalog::sortIndicators($assessment->indicators);
        foreach ($indicators as $indicator) {
            $answer = $indicator->answer;
            if ($answer === 'yes') {
                continue;
            }
            $meta = FatCatalog::indicator($indicator->code);
            if (! $meta) {
                continue;
            }
            $catalog = self::fiCatalog($indicator->code);
            $items[] = [
                'priority' => $answer === 'no' ? 'high' : 'medium',
                'focus' => $indicator->code,
                'domain' => FatCatalog::groupLabel((string) ($meta['group'] ?? 'sg')),
                'title' => $indicator->code.' · '.$indicator->title,
                'action' => $catalog['action'],
                'why' => $answer === 'no'
                    ? $indicator->code.' is encoded No. '.$catalog['why']
                    : $indicator->code.' is not encoded yet. '.$catalog['why'],
            ];
        }

        foreach (['structure', 'members', 'operations', 'baseline', 'stakeholders', 'improvement', 'partnerships'] as $key) {
            $check = $checks[$key] ?? null;
            if (! $check || ($check['state'] ?? '') === 'done') {
                continue;
            }
            $maturityItem = self::maturityCatalog($key, (string) ($check['detail'] ?? ''));
            if ($maturityItem) {
                $items[] = $maturityItem;
            }
        }

        if ($assessment->status === 'validated' && $assessment->result === 'not_yet') {
            array_unshift($items, [
                'priority' => 'high',
                'focus' => 'result',
                'domain' => 'Continuous improvement',
                'title' => 'Not yet Functional — plan follow-up TA',
                'action' => 'Schedule targeted TA on the unmet FIs so the school can improve before the next validation window.',
                'why' => 'Division posted Not yet Functional (fewer than 10 of 12 validated Yes FIs).',
            ]);
        }

        return self::dedupeAndSort($items);
    }

    /**
     * @return array{action: string, why: string}
     */
    private static function fiCatalog(string $code): array
    {
        return match ($code) {
            'FI1' => [
                'action' => 'Coach induction, role orientation, org chart, and regular meeting notices so members can exercise their roles.',
                'why' => 'Members must be informed of roles and given a venue for decision-making.',
            ],
            'FI2' => [
                'action' => 'Guide the SGC to meet with the SPT on SIP/AIP and pass clear recommendations to the School Head.',
                'why' => 'The council should act as a consultative body on school policies.',
            ],
            'FI3' => [
                'action' => 'Help the school schedule regular SGC meetings with quorum and keep minutes/resolutions as Minimum MOVs.',
                'why' => 'Regular meetings are required under DO 26, s. 2022.',
            ],
            'FI4' => [
                'action' => 'Facilitate alignment meetings with school committees and document minutes for stakeholder coordination.',
                'why' => 'Work across committees must be synchronized.',
            ],
            'FI5' => [
                'action' => 'Support a clear channel from SGC concerns to the School Head, with written coordination records.',
                'why' => 'Programs and activities need synchronized direction with school management.',
            ],
            'FI6' => [
                'action' => 'Help plan and document stakeholder-initiated programs (e.g. Brigada, Gulayan) with concept notes and M&E.',
                'why' => 'Needs-based stakeholder programs strengthen shared governance.',
            ],
            'FI7' => [
                'action' => 'Coach preparation of an SGC resolution recommending SIP-based policies/programs to the LSB.',
                'why' => 'LSB linkage strengthens LGU–school partnership.',
            ],
            'FI8' => [
                'action' => 'Review membership for inclusive sector representation and document the expanded council list.',
                'why' => 'Inclusive stakeholder representation is a core SGC function.',
            ],
            'FI9' => [
                'action' => 'Encourage participation in assemblies/PTA/SOSA and keep a report of issues raised.',
                'why' => 'Feedback starts with presence in stakeholder activities.',
            ],
            'FI10' => [
                'action' => 'Help organize a stakeholder forum or discussion and keep program documentation plus related minutes.',
                'why' => 'Forums invite broader engagement beyond routine meetings.',
            ],
            'FI11' => [
                'action' => 'Guide SRC / Transparency Board communication so stakeholders can access school data.',
                'why' => 'Access to information supports accountable governance.',
            ],
            'FI12' => [
                'action' => 'Facilitate an SGC review of SIP/AIP quality and capture improvement recommendations in a resolution.',
                'why' => 'Continuous improvement of school plans is an SGC feedback role.',
            ],
            default => [
                'action' => 'Provide TA so the school can meet this functionality indicator with the required Minimum MOV.',
                'why' => 'This FI is unmet or unfinished.',
            ],
        };
    }

    /**
     * @return array{priority: string, focus: string, domain: string, title: string, action: string, why: string}|null
     */
    private static function maturityCatalog(string $key, string $detail): ?array
    {
        $map = [
            'structure' => [
                'priority' => 'medium',
                'title' => 'Establish council structure',
                'action' => 'Help the school save officers in Form data and upload an organizational chart.',
            ],
            'members' => [
                'priority' => 'medium',
                'title' => 'Complete membership records',
                'action' => 'Guide upload of membership/induction certificates or the official voting-member list.',
            ],
            'operations' => [
                'priority' => 'medium',
                'title' => 'Start basic SGC operations',
                'action' => 'Coach meeting details in Form data plus a notice or minutes file.',
            ],
            'baseline' => [
                'priority' => 'medium',
                'title' => 'Close Level II baseline gaps',
                'action' => 'Support stakeholder minutes (FI4C) and a stakeholder monitoring report (FI6B).',
            ],
            'stakeholders' => [
                'priority' => 'low',
                'title' => 'Strengthen stakeholder meeting records',
                'action' => 'Help the school upload minutes from stakeholder engagement meetings.',
            ],
            'improvement' => [
                'priority' => 'low',
                'title' => 'Lead school improvement planning',
                'action' => 'Coach preparation and upload of an SGC action / advocacy plan.',
            ],
            'partnerships' => [
                'priority' => 'low',
                'title' => 'Sustain community partnerships',
                'action' => 'Guide a concept note or project brief for a stakeholder-initiated partnership.',
            ],
        ];

        if (! isset($map[$key])) {
            return null;
        }

        return [
            'priority' => $map[$key]['priority'],
            'focus' => 'maturity:'.$key,
            'domain' => 'Maturity development',
            'title' => $map[$key]['title'],
            'action' => $map[$key]['action'],
            'why' => $detail !== '' ? $detail : 'This maturity step is not done yet.',
        ];
    }

    /**
     * @param  list<array{priority: string, focus: string, domain: string, title: string, action: string, why: string}>  $items
     * @return list<array{priority: string, focus: string, domain: string, title: string, action: string, why: string}>
     */
    private static function dedupeAndSort(array $items): array
    {
        return collect($items)
            ->unique(fn (array $item) => $item['focus'].'|'.$item['title'])
            ->sort(function (array $a, array $b) {
                $cmp = self::priorityRank($a['priority']) <=> self::priorityRank($b['priority']);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp($a['focus'], $b['focus']);
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{priority: string, focus: string, domain: string, title: string, action: string, why: string}>  $items
     */
    private static function overallPriority(array $items, int $yes, ?Assessment $assessment): string
    {
        if ($items === []) {
            return 'none';
        }
        if ($yes < 10 || ($assessment && $assessment->result === 'not_yet') || collect($items)->contains(fn (array $i) => $i['priority'] === 'high')) {
            return 'high';
        }
        if (collect($items)->contains(fn (array $i) => $i['priority'] === 'medium')) {
            return 'medium';
        }

        return 'low';
    }

    private static function priorityRank(string $priority): int
    {
        return match ($priority) {
            'high' => 0,
            'medium' => 1,
            'low' => 2,
            default => 3,
        };
    }

    /**
     * @param  list<array{priority: string, focus: string, domain: string, title: string, action: string, why: string}>  $items
     */
    private static function summaryText(string $schoolName, int $yes, int $need, int $maturity, array $items, ?Assessment $assessment): string
    {
        if ($items === []) {
            return $schoolName.' has no open governance TA gaps in the current packet snapshot.';
        }

        $level = ['Level 0', 'Level I', 'Level II', 'Level III'][$maturity] ?? 'Level 0';
        if ($need > 0) {
            return $schoolName.' is at '.$yes.'/12 Yes FIs ('.$level.'). Priority TA: close '.$need.' more FI'.($need === 1 ? '' : 's').' toward Functional, starting with '.($items[0]['title'] ?? 'the top gap').'.';
        }

        $result = $assessment?->result === 'functional' ? 'Functional' : 'at or above the 10/12 bar';

        return $schoolName.' is '.$result.' ('.$level.'). Continue improvement TA on: '.($items[0]['title'] ?? 'remaining maturity gaps').'.';
    }

    /**
     * @param  array{
     *   live: bool,
     *   priority: string,
     *   summary: string,
     *   needs: list<string>,
     *   items: list<array{priority: string, focus: string, domain: string, title: string, action: string, why: string}>,
     *   yes: int,
     *   need_for_functional: int,
     *   maturity_level: int,
     *   ta_flagged: bool
     * }  $bundle
     * @return array{
     *   live: bool,
     *   priority: string,
     *   summary: string,
     *   needs: list<string>,
     *   items: list<array{priority: string, focus: string, domain: string, title: string, action: string, why: string}>,
     *   yes: int,
     *   need_for_functional: int,
     *   maturity_level: int,
     *   ta_flagged: bool
     * }
     */
    private static function polishBundle(array $bundle, ?string $schoolCode): array
    {
        if (! EncoderRecommendation::enabled()) {
            return $bundle;
        }

        $fingerprint = hash('sha256', (string) json_encode([
            $schoolCode,
            $bundle['priority'],
            $bundle['yes'],
            $bundle['need_for_functional'],
            $bundle['maturity_level'],
            collect($bundle['items'])->map(fn (array $item) => [
                $item['focus'], $item['title'], $item['action'], $item['why'],
            ])->all(),
        ]));

        return Cache::remember('sgc:ai:govrec:'.$fingerprint, 900, function () use ($bundle) {
            try {
                $response = Http::timeout(25)
                    ->connectTimeout(10)
                    ->retry(1, 500)
                    ->withToken((string) config('services.openai.key'))
                    ->acceptJson()
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => config('services.openai.model', 'gpt-4o-mini'),
                        'max_completion_tokens' => 1200,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You help DepEd Division SGC focals write technical assistance recommendations. Rewrite summary and each item action into clear coaching language for Division staff. Keep the same number of items and the same focus/priority/domain/title/why facts. Do not invent Yes/No answers. Do not declare Functional unless yes>=10 is already in the JSON. Return JSON {"summary":"...","items":[{"focus":"...","action":"..."}]} only.',
                            ],
                            [
                                'role' => 'user',
                                'content' => json_encode([
                                    'summary' => $bundle['summary'],
                                    'yes' => $bundle['yes'],
                                    'need_for_functional' => $bundle['need_for_functional'],
                                    'maturity_level' => $bundle['maturity_level'],
                                    'items' => $bundle['items'],
                                ], JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                        'response_format' => ['type' => 'json_object'],
                    ]);

                if (! $response->successful()) {
                    Log::warning('Governance recommendation polish failed.', ['status' => $response->status()]);

                    return $bundle;
                }

                $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content'), true);
                if (! is_array($decoded)) {
                    return $bundle;
                }

                $summary = $decoded['summary'] ?? null;
                if (is_string($summary) && trim($summary) !== '') {
                    $bundle['summary'] = trim($summary);
                }

                $polished = $decoded['items'] ?? null;
                if (! is_array($polished)) {
                    return $bundle;
                }

                $byFocus = collect($polished)->keyBy(fn ($row) => is_array($row) ? (string) ($row['focus'] ?? '') : '');
                $bundle['items'] = collect($bundle['items'])->map(function (array $item) use ($byFocus) {
                    $row = $byFocus->get($item['focus']);
                    if (is_array($row) && is_string($row['action'] ?? null) && trim($row['action']) !== '') {
                        $item['action'] = trim($row['action']);
                    }

                    return $item;
                })->all();

                return $bundle;
            } catch (\Throwable $e) {
                Log::warning('Governance recommendation polish failed.', ['message' => $e->getMessage()]);

                return $bundle;
            }
        });
    }
}
