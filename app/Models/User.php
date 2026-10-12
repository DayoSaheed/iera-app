<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Str;

final class User
{
    public const TYPES = ['volunteer', 'newmuslim', 'org', 'superadmin'];
    public const GROUPS = ['volunteer', 'newmuslim', 'org'];
    public const SUB_GROUPS = ['mentor', 'daee'];

    /** Status vocabulary (single source of truth for the whole app). */
    public const VOLUNTEER_STATUSES = [
        'new' => 'New (not registered)',
        'enrolled' => 'Enrolled',
        'id_verified' => 'ID Verified',
        'training_video' => 'Proof of Training',
        'in_review' => 'In Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'paused' => 'Paused',
        'inactive' => 'Inactive',
        'removed' => 'Removed',
    ];
    public const NEWMUSLIM_STATUSES = [
        'new' => 'New (not registered)',
        'enrolled' => 'Registered',
        'in_review' => 'In Review',
        'approved' => 'Approved',
        'completed' => 'Training Completed',
        'inactive' => 'Inactive',
        'removed' => 'Removed',
    ];
    public const ORG_STATUSES = ['approved' => 'Approved', 'inactive' => 'Inactive'];

    /** Real columns; everything else goes to profile_json. */
    public const COLUMNS = [
        'email', 'password_hash', 'type', 'status', 'is_sysadmin', 'first_name', 'last_name', 'gender', 'mobile', 'phone',
        'address', 'postal_code', 'city', 'province', 'country', 'birth_year', 'pin', 'position', 'enrollment_method',
        'previous_religion', 'shahada_location', 'referrer_name', 'referrer_email', 'training_type', 'training_date',
        'stripe_verification_id', 'stripe_status', 'comments', 'paused_until', 'created_by', 'last_login_at',
        'totp_secret', 'totp_enabled',
    ];

    public static function idForEmail(string $email): string
    {
        return Str::uuid5(strtolower(trim($email)));
    }

