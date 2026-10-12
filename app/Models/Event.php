<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Str;

final class Event
{
    public const FIELDS = ['team_id', 'name', 'description', 'city', 'min_participants', 'event_type', 'is_public', 'active_events', 'start_at', 'event_length', 'recurrence', 'status', 'author_id'];

    public static function find(string $id): ?array
    {
        return DB::fetch('SELECT * FROM events WHERE id = ?', [$id]);
    }

    public static function forTeam(string $teamId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM events WHERE team_id = ?';
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        return DB::fetchAll($sql . ' ORDER BY start_at DESC', [$teamId]);
    }

    public static function create(array $data): string
    {
        $id = Str::uuid();
        $row = array_intersect_key($data, array_flip(self::FIELDS));
        $row['id'] = $id;
        $row['created_at'] = now();
        $row['updated_at'] = now();
        DB::insert('events', $row);
        return $id;
    }

    public static function update(string $id, array $data): void
    {
        $row = array_intersect_key($data, array_flip(self::FIELDS));
        $row['updated_at'] = now();
        DB::update('events', $row, 'id = ?', [$id]);
    }
}
