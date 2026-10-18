<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\Mov;
use App\Models\SchoolFormProfile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DriveMovFolders
{
    public const WRITE_SCOPE = 'https://www.googleapis.com/auth/drive';

    /**
     * Official SGC FAT Drive folders (one Minimum + one Additional per FI group).
     *
     * @return array<string, array{key: string, id: string, label: string}>
     */
    public static function catalog(): array
    {
        $rows = [
            ['key' => 'FI1A', 'id' => '1Hn9BH4B8IgX8Mp7OYjLkV934TsarBvjg', 'label' => '1. FI1A | Minimum MOVs'],
            ['key' => 'FI1A-ADD', 'id' => '1BQgXyBiBoyZ4LV-h13sGH-H7JYI0Kybc', 'label' => '2. FI1A | Additional MOVs'],
            ['key' => 'FI1B', 'id' => '1PRnGPcv1tgBAboqKTZaLXSNOwMvx3S4z', 'label' => '3. FI1B | Minimum MOVs'],
            ['key' => 'FI1B-ADD', 'id' => '1w4BEIZ-L1tLswmHf78aI_R7GKb3GB0Yb', 'label' => '4. FI1B | Additional MOVs'],
            ['key' => 'FI1C', 'id' => '1yVEMHiisqeQqr3TILtaXtWczsaR2i8lf', 'label' => '5. FI1C | Minimum MOVs'],
            ['key' => 'FI1C-ADD', 'id' => '11pOFcXZYR_vSyLZoaH93wTEY8ccVBbbQ', 'label' => '6. FI1C | Additional MOVs'],
            ['key' => 'FI2A', 'id' => '1raQ-xguMDYuIZ4sKWKPIlyLjDDiXccBJ', 'label' => '7. FI2A | Minimum MOVs'],
            ['key' => 'FI2A-ADD', 'id' => '1U5Qghs6T3zn_aCTUtQ9CbVMOXGp4F5QR', 'label' => '8. FI2A | Additional MOVs'],
            ['key' => 'FI2B', 'id' => '1s6Q04_oH-hJAPSk-kGQaWZuzPPqZcbHL', 'label' => '9. FI2B | Minimum MOVs'],
            ['key' => 'FI2B-ADD', 'id' => '1Q4gLZlWdvPIMU4pw8_IxHUyG1PSctQUl', 'label' => '10. FI2B | Additional MOVs'],
            ['key' => 'FI2C', 'id' => '1CZAJKsKrimxaeNFjCBsgtaa4U5vcstln', 'label' => '11. FI2C | Minimum MOVs'],
            ['key' => 'FI2C-ADD', 'id' => '14znfAqKTHNTl6rCWpbcLEf4ff8SjP4_R', 'label' => '12. FI2C | Additional MOVs'],
            ['key' => 'FI3A', 'id' => '1d1qFrO3JcQtMqD_OAF5qEAwDLNAlm5Dq', 'label' => '13. FI3A | Minimum MOVs'],
            ['key' => 'FI3A-ADD', 'id' => '1fM8nuKEGFcmewoRxwrcogxBG_uFQGAu3', 'label' => '14. FI3A | Additional MOVs'],
            ['key' => 'FI3BCD', 'id' => '1EhNkNa33HEc5F9yFfZJFJloZHsc2tSve', 'label' => '15. FI3B, FI3C, FI3D | Minimum and Additional MOVs'],
            ['key' => 'FI4A', 'id' => '1GPZoJdZCJUzA2QXBTcbxxRNOEzv0vS-F', 'label' => '16. FI4A | Minimum MOVs'],
            ['key' => 'FI4A-ADD', 'id' => '1h87EiO084MTlIx88Lt0RDkxD_CIXdzBE', 'label' => '17. FI4A | Additional MOVs'],
            ['key' => 'FI4B', 'id' => '1F7kDjcQqMP5Qc8bk2i6ICurQt4d4trc5', 'label' => '18. FI4B | Minimum MOVs'],
            ['key' => 'FI4B-ADD', 'id' => '1da2vbDfUUrj6pNDyLZQ_deKHxydINJAa', 'label' => '19. FI4B | Additional MOVs'],
            ['key' => 'FI4C', 'id' => '1aZEx3Umhgo_OI_koFmtZMa5DSia_lPRJ', 'label' => '20. FI4C | Minimum MOVs'],
            ['key' => 'FI4C-ADD', 'id' => '1N7RNdIPxzwForkyrShSQ3nTMEeq1S4HM', 'label' => '21. FI4C | Additional MOVs'],
            ['key' => 'FI5A', 'id' => '1hCoCN5dAU4IeOn2CXF7lqFDYisoVGGT7', 'label' => '22. FI5A | Minimum MOVs'],
            ['key' => 'FI5A-ADD', 'id' => '1sxTfo0H0oszoRcASu285yBZSESpRmYRD', 'label' => '23. FI5A | Additional MOVs'],
            ['key' => 'FI6A', 'id' => '1VPqlab_rAqkrSs8BTTXxfGfRrPpdTvOY', 'label' => '24. FI6A | Minimum MOVs'],
            ['key' => 'FI6A-ADD', 'id' => '1B3bfP8cjjjaJYlz4VXDwK38Ujx0knCxX', 'label' => '25. FI6A | Additional MOVs'],
            ['key' => 'FI6B', 'id' => '1c8h8xQ3vObYtBwAHn2yIMBDif_5gmIW8', 'label' => '26. FI6B | Minimum MOVs'],
            ['key' => 'FI6B-ADD', 'id' => '11fPb2q8P9VpIbgzeNlPKEbGCtr4aJ0jS', 'label' => '27. FI6B | Additional MOVs'],
            ['key' => 'FI6C', 'id' => '1UwDFOX082u8B8zljXIgrsz9x4tMvN5bY', 'label' => '28. FI6C | Minimum MOVs'],
            ['key' => 'FI6C-ADD', 'id' => '1VxIHNkajzJGKiCq0YH9xgi1OV8--E9Qy', 'label' => '29. FI6C | Additional MOVs'],
            ['key' => 'FI7', 'id' => '1_yi_zNv5j-EKX58Os9TooPRXl93pVGT-', 'label' => '30. FI7 | Minimum MOVs'],
            ['key' => 'FI7-ADD', 'id' => '1H8sVolwLhkWr2ET21IhoZl6gIaVfPnCp', 'label' => '31. FI7 | Additional MOVs'],
            ['key' => 'FI8', 'id' => '12xxRrqpZc1_y5xB9ewmfzNEXGNa3Kwm1', 'label' => '32. FI8 | Minimum MOVs'],
            ['key' => 'FI8-ADD', 'id' => '1jj0iyBAYlTNZm_897L4YppVI6bhxD0Ff', 'label' => '33. FI8 | Additional MOVs'],
            ['key' => 'FI9', 'id' => '1yUpdhBN-kSjDihWFiFN4itL3bPSU0me6', 'label' => '34. FI9 | Minimum MOVs'],
            ['key' => 'FI9-ADD', 'id' => '1lqJeee2x03aPpufw5FEpC84r9SJB5ET9', 'label' => '35. FI9 | Additional MOVs'],
            ['key' => 'FI10', 'id' => '1P0nODfFpb3bfVKGJPNTuY4gyL49gLgM6', 'label' => '36. FI10 | Minimum MOVs'],
            ['key' => 'FI10-ADD', 'id' => '1H_WxD0A-CdzT1QUOR0AmQxfWmGpMKzlG', 'label' => '37. FI10 | Additional MOVs'],
            ['key' => 'FI11A', 'id' => '1PIsgpYPQHiW63ZivuGeHYG9AQ8138B5f', 'label' => '38. FI11A | Minimum MOVs'],
            ['key' => 'FI11A-ADD', 'id' => '1oFzuJOOhx__Wx_EgOM63I4W8TuZ8NO0Z', 'label' => '39. FI11A | Additional MOVs'],
            ['key' => 'FI11B', 'id' => '1zibEw7in4R1MayF79xbI1yrqJbu7byAR', 'label' => '40. FI11B | Minimum MOVs'],
            ['key' => 'FI11B-ADD', 'id' => '1A6EDoThS5mvmcjMAo2fqv9i4PeWiMnK5', 'label' => '41. FI11B | Additional MOVs'],
            ['key' => 'FI12', 'id' => '1LBlg80H_IR_xlcULLVzGp7AjoEW-I2mC', 'label' => '42. FI12 | Minimum MOVs'],
            ['key' => 'FI12-ADD', 'id' => '1YdM-On2vPaBswO8DPj3t8iCdzR2fkc-z', 'label' => '43. FI12 | Additional MOVs'],
            ['key' => 'validity', 'id' => '1E4pLSEA7bnCmimssrtQ1hS3QcY5nSx-b', 'label' => '44. Validity Form'],
        ];

        return collect($rows)->keyBy('key')->all();
    }

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id'));
    }

    public static function urlForSlot(string $slotCode): ?string
    {
        $folder = self::find(self::folderKeyForSlot($slotCode));

        return $folder ? self::copyUrl($folder['id']) : null;
    }

    /**
     * @return array{key: string, id: string, label: string}|null
     */
    public static function find(string $key): ?array
    {
        return self::catalog()[$key] ?? null;
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    public static function library(): array
    {
        return collect(self::catalog())
            ->map(fn (array $folder) => [
                'key' => $folder['key'],
                'label' => $folder['label'],
                'url' => self::ensureUrl($folder['key']),
            ])
            ->values()
            ->all();
    }

    public static function copyUrl(string $id): string
    {
        return 'https://drive.google.com/drive/folders/'.$id.'?usp=drive_copy';
    }

    public static function ensureUrl(string $folderKey): string
    {
        return route('google.drive.open', ['key' => $folderKey]);
    }

    public static function ensureUrlForSlot(string $slotCode): ?string
    {
        $key = self::folderKeyForSlot($slotCode);

        return $key && self::find($key) ? self::ensureUrl($key) : null;
    }

    /**
     * @return array{year: string, school: string}|null
     */
    public static function placement(User $user): ?array
    {
        $form = SchoolFormProfile::forSchool($user)->toForm($user);
        $school = self::folderName($form['school_name']);
        if ($school === '') {
            return null;
        }

        $year = self::folderName($form['school_year']);

        return [
            'year' => $year !== '' ? $year : self::currentSchoolYear(),
            'school' => $school,
        ];
    }

    public static function currentSchoolYear(): string
    {
        $start = now()->month >= 6 ? (int) now()->year : (int) now()->year - 1;

        return $start.'-'.($start + 1);
    }

    public static function folderName(string $name): string
    {
        $name = trim(preg_replace('/[\/\\\\]+/', ' - ', $name) ?? $name);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        return mb_substr($name, 0, 200);
    }

    public static function schoolFolderCacheKey(string $schoolCode, string $year, string $school, string $parentId): string
    {
        return 'google-drive-school-folder:'.sha1($schoolCode.'|'.$year.'|'.$school.'|'.$parentId);
    }

    public static function folderKeyForSlot(string $slotCode): ?string
    {
        if ($slotCode === 'Validity') {
            return 'validity';
        }

        if ($slotCode === 'FI3BCD') {
            return 'FI3BCD';
        }

        if (preg_match('/^FI(7|8|9|10|12)/', $slotCode, $match)) {
            $key = 'FI'.$match[1];

            return str_contains($slotCode, 'ADD') ? $key.'-ADD' : $key;
        }

        if (preg_match('/^(FI\d+[A-C])/', $slotCode, $match)) {
            $key = $match[1];

            return str_contains($slotCode, 'ADD') ? $key.'-ADD' : $key;
        }

        return null;
    }

    public static function tokenCacheKey(int $userId): string
    {
        return 'google-drive-refresh:'.$userId;
    }

    public static function scopeCacheKey(int $userId): string
    {
        return 'google-drive-scope:'.$userId;
    }

    public static function connected(?int $userId): bool
    {
        return $userId !== null && filled(cache()->get(self::tokenCacheKey($userId)));
    }

    public static function writable(?int $userId): bool
    {
        return self::connected($userId) && cache()->get(self::scopeCacheKey((int) $userId)) === self::WRITE_SCOPE;
    }

    public static function refreshTokenFor(User $user): ?string
    {
        $ids = collect([$user->id])->merge(
            User::schoolTeam($user->school_code)->pluck('id'),
        )->unique();

        foreach ($ids as $id) {
            if (! self::writable((int) $id)) {
                continue;
            }
            $token = cache()->get(self::tokenCacheKey((int) $id));
            if (filled($token)) {
                return (string) $token;
            }
        }

        return null;
    }

    /**
     * Copy an attached draft into the school's folder for that MOV.
     * Returns null when Drive is not connected, saved, or failed.
     */
    public static function publishDraft(User $user, Mov $mov): ?string
    {
        $token = self::refreshTokenFor($user);
        if ($token === null || blank($mov->path) || ! Storage::disk('local')->exists($mov->path)) {
            return null;
        }

        $key = self::folderKeyForSlot($mov->code);
        $folder = $key ? self::find($key) : null;
        $placement = self::placement($user);
        if (! $folder || ! $placement) {
            return 'failed';
        }

        $client = app(GoogleDriveClient::class);
        $schoolFolder = $client->ensure(
            $token,
            $user->packetSchoolCode(),
            $folder['id'],
            $placement['year'],
            $placement['school'],
        );
        if (! $schoolFolder['id']) {
            return 'failed';
        }

        $previous = $mov->drive_file_id;
        $uploaded = $client->putFile(
            $token,
            $schoolFolder['id'],
            self::folderName($mov->code.' '.($mov->original_name ?: 'MOV')),
            $mov->mime ?: 'application/octet-stream',
            Storage::disk('local')->get($mov->path),
        );
        if (! $uploaded['id']) {
            return 'failed';
        }

        $mov->update(['drive_file_id' => $uploaded['id']]);
        $client->shareAnyone($token, $uploaded['id']);
        if (filled($previous) && $previous !== $uploaded['id']) {
            $client->deleteFile($token, (string) $previous);
        }

        return 'saved';
    }

    public static function removeDraft(User $user, Mov $mov): void
    {
        if (blank($mov->drive_file_id)) {
            return;
        }

        $token = self::refreshTokenFor($user);
        if ($token !== null) {
            app(GoogleDriveClient::class)->deleteFile($token, (string) $mov->drive_file_id);
        }
    }

    public static function refreshTokenForSchool(?string $schoolCode): ?string
    {
        foreach (User::schoolTeam($schoolCode) as $member) {
            if (! self::writable($member->id)) {
                continue;
            }
            $token = cache()->get(self::tokenCacheKey($member->id));
            if (filled($token)) {
                return (string) $token;
            }
        }

        return null;
    }

    /**
     * Read a MOV from the server while it is still a draft, or from Drive after submit.
     */
    public static function contents(Mov $mov): ?string
    {
        if (filled($mov->path) && Storage::disk('local')->exists($mov->path)) {
            return Storage::disk('local')->get($mov->path);
        }

        if (blank($mov->drive_file_id)) {
            return null;
        }

        $mov->loadMissing('assessment');
        $token = self::refreshTokenForSchool($mov->assessment?->school_code);
        if ($token === null) {
            return null;
        }

        return app(GoogleDriveClient::class)->getFile($token, (string) $mov->drive_file_id);
    }

    /**
     * Read the Google Drive copy only. Division review uses this so the
     * opened file is the Drive upload, even if a server copy still exists.
     */
    public static function driveContents(Mov $mov): ?string
    {
        if (blank($mov->drive_file_id)) {
            return null;
        }

        $mov->loadMissing('assessment');
        $token = self::refreshTokenForSchool($mov->assessment?->school_code);
        if ($token === null) {
            return null;
        }

        return app(GoogleDriveClient::class)->getFile($token, (string) $mov->drive_file_id);
    }

    /**
     * Upload local MOVs, then delete those server copies.
     * Stops after $seconds once one file is done so one queued attempt stays
     * inside the worker time limit.
     * Returns saved, partial (more files remain), offline, or failed.
     */
    public static function archivePacket(User $user, Assessment $assessment, int $seconds = 20): string
    {
        $local = $assessment->movs()
            ->get()
            ->filter(fn (Mov $mov) => filled($mov->path) && Storage::disk('local')->exists($mov->path));

        if ($local->isEmpty()) {
            return 'saved';
        }

        if (self::refreshTokenFor($user) === null) {
            return 'offline';
        }

        $started = microtime(true);
        $done = 0;

        foreach ($local as $mov) {
            if ($done > 0 && (microtime(true) - $started) >= $seconds) {
                return 'partial';
            }

            if (self::publishDraft($user, $mov->fresh()) !== 'saved') {
                return 'failed';
            }

            $fresh = $mov->fresh();
            if ($fresh === null || blank($fresh->drive_file_id) || blank($fresh->path)) {
                return 'failed';
            }

            Storage::disk('local')->delete($fresh->path);
            $fresh->update(['path' => null]);
            $done++;
        }

        Storage::disk('local')->deleteDirectory('movs/'.$assessment->id);

        return 'saved';
    }
}
