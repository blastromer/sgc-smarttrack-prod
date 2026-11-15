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

class ConceptFeed
{
    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public const OBJECTIVE_COUNT = 3;

    public const ACTIVITY_COUNT = 3;

    public const ACCOMPLISHMENT_COUNT = 3;

    public const INDICATOR_COUNT = 3;

    public const BUDGET_COUNT = 3;

    /**
     * Ask the model for a full sample concept note / project brief.
     *
     * @return array{
     *   title: string,
     *   supervisor: string,
     *   organization: string,
     *   start_date: string,
     *   end_date: string,
     *   background: string,
     *   objectives: list<string>,
     *   activities: list<string>,
     *   accomplishments: list<string>,
     *   indicators: list<string>,
     *   budget: list<array{item: string, quantity: string, unit_price: string, total: string}>
     * }|null
     */
    public static function draft(User $user, string $slotTitle): ?array
    {
        if (! EncoderRecommendation::enabled()) {
            return null;
        }

        $form = FormFill::fields($user);
        $pattern = AiPattern::text('concept');
        $fingerprint = hash('sha256', (string) json_encode([
            $user->school_code,
            $slotTitle,
            $form,
            $pattern,
        ]));
        $key = 'sgc:concept-feed:'.$fingerprint;
        $cached = Cache::get($key);
        if (is_array($cached) && self::valid($cached)) {
            return $cached;
        }

        $draft = self::ask($slotTitle, $form, $pattern);
        if ($draft === null) {
            return null;
        }

        Cache::put($key, $draft, 86400);

        return $draft;
    }

