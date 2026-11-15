<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

class MinutesFeed
{
    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Write the sample minutes attendance and agenda into the official minutes form.
     */
    public static function apply(string $docxPath): void
    {
        $people = self::attendance();
        $agenda = self::agenda();
        if ($people['present'] === [] && $people['absent'] === [] && $agenda === []) {
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
        $plain = [];
        $sheet = null;
        foreach ($xpath->query('//w:tbl') ?: [] as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }
            $headers = self::headers($xpath, $table);
            if (! self::has($headers, 'name') || ! self::has($headers, 'position') || ! self::has($headers, 'organization')) {
                continue;
            }
            if (self::has($headers, 'signature')) {
                $sheet = $table;
                continue;
            }
            $plain[] = $table;
        }

        if (isset($plain[0])) {
            self::fill($xpath, $plain[0], $people['present'], false);
        }
        if (isset($plain[1])) {
            self::fill($xpath, $plain[1], $people['absent'], true);
        }
        if ($sheet instanceof DOMElement) {
            self::fill($xpath, $sheet, $people['present'], false);
        }
        foreach ($xpath->query('//w:tbl') ?: [] as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }
            $headers = self::headers($xpath, $table);
            if (self::has($headers, 'agenda') && self::has($headers, 'discussion')) {
                self::fillAgenda($xpath, $table, $agenda);
                break;
            }
        }

