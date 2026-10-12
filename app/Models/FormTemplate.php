<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class FormTemplate
{
    private static array $cache = [];

    public static function all(): array
    {
        return DB::fetchAll('SELECT id, label, updated_at FROM form_templates ORDER BY id');
    }

    /** @return array<int, array> field definitions */
    public static function fields(string $id): array
    {
        if (!isset(self::$cache[$id])) {
            $json = DB::fetchColumn('SELECT fields_json FROM form_templates WHERE id = ?', [$id]);
            self::$cache[$id] = $json ? (json_decode((string) $json, true) ?: []) : [];
        }
        return self::$cache[$id];
    }

    public static function get(string $id): ?array
    {
        $row = DB::fetch('SELECT * FROM form_templates WHERE id = ?', [$id]);
        if ($row) {
            $row['fields'] = json_decode($row['fields_json'], true) ?: [];
        }
        return $row;
    }

    public static function save(string $id, string $label, array $fields): void
    {
        $json = json_encode(array_values($fields), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (DB::fetchColumn('SELECT 1 FROM form_templates WHERE id = ?', [$id])) {
            DB::update('form_templates', ['label' => $label, 'fields_json' => $json, 'updated_at' => now()], 'id = ?', [$id]);
        } else {
            DB::insert('form_templates', ['id' => $id, 'label' => $label, 'fields_json' => $json, 'updated_at' => now()]);
        }
        unset(self::$cache[$id]);
    }

    /** Label of an option value for a select field in a template. */
    public static function optionLabel(string $templateId, string $fieldName, mixed $value): string
    {
        foreach (self::fields($templateId) as $f) {
            if (($f['name'] ?? '') === $fieldName) {
                foreach ($f['options'] ?? [] as $o) {
                    if ((string) ($o['value'] ?? '') === (string) $value) {
                        return (string) ($o['label'] ?? $value);
                    }
                }
            }
        }
        return (string) $value;
    }
}
