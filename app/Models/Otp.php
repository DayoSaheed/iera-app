<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Str;

/** One-time codes for passwordless login and sensitive confirmations. */
final class Otp
{
    public const TTL_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;

    public static function issue(string $userId, string $purpose, ?string $ip): string
    {
        DB::update('otp_codes', ['used_at' => now()], 'user_id = ? AND purpose = ? AND used_at IS NULL', [$userId, $purpose]);
        $code = Str::numericCode(6);
        DB::insert('otp_codes', [
            'user_id' => $userId,
            'purpose' => $purpose,
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60),
            'attempts' => 0,
            'ip' => $ip,
            'created_at' => now(),
        ]);
        return $code;
    }

    /** Returns true when the code matches a live, unused code; consumes it. */
    public static function verify(string $userId, string $purpose, string $code): bool
    {
        $row = DB::fetch(
            'SELECT * FROM otp_codes WHERE user_id = ? AND purpose = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1',
            [$userId, $purpose]
        );
        if (!$row || strtotime($row['expires_at']) < time() || (int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }
        if (!password_verify(preg_replace('/\D+/', '', $code) ?? '', $row['code_hash'])) {
            DB::execute('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            return false;
        }
        DB::update('otp_codes', ['used_at' => now()], 'id = ?', [$row['id']]);
        return true;
    }

    /** Seconds since the last code was issued (for resend throttling). */
    public static function secondsSinceLast(string $userId, string $purpose): int
    {
        $last = DB::fetchColumn('SELECT created_at FROM otp_codes WHERE user_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1', [$userId, $purpose]);
        return $last ? time() - strtotime((string) $last) : PHP_INT_MAX;
    }

    public static function purge(): void
    {
        DB::delete('otp_codes', 'created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}