    /**
     * @param  array{
     *   title: string,
     *   supervisor: string,
     *   organization: string,
     *   start_date: string,
     *   end_date: string,
     *   background: string,
     *   objectives: list<string>,
     *   activities: list<string>,
     *   accomplishments: list<string>,
     *   indicators: list<string>,
     *   budget: list<array{item: string, quantity: string, unit_price: string, total: string}>
     * }  $draft
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

        self::fillHeaderTable($xpath, $draft);
        self::fillBudgetTable($xpath, $draft['budget']);
        self::fillParagraphs($xpath, $draft);

        $zip->addFromString('word/document.xml', $dom->saveXML() ?: $xml);
        $zip->close();
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public static function valid(array $draft): bool
    {
        foreach (['title', 'supervisor', 'organization', 'start_date', 'end_date', 'background'] as $key) {
            if (! is_string($draft[$key] ?? null) || trim($draft[$key]) === '') {
                return false;
            }
        }

        foreach ([
            'objectives' => self::OBJECTIVE_COUNT,
            'activities' => self::ACTIVITY_COUNT,
            'accomplishments' => self::ACCOMPLISHMENT_COUNT,
            'indicators' => self::INDICATOR_COUNT,
        ] as $key => $need) {
            if (! is_array($draft[$key] ?? null) || count($draft[$key]) < $need) {
                return false;
            }
            foreach ($draft[$key] as $value) {
                if (! is_string($value) || trim($value) === '') {
                    return false;
                }
            }
        }

        if (! is_array($draft['budget'] ?? null) || count($draft['budget']) < self::BUDGET_COUNT) {
            return false;
        }
        foreach ($draft['budget'] as $row) {
            if (! is_array($row)) {
                return false;
            }
            foreach (['item', 'quantity', 'unit_price', 'total'] as $key) {
                if (! is_string($row[$key] ?? null) || trim($row[$key]) === '') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $form
     * @return array{
     *   title: string,
     *   supervisor: string,
     *   organization: string,
     *   start_date: string,
     *   end_date: string,
     *   background: string,
     *   objectives: list<string>,
     *   activities: list<string>,
     *   accomplishments: list<string>,
     *   indicators: list<string>,
     *   budget: list<array{item: string, quantity: string, unit_price: string, total: string}>
     * }|null
     */
    private static function ask(string $slotTitle, array $form, ?string $pattern): ?array
    {
        try {
            $response = Http::timeout(45)
                ->connectTimeout(8)
                ->withToken((string) config('services.openai.key'))
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'max_completion_tokens' => 1800,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You write a DepEd School Governance Council project concept note the school can edit. Follow the pattern sample structure and level of detail, but write for the current school. Do not copy the sample school name, school ID, people, address, region, division, dates, or project names. Supervisor and organization may use role titles such as School Head or School Governance Council — do not invent personal names, signatures, phone numbers, or email addresses. Background is 2-4 sentences. Objectives, activities, accomplishments, and indicators are short lines without Obj/MA/EA/IA labels. Budget rows use simple school-activity items with peso amounts as plain text. Do not encode Yes or No. Do not say the SGC is Functional. Return JSON {"title":"","supervisor":"","organization":"","start_date":"","end_date":"","background":"","objectives":["","",""],"activities":["","",""],"accomplishments":["","",""],"indicators":["","",""],"budget":[{"item":"","quantity":"","unit_price":"","total":""}]} with exactly 3 objectives, 3 activities, 3 accomplishments, 3 indicators, and 3 budget rows.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'template' => 'Concept note / project brief',
                                'kind' => 'concept',
                                'slot' => $slotTitle,
                                'need' => [
                                    'objectives' => self::OBJECTIVE_COUNT,
                                    'activities' => self::ACTIVITY_COUNT,
                                    'accomplishments' => self::ACCOMPLISHMENT_COUNT,
                                    'indicators' => self::INDICATOR_COUNT,
                                    'budget' => self::BUDGET_COUNT,
                                ],
                                'form' => $form,
                                'pattern' => $pattern,
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                Log::warning('Concept note AI fill failed.', ['status' => $response->status()]);

                return null;
            }

            $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content'), true);
            if (! is_array($decoded)) {
                return null;
            }

            return self::normalize($decoded, $form);
        } catch (\Throwable $e) {
            Log::warning('Concept note AI fill failed.', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<string, string>  $form
     * @return array{
     *   title: string,
     *   supervisor: string,
     *   organization: string,
     *   start_date: string,
     *   end_date: string,
     *   background: string,
     *   objectives: list<string>,
     *   activities: list<string>,
     *   accomplishments: list<string>,
     *   indicators: list<string>,
     *   budget: list<array{item: string, quantity: string, unit_price: string, total: string}>
     * }|null
     */
    private static function normalize(array $decoded, array $form): ?array
    {
        $draft = [
            'title' => self::clean($decoded['title'] ?? null),
            'supervisor' => self::clean($decoded['supervisor'] ?? null) ?: self::clean($form['School Head'] ?? null) ?: 'School Head',
            'organization' => self::clean($decoded['organization'] ?? null) ?: 'School Governance Council',
            'start_date' => self::clean($decoded['start_date'] ?? null),
            'end_date' => self::clean($decoded['end_date'] ?? null),
            'background' => self::clean($decoded['background'] ?? null),
            'objectives' => self::stringList($decoded['objectives'] ?? null, self::OBJECTIVE_COUNT, '/^Obj\d+\s*/iu'),
            'activities' => self::stringList($decoded['activities'] ?? null, self::ACTIVITY_COUNT, '/^MA\d+\s*/iu'),
            'accomplishments' => self::stringList($decoded['accomplishments'] ?? null, self::ACCOMPLISHMENT_COUNT, '/^EA\d+\s*/iu'),
            'indicators' => self::stringList($decoded['indicators'] ?? null, self::INDICATOR_COUNT, '/^IA\d+\s*/iu'),
            'budget' => self::budgetList($decoded['budget'] ?? null),
        ];

        return self::valid($draft) ? $draft : null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, int $need, string $strip): array
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
            $text = preg_replace($strip, '', $text) ?? $text;
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
     * @return list<array{item: string, quantity: string, unit_price: string, total: string}>
     */
    private static function budgetList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }
            $item = [
                'item' => self::clean($row['item'] ?? null),
                'quantity' => self::clean($row['quantity'] ?? null),
                'unit_price' => self::clean($row['unit_price'] ?? null),
                'total' => self::clean($row['total'] ?? null),
            ];
            if (in_array('', $item, true)) {
                continue;
            }
            $out[] = $item;
            if (count($out) >= self::BUDGET_COUNT) {
                break;
            }
        }

