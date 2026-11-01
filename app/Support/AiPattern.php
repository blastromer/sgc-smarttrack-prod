<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use ZipArchive;

class AiPattern
{
    /**
     * Plain text of the finished sample for this form, when one is shipped with the app.
     */
    public static function text(string $kind): ?string
    {
        $path = self::path($kind);
        if ($path === null) {
            return null;
        }

        $stamp = filemtime($path) ?: 0;

        return Cache::remember('sgc:ai-pattern:'.$kind.':'.$stamp, 86400, function () use ($path) {
            $text = self::docxText($path);
            if ($text === '') {
                return null;
            }

            return mb_substr($text, 0, 8000);
        });
    }

    public static function path(string $kind): ?string
    {
        $file = match ($kind) {
            'org_chart' => 'org-chart.docx',
            'notice' => 'notice.docx',
            'minutes' => 'minutes.docx',
            'resolution' => 'resolution.docx',
            'transmittal' => 'transmittal.docx',
            'progress' => 'progress.docx',
            'documentation' => 'documentation.docx',
            'concept' => 'concept.docx',
            default => null,
        };

        if ($file === null) {
            return null;
        }

        $path = resource_path('ai-patterns/'.$file);

        return is_file($path) ? $path : null;
    }

    private static function docxText(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml)) {
            return '';
        }

        $text = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{2,}/", "\n", $text) ?? $text;

        return trim($text);
    }
}