    public static function find(?string $id, bool $withRelations = true): ?array
    {
        if (!$id) {
            return null;
        }
        $row = DB::fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$row) {
            return null;
        }
        $rows = [$row];
        self::hydrate($rows, $withRelations);
        return $rows[0];
    }

    public static function findByEmail(string $email, bool $withRelations = true): ?array
    {
        $row = DB::fetch('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
        if (!$row) {
            return null;
        }
        $rows = [$row];
        self::hydrate($rows, $withRelations);
        return $rows[0];
    }

    public static function exists(string $id): bool
    {
        return (bool) DB::fetchColumn('SELECT 1 FROM users WHERE id = ?', [$id]);
    }

    /** Decode profile JSON and attach groups/sub groups/teams/admin teams for a list of rows. */
    public static function hydrate(array &$rows, bool $withRelations = true): void
    {
        if (!$rows) {
            return;
        }
        foreach ($rows as &$r) {
            $r['profile'] = $r['profile_json'] ? (json_decode((string) $r['profile_json'], true) ?: []) : [];
            unset($r['profile_json']);
            $r['name'] = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            $r['groups'] = [];
            $r['sub_groups'] = [];
            $r['teams'] = [];
            $r['admin_teams'] = [];
        }
        unset($r);
        if (!$withRelations) {
            return;
        }
        $ids = array_column($rows, 'id');
        $index = array_flip($ids);
        [$in, $params] = DB::in($ids);
        foreach (DB::fetchAll("SELECT user_id, grp FROM user_groups WHERE user_id IN $in", $params) as $g) {
            $rows[$index[$g['user_id']]]['groups'][] = $g['grp'];
        }
        foreach (DB::fetchAll("SELECT user_id, sub_group FROM user_sub_groups WHERE user_id IN $in", $params) as $g) {
            $rows[$index[$g['user_id']]]['sub_groups'][] = $g['sub_group'];
        }
        foreach (DB::fetchAll("SELECT user_id, team_id FROM team_members WHERE user_id IN $in", $params) as $g) {
            $rows[$index[$g['user_id']]]['teams'][] = $g['team_id'];
        }
        foreach (DB::fetchAll("SELECT user_id, team_id FROM team_admins WHERE user_id IN $in", $params) as $g) {
            $rows[$index[$g['user_id']]]['admin_teams'][] = $g['team_id'];
        }
    }

    /** Create a user. Returns the new id. */
    public static function create(array $data): string
    {
        $email = strtolower(trim((string) $data['email']));
        $id = $data['id'] ?? self::idForEmail($email);
        $data['email'] = $email;
        [$columns, $profile] = self::split($data);
        $columns['id'] = $id;
        $columns['type'] ??= 'volunteer';
        $columns['status'] ??= 'new';
        $columns['profile_json'] = $profile ? json_encode($profile, JSON_UNESCAPED_UNICODE) : null;
        $columns['created_at'] = now();
        $columns['updated_at'] = now();
        DB::insert('users', $columns);
        if (!empty($data['groups'])) {
            self::setGroups($id, (array) $data['groups']);
        }
        if (!empty($data['sub_groups'])) {
            self::setSubGroups($id, (array) $data['sub_groups']);
        }
        if (!empty($data['teams'])) {
            self::setTeams($id, (array) $data['teams']);
        }
        if (!empty($data['admin_teams'])) {
            self::setAdminTeams($id, (array) $data['admin_teams']);
        }
        return $id;
    }

    /**
     * Update columns and merge profile fields. Callers must pass only whitelisted keys.
     * Relations (groups, sub_groups, teams, admin_teams) are replaced when present.
     */
    public static function save(string $id, array $data): void
    {
        unset($data['id']);
        [$columns, $profile] = self::split($data);
        if ($profile) {
            $current = DB::fetchColumn('SELECT profile_json FROM users WHERE id = ?', [$id]);
            $merged = array_merge($current ? (json_decode((string) $current, true) ?: []) : [], $profile);
            $columns['profile_json'] = json_encode($merged, JSON_UNESCAPED_UNICODE);
        }
        if (isset($columns['email'])) {
            $columns['email'] = strtolower(trim((string) $columns['email']));
        }
        $columns['updated_at'] = now();
        DB::update('users', $columns, 'id = ?', [$id]);
        if (array_key_exists('groups', $data)) {
            self::setGroups($id, (array) $data['groups']);
        }
        if (array_key_exists('sub_groups', $data)) {
            self::setSubGroups($id, (array) $data['sub_groups']);
        }
        if (array_key_exists('teams', $data)) {
            self::setTeams($id, (array) $data['teams']);
        }
        if (array_key_exists('admin_teams', $data)) {
            self::setAdminTeams($id, (array) $data['admin_teams']);
        }
    }

    private static function split(array $data): array
    {
        $columns = [];
        $profile = [];
        foreach ($data as $k => $v) {
            if (in_array($k, ['groups', 'sub_groups', 'teams', 'admin_teams', 'profile', 'id', 'name'], true)) {
                continue;
            }
            if (in_array($k, self::COLUMNS, true)) {
                if ($k === 'birth_year') {
                    $v = $v === '' || $v === null ? null : (int) $v;
                }
                if (in_array($k, ['training_date', 'paused_until', 'last_login_at'], true) && $v === '') {
                    $v = null;
                }
                $columns[$k] = is_array($v) ? json_encode($v) : $v;
            } else {
                $profile[$k] = $v;
            }
        }
        if (isset($data['profile']) && is_array($data['profile'])) {
            $profile = array_merge($profile, $data['profile']);
        }
        return [$columns, $profile];
    }

    private static function syncPivot(string $table, string $column, string $userId, array $values, array $allowed = null): void
    {
        $values = array_values(array_unique(array_filter(array_map('strval', $values))));
        if ($allowed !== null) {
            $values = array_values(array_intersect($values, $allowed));
        }
        DB::transaction(function () use ($table, $column, $userId, $values) {
            DB::delete($table, 'user_id = ?', [$userId]);
            foreach ($values as $v) {
                $row = ['user_id' => $userId, $column => $v];
                if ($table === 'team_members' || $table === 'team_admins') {
                    $row['created_at'] = now();
                }
                DB::insert($table, $row);
            }
        });
    }

    public static function setGroups(string $id, array $groups): void
    {
        self::syncPivot('user_groups', 'grp', $id, $groups, self::GROUPS);
    }

    public static function setSubGroups(string $id, array $subs): void
    {
        self::syncPivot('user_sub_groups', 'sub_group', $id, $subs, self::SUB_GROUPS);
    }

    public static function setTeams(string $id, array $teams): void
    {
        self::syncPivot('team_members', 'team_id', $id, $teams);
    }

    public static function setAdminTeams(string $id, array $teams): void
    {
        self::syncPivot('team_admins', 'team_id', $id, $teams);
    }

    public static function addToTeam(string $id, string $teamId): void
    {
        if (!DB::fetchColumn('SELECT 1 FROM team_members WHERE user_id = ? AND team_id = ?', [$id, $teamId])) {
            DB::insert('team_members', ['user_id' => $id, 'team_id' => $teamId, 'created_at' => now()]);
        }
    }

    public static function touchLogin(string $id): void
    {
        DB::update('users', ['last_login_at' => now()], 'id = ?', [$id]);
    }

    public static function delete(string $id): void
    {
        DB::delete('users', 'id = ?', [$id]);
    }

    /**
     * List users with filters: type, group, status, team, sub_group, created_by, search, not_status, ids.
     */
    public static function list(array $f = [], bool $withRelations = true): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($f['type'])) {
            $where[] = 'u.type = ?';
            $params[] = $f['type'];
        }
        if (!empty($f['group'])) {
            $where[] = 'EXISTS (SELECT 1 FROM user_groups g WHERE g.user_id = u.id AND g.grp = ?)';
            $params[] = $f['group'];
        }
        if (!empty($f['no_groups'])) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM user_groups g WHERE g.user_id = u.id)';
        }
        if (!empty($f['sub_group'])) {
            $where[] = 'EXISTS (SELECT 1 FROM user_sub_groups s WHERE s.user_id = u.id AND s.sub_group = ?)';
            $params[] = $f['sub_group'];
        }
        if (!empty($f['team'])) {
            $where[] = '(EXISTS (SELECT 1 FROM team_members t WHERE t.user_id = u.id AND t.team_id = ?) OR EXISTS (SELECT 1 FROM team_admins a WHERE a.user_id = u.id AND a.team_id = ?))';
            $params[] = $f['team'];
            $params[] = $f['team'];
        }
        if (!empty($f['teams']) && is_array($f['teams'])) {
            [$in, $p] = DB::in($f['teams']);
            $where[] = "(EXISTS (SELECT 1 FROM team_members t WHERE t.user_id = u.id AND t.team_id IN $in) OR EXISTS (SELECT 1 FROM team_admins a WHERE a.user_id = u.id AND a.team_id IN $in))";
            $params = array_merge($params, $p, $p);
        }
        if (!empty($f['status'])) {
            $statuses = (array) $f['status'];
            [$in, $p] = DB::in($statuses);
            $where[] = "u.status IN $in";
            $params = array_merge($params, $p);
        }
        if (!empty($f['not_status'])) {
            [$in, $p] = DB::in((array) $f['not_status']);
            $where[] = "u.status NOT IN $in";
            $params = array_merge($params, $p);
        }
        if (!empty($f['created_by'])) {
            $where[] = 'u.created_by = ?';
            $params[] = $f['created_by'];
        }
        if (!empty($f['ids'])) {
            [$in, $p] = DB::in((array) $f['ids']);
            $where[] = "u.id IN $in";
            $params = array_merge($params, $p);
        }
        if (!empty($f['search'])) {
            $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.city LIKE ?)';
            $like = '%' . $f['search'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($f['exclude_type'])) {
            $where[] = 'u.type <> ?';
            $params[] = $f['exclude_type'];
        }
        $order = $f['order'] ?? 'u.created_at DESC';
        $rows = DB::fetchAll('SELECT u.* FROM users u WHERE ' . implode(' AND ', $where) . " ORDER BY $order", $params);
        self::hydrate($rows, $withRelations);
        return $rows;
    }

    /** id => "First Last" for a list of ids */
    public static function names(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            return [];
        }
        [$in, $p] = DB::in($ids);
        $out = [];
        foreach (DB::fetchAll("SELECT id, first_name, last_name, email FROM users WHERE id IN $in", $p) as $r) {
            $out[$r['id']] = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: $r['email'];
        }
        return $out;
    }

    public static function fullName(?array $u): string
    {
        if (!$u) {
            return '';
        }
        return trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: (string) ($u['email'] ?? '');
    }

    public static function phoneOf(?array $u): string
    {
        return (string) (($u['mobile'] ?? '') ?: ($u['phone'] ?? ''));
    }

    public static function statusLabel(array $u): string
    {
        $map = $u['type'] === 'newmuslim' ? self::NEWMUSLIM_STATUSES : ($u['type'] === 'org' ? self::ORG_STATUSES : self::VOLUNTEER_STATUSES);
        return $map[$u['status']] ?? Str::startCase((string) $u['status']);
    }

    public static function nextPin(): string
    {
        if (DB::driver() === 'sqlite') {
            $max = (int) DB::fetchColumn("SELECT MAX(CAST(pin AS INTEGER)) FROM users WHERE pin GLOB '[0-9]*'");
        } else {
            $max = (int) DB::fetchColumn("SELECT MAX(CAST(pin AS UNSIGNED)) FROM users WHERE pin REGEXP '^[0-9]+$'");
        }
        return (string) ($max + 1);
    }

    public static function countBy(string $type, array $statuses = []): int
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE type = ?';
        $p = [$type];
        if ($statuses) {
            [$in, $ip] = DB::in($statuses);
            $sql .= " AND status IN $in";
            $p = array_merge($p, $ip);
        }
        return (int) DB::fetchColumn($sql, $p);
    }
}
