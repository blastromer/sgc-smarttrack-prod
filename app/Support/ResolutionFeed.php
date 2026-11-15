<?php

namespace App\Support;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class ResolutionFeed
{
    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public const WHEREAS_COUNT = 9;

    public const RESOLVED_COUNT = 2;

    /**
     * Ask the model for a structured SGC resolution the school can edit.
     *
     * @return array{subject: string, whereas: list<string>, resolved: list<string>, day: string, month: string, year: string}|null
     */
    public static function draft(User $user, string $slotTitle): ?array
    {
        if (! EncoderRecommendation::enabled()) {
            return null;
        }

        $form = FormFill::fields($user);
        $pattern = AiPattern::text('resolution');
        $fingerprint = hash('sha256', (string) json_encode([
            $user->school_code,
            $slotTitle,
            $form,
            $pattern,
        ]));
        $key = 'sgc:resolution-feed:'.$fingerprint;
        $cached = Cache::get($key);
        if (is_array($cached) && self::valid($cached)) {
            return $cached;
        }

        $draft = self::ask($user, $slotTitle, $form, $pattern);
        if ($draft === null) {
            return null;
        }

        Cache::put($key, $draft, 86400);

        return $draft;
    }

    /**
     * Write a structured resolution draft into the official resolution form by position.
     *
     * @param  array{subject: string, whereas: list<string>, resolved: list<string>, day: string, month: string, year: string}  $draft
     */
    public static function apply(string $docxPath, array $draft): void
    {
        if (! self::valid($draft)) {
            return;
        }

        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if (! is_string($xml)) {
            $zip->close();

            return;
        }

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        if (! $dom->loadXML($xml)) {
            $zip->close();

            return;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS);

        $whereasIndex = 0;
        $resolvedIndex = 0;

        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }

            $text = self::plain($xpath, $paragraph);
            if ($text === '') {
                continue;
            }

            if (str_contains($text, '[State the Subject]') && preg_match('/A\s+RESOLUTION\s+ON/iu', $text)) {
                self::writeParagraph($xpath, $paragraph, 'A RESOLUTION ON '.$draft['subject']);
                continue;
            }

            if (str_contains($text, '[State the Subject]') && preg_match('/^WHEREAS,/iu', $text)) {
                $clause = $draft['whereas'][$whereasIndex] ?? null;
                if (is_string($clause) && $clause !== '') {
                    self::writeParagraph($xpath, $paragraph, 'WHEREAS, '.$clause);
                    $whereasIndex++;
                }
                continue;
            }

            if (str_contains($text, '[State details]') && preg_match('/RESOLVED\s+AS\s+IT\s+IS\s+HEREBY\s+RESOLVED/iu', $text)) {
                $detail = $draft['resolved'][$resolvedIndex] ?? null;
                if (is_string($detail) && $detail !== '') {
                    self::writeParagraph($xpath, $paragraph, 'RESOLVED AS IT IS HEREBY RESOLVED THAT '.$detail);
                    $resolvedIndex++;
                }
                continue;
            }

            if (str_contains($text, '[State details]') && preg_match('/RESOLVED\s+FURTHER/iu', $text)) {
                $detail = $draft['resolved'][$resolvedIndex] ?? null;
                if (is_string($detail) && $detail !== '') {
                    self::writeParagraph($xpath, $paragraph, 'RESOLVED FURTHER THAT '.$detail);
                    $resolvedIndex++;
                }
                continue;
            }

