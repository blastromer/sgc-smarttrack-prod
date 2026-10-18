<?php

namespace App\Support;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleDriveClient
{
    /**
     * @return array{id: ?string, error: ?string}
     */
    public function ensure(string $refreshToken, string $schoolCode, string $parentId, string $year, string $school): array
    {
        $cacheKey = DriveMovFolders::schoolFolderCacheKey($schoolCode, $year, $school, $parentId);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return ['id' => $cached, 'error' => null];
        }

        $token = $this->accessToken($refreshToken);
        if ($token === null) {
            return ['id' => null, 'error' => 'token'];
        }

        $yearId = $this->findOrCreate($token, $parentId, $year);
        if ($yearId['error'] || ! $yearId['id']) {
            return ['id' => null, 'error' => $yearId['error'] ?? 'drive'];
        }

        $schoolId = $this->findOrCreate($token, $yearId['id'], $school);
        if ($schoolId['id']) {
            Cache::put($cacheKey, $schoolId['id'], now()->addDays(30));
        }

        return ['id' => $schoolId['id'], 'error' => $schoolId['error']];
    }

    /**
     * @return array{created: int, ready: int, error: ?string}
     */
    public function ensureAll(string $refreshToken, string $schoolCode, string $year, string $school): array
    {
        $token = $this->accessToken($refreshToken);
        if ($token === null) {
            return ['created' => 0, 'ready' => 0, 'error' => 'token'];
        }

        $parents = [];
        foreach (DriveMovFolders::catalog() as $key => $folder) {
            $parents[$key] = $folder['id'];
        }

        $years = $this->resolveLevel($token, $parents, $year);
        if ($years['error']) {
            return ['created' => 0, 'ready' => 0, 'error' => $years['error']];
        }

        $schools = $this->resolveLevel($token, $years['ids'], $school);
        if ($schools['error']) {
            return ['created' => 0, 'ready' => 0, 'error' => $schools['error']];
        }

        foreach ($schools['ids'] as $key => $id) {
            $parentId = $parents[$key] ?? null;
            if ($parentId) {
                Cache::put(
                    DriveMovFolders::schoolFolderCacheKey($schoolCode, $year, $school, $parentId),
                    $id,
                    now()->addDays(30),
                );
            }
        }

        return [
            'created' => $schools['created'],
            'ready' => count($schools['ids']) - $schools['created'],
            'error' => null,
        ];
    }

    public function accessToken(string $refreshToken): ?string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        $token = $response->json('access_token');

        return $response->successful() && is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @return array{id: ?string, error: ?string}
     */
    public function putFile(string $refreshToken, string $folderId, string $name, string $mime, string $contents): array
    {
        $token = $this->accessToken($refreshToken);
        if ($token === null) {
            return ['id' => null, 'error' => 'token'];
        }

        $boundary = 'sgc'.bin2hex(random_bytes(8));
        $metadata = json_encode([
            'name' => $name,
            'parents' => [$folderId],
        ], JSON_UNESCAPED_UNICODE);
        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$metadata."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: {$mime}\r\n\r\n"
            .$contents."\r\n"
            ."--{$boundary}--";

        $response = Http::withToken($token)
            ->withBody($body, 'multipart/related; boundary='.$boundary)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true');

        $id = $response->json('id');
        if ($response->status() === 403) {
            return ['id' => null, 'error' => 'scope'];
        }

        return [
            'id' => $response->successful() && is_string($id) && $id !== '' ? $id : null,
            'error' => $response->successful() ? null : 'drive',
        ];
    }

    public function getFile(string $refreshToken, string $fileId): ?string
    {
        $token = $this->accessToken($refreshToken);
        if ($token === null || $fileId === '') {
            return null;
        }

        $response = Http::withToken($token)->get(
            'https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'?alt=media&supportsAllDrives=true',
        );

        return $response->successful() ? $response->body() : null;
    }

    public function shareAnyone(string $refreshToken, string $fileId): void
    {
        $token = $this->accessToken($refreshToken);
        if ($token === null || $fileId === '') {
            return;
        }

        Http::withToken($token)->post(
            'https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'/permissions?supportsAllDrives=true',
            ['role' => 'reader', 'type' => 'anyone'],
        );
    }

    public function deleteFile(string $refreshToken, string $fileId): void
    {
        $token = $this->accessToken($refreshToken);
        if ($token === null || $fileId === '') {
            return;
        }

        Http::withToken($token)->delete(
            'https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'?supportsAllDrives=true',
        );
    }

    /**
     * @return array{id: ?string, error: ?string}
     */
    private function findOrCreate(string $token, string $parentId, string $name): array
    {
        $found = $this->search($token, ['one' => $parentId], $name);
        if ($found['error']) {
            return ['id' => null, 'error' => $found['error']];
        }
        if ($found['ids']['one'] ?? null) {
            return ['id' => $found['ids']['one'], 'error' => null, 'created' => false];
        }

        $made = $this->createMany($token, ['one' => $parentId], $name);
        if ($made['error']) {
            return ['id' => null, 'error' => $made['error'], 'created' => false];
        }

        return [
            'id' => $made['ids']['one'] ?? null,
            'error' => isset($made['ids']['one']) ? null : 'drive',
            'created' => isset($made['ids']['one']),
        ];
    }

    /**
     * Create school folders for a short slice of time. A queued job calls this
     * again until every folder exists. Cached folders are counted immediately.
     *
     * @return array{created: int, ready: int, pending: int, error: ?string}
     */
    public function ensureWithin(string $refreshToken, string $schoolCode, string $year, string $school, int $seconds = 20): array
    {
        $token = null;
        $created = 0;
        $ready = 0;
        $pending = 0;
        $started = microtime(true);
        $stop = false;

        foreach (DriveMovFolders::catalog() as $folder) {
            $cacheKey = DriveMovFolders::schoolFolderCacheKey($schoolCode, $year, $school, $folder['id']);
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $ready++;

                continue;
            }

            if ($stop || (($created + $ready) > 0 && (microtime(true) - $started) >= $seconds)) {
                $stop = true;
                $pending++;

                continue;
            }

            if ($token === null) {
                $token = $this->accessToken($refreshToken);
                if ($token === null) {
                    return ['created' => 0, 'ready' => 0, 'pending' => 0, 'error' => 'token'];
                }
            }

            $yearId = $this->findOrCreate($token, $folder['id'], $year);
            if ($yearId['error'] || ! $yearId['id']) {
                return ['created' => $created, 'ready' => $ready, 'pending' => $pending, 'error' => $yearId['error'] ?? 'drive'];
            }

            $schoolId = $this->findOrCreate($token, $yearId['id'], $school);
            if ($schoolId['error'] || ! $schoolId['id']) {
                return ['created' => $created, 'ready' => $ready, 'pending' => $pending, 'error' => $schoolId['error'] ?? 'drive'];
            }

            Cache::put($cacheKey, $schoolId['id'], now()->addDays(30));
            if (! empty($schoolId['created'])) {
                $created++;
            } else {
                $ready++;
            }
        }

        return ['created' => $created, 'ready' => $ready, 'pending' => $pending, 'error' => null];
    }

    /**
     * @param  array<string, string>  $parents
     * @return array{ids: array<string, string>, created: int, error: ?string}
     */
    private function resolveLevel(string $token, array $parents, string $name): array
    {
        $ids = [];
        $created = 0;

        foreach (array_chunk($parents, 10, true) as $chunk) {
            $found = $this->search($token, $chunk, $name);
            if ($found['error']) {
                return ['ids' => [], 'created' => 0, 'error' => $found['error']];
            }

            $missing = [];
            foreach ($chunk as $key => $parentId) {
                $id = $found['ids'][$key] ?? null;
                if ($id) {
                    $ids[$key] = $id;
                } else {
                    $missing[$key] = $parentId;
                }
            }

            if ($missing === []) {
                continue;
            }

            $made = $this->createMany($token, $missing, $name);
            if ($made['error']) {
                return ['ids' => [], 'created' => 0, 'error' => $made['error']];
            }

            foreach ($missing as $key => $parentId) {
                $id = $made['ids'][$key] ?? null;
                if (! $id) {
                    return ['ids' => [], 'created' => 0, 'error' => 'drive'];
                }
                $ids[$key] = $id;
                $created++;
            }
        }

        return ['ids' => $ids, 'created' => $created, 'error' => null];
    }

    /**
     * @param  array<string, string>  $parents
     * @return array{ids: array<string, ?string>, error: ?string}
     */
    private function search(string $token, array $parents, string $name): array
    {
        $responses = Http::pool(function (Pool $pool) use ($parents, $token, $name) {
            foreach ($parents as $key => $parentId) {
                $pool->as((string) $key)->withToken($token)->get('https://www.googleapis.com/drive/v3/files', [
                    'q' => $this->query($parentId, $name),
                    'fields' => 'files(id)',
                    'pageSize' => 1,
                    'supportsAllDrives' => 'true',
                    'includeItemsFromAllDrives' => 'true',
                ]);
            }
        });

        return $this->readPool($responses, $parents, function (Response $response) {
            $id = $response->json('files.0.id');

            return is_string($id) && $id !== '' ? $id : null;
        });
    }

    /**
     * @param  array<string, string>  $parents
     * @return array{ids: array<string, string>, error: ?string}
     */
    private function createMany(string $token, array $parents, string $name): array
    {
        $responses = Http::pool(function (Pool $pool) use ($parents, $token, $name) {
            foreach ($parents as $key => $parentId) {
                $pool->as((string) $key)
                    ->withToken($token)
                    ->post('https://www.googleapis.com/drive/v3/files?supportsAllDrives=true', [
                        'name' => $name,
                        'mimeType' => 'application/vnd.google-apps.folder',
                        'parents' => [$parentId],
                    ]);
            }
        });

        $read = $this->readPool($responses, $parents, function (Response $response) {
            $id = $response->json('id');

            return is_string($id) && $id !== '' ? $id : null;
        });

        $ids = [];
        foreach ($read['ids'] as $key => $id) {
            if (is_string($id) && $id !== '') {
                $ids[$key] = $id;
            }
        }

        return ['ids' => $ids, 'error' => $read['error']];
    }

    /**
     * @param  array<string, mixed>  $responses
     * @param  array<string, string>  $parents
     * @param  callable(Response): ?string  $idFrom
     * @return array{ids: array<string, ?string>, error: ?string}
     */
    private function readPool(array $responses, array $parents, callable $idFrom): array
    {
        $ids = [];
        foreach ($parents as $key => $parentId) {
            $response = $responses[$key] ?? null;
            if ($response instanceof Throwable) {
                return ['ids' => [], 'error' => 'drive'];
            }
            if (! $response instanceof Response) {
                return ['ids' => [], 'error' => 'drive'];
            }
            if ($response->status() === 403) {
                return ['ids' => [], 'error' => 'scope'];
            }
            if (! $response->successful()) {
                return ['ids' => [], 'error' => 'drive'];
            }
            $ids[$key] = $idFrom($response);
        }

        return ['ids' => $ids, 'error' => null];
    }

    private function query(string $parentId, string $name): string
    {
        $safeParent = str_replace(['\\', "'"], ['\\\\', "\\'"], $parentId);
        $safeName = str_replace(['\\', "'"], ['\\\\', "\\'"], $name);

        return "'{$safeParent}' in parents and mimeType = 'application/vnd.google-apps.folder' and name = '{$safeName}' and trashed = false";
    }
}
