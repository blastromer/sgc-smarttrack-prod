<?php

namespace App\Support;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

class OrgChartFeed
{
    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Stamp Form-data officers into the official org-chart drawing text boxes.
     */
    public static function apply(string $docxPath, User $user): void
    {
        $labels = self::labels($user);
        if ($labels === []) {
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

        foreach ($xpath->query('//w:txbxContent') ?: [] as $box) {
            if (! $box instanceof DOMElement) {
                continue;
            }
            $role = self::normalize(self::plain($xpath, $box));
            if ($role === '' || ! isset($labels[$role])) {
                continue;
            }
            self::writeBox($xpath, $box, $labels[$role]['name'], $labels[$role]['role']);
        }

        $zip->addFromString('word/document.xml', $dom->saveXML() ?: $xml);
        $zip->close();
    }

    /**
     * @return array<string, array{name: string, role: string}>
     */
    public static function labels(User $user): array
    {
        $v = FormFill::composed($user);

        $map = [
            'School Principal' => [
                'name' => trim((string) ($v['school_head_name'] ?? '')),
                'role' => 'School Principal',
            ],
            'Designated Co-Chairperson' => [
                'name' => trim((string) ($v['co_chair_designated'] ?? '')),
                'role' => 'Designated Co-Chairperson',
            ],
            'Elected Co-Chairperson' => [
                'name' => trim((string) ($v['co_chair_elected'] ?? '')),
                'role' => 'Elected Co-Chairperson',
            ],
            'Elected Secretary' => [
                'name' => trim((string) ($v['secretary_name'] ?? '')),
                'role' => 'Elected Secretary',
            ],
        ];

        $out = [];
        foreach ($map as $key => $row) {
            $out[$key] = $row;
        }

        return $out;
    }

    private static function normalize(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function plain(DOMXPath $xpath, DOMElement $box): string
    {
        $text = '';
        foreach ($xpath->query('.//w:t', $box) ?: [] as $node) {
            $text .= $node->textContent;
        }

        return $text;
    }

    private static function writeBox(DOMXPath $xpath, DOMElement $box, string $name, string $role): void
    {
        $paragraphs = [];
        foreach ($xpath->query('./w:p', $box) ?: [] as $paragraph) {
            if ($paragraph instanceof DOMElement) {
                $paragraphs[] = $paragraph;
            }
        }

        if ($paragraphs === []) {
            return;
        }

        $lines = $name !== '' ? [$name, $role] : [$role];
        while (count($paragraphs) < count($lines)) {
            $clone = $paragraphs[0]->cloneNode(true);
            if (! $clone instanceof DOMElement) {
                break;
            }
            $box->appendChild($clone);
            $paragraphs[] = $clone;
        }

        foreach ($paragraphs as $index => $paragraph) {
            $line = $lines[$index] ?? '';
            self::writeParagraph($xpath, $paragraph, $line);
        }
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
            if ($text === '') {
                return;
            }
            $document = $paragraph->ownerDocument;
            if ($document === null) {
                return;
            }
            $run = $document->createElementNS(self::NS, 'w:r');
            $node = $document->createElementNS(self::NS, 'w:t');
            $node->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $node->appendChild($document->createTextNode($text));
            $run->appendChild($node);
            $paragraph->appendChild($run);

            return;
        }

        $texts[0]->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        while ($texts[0]->firstChild) {
            $texts[0]->removeChild($texts[0]->firstChild);
        }
        $document = $texts[0]->ownerDocument;
        if ($document !== null) {
            $texts[0]->appendChild($document->createTextNode($text));
        }
        for ($i = 1; $i < count($texts); $i++) {
            while ($texts[$i]->firstChild) {
                $texts[$i]->removeChild($texts[$i]->firstChild);
            }
        }
    }
}
