<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Str;

final class Team
{
    public static function all(?string $parent = null, ?string $status = null): array
    {
        $where = ['1=1'];
        $p = [];
        if ($parent) {
            $where[] = 'parent_team = ?';
            $p[] = $parent;
        }
        if ($status) {
            $where[] = 'status = ?';
            $p[] = $status;
        }
        return DB::fetchAll('SELECT * FROM teams WHERE ' . implode(' AND ', $where) . ' ORDER BY name', $p);
    }

    public static function find(string $id): ?array
    {
        return DB::fetch('SELECT * FROM teams WHERE id = ?', [$id]);
    }

    public static function byIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        [$in, $p] = DB::in($ids);
        return DB::fetchAll("SELECT * FROM teams WHERE id IN $in ORDER BY name", $p);
    }

    /** id => name map for all teams */
    public static function names(): array
    {
        $out = [];
        foreach (DB::fetchAll('SELECT id, name FROM teams') as $t) {
            $out[$t['id']] = $t['name'];
        }
        return $out;
    }

    public static function nameOf(?string $id): string
    {
        if (!$id) {
            return '';
        }
        return (string) (DB::fetchColumn('SELECT name FROM teams WHERE id = ?', [$id]) ?? Str::startCase($id));
    }

    public static function create(array $data): string
    {
        $id = $data['id'] ?? Str::slug((string) $data['name']);
        if (self::find($id)) {
            $id .= '_' . substr(Str::randomToken(2), 0, 4);
        }
        DB::insert('teams', [
            'id' => $id,
            'name' => trim((string) $data['name']),
            'parent_team' => $data['parent_team'] ?: null,
            'status' => $data['status'] ?? 'approved',
            'description' => $data['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $id;
    }

    public static function update(string $id, array $data): void
    {
        $allowed = array_intersect_key($data, array_flip(['name', 'parent_team', 'status', 'description']));
        if (array_key_exists('parent_team', $allowed) && $allowed['parent_team'] === '') {
            $allowed['parent_team'] = null;
        }
        $allowed['updated_at'] = now();
        DB::update('teams', $allowed, 'id = ?', [$id]);
    }

    public static function members(string $teamId, bool $includeInactive = false): array
    {
        $sql = 'SELECT u.* FROM users u JOIN team_members m ON m.user_id = u.id WHERE m.team_id = ?';
        if (!$includeInactive) {
            $sql .= " AND u.status NOT IN ('inactive','removed','rejected')";
        }
        $rows = DB::fetchAll($sql . ' ORDER BY u.first_name, u.last_name', [$teamId]);
        User::hydrate($rows);
        return $rows;
    }

    public static function admins(string $teamId): array
    {
        $rows = DB::fetchAll('SELECT u.* FROM users u JOIN team_admins a ON a.user_id = u.id WHERE a.team_id = ? ORDER BY u.first_name', [$teamId]);
        User::hydrate($rows, false);
        return $rows;
    }

    public static function adminEmails(string $teamId): array
    {
        return array_column(DB::fetchAll('SELECT u.email FROM users u JOIN team_admins a ON a.user_id = u.id WHERE a.team_id = ?', [$teamId]), 'email');
    }

    /** Attach members_count, admins_count, active_events (future) to each team row. */
    public static function withCounts(array $teams, bool $past = false): array
    {
        if (!$teams) {
            return [];
        }
        $ids = array_column($teams, 'id');
        [$in, $p] = DB::in($ids);
        $members = [];
        foreach (DB::fetchAll("SELECT team_id, COUNT(*) c FROM team_members WHERE team_id IN $in GROUP BY team_id", $p) as $r) {
            $members[$r['team_id']] = (int) $r['c'];
        }
        $admins = [];
        foreach (DB::fetchAll("SELECT team_id, COUNT(*) c FROM team_admins WHERE team_id IN $in GROUP BY team_id", $p) as $r) {
            $admins[$r['team_id']] = (int) $r['c'];
        }
        $cmp = $past ? '<' : '>=';
        $events = [];
        foreach (DB::fetchAll("SELECT team_id, COUNT(*) c FROM sub_events WHERE status = 'active' AND start_at $cmp ? AND team_id IN $in GROUP BY team_id", array_merge([now()], $p)) as $r) {
            $events[$r['team_id']] = (int) $r['c'];
        }
        foreach ($teams as &$t) {
            $t['members_count'] = $members[$t['id']] ?? 0;
            $t['admins_count'] = $admins[$t['id']] ?? 0;
            $t['active_events'] = $events[$t['id']] ?? 0;
        }
        return $teams;
    }

    public static function parents(): array
    {
        return array_column(DB::fetchAll('SELECT DISTINCT parent_team FROM teams WHERE parent_team IS NOT NULL ORDER BY parent_team'), 'parent_team');
    }
}