            if (str_contains($text, '[day]') || str_contains($text, '[month]') || str_contains($text, '[year]')) {
                $updated = str_replace(
                    ['[day]', '[month]', '[year]'],
                    [$draft['day'], $draft['month'], $draft['year']],
                    $text
                );
                self::writeParagraph($xpath, $paragraph, $updated);
            }
        }

        $zip->addFromString('word/document.xml', $dom->saveXML() ?: $xml);
        $zip->close();
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public static function valid(array $draft): bool
    {
        if (! is_string($draft['subject'] ?? null) || trim($draft['subject']) === '') {
            return false;
        }
        if (! is_array($draft['whereas'] ?? null) || count($draft['whereas']) < self::WHEREAS_COUNT) {
            return false;
        }
        if (! is_array($draft['resolved'] ?? null) || count($draft['resolved']) < self::RESOLVED_COUNT) {
            return false;
        }
        foreach (['day', 'month', 'year'] as $key) {
            if (! is_string($draft[$key] ?? null) || trim($draft[$key]) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $form
     * @return array{subject: string, whereas: list<string>, resolved: list<string>, day: string, month: string, year: string}|null
     */
    private static function ask(User $user, string $slotTitle, array $form, ?string $pattern): ?array
    {
        try {
            $response = Http::timeout(40)
                ->connectTimeout(8)
                ->withToken((string) config('services.openai.key'))
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'max_completion_tokens' => 1600,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You write a DepEd School Governance Council resolution the school can edit. Follow the pattern sample structure, sequence, and level of detail, but write for the current school. Do not copy the sample school name, school ID, people, address, region, division, dates, or projects. Subject is one short uppercase-ready resolution title without the words "A RESOLUTION ON". Each WHEREAS item is one complete clause without the word WHEREAS. Each resolved item is one complete clause without RESOLVED wording. Use the school facts when present. Do not invent personal names, signatures, phone numbers, or email addresses. Do not encode Yes or No. Do not say the SGC is Functional. Return JSON {"subject":"","whereas":["",""],"resolved":["",""],"day":"","month":"","year":""} with exactly '.self::WHEREAS_COUNT.' whereas items and exactly '.self::RESOLVED_COUNT.' resolved items.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'template' => 'SGC Resolution',
                                'kind' => 'resolution',
                                'slot' => $slotTitle,
                                'need' => [
                                    'whereas_count' => self::WHEREAS_COUNT,
                                    'resolved_count' => self::RESOLVED_COUNT,
                                ],
                                'form' => $form,
                                'pattern' => $pattern,
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                Log::warning('Resolution AI fill failed.', ['status' => $response->status()]);

                return null;
            }

            $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content'), true);
            if (! is_array($decoded)) {
                return null;
            }

            return self::normalize($decoded, $form);
        } catch (\Throwable $e) {
            Log::warning('Resolution AI fill failed.', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<string, string>  $form
     * @return array{subject: string, whereas: list<string>, resolved: list<string>, day: string, month: string, year: string}|null
     */
    private static function normalize(array $decoded, array $form): ?array
    {
        $subject = self::clean(is_string($decoded['subject'] ?? null) ? $decoded['subject'] : '');
        $subject = preg_replace('/^A\s+RESOLUTION\s+ON\s+/iu', '', $subject) ?? $subject;
        $subject = trim($subject);
        if ($subject === '') {
            return null;
        }

        $whereas = self::stringList($decoded['whereas'] ?? null, self::WHEREAS_COUNT, 'whereas');
        $resolved = self::stringList($decoded['resolved'] ?? null, self::RESOLVED_COUNT, 'resolved');
        if ($whereas === [] || $resolved === []) {
            return null;
        }

        $day = self::clean(is_string($decoded['day'] ?? null) ? $decoded['day'] : '');
        $month = self::clean(is_string($decoded['month'] ?? null) ? $decoded['month'] : '');
        $year = self::clean(is_string($decoded['year'] ?? null) ? $decoded['year'] : '');

        if ($day === '' || $month === '' || $year === '') {
            $fallback = self::dateFallback($form);
            $day = $day !== '' ? $day : $fallback['day'];
            $month = $month !== '' ? $month : $fallback['month'];
            $year = $year !== '' ? $year : $fallback['year'];
        }

        $draft = [
            'subject' => $subject,
            'whereas' => $whereas,
            'resolved' => $resolved,
            'day' => $day,
            'month' => $month,
            'year' => $year,
        ];

        return self::valid($draft) ? $draft : null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, int $need, string $kind): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $row) {
            if (! is_string($row)) {
                continue;
            }
            $text = self::clean($row);
            if ($kind === 'whereas') {
                $text = preg_replace('/^WHEREAS,?\s*/iu', '', $text) ?? $text;
            }
            if ($kind === 'resolved') {
                $text = preg_replace('/^RESOLVED(\s+(AS IT IS HEREBY RESOLVED|FURTHER))?(\s+THAT)?\s*/iu', '', $text) ?? $text;
            }
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $out[] = $text;
            if (count($out) >= $need) {
                break;
            }
        }

        return count($out) >= $need ? array_slice($out, 0, $need) : [];
    }

    /**
     * @param  array<string, string>  $form
     * @return array{day: string, month: string, year: string}
     */
    private static function dateFallback(array $form): array
    {
        $raw = $form['Meeting date'] ?? $form['Date prepared'] ?? now()->format('F j, Y');
        $time = strtotime($raw);
        if ($time === false) {
            $time = time();
        }

        return [
            'day' => date('j', $time),
            'month' => date('F', $time),
            'year' => date('Y', $time),
        ];
    }

    private static function clean(string $value): string
    {
        $value = preg_replace("/[ \t]+/", ' ', $value) ?? $value;

        return trim($value);
    }

    private static function plain(DOMXPath $xpath, DOMElement $paragraph): string
    {
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $node) {
            $text .= $node->textContent;
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private static function writeParagraph(DOMXPath $xpath, DOMElement $paragraph, string $text): void
    {
        $texts = [];
        foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $texts[] = $node;
            }
        }

        if ($texts === []) {
            $document = $paragraph->ownerDocument;
            if ($document === null) {
                return;
            }
            $run = $document->createElementNS(self::NS, 'w:r');
            $node = $document->createElementNS(self::NS, 'w:t');
            $node->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $node->textContent = $text;
            $run->appendChild($node);
            $paragraph->appendChild($run);

            return;
        }

        $texts[0]->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $texts[0]->textContent = $text;
        for ($i = 1; $i < count($texts); $i++) {
            $texts[$i]->textContent = '';
        }
    }
}
