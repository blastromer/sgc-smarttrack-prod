<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\CoachingSession;
use App\Models\Cycle;
use App\Models\ImprovementPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Persisted School Governance Improvement Plan.
 * Catalog + packet snapshot first; optional OpenAI rewrite. Never invents Yes/No or Functional.
 */
class GovernanceImprovementPlan
{
    public const MAX_ACTIONS = 6;

    /**
     * @return array<string, mixed>|null
     */
    public static function latestPayload(string $schoolCode, ?int $cycleId = null): ?array
    {
        $plan = self::latest($schoolCode, $cycleId);
        if (! $plan) {
            return null;
        }

        return self::toPayload($plan, self::upcomingSession($schoolCode, $cycleId));
    }

    public static function latest(string $schoolCode, ?int $cycleId = null): ?ImprovementPlan
    {
        return ImprovementPlan::query()
            ->where('school_code', $schoolCode)
            ->when($cycleId, fn ($query) => $query->where('cycle_id', $cycleId))
            ->where('status', 'issued')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }

    public static function upcomingSession(string $schoolCode, ?int $cycleId = null): ?CoachingSession
    {
        return CoachingSession::query()
            ->where('school_code', $schoolCode)
            ->when($cycleId, fn ($query) => $query->where('cycle_id', $cycleId))
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public static function toPayload(ImprovementPlan $plan, ?CoachingSession $session = null): array
    {
        return [
            'id' => $plan->id,
            'version' => $plan->version,
            'status' => $plan->status,
            'priority' => $plan->priority,
            'summary' => $plan->summary,
            'objectives' => array_values($plan->objectives ?? []),
            'actions' => array_values($plan->actions ?? []),
            'live' => $plan->model !== null && $plan->model !== 'catalog',
            'model' => $plan->model,
            'created_at' => $plan->created_at?->format('M j, Y g:i A'),
            'coaching' => $session ? self::sessionPayload($session) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sessionPayload(CoachingSession $session): array
    {
        return [
            'id' => $session->id,
            'scheduled_at' => $session->scheduled_at?->format('Y-m-d\\TH:i'),
            'when' => $session->scheduled_at?->format('D, M j, Y g:i A'),
            'mode' => $session->mode,
            'venue' => $session->venue,
            'focus' => $session->focus,
            'notes' => $session->notes,
            'status' => $session->status,
        ];
    }

    public static function generate(User $actor, string $schoolCode, string $schoolName, ?Assessment $assessment, Cycle $cycle): ImprovementPlan
    {
        $catalog = GovernanceRecommendation::forSchool($assessment, $schoolName, false);
        $draft = self::catalogDraft($catalog, $schoolName, $assessment);
        $modelName = 'catalog';

        if (EncoderRecommendation::enabled()) {
            $polished = self::askModel($draft, $catalog, $assessment);
            if ($polished !== null) {
                $draft = $polished;
                $modelName = (string) config('services.openai.model', 'gpt-4o-mini');
            }
        }

        $version = (int) ImprovementPlan::query()
            ->where('school_code', $schoolCode)
            ->where('cycle_id', $cycle->id)
            ->max('version') + 1;

        return ImprovementPlan::query()->create([
            'school_code' => $schoolCode,
            'cycle_id' => $cycle->id,
            'assessment_id' => $assessment?->id,
            'version' => $version,
            'status' => 'issued',
            'priority' => $catalog['priority'] ?? 'medium',
            'summary' => $draft['summary'],
            'objectives' => $draft['objectives'],
            'actions' => $draft['actions'],
            'source_fingerprint' => $draft['fingerprint'],
            'model' => $modelName,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array{summary: string, objectives: list<string>, actions: list<array<string, string>>, fingerprint: string}
     */
    public static function catalogDraft(array $catalog, string $schoolName, ?Assessment $assessment): array
    {
        $items = array_slice($catalog['items'] ?? [], 0, self::MAX_ACTIONS);
        $actions = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $actions[] = self::actionFromCatalog($item);
        }

        if ($actions === []) {
            $actions[] = [
                'focus' => 'submission',
                'area' => 'Assessment readiness',
                'activity' => 'Help the school start encoding the 12 functionality indicators.',
                'output' => 'Packet opened with initial FI answers.',
                'timeframe' => 'This quarter',
                'persons' => 'School Head, Encoder, Division SGC focal',
                'materials' => 'SGC FAT Volume 2, official MOV templates',
            ];
        }

        $need = (int) ($catalog['need_for_functional'] ?? 0);
        $yes = (int) ($catalog['yes'] ?? 0);
        $objectives = [];
        if ($need > 0) {
            $objectives[] = 'Close '.$need.' more functionality indicator'.($need === 1 ? '' : 's').' toward the 10 of 12 bar (currently '.$yes.'/12 Yes).';
        } else {
            $objectives[] = 'Sustain validated Yes answers and close remaining maturity gaps.';
        }
        foreach (array_slice($actions, 0, 3) as $action) {
            $objectives[] = 'Complete: '.$action['activity'];
        }
        $objectives = array_values(array_unique(array_slice($objectives, 0, 5)));

        $summary = is_string($catalog['summary'] ?? null) && $catalog['summary'] !== ''
            ? (string) $catalog['summary']
            : $schoolName.' needs a School Governance Improvement Plan grounded in the current FAT snapshot.';

        $fingerprint = hash('sha256', (string) json_encode([
            $assessment?->id,
            $catalog['priority'] ?? null,
            $yes,
            $need,
            collect($actions)->pluck('focus')->all(),
        ]));

        return compact('summary', 'objectives', 'actions', 'fingerprint');
    }

    /**
     * @param  array{priority?: string, focus?: string, domain?: string, title?: string, action?: string, why?: string}  $item
     * @return array{focus: string, area: string, activity: string, output: string, timeframe: string, persons: string, materials: string}
     */
    private static function actionFromCatalog(array $item): array
    {
        $priority = (string) ($item['priority'] ?? 'medium');

        return [
            'focus' => (string) ($item['focus'] ?? 'submission'),
            'area' => (string) ($item['domain'] ?? 'School governance'),
            'activity' => (string) ($item['action'] ?? $item['title'] ?? 'Provide targeted SGC technical assistance.'),
            'output' => (string) ($item['why'] ?? 'Documented progress on this governance gap.'),
            'timeframe' => match ($priority) {
                'high' => 'Within 30 days',
                'low' => 'This school year',
                default => 'This quarter',
            },
            'persons' => 'School Head, SGC officers, Division SGC focal',
            'materials' => 'SGC FAT Volume 2 Minimum MOVs, meeting templates',
        ];
    }

    /**
     * @param  array{summary: string, objectives: list<string>, actions: list<array<string, string>>, fingerprint: string}  $draft
     * @param  array<string, mixed>  $catalog
     * @return array{summary: string, objectives: list<string>, actions: list<array<string, string>>, fingerprint: string}|null
     */
    private static function askModel(array $draft, array $catalog, ?Assessment $assessment): ?array
    {
        $allowed = collect($draft['actions'])->pluck('focus')->filter()->values()->all();

        try {
            $response = Http::timeout(35)
                ->connectTimeout(10)
                ->retry(1, 500)
                ->withToken((string) config('services.openai.key'))
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'max_completion_tokens' => 2200,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You write a School Governance Improvement Plan for a DepEd Division SGC focal (DO 26 s.2022, SGC FAT). Rewrite summary, 3-5 objectives, and each action into clear coaching language. Keep the same number of actions and the same focus values. Do not invent Yes/No answers. Do not declare Functional unless yes>=10 is already in the JSON. Return JSON only: {"summary":"...","objectives":["..."],"actions":[{"focus":"...","area":"...","activity":"...","output":"...","timeframe":"...","persons":"...","materials":"..."}]}.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'yes' => $catalog['yes'] ?? 0,
                                'need_for_functional' => $catalog['need_for_functional'] ?? 0,
                                'maturity_level' => $catalog['maturity_level'] ?? 0,
                                'result' => $assessment?->result,
                                'draft' => [
                                    'summary' => $draft['summary'],
                                    'objectives' => $draft['objectives'],
                                    'actions' => $draft['actions'],
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                Log::warning('SGIP generation failed.', ['status' => $response->status()]);

                return null;
            }

            $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content'), true);
            if (! is_array($decoded)) {
                return null;
            }

            return self::mergeModel($draft, $decoded, $allowed);
        } catch (\Throwable $e) {
            Log::warning('SGIP generation failed.', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array{summary: string, objectives: list<string>, actions: list<array<string, string>>, fingerprint: string}  $draft
     * @param  array<string, mixed>  $decoded
     * @param  list<string>  $allowed
     * @return array{summary: string, objectives: list<string>, actions: list<array<string, string>>, fingerprint: string}
     */
    private static function mergeModel(array $draft, array $decoded, array $allowed): array
    {
        $summary = $decoded['summary'] ?? null;
        if (is_string($summary) && trim($summary) !== '') {
            $draft['summary'] = trim($summary);
        }

        $objectives = $decoded['objectives'] ?? null;
        if (is_array($objectives)) {
            $clean = [];
            foreach ($objectives as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $clean[] = trim($line);
                }
            }
            if ($clean !== []) {
                $draft['objectives'] = array_values(array_slice($clean, 0, 5));
            }
        }

        $byFocus = collect(is_array($decoded['actions'] ?? null) ? $decoded['actions'] : [])
            ->filter(fn ($row) => is_array($row))
            ->keyBy(fn (array $row) => (string) ($row['focus'] ?? ''));

        $draft['actions'] = collect($draft['actions'])->map(function (array $action) use ($byFocus, $allowed) {
            $focus = $action['focus'];
            if (! in_array($focus, $allowed, true)) {
                return $action;
            }
            $row = $byFocus->get($focus);
            if (! is_array($row)) {
                return $action;
            }
            foreach (['area', 'activity', 'output', 'timeframe', 'persons', 'materials'] as $key) {
                $value = $row[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $action[$key] = trim($value);
                }
            }

            return $action;
        })->all();

        return $draft;
    }
}
