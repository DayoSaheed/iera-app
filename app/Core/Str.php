<?php
declare(strict_types=1);

namespace App\Core;

final class Str
{
    /** RFC 4122 v4 UUID */
    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** Deterministic v5-style UUID from a string (used to keep legacy ids stable on import). */
    public static function uuid5(string $name, string $namespace = '6ba7b811-9dad-11d1-80b4-00c04fd430c8'): string
    {
        $ns = hex2bin(str_replace('-', '', $namespace));
        $hash = sha1($ns . $name);
        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );
    }

    public static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '_', $value) ?? '';
        return trim($value, '_');
    }

    public static function startCase(string $value): string
    {
        $value = preg_replace('/[_\-]+/', ' ', $value) ?? $value;
        $value = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value) ?? $value;
        return ucwords(trim($value));
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function numericCode(int $digits = 6): string
    {
        $max = (10 ** $digits) - 1;
        return str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);
    }

    public static function limit(string $value, int $max = 80): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 1) . '…' : $value;
    }

    public static function formatPhone(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
        }
        return (string) $value;
    }
}
