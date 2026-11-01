<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class MovDraft
{
    /**
     * Ask the model to fill the narrative blanks still left in a form. The app does not write that text.
     *
     * @param  list<string>  $blanks
     * @return array{replacements: array<string, string>, source: string}
     */
    public static function fill(User $user, string $kind, string $label, string $slotTitle, array $blanks): array
    {
        if ($blanks === [] || ! EncoderRecommendation::enabled()) {
            return ['replacements' => [], 'source' => 'form'];
        }

        $fingerprint = hash('sha256', (string) json_encode([
            $user->school_code,
            $kind,
            $label,
            $slotTitle,
            $blanks,
            FormFill::fields($user),
            AiPattern::text($kind),
        ]));
        $key = 'sgc:mov-draft:'.$fingerprint;
        $cached = Cache::get($key);
        if (is_array($cached) && $cached !== []) {
            return ['replacements' => $cached, 'source' => 'ai'];
        }

        $replacements = self::ask($user, $kind, $label, $slotTitle, $blanks) ?? [];
        if ($replacements !== []) {
            Cache::put($key, $replacements, 86400);
        }

        return [
            'replacements' => $replacements,
            'source' => $replacements === [] ? 'form' : 'ai',
        ];
    }

    /**
     * Prompts inside the official form that still need recommended wording.
     *
     * @return list<string>
     */
    public static function narrativeBlanks(string $docxPath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return [];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml)) {
            return [];
        }

        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        preg_match_all('/\[[^\[\]]{2,180}\]|Agenda[n]? #\d+/u', $text, $found);

        $blanks = [];
        foreach ($found[0] ?? [] as $blank) {
            $blank = trim((string) $blank);
            if (self::isNarrative($blank)) {
                $blanks[$blank] = $blank;
            }
        }

        return array_values($blanks);
    }

    public static function isNarrative(string $blank): bool
    {
        return (bool) preg_match('/^Agenda[n]? #\d+$/u', $blank)
            || (bool) preg_match('/^\[insert\b/iu', $blank);
    }

    /**
     * @param  list<string>  $blanks
     * @return array<string, string>|null
     */
    private static function ask(User $user, string $kind, string $label, string $slotTitle, array $blanks): ?array
    {
        try {
            $response = Http::timeout(25)
                ->connectTimeout(8)
                ->withToken((string) config('services.openai.key'))
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'max_completion_tokens' => 900,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You fill the remaining blank prompts in a DepEd SGC means-of-verification Word form. Recommend wording the school can edit. Use the school facts when they are present. When a pattern sample is included, follow its structure, sequence, and level of detail, but write for the current school. Do not copy the sample school name, school ID, people, address, region, division, dates, or projects. For a missing venue, subject, date, purpose, or agenda item, write a realistic recommendation for that form. Each agenda value is one short line and must not repeat the words Agenda #. Do not invent personal names, signatures, phone numbers, or email addresses. Do not encode Yes or No. Do not say the SGC is Functional. Return JSON {"replacements":{"exact blank":"recommended text"}}. Every key must be copied exactly from the blanks list.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'template' => $label,
                                'kind' => $kind,
                                'slot' => $slotTitle,
                                'blanks' => $blanks,
                                'form' => FormFill::fields($user),
                                'pattern' => AiPattern::text($kind),
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                Log::warning('MOV draft AI fill failed.', ['status' => $response->status()]);

                return null;
            }

            $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content'), true);
            $rows = is_array($decoded) ? ($decoded['replacements'] ?? null) : null;
            if (! is_array($rows)) {
                return null;
            }

            $allowed = array_fill_keys($blanks, true);
            $replacements = [];
            foreach ($rows as $blank => $value) {
                if (! is_string($blank) || ! isset($allowed[$blank]) || ! is_string($value) || trim($value) === '') {
                    continue;
                }
                $replacements[$blank] = trim($value);
            }

            return $replacements === [] ? null : $replacements;
        } catch (\Throwable $e) {
            Log::warning('MOV draft AI fill failed.', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
