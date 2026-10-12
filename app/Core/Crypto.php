<?php
declare(strict_types=1);

namespace App\Core;

/** AES-256-GCM encryption for secrets stored in the database, keyed from app.key. */
final class Crypto
{
    private static function key(): string
    {
        return hash('sha256', (string) Config::get('app.key', ''), true);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return 'enc:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $stored): string
    {
        if ($stored === null || $stored === '') {
            return '';
        }
        if (!str_starts_with($stored, 'enc:')) {
            return $stored; // legacy plain value
        }
        $raw = base64_decode(substr($stored, 4), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }
}
