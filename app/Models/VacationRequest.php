<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Str;

final class VacationRequest
{
    public const STATUSES = ['requested' => 'Requested', 'approved' => 'Approved', 'denied' => 'Denied'];

    public static function find(string $id): ?array
    {
        return DB::fetch('SELECT * FROM vacation_requests WHERE id = ?', [$id]);
    }

    public static function forUser(string $userId): array
    {
        return DB::fetchAll('SELECT * FROM vacation_requests WHERE user_id = ? ORDER BY created_at DESC', [$userId]);
    }

    public static function all(?array $teamIds = null): array
    {
        $sql = 'SELECT v.*, u.first_name, u.last_name, u.email FROM vacation_requests v JOIN users u ON u.id = v.user_id';
        $p = [];
        if ($teamIds !== null) {
            if (!$teamIds) {
                return [];
            }
            [$in, $ip] = DB::in($teamIds);
            $sql .= " WHERE EXISTS (SELECT 1 FROM team_members m WHERE m.user_id = v.user_id AND m.team_id IN $in)";
            $p = $ip;
        }
        return DB::fetchAll($sql . ' ORDER BY v.created_at DESC', $p);
    }

    public static function create(string $userId, array $data): string
    {
        $id = Str::uuid();
        DB::insert('vacation_requests', [
            'id' => $id,
            'user_id' => $userId,
            'reason' => $data['reason'] ?? null,
            'from_date' => $data['from_date'] ?: null,
            'to_date' => $data['to_date'] ?: null,
            'status' => 'requested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $id;
    }

    public static function update(string $id, array $data): void
    {
        $row = array_intersect_key($data, array_flip(['reason', 'from_date', 'to_date', 'status', 'decided_by', 'decided_at']));
        $row['updated_at'] = now();
        DB::update('vacation_requests', $row, 'id = ?', [$id]);
    }

    /** Users currently on an approved vacation (for reminder suppression). */
    public static function onVacationUserIds(): array
    {
        $today = date('Y-m-d');
        return array_column(DB::fetchAll("SELECT DISTINCT user_id FROM vacation_requests WHERE status = 'approved' AND from_date <= ? AND to_date >= ?", [$today, $today]), 'user_id');
    }
}
