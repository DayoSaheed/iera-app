<?php
declare(strict_types=1);

namespace App\Services;

/** Canadian postal code -> city lookup and city autocomplete (offline, from data/postal_codes_ca.json). */
final class Geo
{
    private static ?array $map = null;

    private static function map(): array
    {
        if (self::$map === null) {
            $file = BASE_PATH . '/data/postal_codes_ca.json';
            self::$map = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        }
        return self::$map;
    }

    public static function cityForPostal(?string $postal): ?string
    {
        $fsa = strtoupper(substr(preg_replace('/\s+/', '', (string) $postal) ?? '', 0, 3));
        $city = self::map()[$fsa] ?? '';
        return $city !== '' ? $city : null;
    }

    /** Distinct "City, PR" values matching a query. */
    public static function searchCities(string $q, int $limit = 8): array
    {
        $q = mb_strtolower(trim($q));
        if ($q === '') {
            return [];
        }
        $seen = [];
        foreach (self::map() as $city) {
            if ($city === '' || isset($seen[$city])) {
                continue;
            }
            if (str_starts_with(mb_strtolower($city), $q) || str_contains(mb_strtolower($city), ' ' . $q)) {
                $seen[$city] = true;
                if (count($seen) >= $limit) {
                    break;
                }
            }
        }
        $out = array_keys($seen);
        sort($out);
        return $out;
    }

    public static function normalizePostal(?string $postal): ?string
    {
        $p = strtoupper(preg_replace('/\s+/', '', (string) $postal) ?? '');
        if (!preg_match('/^[A-Z]\d[A-Z]\d[A-Z]\d$/', $p)) {
            return $postal ?: null;
        }
        return substr($p, 0, 3) . ' ' . substr($p, 3);
    }
}
