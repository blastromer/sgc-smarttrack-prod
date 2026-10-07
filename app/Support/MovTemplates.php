<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\Mov;
use Illuminate\Support\Facades\Storage;

class MovTemplates
{
    /**
     * Official SGC MOV templates for school use. Keys match files in resources/sgc-templates.
     *
     * @return array<string, array{key: string, file: string, label: string, kind: string}>
     */
    public static function catalog(): array
    {
        return [
            'notice-sgc' => ['key' => 'notice-sgc', 'file' => 'notice-sgc.docx', 'label' => 'SGC Notice of Meeting', 'kind' => 'notice'],
            'notice-pawim' => ['key' => 'notice-pawim', 'file' => 'notice-pawim.docx', 'label' => 'Generic Notice of Meeting', 'kind' => 'notice'],
            'minutes-sgc' => ['key' => 'minutes-sgc', 'file' => 'minutes-sgc.docx', 'label' => 'SGC Minutes of the Meeting', 'kind' => 'minutes'],
            'minutes-pawim' => ['key' => 'minutes-pawim', 'file' => 'minutes-pawim.docx', 'label' => 'Generic Minutes of the Meeting', 'kind' => 'minutes'],
            'resolution-sgc' => ['key' => 'resolution-sgc', 'file' => 'resolution-sgc.docx', 'label' => 'SGC Resolution', 'kind' => 'resolution'],
            'transmittal-sgc' => ['key' => 'transmittal-sgc', 'file' => 'transmittal-sgc.docx', 'label' => 'Transmittal letter', 'kind' => 'transmittal'],
            'org-chart-sgc' => ['key' => 'org-chart-sgc', 'file' => 'org-chart-sgc.docx', 'label' => 'Organizational chart', 'kind' => 'org_chart'],
            'members-list-sgc' => ['key' => 'members-list-sgc', 'file' => 'members-list-sgc.docx', 'label' => 'Official list of SGC members', 'kind' => 'members'],
            'membership-cert-sgc' => ['key' => 'membership-cert-sgc', 'file' => 'membership-cert-sgc.pptx', 'label' => 'Membership certificate', 'kind' => 'members'],
            'action-plan-sgc' => ['key' => 'action-plan-sgc', 'file' => 'action-plan-sgc.docx', 'label' => 'Action / advocacy plan', 'kind' => 'action_plan'],
            'concept-note-sgc' => ['key' => 'concept-note-sgc', 'file' => 'concept-note-sgc.docx', 'label' => 'Concept note / project brief', 'kind' => 'concept'],
            'documentation-sgc' => ['key' => 'documentation-sgc', 'file' => 'documentation-sgc.docx', 'label' => 'Documentation / report', 'kind' => 'documentation'],
            'monitoring-sgc' => ['key' => 'monitoring-sgc', 'file' => 'monitoring-sgc.docx', 'label' => 'Monitoring and evaluation', 'kind' => 'monitoring'],
            'progress-report-sgc' => ['key' => 'progress-report-sgc', 'file' => 'progress-report-sgc.docx', 'label' => 'Quarterly progress report', 'kind' => 'progress'],
            'participation-sgc' => ['key' => 'participation-sgc', 'file' => 'participation-sgc.pptx', 'label' => 'Certificate of participation', 'kind' => 'participation'],
        ];
    }

    public static function find(string $key): ?array
    {
        return self::catalog()[$key] ?? null;
    }

    /**
     * Full encoder library: every official template, grouped, with the FIs that use it.
     *
     * @return list<array{kind: string, label: string, files: list<array{key: string, label: string, ext: string, previewable: bool, uses: list<string>}>}>
     */
    public static function library(): array
    {
        $usesByKind = [];
        foreach (self::slotKinds() as $slot => $kinds) {
            $fi = preg_match('/^(FI\d+)/', $slot, $match) ? $match[1] : $slot;
            foreach ($kinds as $kind) {
                $usesByKind[$kind][] = $fi;
            }
        }

        $groups = [];
        foreach (self::catalog() as $item) {
            $kind = $item['kind'];
            if (! isset($groups[$kind])) {
                $groups[$kind] = [
                    'kind' => $kind,
                    'label' => self::kindLabel($kind),
                    'files' => [],
                ];
            }
            $groups[$kind]['files'][] = self::card($item) + [
                'uses' => array_values(array_unique($usesByKind[$kind] ?? [])),
            ];
        }

        return array_values($groups);
    }

