<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class DriveWorkStatus
{
    /**
     * @return array{state: string, message: string, done: ?int, total: ?int}|null
     */
    public static function get(string $key): ?array
    {
        $value = Cache::get($key);

        if (! is_array($value) || ! isset($value['state'], $value['message'])) {
            return null;
        }

        return [
            'state' => (string) $value['state'],
            'message' => (string) $value['message'],
            'done' => isset($value['done']) && is_numeric($value['done']) ? (int) $value['done'] : null,
            'total' => isset($value['total']) && is_numeric($value['total']) ? (int) $value['total'] : null,
        ];
    }

    public static function running(string $key): bool
    {
        $status = self::get($key);

        return in_array($status['state'] ?? null, ['queued', 'running'], true);
    }

    public static function put(string $key, string $state, string $message, ?int $done = null, ?int $total = null): void
    {
        Cache::put($key, [
            'state' => $state,
            'message' => $message,
            'done' => $done,
            'total' => $total,
        ], now()->addHour());
    }

    /**
     * Start work once, or report work that is already running or finished.
     * The callback runs only for a new claim. On the sync driver it finishes
     * before this method returns.
     *
     * @param  callable(): void  $start
     * @return array{state: string, message: string, done: ?int, total: ?int, more: bool}
     */
    public static function claim(string $key, string $runningMessage, callable $start, string $state = 'running'): array
    {
        $status = self::get($key);
        if (in_array($status['state'] ?? null, ['queued', 'running'], true)) {
            return self::report($status, $runningMessage, true);
        }

        if (in_array($status['state'] ?? null, ['done', 'failed'], true)) {
            Cache::forget($key);

            return self::report($status, $runningMessage, false);
        }

        if (! Cache::add($key, [
            'state' => $state,
            'message' => $runningMessage,
            'done' => null,
            'total' => null,
        ], now()->addHour())) {
            $status = self::get($key);

            return self::report($status, $runningMessage, in_array($status['state'] ?? null, ['queued', 'running'], true));
        }

        $start();

        $status = self::get($key) ?? [
            'state' => $state,
            'message' => $runningMessage,
            'done' => null,
            'total' => null,
        ];
        if (! in_array($status['state'], ['queued', 'running'], true)) {
            Cache::forget($key);

            return self::report($status, $runningMessage, false);
        }

        return self::report($status, $runningMessage, true);
    }

    /**
     * @param  array{state: string, message: string, done: ?int, total: ?int}|null  $status
     * @return array{state: string, message: string, done: ?int, total: ?int, more: bool}
     */
    private static function report(?array $status, string $fallback, bool $more): array
    {
        return [
            'state' => $status['state'] ?? 'running',
            'message' => ($status['message'] ?? '') !== '' ? $status['message'] : $fallback,
            'done' => $status['done'] ?? null,
            'total' => $status['total'] ?? null,
            'more' => $more,
        ];
    }
}
