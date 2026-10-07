<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use RuntimeException;
use ZipArchive;

class DocxHtmlPreview
{
    /**
     * Convert an official DOCX template into sanitised HTML for an in-app reader.
     */
    public static function fromPath(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Cannot open this Word template.');
        }

        $parts = [];
        foreach (['word/header1.xml', 'word/header2.xml', 'word/header3.xml'] as $header) {
            $xml = $zip->getFromName($header);
            if ($xml !== false) {
                $parts[] = self::convertPart($zip, $xml, $header);
            }
        }

        $document = $zip->getFromName('word/document.xml');
        if ($document === false) {
            $zip->close();
            throw new RuntimeException('This Word template has no document body.');
        }
        $parts[] = self::convertPart($zip, $document, 'word/document.xml');

        foreach (['word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml'] as $footer) {
            $xml = $zip->getFromName($footer);
            if ($xml !== false) {
                $parts[] = self::convertPart($zip, $xml, $footer);
            }
        }

        $zip->close();

        $html = trim(implode('', $parts));

        return $html !== '' ? $html : '<p>This template has no readable text. Download it to open in Word.</p>';
    }

    private static function convertPart(ZipArchive $zip, string $xml, string $partName): string
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml)) {
            return '';
        }

        $images = self::imageMap($zip, $partName);
        $html = '';
        foreach ($dom->documentElement?->childNodes ?? [] as $node) {
            if ($node instanceof DOMElement) {
                $html .= self::block($node, $images);
            }
        }

        return $html;
    }

    /**
     * @return array<string, string>
     */
    private static function imageMap(ZipArchive $zip, string $partName): array
    {
        $relsName = self::relsPath($partName);
        $relsXml = $zip->getFromName($relsName);
        if ($relsXml === false) {
            return [];
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($relsXml)) {
            return [];
        }

        $baseDir = dirname($partName);
        $map = [];
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            if (! $rel instanceof DOMElement || ! str_contains($rel->getAttribute('Type'), '/image')) {
                continue;
            }

            $target = self::zipPath($baseDir, $rel->getAttribute('Target'));
            $bytes = $zip->getFromName($target);
            if ($bytes === false) {
                continue;
            }

            $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => null,
            };
            if ($mime === null) {
                continue;
            }

            $map[$rel->getAttribute('Id')] = 'data:'.$mime.';base64,'.base64_encode($bytes);
        }

        return $map;
    }

    private static function relsPath(string $partName): string
    {
        $dir = dirname($partName);
        $file = basename($partName);

        return ($dir === '.' ? '' : $dir.'/').'_rels/'.$file.'.rels';
    }

    private static function zipPath(string $fromDir, string $target): string
    {
        $target = str_replace('\\', '/', $target);
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        $parts = $fromDir === '.' ? [] : explode('/', $fromDir);
        foreach (explode('/', $target) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /**
     * @param  array<string, string>  $images
     */
    private static function block(DOMElement $el, array $images): string
    {
        return match ($el->localName) {
            'body', 'ftr', 'hdr', 'sdtContent', 'txbxContent' => self::children($el, $images),
            'sdt' => self::first($el, 'sdtContent', $images),
            'tbl' => self::table($el, $images),
            'p' => self::paragraph($el, $images),
            'sectPr', 'tblGrid' => '',
            default => self::children($el, $images),
        };
    }

    /**
     * @param  array<string, string>  $images
     */
    private static function children(DOMElement $el, array $images): string
    {
        $html = '';
        foreach ($el->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $html .= self::block($node, $images);
            }
        }

        return $html;
    }

    /**
     * @param  array<string, string>  $images
     */
    private static function first(DOMElement $el, string $name, array $images): string
    {
        foreach ($el->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $name) {
                return self::block($node, $images);
            }
        }

        return '';
    }

    /**
     * @param  array<string, string>  $images
     */
    private static function table(DOMElement $el, array $images): string
    {
        $rows = self::tableRows($el, $images);

        return $rows === '' ? '' : '<table>'.$rows.'</table>';
    }

    /**
     * @param  array<string, string>  $images
     */
    private static function tableRows(DOMElement $el, array $images): string
    {
        $rows = '';
        foreach ($el->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            if ($child->localName === 'tr') {
                $rows .= self::tableRow($child, $images);
            } elseif (in_array($child->localName, ['sdt', 'sdtContent'], true)) {
                $rows .= self::tableRows($child, $images);
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $images
     */
    private static function tableRow(DOMElement $tr, array $images): string
    {
        $cells = '';
        foreach ($tr->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'tc') {
                $span = self::gridSpan($child);
                $attr = $span > 1 ? ' colspan="'.$span.'"' : '';
                $inner = trim(self::children($child, $images));
                $cells .= '<td'.$attr.'>'.($inner !== '' ? $inner : '&nbsp;').'</td>';
            }
        }

        return $cells === '' ? '' : '<tr>'.$cells.'</tr>';
    }

    private static function gridSpan(DOMElement $tc): int
    {
        foreach ($tc->getElementsByTagNameNS($tc->namespaceURI ?: '', 'gridSpan') as $span) {
            $n = (int) self::attr($span, 'val');

            return $n > 1 ? $n : 1;
        }

        return 1;
    }

    /**
     * @param  array<string, string>  $images
     */
    private static function paragraph(DOMElement $el, array $images): string
    {
        $align = self::align($el);
        $style = self::pStyle($el);
        $tag = match (true) {
            str_contains($style, 'Title') => 'h1',
            str_contains($style, 'Heading1') => 'h2',
            str_contains($style, 'Heading2') => 'h3',
            default => 'p',
        };
        $inner = self::runs($el, $images);
        if (trim(strip_tags(str_replace('&nbsp;', '', $inner))) === '' && ! str_contains($inner, '<img')) {
            return '<p class="sgc-docx-gap">&nbsp;</p>';
        }

        $attr = $align !== '' ? ' style="text-align:'.$align.'"' : '';

        return '<'.$tag.$attr.'>'.$inner.'</'.$tag.'>';
    }

    private static function align(DOMElement $p): string
    {
        foreach ($p->getElementsByTagNameNS($p->namespaceURI ?: '', 'jc') as $jc) {
            if ($jc->parentNode?->localName !== 'pPr') {
                continue;
            }

            return match (self::attr($jc, 'val')) {
                'center' => 'center',
                'right' => 'right',
                'both', 'distribute' => 'justify',
                default => '',
            };
        }

        return '';
    }

    private static function pStyle(DOMElement $p): string
    {
        foreach ($p->getElementsByTagNameNS($p->namespaceURI ?: '', 'pStyle') as $style) {
            return self::attr($style, 'val');
        }

        return '';
    }

    /**
     * @param  array<string, string>  $images
     */
    private static function runs(DOMElement $el, array $images): string
    {
        $html = '';
        foreach ($el->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $html .= match ($node->localName) {
                'r' => self::run($node, $images),
                'hyperlink', 'ins', 'smartTag' => self::runs($node, $images),
                'sdt' => self::sdtRuns($node, $images),
                default => '',
            };
        }

        return $html;
    }

    /**
     * @param  array<string, string>  $images
     */
    private static function sdtRuns(DOMElement $sdt, array $images): string
    {
        foreach ($sdt->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === 'sdtContent') {
                return self::runs($node, $images);
            }
        }

        return '';
    }

    /**
     * @param  array<string, string>  $images
     */
    private static function run(DOMElement $run, array $images): string
    {
        $text = '';
        foreach ($run->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $text .= match ($node->localName) {
                't' => htmlspecialchars($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'tab' => '&nbsp;&nbsp;&nbsp;&nbsp;',
                'br', 'cr' => '<br>',
                'drawing', 'pict' => self::image($node, $images),
                default => '',
            };
        }

        if ($text === '') {
            return '';
        }

        $wrap = $text;
        $rPr = null;
        foreach ($run->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === 'rPr') {
                $rPr = $node;
                break;
            }
        }
        if ($rPr) {
            if (self::hasChild($rPr, 'b')) {
                $wrap = '<strong>'.$wrap.'</strong>';
            }
            if (self::hasChild($rPr, 'i')) {
                $wrap = '<em>'.$wrap.'</em>';
            }
            if (self::hasChild($rPr, 'u')) {
                $wrap = '<u>'.$wrap.'</u>';
            }
        }

        return $wrap;
    }

    /**
     * @param  array<string, string>  $images
     */
    private static function image(DOMElement $el, array $images): string
    {
        $id = '';
        foreach ($el->getElementsByTagNameNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed') as $embed) {
            $id = $embed->nodeValue ?: $embed->textContent;
        }
        if ($id === '') {
            foreach ($el->getElementsByTagName('*') as $node) {
                if ($node instanceof DOMElement && $node->hasAttribute('r:embed')) {
                    $id = $node->getAttribute('r:embed');
                    break;
                }
                if ($node instanceof DOMElement && $node->localName === 'blip' && $node->hasAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed')) {
                    $id = $node->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');
                    break;
                }
            }
        }
        if ($id === '' || ! isset($images[$id])) {
            return '';
        }

        return '<img src="'.$images[$id].'" alt="">';
    }

    private static function hasChild(DOMElement $el, string $name): bool
    {
        foreach ($el->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $name) {
                $val = self::attr($node, 'val');

                return $val === '' || $val === 'true' || $val === '1' || $val === 'on';
            }
        }

        return false;
    }

    private static function attr(DOMElement $el, string $name): string
    {
        $ns = $el->namespaceURI ?: '';
        if ($ns !== '') {
            $value = $el->getAttributeNS($ns, $name);
            if ($value !== '') {
                return $value;
            }
        }

        return $el->getAttribute($name);
    }
}
