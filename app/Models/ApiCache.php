<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class ApiCache
{
    public static function get(string $key): ?string
    {
        $row = DB::fetch('SELECT value, expires_at FROM api_cache WHERE cache_key = ?', [$key]);
        if (!$row || ($row['expires_at'] && strtotime($row['expires_at']) < time())) {
            return null;
        }
        return $row['value'];
    }

    public static function set(string $key, string $value, ?int $ttlSeconds = null): void
    {
        $expires = $ttlSeconds ? date('Y-m-d H:i:s', time() + $ttlSeconds) : null;
        if (DB::fetchColumn('SELECT 1 FROM api_cache WHERE cache_key = ?', [$key])) {
            DB::update('api_cache', ['value' => $value, 'expires_at' => $expires], 'cache_key = ?', [$key]);
        } else {
            DB::insert('api_cache', ['cache_key' => $key, 'value' => $value, 'expires_at' => $expires]);
        }
    }
}
