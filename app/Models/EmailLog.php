<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class EmailLog
{
    public static function record(array $message, string $status, ?string $error): void
    {
        try {
            DB::insert('email_log', [
                'to_email' => substr(implode(', ', array_column($message['to'] ?? [], 'email')), 0, 500),
                'subject' => substr((string) ($message['subject'] ?: ('[template ' . ($message['template_id'] ?? '') . ']')), 0, 255),
                'template_key' => $message['template_key'] ?? null,
                'status' => $status,
                'driver' => $message['driver'] ?? null,
                'error' => $error,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    public static function recent(int $limit = 200, ?string $search = null): array
    {
        $sql = 'SELECT * FROM email_log';
        $p = [];
        if ($search) {
            $sql .= ' WHERE to_email LIKE ? OR subject LIKE ? OR template_key LIKE ?';
            $like = '%' . $search . '%';
            $p = [$like, $like, $like];
        }
        return DB::fetchAll($sql . " ORDER BY id DESC LIMIT $limit", $p);
    }

    public static function countSince(string $since, ?string $status = null): int
    {
        $sql = 'SELECT COUNT(*) FROM email_log WHERE created_at >= ?';
        $p = [$since];
        if ($status) {
            $sql .= ' AND status = ?';
            $p[] = $status;
        }
        return (int) DB::fetchColumn($sql, $p);
    }

    public static function purge(int $days = 90): void
    {
        DB::delete('email_log', 'created_at < ?', [date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
