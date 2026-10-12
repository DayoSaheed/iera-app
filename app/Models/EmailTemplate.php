<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class EmailTemplate
{
    public static function all(): array
    {
        return DB::fetchAll('SELECT * FROM email_templates ORDER BY name');
    }

    public static function get(string $key): ?array
    {
        return DB::fetch('SELECT * FROM email_templates WHERE tpl_key = ?', [$key]);
    }

    public static function save(string $key, array $data): void
    {
        $row = array_intersect_key($data, array_flip(['name', 'subject', 'body_html', 'sendgrid_template_id', 'enabled', 'description', 'variables']));
        if (isset($row['sendgrid_template_id']) && $row['sendgrid_template_id'] === '') {
            $row['sendgrid_template_id'] = null;
        }
        $row['updated_at'] = now();
        if (self::get($key)) {
            DB::update('email_templates', $row, 'tpl_key = ?', [$key]);
        } else {
            $row['tpl_key'] = $key;
            DB::insert('email_templates', $row);
        }
    }
}
