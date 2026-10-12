<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Simple rate limiting for login related endpoints. */
final class LoginAttempt
{
    public static function record(string $identifier, string $ip): void
    {
        DB::insert('login_attempts', ['identifier' => strtolower($identifier), 'ip' => $ip, 'created_at' => now()]);
    }

    public static function tooMany(string $identifier, string $ip, int $maxPerIdentifier = 10, int $maxPerIp = 30, int $minutes = 15): bool
    {
        $since = date('Y-m-d H:i:s', time() - $minutes * 60);
        $byId = (int) DB::fetchColumn('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at > ?', [strtolower($identifier), $since]);
        $byIp = (int) DB::fetchColumn('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [$ip, $since]);
        return $byId >= $maxPerIdentifier || $byIp >= $maxPerIp;
    }

    public static function clear(string $identifier): void
    {
        DB::delete('login_attempts', 'identifier = ?', [strtolower($identifier)]);
    }

    public static function purge(): void
    {
        DB::delete('login_attempts', 'created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}
