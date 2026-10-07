<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SgcAi
{
    /**
     * Role-aware dashboard AI: ideas, data interpretation, graph reading, analytics.
     *
     * @return array<string, mixed>
     */
    public static function forUser(User $user, ?string $path = null): array
    {
        $bundle = match (true) {
            $user->isSchoolStaff() => self::forSchool($user),
            $user->role === 'division' => self::forDivision(),
            default => self::forSuper(),
        };

        $page = self::pageContext($user, $path);
        if ($page === []) {
            return $bundle;
        }

        $bundle['page'] = $page;
        $bundle['prompts'] = $page['prompts'];
        $bundle['next'] = $page['next'] ?? $bundle['next'];
        $bundle['href'] = $page['href'] ?? $bundle['href'];
        $bundle['hint'] = $page['explain'];

        return $bundle;
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    public static function chat(User $user, string $message, array $history = [], ?string $path = null): string
    {
        $bundle = self::forUser($user, $path);
        $intent = self::intent($message);
        $fallback = self::fallbackReply($bundle, $intent, $message);

        if ($user->isSchoolStaff() && EncoderRecommendation::isWalkthroughRequest($message)) {
            $assessment = AssessmentEngine::forSchool($user);
            $packet = $assessment ? EncoderRecommendation::forPacket($assessment, false) : $bundle;

            return EncoderRecommendation::walkthroughReply($packet);
        }

        if (! EncoderRecommendation::enabled()) {
            return $fallback;
        }

        $roleLabel = $user->role_label;
        $guard = $user->isSchoolStaff()
            ? 'Never decide Yes or No. Never say the SGC is Functional. Never claim to upload files.'
            : 'Do not invent school names or counts that are not in the JSON. Do not declare an SGC Functional unless the snapshot already says so.';

        $style = match ($intent) {
            'page' => 'Explain ONLY the current page in page JSON. Name the screen. Say what this user can do here. Use packet/school facts on that page. Do not quote division-wide submission rates, funnel, or other schools unless page.key is division.overview.',
            'ideas' => 'Generate 3–5 concrete next actions for THIS page. Number them. No fluff.',
            'graphs' => 'Read the charts/bars in the snapshot. Name the weakest and strongest signals. 3–5 sentences.',
            'interpret', 'analytics' => 'Interpret what is on THIS page. Then one idea for what to do next. 3–5 sentences. If page JSON is present, do not switch to another screen.',
            default => 'Answer in 2–5 short sentences about the current page. Use page JSON first, then dashboard JSON.',
        };

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are SGC SmartTrack AI (DepEd SGC FAT, DO 26 s.2022). Audience: '.$roleLabel.'. The user is looking at one screen. '.$style.' '.$guard,
            ],
            [
                'role' => 'system',
                'content' => 'Current page JSON: '.json_encode(self::promptPayload($bundle), JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach (array_slice($history, -8) as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = Http::timeout(12)
                ->connectTimeout(8)
                ->retry(1, 400)
                ->withToken((string) config('services.openai.key'))
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.3,
                    'max_tokens' => $intent === 'ideas' ? 420 : 320,
                    'messages' => $messages,
                ]);

            if (! $response->successful()) {
                Log::warning('SGC AI chat failed.', ['status' => $response->status(), 'role' => $user->role]);

                return $fallback;
            }

            $reply = trim((string) data_get($response->json(), 'choices.0.message.content'));

            return $reply !== '' ? $reply : $fallback;
        } catch (\Throwable $e) {
            Log::warning('SGC AI chat failed.', ['message' => $e->getMessage(), 'role' => $user->role]);

            return $fallback;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function forSchool(User $user): array
    {
        $packet = EncoderRecommendation::forUser($user) ?? [
            'template' => 'Official SGC MOV template',
            'hint' => 'No open SGC FAT cycle.',
            'next' => 'No open SGC FAT cycle',
            'unencoded' => [],
            'missing' => [],
            'returned' => [],
            'href' => '/school/assessment',
            'open' => 0,
            'live' => EncoderRecommendation::enabled(),
        ];

        $assessment = AssessmentEngine::forSchool($user);
        $snap = $assessment ? AssessmentEngine::snapshot($assessment) : null;
        $yes = (int) ($snap['yes_count'] ?? 0);
        $encoded = (int) ($snap['encoded'] ?? 0);
        $returned = (int) ($snap['returned_count'] ?? 0);
        $missing = (int) ($snap['missing_count'] ?? 0);

        $graphs = $snap
            ? $encoded.' of 12 FIs encoded, '.$yes.' Yes. Need '.max(0, 10 - $yes).' more Yes for Functional (10/12). The indicator chart is Y = Yes, N = No, R = returned MOV, dash = not started.'
            : 'No cycle is open, so the dashboard has no FI bars yet.';

        $ideas = [];
        if (($packet['returned'] ?? []) !== []) {
            $ideas[] = 'Replace returned MOVs first: '.implode('; ', array_slice($packet['returned'], 0, 3)).'.';
        }
        if (($packet['unencoded'] ?? []) !== []) {
            $ideas[] = 'Encode remaining primary FIs, starting with '.$packet['unencoded'][0].'. AI will not tap Yes or No.';
        }
        if (($packet['missing'] ?? []) !== []) {
            $ideas[] = 'Upload missing Minimum MOVs: '.implode('; ', array_slice($packet['missing'], 0, 3)).'.';
        }
        if ($ideas === []) {
            $ideas[] = $packet['next'] ?? 'Open Submit so the School Head can certify QA.';
        }
        $ideas[] = 'Use the indicator chart: finish blank FIs, then any R (returned) bars.';

        $hint = $packet['hint'];
        if ($snap) {
            $hint = $graphs.' '.$hint;
        }

        return self::bundle('school', $packet + [
            'uses' => ['ideas', 'interpret', 'graphs', 'analytics'],
            'hint' => $hint,
            'ideas' => $ideas,
            'graphs' => $graphs,
            'prompts' => [
                'Interpret this dashboard',
                'What do the graphs show?',
                'Generate next actions',
                'Would you like me to walk you through?',
            ],
            'kpis' => $snap ? [
                'yes' => $yes,
                'encoded' => $encoded,
                'returned' => $returned,
                'missing' => $missing,
            ] : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function forDivision(): array
    {
        $s = PortalMetrics::analyticsSnapshot();
        $weak = collect($s['weakest'] ?? [])->map(fn (array $bar) => $bar['label'].' '.$bar['value'])->values()->all();
        $graphs = $weak === []
            ? 'Weakest-indicator and funnel charts are empty until School Heads are accepted.'
            : 'Weakest FIs (share of schools with Yes): '.implode(', ', $weak).'. Funnel: not started '.$s['overdue'].', submitted '.$s['submitted'].', in queue '.$s['queue'].', validated '.$s['validated'].'.';

        $hint = $s['schools'] === 0
            ? 'No accepted School Heads yet. Analytics stay empty until registrations are accepted.'
            : $s['submitted'].' of '.$s['schools'].' schools submitted ('.$s['compliance'].'%). '.$s['overdue'].' overdue, '.$s['queue'].' in the validation queue, '.$s['functional'].' Functional (≥10/12), '.$s['returned_movs'].' returned MOVs.';

        $ideas = [];
        if ($s['queue'] > 0) {
            $ideas[] = 'Clear the validation queue ('.$s['queue'].' packet'.($s['queue'] === 1 ? '' : 's').') so schools are not waiting on Composite Team review.';
        }
        if ($s['overdue'] > 0) {
            $ideas[] = 'Follow up '.$s['overdue'].' school'.($s['overdue'] === 1 ? '' : 's').' with no submission. Use Alerts for names.';
        }
        if ($s['returned_movs'] > 0) {
            $ideas[] = 'Returned MOVs ('.$s['returned_movs'].') need school resubmit — check Alerts, then re-queue.';
        }
        if ($weak !== []) {
            $ideas[] = 'Plan TA around the weakest FIs: '.implode(', ', array_slice($weak, 0, 3)).'.';
        }
        if ($ideas === []) {
            $ideas[] = 'Submission looks current. Spot-check Functional packets and keep the cycle deadline in view ('.($s['deadline'] ?? 'no open cycle').').';
        }

        $open = (int) $s['overdue'] + (int) $s['queue'] + (int) $s['returned_movs'];

        return self::bundle('division', [
            'template' => 'Division analytics',
            'hint' => $hint,
            'next' => $s['queue'] ? 'Open the validation queue' : ($s['overdue'] ? 'Open Alerts for overdue schools' : 'Review weakest indicators'),
            'unencoded' => [],
            'missing' => $weak,
            'returned' => [],
            'href' => $s['queue'] ? '/division/queue' : '/division/alerts',
            'open' => $open,
            'live' => EncoderRecommendation::enabled(),
            'uses' => ['ideas', 'interpret', 'graphs', 'analytics'],
            'ideas' => $ideas,
            'graphs' => $graphs,
            'prompts' => [
                'Interpret this dashboard',
                'What do the graphs show?',
                'Generate TA ideas',
                'Which schools need follow-up?',
            ],
            'kpis' => $s,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function forSuper(): array
    {
        $s = PortalMetrics::analyticsSnapshot();
        $weak = collect($s['weakest'] ?? [])->map(fn (array $bar) => $bar['label'].' '.$bar['value'])->values()->all();
        $graphs = $weak === []
            ? 'Indicator and compliance charts fill after School Heads are accepted into the cycle.'
            : 'Indicator performance (lowest first): '.implode(', ', $weak).'. Compliance funnel uses '.$s['schools'].' accepted schools.';

        $hint = $s['schools'] === 0
            ? 'No schools in cycle yet. Create a Division Admin and accept School Heads so analytics have data.'
            : 'System: '.$s['schools'].' schools, '.$s['compliance'].'% submitted, '.$s['functional'].' Functional, '.$s['validated'].' validated, '.$s['returned_movs'].' returned MOVs. Deadline: '.($s['deadline'] ?? 'no open cycle').'.';

        $ideas = [];
        if ($s['schools'] === 0) {
            $ideas[] = 'Accept or create School Heads so the cycle has packets to measure.';
        }
        if ($s['overdue'] > 0) {
            $ideas[] = $s['overdue'].' schools have not submitted — Division should chase them before the deadline.';
        }
        if ($s['returned_movs'] > 0) {
            $ideas[] = 'Returned MOVs ('.$s['returned_movs'].') stall Functional counts until schools replace files.';
        }
        if ($weak !== []) {
            $ideas[] = 'National/division TA should target '.implode(', ', array_slice($weak, 0, 3)).'.';
        }
        if ($ideas === []) {
            $ideas[] = 'Compliance is current. Watch cycle status and admin accounts ('.$s['admins'].').';
        }

        $open = (int) $s['overdue'] + (int) $s['queue'] + (int) $s['returned_movs'];

        return self::bundle('super', [
            'template' => 'System analytics',
            'hint' => $hint,
            'next' => $s['schools'] ? 'Review indicator performance and compliance funnel' : 'Open Users & roles to add Division / schools',
            'unencoded' => [],
            'missing' => $weak,
            'returned' => [],
            'href' => '/super',
            'open' => $open,
            'live' => EncoderRecommendation::enabled(),
            'uses' => ['ideas', 'interpret', 'graphs', 'analytics'],
            'ideas' => $ideas,
            'graphs' => $graphs,
            'prompts' => [
                'Interpret system analytics',
                'What do the graphs show?',
                'Where is compliance weakest?',
                'Generate next admin actions',
            ],
            'kpis' => $s,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private static function bundle(string $role, array $fields): array
    {
        return array_merge([
            'role' => $role,
            'uses' => ['ideas', 'interpret', 'graphs', 'analytics'],
            'ideas' => [],
            'graphs' => '',
            'prompts' => [],
            'kpis' => [],
        ], $fields);
    }

    /**
     * @param  array<string, mixed>  $bundle
     * @return array<string, mixed>
     */
    private static function promptPayload(array $bundle): array
    {
        return [
            'role' => $bundle['role'] ?? '',
            'page' => $bundle['page'] ?? [],
            'hint' => $bundle['hint'] ?? '',
            'next' => $bundle['next'] ?? '',
            'ideas' => $bundle['ideas'] ?? [],
            'graphs' => $bundle['graphs'] ?? '',
            'unencoded' => $bundle['unencoded'] ?? [],
            'missing' => $bundle['missing'] ?? [],
            'returned' => $bundle['returned'] ?? [],
            'kpis' => $bundle['kpis'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pageContext(User $user, ?string $path): array
    {
        $path = self::normalizePath($path);
        if ($path === '') {
            return [];
        }

        if (preg_match('#^/division/queue/(\d+)$#', $path, $match) && in_array($user->role, ['division', 'super'], true)) {
            return self::divisionReviewPage((int) $match[1]);
        }

        if (preg_match('#^/division/schools/([^/]+)$#', $path) && in_array($user->role, ['division', 'super'], true)) {
            return self::describePage(
                'division.school',
                'School dossier',
                'This page is one school’s file: identity and Form data, School Head, Encoders, and the current FAT packet (Yes/No per FI and MOV files). Use Open packet only when the school has submitted for validation.',
                $path,
                'Review the team and the packet, then open validation if it is in queue',
                ['Explain this page', 'Who is on this school team?', 'What is the submission status?'],
            );
        }

        return match (true) {
            $path === '/division/queue' && $user->role === 'division' => self::describePage(
                'division.queue',
                'Validation queue',
                'This is the list of school packets sent to Division. Open Review on a row to accept or return each MOV. It is not the school list and not the dashboard charts.',
                '/division/queue',
                'Open a packet and decide each uploaded MOV',
                ['Explain this page', 'Which packets still need review?', 'What does Returned mean?'],
            ),
            $path === '/division/schools' && in_array($user->role, ['division', 'super'], true) => self::describePage(
                'division.schools',
                'Schools',
                'This table lists School Heads in the division. Click a row to open that school’s data, School Head, Encoders, and submission. Counts on this table are per school, not division-wide charts.',
                '/division/schools',
                'Click a school to open its dossier',
                ['Explain this page', 'How do I open a school?', 'What does No data mean?'],
            ),
            $path === '/division/registrations' && $user->role === 'division' => self::describePage(
                'division.registrations',
                'School Head registrations',
                'This page is only pending School Head accounts. Accept a request so that school can sign in and approve its Encoders. It is not MOV validation.',
                '/division/registrations',
                'Accept pending School Heads',
                ['Explain this page', 'What happens after I accept?'],
            ),
            $path === '/division/alerts' && $user->role === 'division' => self::describePage(
                'division.alerts',
                'Alerts',
                'This page lists returned MOVs and schools with no submission yet. Use it for follow-up, then open the queue or the school dossier for the actual files.',
                '/division/alerts',
                'Open a school or the queue from an alert',
                ['Explain this page', 'Which schools need follow-up?'],
            ),
            $path === '/division' && $user->role === 'division' => self::describePage(
                'division.overview',
                'Division dashboard',
                'This dashboard is division-wide KPIs and charts (submission, queue, Functional, weakest FIs). It is not a single school packet. Open Validation queue or Schools for a specific school.',
                '/division',
                'Use Validation queue or Schools for packet work',
                ['Explain this page', 'Interpret this dashboard', 'What do the graphs show?'],
            ),
            $path === '/school/assessment' && $user->isSchoolStaff() => self::describePage(
                'school.assessment',
                'My assessment',
                'This page is where the Encoder answers Yes or No on each FI and uploads Minimum MOVs. AI does not encode Yes or No. Download filled templates from Form data.',
                '/school/assessment',
                'Encode remaining FIs or upload Minimum MOVs',
                ['Explain this page', 'Which FI should I do next?'],
            ),
            $path === '/school/form-data' && $user->isSchoolStaff() => self::describePage(
                'school.form-data',
                'Form data',
                'Save school letterhead and officers here once. Open or Download filled on Templates stamps these values into official Word files.',
                '/school/form-data',
                'Save form data, then generate MOV forms',
                ['Explain this page', 'What gets stamped into Word?'],
            ),
            $path === '/school/encoders' && $user->isSchoolHead() => self::describePage(
                'school.encoders',
                'Encoders',
                'Pending requests wait for Accept. Encoders in this school are already active and can encode FIs and upload MOVs.',
                '/school/encoders',
                'Accept pending teachers or review the active list',
                ['Explain this page', 'Where is the encoder list?'],
            ),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function divisionReviewPage(int $id): array
    {
        $assessment = Assessment::query()->with(['user', 'movs', 'indicators'])->find($id);
        if (! $assessment) {
            return self::describePage(
                'division.review',
                'Validation review',
                'This is a packet review screen, but that packet was not found. Go back to Validation queue.',
                '/division/queue',
                'Open the validation queue',
                ['Explain this page'],
            );
        }

        $school = $assessment->user?->school_name ?: 'this school';
        $yes = $assessment->indicators->where('answer', 'yes')->count();
        $returned = $assessment->movs->where('status', 'returned');
        $pending = $assessment->movs
            ->filter(fn ($mov) => $mov->hasFile() && ! in_array($mov->status, ['valid', 'returned'], true))
            ->values();

        $explain = 'This is MOV validation for '.$school.', not the division dashboard. Each row is one Means of Verification. Accept a file if it matches FAT Volume 2, or type why it is invalid and Return it. Packet status: '.$assessment->status.'. Yes answers: '.$yes.'/12.';
        if ($returned->isNotEmpty()) {
            $explain .= ' Returned: '.$returned->map(fn ($mov) => $mov->code.($mov->return_reason ? ' ('.$mov->return_reason.')' : ''))->implode('; ').'. Completing validation waits until the school replaces those files.';
        }
        if ($pending->isNotEmpty()) {
            $explain .= ' Still awaiting a decision: '.$pending->pluck('code')->implode(', ').'.';
        }

        return [
            'key' => 'division.review',
            'title' => 'Validation review · '.$school,
            'explain' => $explain,
            'next' => $returned->isNotEmpty()
                ? 'Wait for '.$school.' to replace returned MOVs, then review the new files'
                : 'Accept remaining uploaded files, then complete validation if every file is valid',
            'href' => '/division/queue/'.$assessment->id,
            'prompts' => [
                'Explain this page',
                'Which MOVs still need a decision?',
                'What happens if I return a file?',
            ],
            'packet' => [
                'school' => $school,
                'status' => $assessment->status,
                'yes' => $yes,
                'returned' => $returned->pluck('code')->values()->all(),
                'awaiting' => $pending->pluck('code')->values()->all(),
            ],
        ];
    }

    /**
     * @param  list<string>  $prompts
     * @return array<string, mixed>
     */
    private static function describePage(string $key, string $title, string $explain, string $href, string $next, array $prompts): array
    {
        return compact('key', 'title', 'explain', 'href', 'next', 'prompts');
    }

    private static function normalizePath(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?: '');
        }
        $path = strtok($path, '?') ?: $path;

        return '/'.trim($path, '/');
    }

    private static function intent(string $message): string
    {
        if (preg_match('/\b(this page|this screen|what (page|screen)|where am i|explain this page|what (am i|is this)|how (do i|does this page))\b/i', $message)) {
            return 'page';
        }
        if (preg_match('/\b(idea|ideas|generate|recommend|next action|what should|TA|technical assistance)\b/i', $message)) {
            return 'ideas';
        }
        if (preg_match('/\b(graph|graphs|chart|charts|bar|funnel|weakest)\b/i', $message)) {
            return 'graphs';
        }
        if (preg_match('/\b(interpret|interpretation|meaning|explain|what does|analytics|kpi|dashboard|data)\b/i', $message)) {
            return 'interpret';
        }

        return 'general';
    }

    /**
     * @param  array<string, mixed>  $bundle
     */
    private static function fallbackReply(array $bundle, string $intent, string $message): string
    {
        if (EncoderRecommendation::isWalkthroughRequest($message) && ($bundle['role'] ?? '') === 'school') {
            return EncoderRecommendation::walkthroughReply($bundle);
        }

        if ($intent === 'page' || (($bundle['page']['explain'] ?? '') !== '' && $intent === 'general')) {
            $page = $bundle['page'] ?? [];

            return trim((string) ($page['explain'] ?? $bundle['hint'] ?? '').' '.(string) ($page['next'] ?? $bundle['next'] ?? ''));
        }

        return match ($intent) {
            'ideas' => trim(implode("\n", array_map(
                fn (int $i, string $idea) => ($i + 1).'. '.$idea,
                array_keys($bundle['ideas'] ?? []),
                array_values($bundle['ideas'] ?? []),
            ))) ?: (string) ($bundle['next'] ?? ''),
            'graphs' => (string) ($bundle['graphs'] ?? $bundle['hint'] ?? ''),
            default => trim((string) ($bundle['hint'] ?? '').' '.(string) ($bundle['next'] ?? '')),
        };
    }
}