    /**
     * Common templates for the encoder dashboard.
     *
     * @return list<array{key: string, label: string, ext: string, previewable: bool}>
     */
    public static function featured(): array
    {
        $keys = ['notice-sgc', 'minutes-sgc', 'resolution-sgc', 'transmittal-sgc', 'concept-note-sgc', 'documentation-sgc'];

        return collect($keys)
            ->map(fn (string $key) => self::find($key))
            ->filter()
            ->map(fn (array $item) => self::card($item))
            ->values()
            ->all();
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'notice' => 'Notices of meeting',
            'minutes' => 'Minutes of the meeting',
            'resolution' => 'SGC resolutions',
            'transmittal' => 'Transmittal letters',
            'org_chart' => 'Organizational chart',
            'members' => 'Membership',
            'action_plan' => 'Action / advocacy plans',
            'concept' => 'Concept notes / project briefs',
            'documentation' => 'Documentation / reports',
            'monitoring' => 'Monitoring and evaluation',
            'progress' => 'Progress reports',
            'participation' => 'Certificates of participation',
            default => ucfirst(str_replace('_', ' ', $kind)),
        };
    }

    public static function path(string $key): ?string
    {
        $item = self::find($key);
        if (! $item) {
            return null;
        }

        $path = resource_path('sgc-templates/'.$item['file']);

        return is_file($path) ? $path : null;
    }

    /**
     * @return list<array{key: string, label: string, ext: string, previewable: bool}>
     */
    public static function forSlot(string $slotCode): array
    {
        $kinds = self::kindsForSlot($slotCode);
        if ($kinds === []) {
            return [];
        }

        return collect(self::catalog())
            ->filter(fn (array $item) => in_array($item['kind'], $kinds, true))
            ->map(fn (array $item) => self::card($item))
            ->values()
            ->all();
    }

    /**
     * @param  array{key: string, file: string, label: string, kind: string}  $item
     * @return array{key: string, label: string, ext: string, previewable: bool}
     */
    public static function card(array $item): array
    {
        $ext = strtolower(pathinfo($item['file'], PATHINFO_EXTENSION));

        return [
            'key' => $item['key'],
            'label' => $item['label'],
            'ext' => $ext,
            'previewable' => $ext === 'docx',
        ];
    }

    /**
     * Read-only in-app view of an official template. PPTX is download-only.
     *
     * @return array{key: string, label: string, ext: string, previewable: bool, html: string|null, note: string}
     */
    public static function preview(string $key): ?array
    {
        $item = self::find($key);
        $path = self::path($key);
        if (! $item || ! $path) {
            return null;
        }

        $card = self::card($item);

        return [
            ...$card,
            'html' => $card['previewable'] ? DocxHtmlPreview::fromPath($path) : null,
            'note' => $card['previewable']
                ? 'Read-only official template. Download it to fill the blanks in Word, then upload the completed file to this slot.'
                : 'This official template is a PowerPoint file. Download it to open on your computer.',
        ];
    }

    /**
     * Templates for the scored minimum slots of an FI.
     *
     * @return list<array{key: string, label: string, ext: string, previewable: bool}>
     */
    public static function forIndicator(string $indicatorCode): array
    {
        $seen = [];
        $out = [];
        foreach (FatCatalog::minimumSlots($indicatorCode) as $slot) {
            foreach (self::forSlot($slot['code']) as $template) {
                if (isset($seen[$template['key']])) {
                    continue;
                }
                $seen[$template['key']] = true;
                $out[] = $template;
            }
        }

        return $out;
    }

    /**
     * Files already uploaded by this school that can fill the slot (same packet or a prior cycle).
     *
     * @return list<array{id: int, code: string, title: string, file: string, source: string}>
     */
    public static function reuseOptions(Assessment $assessment, string $slotCode): array
    {
        $kinds = self::kindsForSlot($slotCode);
        if ($kinds === []) {
            return [];
        }

        $slot = $assessment->movs->firstWhere('code', $slotCode);
        if ($slot && ! $assessment->canReplaceMov($slot)) {
            return [];
        }

        return Mov::query()
            ->whereNotNull('path')
            ->where('id', '!=', $slot?->id)
            ->whereHas('assessment', fn ($query) => $query->where('school_code', $assessment->school_code))
            ->with('assessment.cycle')
            ->latest('id')
            ->get()
            ->filter(function (Mov $mov) use ($kinds) {
                if (! $mov->hasFile() || ! Storage::disk('local')->exists($mov->path)) {
                    return false;
                }

                $slotKinds = self::kindsForSlot((string) $mov->code);

                return $slotKinds !== [] && array_intersect($kinds, $slotKinds) !== [];
            })
            ->unique(fn (Mov $mov) => $mov->assessment_id.'-'.$mov->code)
            ->take(6)
            ->map(function (Mov $mov) use ($assessment) {
                $same = $mov->assessment_id === $assessment->id;
                $cycle = $mov->assessment?->cycle?->name;

                return [
                    'id' => $mov->id,
                    'code' => $mov->code,
                    'title' => $mov->title,
                    'file' => $mov->original_name ?: $mov->code,
                    'source' => $same
                        ? 'This packet · '.$mov->code
                        : trim(($cycle ?: 'Earlier cycle').' · '.$mov->code),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function kindsForSlot(string $slotCode): array
    {
        return self::slotKinds()[$slotCode] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function slotKinds(): array
    {
        return [
            'FI1A' => ['notice'],
            'FI1A-ADD' => ['notice'],
            'FI1B' => ['members', 'resolution'],
            'FI1B-ADD1' => ['members'],
            'FI1B-ADD2' => ['resolution'],
            'FI1C' => ['org_chart'],
            'FI1C-ADD' => ['org_chart'],
            'FI2A' => ['minutes'],
            'FI2A-ADD1' => ['minutes'],
            'FI2A-ADD2' => ['minutes'],
            'FI2A-ADD3' => ['action_plan'],
            'FI2A-ADD4' => ['resolution'],
            'FI2B' => ['resolution'],
            'FI2B-ADD' => ['resolution'],
            'FI2C' => ['minutes'],
            'FI2C-ADD1' => ['minutes'],
            'FI2C-ADD2' => ['resolution'],
            'FI3A' => ['resolution'],
            'FI3A-ADD1' => ['resolution'],
            'FI3A-ADD2' => ['action_plan'],
            'FI3BCD' => ['minutes'],
            'FI4A' => ['minutes'],
            'FI4A-ADD1' => ['minutes'],
            'FI4A-ADD2' => ['action_plan'],
            'FI4A-ADD3' => ['resolution'],
            'FI4B' => ['documentation', 'minutes'],
            'FI4B-ADD1' => ['documentation', 'minutes'],
            'FI4B-ADD2' => ['minutes'],
            'FI4C' => ['minutes'],
            'FI4C-ADD1' => ['minutes'],
            'FI4C-ADD2' => ['resolution'],
            'FI5A' => ['transmittal'],
            'FI5A-ADD1' => ['documentation'],
            'FI5A-ADD2' => ['documentation'],
            'FI6A' => ['minutes'],
            'FI6A-2' => ['concept'],
            'FI6A-ADD1' => ['concept'],
            'FI6A-ADD2' => ['concept'],
            'FI6B' => ['monitoring'],
            'FI6B-ADD1' => ['monitoring'],
            'FI6B-ADD2' => ['progress'],
            'FI6C' => ['resolution'],
            'FI6C-ADD1' => ['resolution'],
            'FI7A' => ['resolution'],
            'FI7A-2' => ['transmittal', 'resolution'],
            'FI7A-ADD' => ['transmittal'],
            'FI8A' => ['resolution'],
            'FI8A-ADD1' => ['members'],
            'FI8A-ADD2' => ['resolution'],
            'FI9A' => ['documentation'],
            'FI9A-ADD1' => ['minutes'],
            'FI9A-ADD2' => ['participation'],
            'FI10A' => ['documentation'],
            'FI10A-2' => ['minutes'],
            'FI10A-ADD1' => ['documentation'],
            'FI10A-ADD2' => ['participation'],
            'FI11A' => ['resolution'],
            'FI11A-2' => ['action_plan'],
            'FI11A-ADD1' => ['action_plan'],
            'FI11B' => ['resolution'],
            'FI12A' => ['resolution'],
            'FI12A-ADD' => ['resolution'],
        ];
    }
}
