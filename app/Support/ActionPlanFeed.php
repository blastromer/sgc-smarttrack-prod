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

class ActionPlanFeed
{
    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public const ROW_COUNT = 5;

    /**
     * Ask the model for sample SGC action / advocacy plan rows.
     *
     * @return list<array{area: string, need: string, objective: string, activities: string, outputs: string, timeframe: string, persons: string, materials: string}>|null
     */
    public static function draft(User $user, string $slotTitle): ?array
    {
        if (! EncoderRecommendation::enabled()) {
            return null;
        }

        $form = FormFill::fields($user);
        $fingerprint = hash('sha256', (string) json_encode([
            $user->school_code,
            $slotTitle,
            $form,
            self::ROW_COUNT,
        ]));
        $key = 'sgc:action-plan-feed:'.$fingerprint;
        $cached = Cache::get($key);
        if (is_array($cached) && self::valid($cached)) {
            return $cached;
        }

        $draft = self::ask($slotTitle, $form);
        if ($draft === null) {
            return null;
        }

        Cache::put($key, $draft, 86400);

        return $draft;
    }

    /**
     * @param  list<array{area: string, need: string, objective: string, activities: string, outputs: string, timeframe: string, persons: string, materials: string}>  $rows
     */
    public static function apply(string $docxPath, array $rows): void
    {
        if (! self::valid($rows)) {
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
        $table = self::findTable($xpath);
        if ($table === null) {
            $zip->close();

            return;
        }

        $dataRows = self::dataRows($xpath, $table);
        if ($dataRows === []) {
            $zip->close();

            return;
        }

        $last = $dataRows[array_key_last($dataRows)];
        while (count($dataRows) < count($rows)) {
            $clone = $last->cloneNode(true);
            if (! $clone instanceof DOMElement) {
                break;
            }
            $table->appendChild($clone);
            $dataRows[] = $clone;
        }

        foreach ($rows as $index => $row) {
            if (! isset($dataRows[$index])) {
                break;
            }
            self::setCell($dataRows[$index], 0, $row['area']);
            self::setCell($dataRows[$index], 1, $row['need']);
            self::setCell($dataRows[$index], 2, $row['objective']);
            self::setCell($dataRows[$index], 3, $row['activities']);
            self::setCell($dataRows[$index], 4, $row['outputs']);
            self::setCell($dataRows[$index], 5, $row['timeframe']);
            self::setCell($dataRows[$index], 6, $row['persons']);
            self::setCell($dataRows[$index], 7, $row['materials']);
        }

        $zip->addFromString('word/document.xml', $dom->saveXML() ?: $xml);
        $zip->close();
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    public static function valid(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                return false;
            }
            foreach (['area', 'need', 'objective', 'activities', 'outputs', 'timeframe', 'persons', 'materials'] as $key) {
                if (! is_string($row[$key] ?? null) || trim($row[$key]) === '') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $form
     * @return list<array{area: string, need: string, objective: string, activities: string, outputs: string, timeframe: string, persons: string, materials: string}>|null
     */
    private static function ask(string $slotTitle, array $form): ?array
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
                            'content' => 'You write sample DepEd School Governance Council action / advocacy plan rows the school can edit. Use school facts when present. Keep each cell short and practical for an SGC action plan table. Roles may use generic titles such as SGC Officers, School Head, or PTA Representatives — do not invent personal names, signatures, phone numbers, or email addresses. Do not encode Yes or No. Do not say the SGC is Functional. Return JSON {"rows":[{"area":"","need":"","objective":"","activities":"","outputs":"","timeframe":"","persons":"","materials":""}]} with exactly '.self::ROW_COUNT.' rows.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'template' => 'Action / advocacy plan',
                                'kind' => 'action_plan',
                                'slot' => $slotTitle,
                                'need' => ['row_count' => self::ROW_COUNT],
                                'columns' => [
                                    'area' => 'AREAS OF CONCERN',
                                    'need' => 'PRIORITIZED NEEDS',
                                    'objective' => 'OBJECTIVE',
                                    'activities' => 'ACTIVITIES',
                                    'outputs' => 'EXPECTED OUTPUTS',
                                    'timeframe' => 'TIME FRAME',
                                    'persons' => 'Persons Involved',
                                    'materials' => 'Materials',
                                ],
                                'form' => $form,
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                Log::warning('Action plan AI fill failed.', ['status' => $response->status()]);

                return null;
            }

            $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content'), true);
            if (! is_array($decoded)) {
                return null;
            }

            return self::normalize($decoded['rows'] ?? null);
        } catch (\Throwable $e) {
            Log::warning('Action plan AI fill failed.', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return list<array{area: string, need: string, objective: string, activities: string, outputs: string, timeframe: string, persons: string, materials: string}>|null
     */
    private static function normalize(mixed $rows): ?array
    {
        if (! is_array($rows)) {
            return null;
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $item = [
                'area' => self::clean($row['area'] ?? null),
                'need' => self::clean($row['need'] ?? null),
                'objective' => self::clean($row['objective'] ?? null),
                'activities' => self::clean($row['activities'] ?? null),
                'outputs' => self::clean($row['outputs'] ?? null),
                'timeframe' => self::clean($row['timeframe'] ?? null),
                'persons' => self::clean($row['persons'] ?? null),
                'materials' => self::clean($row['materials'] ?? null),
            ];
            if (in_array('', $item, true)) {
                continue;
            }
            $out[] = $item;
            if (count($out) >= self::ROW_COUNT) {
                break;
            }
        }

        return count($out) >= self::ROW_COUNT ? array_slice($out, 0, self::ROW_COUNT) : null;
    }

    private static function clean(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = preg_replace("/[ \t]+/", ' ', $value) ?? $value;

        return trim($value);
    }

    private static function findTable(DOMXPath $xpath): ?DOMElement
    {
        foreach ($xpath->query('//w:tbl') ?: [] as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }
            $header = strtolower(implode(' ', self::rowCells($xpath, $table)[0] ?? []));
            if (str_contains($header, 'areas of concern') && str_contains($header, 'prioritized')) {
                return $table;
            }
        }

        return null;
    }

    /**
     * @return list<DOMElement>
     */
    private static function dataRows(DOMXPath $xpath, DOMElement $table): array
    {
        $rows = [];
        foreach ($xpath->query('./w:tr', $table) ?: [] as $row) {
            if ($row instanceof DOMElement) {
                $rows[] = $row;
            }
        }
        if (count($rows) < 3) {
            return [];
        }

        // Row 0 = column headers, row 1 = Persons Involved / Materials sub-headers.
        return array_values(array_slice($rows, 2));
    }

    /**
     * @return list<list<string>>
     */
    private static function rowCells(DOMXPath $xpath, DOMElement $table): array
    {
        $rows = [];
        foreach ($xpath->query('./w:tr', $table) ?: [] as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }
            $cells = [];
            foreach ($xpath->query('./w:tc', $row) ?: [] as $cell) {
                if ($cell instanceof DOMElement) {
                    $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? '');
                }
            }
            $rows[] = $cells;
        }

        return $rows;
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