        $zip->addFromString('word/document.xml', $dom->saveXML() ?: $xml);
        $zip->close();
    }

    /**
     * @return array{present: list<array{name: string, position: string, organization: string}>, absent: list<array{name: string, position: string, organization: string}>}
     */
    public static function attendance(): array
    {
        $path = resource_path('ai-patterns/minutes-feed.docx');
        $present = [];
        $absent = [];
        if (! is_file($path)) {
            return ['present' => $present, 'absent' => $absent];
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return ['present' => $present, 'absent' => $absent];
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml)) {
            return ['present' => $present, 'absent' => $absent];
        }

        $dom = new DOMDocument;
        if (! $dom->loadXML($xml)) {
            return ['present' => $present, 'absent' => $absent];
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS);

        foreach ($xpath->query('//w:tbl') ?: [] as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }
            $rows = self::rows($xpath, $table);
            if ($rows === []) {
                continue;
            }
            $headers = array_map(fn (array $cells) => implode(' ', $cells), [$rows[0]]);
            $header = strtolower($headers[0]);
            if (! str_contains($header, 'name') || ! str_contains($header, 'status')) {
                continue;
            }
            $name = self::index($rows[0], 'name');
            $position = self::index($rows[0], 'role') ?? self::index($rows[0], 'position');
            $organization = self::index($rows[0], 'sector') ?? self::index($rows[0], 'organization');
            $status = self::index($rows[0], 'status');
            if ($name === null || $position === null || $organization === null || $status === null) {
                continue;
            }
            foreach (array_slice($rows, 1) as $row) {
                $person = [
                    'name' => trim($row[$name] ?? ''),
                    'position' => trim($row[$position] ?? ''),
                    'organization' => trim($row[$organization] ?? ''),
                ];
                if ($person['name'] === '') {
                    continue;
                }
                $mark = strtolower($row[$status] ?? '');
                if (str_contains($mark, 'absent')) {
                    $absent[] = $person;
                } else {
                    $present[] = $person;
                }
            }
            break;
        }

        return ['present' => $present, 'absent' => $absent];
    }

    /**
     * @return list<array{topic: string, discussion: string, agreement: string}>
     */
    public static function agenda(): array
    {
        $rows = self::feedRows(function (string $header): bool {
            return str_contains($header, 'agenda') && ! str_contains($header, 'name');
        });
        if ($rows === []) {
            return [];
        }

        $topic = self::index($rows[0], 'agenda');
        $discussion = self::index($rows[0], 'discussion') ?? self::index($rows[0], 'deliberation');
        $agreement = self::index($rows[0], 'agreement') ?? self::index($rows[0], 'resolution');
        if ($topic === null || $discussion === null || $agreement === null) {
            return [];
        }

        $items = [];
        foreach (array_slice($rows, 1) as $row) {
            $item = [
                'topic' => trim($row[$topic] ?? ''),
                'discussion' => trim($row[$discussion] ?? ''),
                'agreement' => trim($row[$agreement] ?? ''),
            ];
            if ($item['topic'] === '' && $item['discussion'] === '' && $item['agreement'] === '') {
                continue;
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  callable(string): bool  $match
     * @return list<list<string>>
     */
    private static function feedRows(callable $match): array
    {
        $path = resource_path('ai-patterns/minutes-feed.docx');
        if (! is_file($path)) {
            return [];
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml)) {
            return [];
        }

        $dom = new DOMDocument;
        if (! $dom->loadXML($xml)) {
            return [];
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS);
        foreach ($xpath->query('//w:tbl') ?: [] as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }
            $rows = self::rows($xpath, $table);
            $header = strtolower(implode(' ', $rows[0] ?? []));
            if ($header !== '' && $match($header)) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param  list<array{topic: string, discussion: string, agreement: string}>  $items
     */
    private static function fillAgenda(DOMXPath $xpath, DOMElement $table, array $items): void
    {
        if ($items === []) {
            return;
        }

        $headers = self::headers($xpath, $table);
        $topic = self::index($headers, 'agenda');
        $discussion = self::index($headers, 'discussion');
        $agreement = self::index($headers, 'agreement') ?? self::index($headers, 'resolution');
        if ($topic === null || $discussion === null || $agreement === null) {
            return;
        }

        $rows = [];
        foreach ($xpath->query('./w:tr', $table) ?: [] as $row) {
            if ($row instanceof DOMElement) {
                $rows[] = $row;
            }
        }
        if (count($rows) < 2) {
            return;
        }

        $data = array_slice($rows, 1);
        $last = $data[array_key_last($data)];
        while (count($data) < count($items)) {
            $clone = $last->cloneNode(true);
            if (! $clone instanceof DOMElement) {
                break;
            }
            $table->appendChild($clone);
            $data[] = $clone;
        }

        foreach ($data as $index => $row) {
            $item = $items[$index] ?? ['topic' => '', 'discussion' => '', 'agreement' => ''];
            self::setCell($row, $topic, $item['topic']);
            self::setCell($row, $discussion, $item['discussion']);
            self::setCell($row, $agreement, $item['agreement']);
        }
    }

    /**
     * @param  list<array{name: string, position: string, organization: string}>  $people
     */
    private static function fill(DOMXPath $xpath, DOMElement $table, array $people, bool $noneIfEmpty): void
    {
        $headers = self::headers($xpath, $table);
        $name = self::index($headers, 'name');
        $position = self::index($headers, 'position');
        $organization = self::index($headers, 'organization');
        if ($name === null || $position === null || $organization === null) {
            return;
        }

        $rows = [];
        foreach ($xpath->query('./w:tr', $table) ?: [] as $row) {
            if ($row instanceof DOMElement) {
                $rows[] = $row;
            }
        }
        if (count($rows) < 2) {
            return;
        }

        $data = array_slice($rows, 1);
        if ($people === []) {
            if ($noneIfEmpty) {
                self::setCell($data[0], $name, 'None');
            }

            return;
        }

        $last = $data[array_key_last($data)];
        while (count($data) < count($people)) {
            $clone = $last->cloneNode(true);
            if (! $clone instanceof DOMElement) {
                break;
            }
            $table->appendChild($clone);
            $data[] = $clone;
        }

        foreach ($people as $index => $person) {
            self::setCell($data[$index], $name, $person['name']);
            self::setCell($data[$index], $position, $person['position']);
            self::setCell($data[$index], $organization, $person['organization']);
        }
    }

    /**
     * @return list<string>
     */
    private static function headers(DOMXPath $xpath, DOMElement $table): array
    {
        $rows = self::rows($xpath, $table);

        return $rows[0] ?? [];
    }

    /**
     * @param  list<string>  $headers
     */
    private static function has(array $headers, string $word): bool
    {
        return self::index($headers, $word) !== null;
    }

    /**
     * @param  list<string>  $headers
     */
    private static function index(array $headers, string $word): ?int
    {
        foreach ($headers as $index => $header) {
            if (str_contains(strtolower($header), $word)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return list<list<string>>
     */
    private static function rows(DOMXPath $xpath, DOMElement $table): array
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
                $texts->item($i)->parentNode?->removeChild($texts->item($i));
            }

            return;
        }

        $runs = $cell->getElementsByTagNameNS(self::NS, 'r');
        $document = $cell->ownerDocument;
        if ($document === null) {
            return;
        }
        $node = $document->createElementNS(self::NS, 'w:t');
        $node->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        self::writeText($node, $text);
        if ($runs->length > 0) {
            $runs->item(0)->appendChild($node);

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
