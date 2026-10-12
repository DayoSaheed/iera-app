<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\DB;

final class AuditLog
{
    public static function log(string $action, ?string $entityType = null, ?string $entityId = null, mixed $details = null, ?string $actorId = null): void
    {
        try {
            DB::insert('audit_log', [
                'actor_id' => $actorId ?? ($_SESSION['uid'] ?? null),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => $details === null ? null : (is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE)),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // never break the request because of audit logging
        }
    }

    public static function recent(int $limit = 200, ?string $search = null): array
    {
        $sql = 'SELECT l.*, u.first_name, u.last_name, u.email FROM audit_log l LEFT JOIN users u ON u.id = l.actor_id';
        $p = [];
        if ($search) {
            $sql .= ' WHERE l.action LIKE ? OR l.entity_id LIKE ? OR l.details LIKE ? OR u.email LIKE ?';
            $like = '%' . $search . '%';
            $p = [$like, $like, $like, $like];
        }
        return DB::fetchAll($sql . " ORDER BY l.id DESC LIMIT $limit", $p);
    }

    public static function forEntity(string $type, string $id, int $limit = 50): array
    {
        return DB::fetchAll(
            "SELECT l.*, u.first_name, u.last_name FROM audit_log l LEFT JOIN users u ON u.id = l.actor_id WHERE l.entity_type = ? AND l.entity_id = ? ORDER BY l.id DESC LIMIT $limit",
            [$type, $id]
        );
    }
}