        return count($out) >= self::BUDGET_COUNT ? array_slice($out, 0, self::BUDGET_COUNT) : [];
    }

    private static function clean(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = preg_replace("/[ \t]+/", ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param  array{title: string, supervisor: string, organization: string, start_date: string, end_date: string}  $draft
     */
    private static function fillHeaderTable(DOMXPath $xpath, array $draft): void
    {
        foreach ($xpath->query('//w:tbl') ?: [] as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }
            $rows = self::tableRows($xpath, $table);
            if (count($rows) < 5) {
                continue;
            }
            $first = strtoupper(implode(' ', self::rowCellTexts($xpath, $rows[0])));
            if (! str_contains($first, 'PROJECT TITLE')) {
                continue;
            }

            $map = [
                'PROJECT TITLE' => $draft['title'],
                'PROJECT SUPERVISOR' => $draft['supervisor'],
                'ORGANIZATION' => $draft['organization'],
                'START DATE' => $draft['start_date'],
                'END DATE' => $draft['end_date'],
            ];
            foreach ($rows as $row) {
                $cells = self::rowCellTexts($xpath, $row);
                $label = strtoupper(trim($cells[0] ?? ''));
                if (isset($map[$label])) {
                    self::setCell($row, 1, $map[$label]);
                }
            }

            return;
        }
    }

    /**
     * @param  list<array{item: string, quantity: string, unit_price: string, total: string}>  $budget
     */
    private static function fillBudgetTable(DOMXPath $xpath, array $budget): void
    {
        foreach ($xpath->query('//w:tbl') ?: [] as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }
            $rows = self::tableRows($xpath, $table);
            if ($rows === []) {
                continue;
            }
            $header = strtoupper(implode(' ', self::rowCellTexts($xpath, $rows[0])));
            if (! str_contains($header, 'ITEM') || ! str_contains($header, 'QUANTITY')) {
                continue;
            }

            $data = array_values(array_slice($rows, 1));
            $last = $data[array_key_last($data)] ?? null;
            while ($last instanceof DOMElement && count($data) < count($budget)) {
                $clone = $last->cloneNode(true);
                if (! $clone instanceof DOMElement) {
                    break;
                }
                $table->appendChild($clone);
                $data[] = $clone;
            }

            $filled = 0;
            $grand = 0.0;
            foreach ($budget as $index => $row) {
                if (! isset($data[$index])) {
                    break;
                }
                self::setCell($data[$index], 0, $row['item']);
                self::setCell($data[$index], 1, $row['quantity']);
                self::setCell($data[$index], 2, $row['unit_price']);
                self::setCell($data[$index], 3, $row['total']);
                $grand += self::moneyAmount($row['total']);
                $filled++;
            }

            if ($filled > 0) {
                self::setCell($data[$filled - 1], 4, self::formatMoney($grand));
            }

            return;
        }
    }

    private static function moneyAmount(string $value): float
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', $value) ?? '';

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private static function formatMoney(float $amount): string
    {
        if (abs($amount - round($amount)) < 0.001) {
            return number_format($amount, 0, '.', ',');
        }

        return number_format($amount, 2, '.', ',');
    }

    /**
     * @param  array{
     *   background: string,
     *   objectives: list<string>,
     *   activities: list<string>,
     *   accomplishments: list<string>,
     *   indicators: list<string>
     * }  $draft
     */
    private static function fillParagraphs(DOMXPath $xpath, array $draft): void
    {
        $objectiveIndex = 0;
        $activityIndex = 0;
        $accomplishmentIndex = 0;
        $indicatorIndex = 0;

        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }
            $text = self::plain($xpath, $paragraph);
            if ($text === '') {
                continue;
            }

            if (str_contains($text, '[Insert exhaustive background')) {
                self::writeParagraph($xpath, $paragraph, $draft['background']);
                continue;
            }

            if (preg_match('/^Obj(\d+)$/u', $text, $m)) {
                $value = $draft['objectives'][$objectiveIndex] ?? null;
                if (is_string($value)) {
                    self::writeParagraph($xpath, $paragraph, ($objectiveIndex + 1).'. '.$value);
                    $objectiveIndex++;
                }
                continue;
            }

            if (preg_match('/^MA(\d+)$/u', $text, $m)) {
                $value = $draft['activities'][$activityIndex] ?? null;
                if (is_string($value)) {
                    self::writeParagraph($xpath, $paragraph, ($activityIndex + 1).'. '.$value);
                    $activityIndex++;
                }
                continue;
            }

            if (strcasecmp($text, 'Specific activity') === 0) {
                self::writeParagraph($xpath, $paragraph, '');
                continue;
            }

            if (preg_match('/^EA(\d+)$/u', $text)) {
                $value = $draft['accomplishments'][$accomplishmentIndex] ?? null;
                if (is_string($value)) {
                    self::writeParagraph($xpath, $paragraph, ($accomplishmentIndex + 1).'. '.$value);
                    $accomplishmentIndex++;
                }
                continue;
            }

            if (preg_match('/^IA(\d+)$/u', $text)) {
                $value = $draft['indicators'][$indicatorIndex] ?? null;
                if (is_string($value)) {
                    self::writeParagraph($xpath, $paragraph, ($indicatorIndex + 1).'. '.$value);
                    $indicatorIndex++;
                }
            }
        }
    }

    /**
     * @return list<DOMElement>
     */
    private static function tableRows(DOMXPath $xpath, DOMElement $table): array
    {
        $rows = [];
        foreach ($xpath->query('./w:tr', $table) ?: [] as $row) {
            if ($row instanceof DOMElement) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function rowCellTexts(DOMXPath $xpath, DOMElement $row): array
    {
        $cells = [];
        foreach ($xpath->query('./w:tc', $row) ?: [] as $cell) {
            if ($cell instanceof DOMElement) {
                $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? '');
            }
        }

        return $cells;
    }

    private static function setCell(DOMElement $row, int $index, string $text): void
    {
        $cells = [];
        foreach ($row->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'tc') {
                $cells[] = $child;
            }
        }
        if (! isset($cells[$index])) {
            return;
        }

        $cell = $cells[$index];
        $texts = $cell->getElementsByTagNameNS(self::NS, 't');
        if ($texts->length > 0 && $texts->item(0) instanceof DOMElement) {
            self::writeText($texts->item(0), $text);
            for ($i = $texts->length - 1; $i >= 1; $i--) {
                $texts->item($i)?->parentNode?->removeChild($texts->item($i));
            }

            return;
        }

        $document = $cell->ownerDocument;
        if ($document === null) {
            return;
        }
        $node = $document->createElementNS(self::NS, 'w:t');
        $node->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        self::writeText($node, $text);

        $runs = $cell->getElementsByTagNameNS(self::NS, 'r');
        if ($runs->length > 0) {
            $runs->item(0)?->appendChild($node);

            return;
        }

        $paragraphs = $cell->getElementsByTagNameNS(self::NS, 'p');
        $paragraph = $paragraphs->item(0);
        if (! $paragraph instanceof DOMElement) {
            $paragraph = $document->createElementNS(self::NS, 'w:p');
            $cell->appendChild($paragraph);
        }
        $run = $document->createElementNS(self::NS, 'w:r');
        $run->appendChild($node);
        $paragraph->appendChild($run);
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
            if ($document === null || $text === '') {
                return;
            }
            $run = $document->createElementNS(self::NS, 'w:r');
            $node = $document->createElementNS(self::NS, 'w:t');
            $node->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            self::writeText($node, $text);
            $run->appendChild($node);
            $paragraph->appendChild($run);

            return;
        }

        $texts[0]->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        self::writeText($texts[0], $text);
        for ($i = 1; $i < count($texts); $i++) {
            self::writeText($texts[$i], '');
        }
    }

    private static function writeText(DOMElement $node, string $text): void
    {
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $document = $node->ownerDocument;
        if ($document === null) {
            return;
        }
        $node->appendChild($document->createTextNode($text));
    }
}
