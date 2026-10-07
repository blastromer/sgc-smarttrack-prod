<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiteAppearance
{
    public const COOKIE = 'sgc_ui';

    public const THEMES = ['night', 'day', 'forest', 'contrast'];

    public const FONTS = ['segoe', 'source', 'atkinson', 'georgia'];

    public const SIZES = ['sm', 'md', 'lg', 'xl'];

    public const DENSITIES = ['comfortable', 'compact'];

    /**
     * @return array{theme: string, accent: string, font: string, text_size: string, density: string}
     */
    public static function defaults(): array
    {
        return [
            'theme' => 'night',
            'accent' => '#2aa7a0',
            'font' => 'segoe',
            'text_size' => 'md',
            'density' => 'comfortable',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{theme: string, accent: string, font: string, text_size: string, density: string}
     */
    public static function sanitize(array $data): array
    {
        $defaults = self::defaults();
        $accent = strtolower((string) ($data['accent'] ?? $defaults['accent']));

        return [
            'theme' => in_array($data['theme'] ?? '', self::THEMES, true) ? (string) $data['theme'] : $defaults['theme'],
            'accent' => preg_match('/^#[0-9a-f]{6}$/', $accent) ? $accent : $defaults['accent'],
            'font' => in_array($data['font'] ?? '', self::FONTS, true) ? (string) $data['font'] : $defaults['font'],
            'text_size' => in_array($data['text_size'] ?? '', self::SIZES, true) ? (string) $data['text_size'] : $defaults['text_size'],
            'density' => in_array($data['density'] ?? '', self::DENSITIES, true) ? (string) $data['density'] : $defaults['density'],
        ];
    }

    /**
     * @return array{theme: string, accent: string, font: string, text_size: string, density: string}
     */
    public static function site(): array
    {
        if (! Storage::disk('local')->exists('site-appearance.json')) {
            return self::defaults();
        }

        $raw = json_decode((string) Storage::disk('local')->get('site-appearance.json'), true);

        return self::sanitize(is_array($raw) ? $raw : []);
    }

    /**
     * @param  array{theme: string, accent: string, font: string, text_size: string, density: string}  $data
     */
    public static function publish(array $data): void
    {
        Storage::disk('local')->put('site-appearance.json', json_encode(self::sanitize($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{theme: string, accent: string, font: string, text_size: string, density: string}
     */
    public static function forRequest(?Request $request = null): array
    {
        $request ??= request();
        $cookie = $request?->cookie(self::COOKIE);
        if (is_string($cookie) && $cookie !== '') {
            $raw = json_decode($cookie, true);
            if (is_array($raw)) {
                return self::sanitize($raw);
            }
        }

        return self::site();
    }

    /**
     * @param  array{theme: string, accent: string, font: string, text_size: string, density: string}  $prefs
     * @return array{theme: string, accent: string, font: string, text_size: string, density: string, font_family: string, font_size: string}
     */
    public static function resolved(array $prefs): array
    {
        $prefs = self::sanitize($prefs);

        return $prefs + [
            'font_family' => self::fontFamily($prefs['font']),
            'font_size' => self::fontSize($prefs['text_size']),
        ];
    }

    public static function fontFamily(string $font): string
    {
        return match ($font) {
            'source' => "'Source Sans 3', 'Segoe UI', sans-serif",
            'atkinson' => "'Atkinson Hyperlegible', 'Segoe UI', sans-serif",
            'georgia' => "Georgia, 'Times New Roman', serif",
            default => "'Segoe UI', Inter, system-ui, sans-serif",
        };
    }

    public static function fontSize(string $size): string
    {
        return match ($size) {
            'sm' => '14px',
            'lg' => '18px',
            'xl' => '20px',
            default => '16px',
        };
    }
}
