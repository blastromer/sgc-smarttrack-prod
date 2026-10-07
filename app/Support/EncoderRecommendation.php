<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EncoderRecommendation
{
    /**
     * Catalog-based hint: what is missing and what to submit. Never encodes Yes/No.
     *
     * @return array{template: string, hint: string, missing: list<string>, submit: list<string>, optional: list<string>}
     */
    public static function forIndicator(Assessment $assessment, string $code, ?string $answer): array
    {
        $mins = FatCatalog::minimumSlots($code);
        $optional = FatCatalog::optionalSlots($code);

        $submit = collect($mins)->map(fn (array $slot) => $slot['code'].' · '.$slot['title'])->values()->all();
        $optionalLabels = collect($optional)->map(fn (array $slot) => $slot['code'].' · '.$slot['title'])->values()->all();

        $missing = collect($mins)->filter(function (array $slot) use ($assessment) {
            $mov = $assessment->movs->firstWhere('code', $slot['code']);

            return ! $mov || ! $mov->hasFile() || $mov->status === 'returned';
        })->map(fn (array $slot) => $slot['code'].' · '.$slot['title'])->values()->all();

        $hint = match ($answer) {
            'no' => 'Answer is No. Do not submit a Minimum MOV for this FI. Other sub-indicators are skipped.',
            'yes' => $missing === []
                ? 'Minimum MOV is complete. Additional and other-sub-indicator files are optional and do not score.'
                : 'Yes is encoded. Still missing Minimum MOV: '.implode('; ', $missing).'. Download the template, fill it, then upload on MOV files — or reuse a file this school already submitted.',
            default => 'Not encoded yet. If Yes, submit: '.implode('; ', $submit).'. Download the official template first. If No, no MOV is required.',
        };

        $templates = MovTemplates::forIndicator($code);

        return [
            'template' => $templates[0]['label'] ?? 'Official SGC MOV template',
            'hint' => $hint,
            'missing' => $missing,
            'submit' => $submit,
            'optional' => $optionalLabels,
        ];
    }

    /**
     * Packet-level hint for the header AI control. Never encodes Yes/No.
     *
     * @return array{template: string, hint: string, next: string, unencoded: list<string>, missing: list<string>, returned: list<string>, href: string, open: int, live: bool}|null
     */
    public static function forUser(User $user): ?array
    {
        if (! $user->isSchoolStaff()) {
            return null;
        }

        $assessment = AssessmentEngine::forSchool($user);
        if (! $assessment) {
            return [
                'template' => 'Official SGC MOV template',
                'hint' => 'No open SGC FAT cycle. AI cannot recommend files until a cycle is open.',
                'next' => 'No open SGC FAT cycle',
                'unencoded' => [],
                'missing' => [],
                'returned' => [],
                'href' => '/school/assessment',
                'open' => 0,
                'live' => self::enabled(),
            ];
        }

        return self::forPacket($assessment);
    }

    /**
     * @return array{template: string, hint: string, next: string, unencoded: list<string>, missing: list<string>, returned: list<string>, href: string, open: int, live: bool}
     */
    public static function forPacket(Assessment $assessment, bool $polish = true): array
    {
        $snap = AssessmentEngine::snapshot($assessment);
        $assessment = $snap['assessment'];
        $indicators = FatCatalog::sortIndicators($assessment->indicators);

        $unencoded = $indicators
            ->filter(fn ($indicator) => $indicator->answer === null)
            ->map(fn ($indicator) => $indicator->code.' · '.$indicator->title)
            ->values()
            ->all();

        $missingSlots = AssessmentEngine::missingRequiredMovs($assessment);
        if ($unencoded !== [] && $indicators->where('answer', 'yes')->isEmpty()) {
            $missingSlots = $missingSlots->reject(fn (array $slot) => ($slot['kind'] ?? '') === 'validity');
        }
        $missing = $missingSlots
            ->map(fn (array $slot) => $slot['code'].' · '.$slot['title'])
            ->values()
            ->all();

        $returned = $assessment->movs
            ->where('status', 'returned')
            ->map(fn ($mov) => $mov->code.' · '.$mov->title)
            ->values()
            ->all();

        $next = (string) ($snap['flow']['banner'] ?? 'Continue the FAT path.');
        $href = match (true) {
            $unencoded !== [] => '/school/assessment',
            $returned !== [] || $missing !== [] => '/school/movs',
            default => '/school/submit',
        };

        $hint = match (true) {
            $returned !== [] => 'Replace returned MOVs first: '.implode('; ', $returned).'. Then resubmit. AI does not encode Yes or No.',
            $unencoded !== [] => 'Encode remaining primary FIs first ('.count($unencoded).' not started). If Yes, submit the Minimum MOV on MOV files. AI does not encode Yes or No.',
            $missing !== [] => 'Need to submit: '.implode('; ', $missing).'. Upload on MOV files. AI does not encode Yes or No.',
            default => $next.' AI does not encode Yes or No.',
        };

        $packet = [
            'template' => 'Official SGC MOV template',
            'hint' => $hint,
            'next' => $next,
            'unencoded' => $unencoded,
            'missing' => $missing,
            'returned' => $returned,
            'href' => $href,
            'open' => count($unencoded) + count($missing) + count($returned),
            'live' => self::enabled(),
        ];

        if ($polish) {
            $packet['hint'] = self::polishPacketHint($packet);
        }

        return $packet;
    }

    /**
     * Conversational encoder help. Never encodes Yes/No.
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    public static function chat(User $user, string $message, array $history = []): string
    {
        return SgcAi::chat($user, $message, $history);
    }

    public static function isWalkthroughRequest(string $message): bool
    {
        return self::wantsWalkthrough($message);
    }

    /**
     * @param  array{hint?: string, next?: string, unencoded?: list<string>, missing?: list<string>, returned?: list<string>}  $packet
     */
    public static function walkthroughReply(array $packet): string
    {
        return self::walkthroughText($packet);
    }

    /**
     * Optional OpenAI rewrite of the packet hint. Falls back to catalog text. Cached so school pages stay fast.
     *
     * @param  array{hint: string, next: string, unencoded: list<string>, missing: list<string>, returned: list<string>}  $packet
     */
    private static function polishPacketHint(array $packet): string
    {
        $fallback = $packet['hint'];
        if (! self::enabled()) {
            return $fallback;
        }

        $fingerprint = hash('sha256', (string) json_encode([
            $packet['next'],
            $packet['unencoded'],
            $packet['missing'],
            $packet['returned'],
        ]));

        return Cache::remember('sgc:ai:packet:'.$fingerprint, 600, function () use ($packet, $fallback) {
            $key = (string) config('services.openai.key');

            try {
                $response = Http::timeout(25)
                    ->connectTimeout(15)
                    ->retry(2, 800)
                    ->withToken($key)
                    ->acceptJson()
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => config('services.openai.model', 'gpt-4o-mini'),
                        'temperature' => 0.2,
                        'max_tokens' => 180,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You help DepEd SGC FAT encoders. Recommend only what to encode next or which MOV files are missing. Never decide Yes or No. Never say the SGC is Functional. Return JSON {"hint":"..."} with at most 2 short sentences.',
                            ],
                            [
                                'role' => 'user',
                                'content' => json_encode([
                                    'next' => $packet['next'],
                                    'unencoded' => $packet['unencoded'],
                                    'missing' => $packet['missing'],
                                    'returned' => $packet['returned'],
                                ], JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                        'response_format' => ['type' => 'json_object'],
                    ]);

                if (! $response->successful()) {
                    Log::warning('Encoder packet AI recommendation failed.', ['status' => $response->status()]);

                    return $fallback;
                }

                $content = data_get($response->json(), 'choices.0.message.content');
                $decoded = json_decode((string) $content, true);
                $hint = is_array($decoded) ? ($decoded['hint'] ?? null) : null;

                return is_string($hint) && $hint !== '' ? $hint : $fallback;
            } catch (\Throwable $e) {
                Log::warning('Encoder packet AI recommendation failed.', ['message' => $e->getMessage()]);

                return $fallback;
            }
        });
    }

    /**
     * Optional OpenAI rewrite of the catalog hint. Falls back to catalog text.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, string> FI code => hint
     */
    public static function polishHints(array $rows): array
    {
        $key = (string) config('services.openai.key');
        if ($key === '') {
            return [];
        }

        $payload = collect($rows)->map(fn (array $row) => [
            'code' => $row['code'],
            'title' => $row['title'],
            'answer' => $row['answer'],
            'missing' => $row['ai']['missing'] ?? [],
            'submit' => $row['ai']['submit'] ?? [],
        ])->all();

        try {
            $response = Http::timeout(25)
                ->connectTimeout(15)
                ->retry(2, 800)
                ->withToken($key)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.2,
                    'max_tokens' => 700,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You help DepEd SGC FAT encoders. Recommend only what MOV files are missing or must be submitted. Never decide Yes or No. Never say the SGC is Functional. Keep each hint to 2 short sentences. Return JSON object keyed by FI code (FI1..FI12) with string hints.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode(['indicators' => $payload], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                return [];
            }

            $content = data_get($response->json(), 'choices.0.message.content');
            $decoded = json_decode((string) $content, true);
            if (! is_array($decoded)) {
                return [];
            }

            $hints = $decoded['hints'] ?? $decoded;
            if (! is_array($hints)) {
                return [];
            }

            $out = [];
            foreach ($hints as $code => $hint) {
                if (is_string($hint) && $hint !== '') {
                    $out[(string) $code] = $hint;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('Encoder AI recommendation failed.', ['message' => $e->getMessage()]);

            return [];
        }
    }

    public static function enabled(): bool
    {
        return filled(config('services.openai.key'));
    }

    private static function wantsWalkthrough(string $message): bool
    {
        return (bool) preg_match('/walk\s*(you\s*)?through|walkthrough|walktrough/i', $message);
    }

    /**
     * @param  array{hint?: string, next?: string, unencoded?: list<string>, missing?: list<string>, returned?: list<string>}  $packet
     */
    private static function walkthroughText(array $packet): string
    {
        $steps = [];
        $n = 1;
        $returned = $packet['returned'] ?? [];
        $unencoded = $packet['unencoded'] ?? [];
        $missing = $packet['missing'] ?? [];

        if ($returned !== []) {
            $steps[] = $n++.'. Replace returned MOVs first: '.implode('; ', array_slice($returned, 0, 3)).'. Open MOV files and replace only that slot.';
        }

        if ($unencoded !== []) {
            $steps[] = $n++.'. Open My assessment. Encode Yes or No on each primary FI. Start with '.$unencoded[0].'. I will not encode Yes or No for you.';
            $steps[] = $n++.'. If Yes, tap Open on the official Word template, download it, fill the blanks in Word, then Choose file or Reuse copy on that slot.';
        } elseif ($missing !== []) {
            $steps[] = $n++.'. Encoding is done. Upload the missing Minimum MOVs on MOV files: '.implode('; ', array_slice($missing, 0, 4)).'.';
            $steps[] = $n++.'. Open the official template on the slot, fill it, then Choose file or Reuse copy.';
        } else {
            $steps[] = $n++.'. Encoding and Minimum MOVs look ready. Ask the School Head to certify QA on Submit, then send the packet to Division.';
        }

        $steps[] = $n++.'. After submit, wait for Division. If a file is returned, replace only that file, certify QA again, and resubmit.';

        return "Yes. Here is a short walkthrough for this packet:\n\n".implode("\n", $steps);
    }
}
