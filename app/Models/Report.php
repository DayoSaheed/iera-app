<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Str;

final class Report
{
    public const TYPES = ['event_report' => 'Event Report', 'mentor' => 'Mentor Report'];

    public static function find(string $id): ?array
    {
        $r = DB::fetch('SELECT * FROM reports WHERE id = ?', [$id]);
        if ($r) {
            $r['submit'] = $r['submit_json'] ? (json_decode($r['submit_json'], true) ?: []) : [];
        }
        return $r;
    }

    public static function exists(string $type, string $subjectId, string $assigneeId, string $periodKey = 'event'): bool
    {
        return (bool) DB::fetchColumn('SELECT 1 FROM reports WHERE type = ? AND subject_id = ? AND assignee_id = ? AND period_key = ?', [$type, $subjectId, $assigneeId, $periodKey]);
    }

    public static function create(string $type, string $subjectId, string $assigneeId, string $periodKey = 'event'): ?string
    {
        if (self::exists($type, $subjectId, $assigneeId, $periodKey)) {
            return null;
        }
        $id = Str::uuid();
        DB::insert('reports', [
            'id' => $id,
            'type' => $type,
            'subject_id' => $subjectId,
            'assignee_id' => $assigneeId,
            'period_key' => $periodKey,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $id;
    }

    public static function submit(string $id, array $data, string $byUserId, bool $onBehalf): void
    {
        DB::update('reports', [
            'submit_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'status' => 'submitted',
            'submitted_by' => $onBehalf ? $byUserId : null,
            'submitted_at' => now(),
            'updated_at' => now(),
            'sharepoint_error' => null,
        ], 'id = ?', [$id]);
    }

    /**
     * List reports with context (sub event + team for event reports, mentor + mentee for mentor reports).
     * Filters: type, status, assignee, teams (array of team ids), subject.
     */
    public static function list(array $f): array
    {
        $type = $f['type'] ?? 'event_report';
        $where = ['r.type = ?'];
        $p = [$type];
        if (!empty($f['status'])) {
            $where[] = 'r.status = ?';
            $p[] = $f['status'];
        }
        if (!empty($f['assignee'])) {
            $where[] = 'r.assignee_id = ?';
            $p[] = $f['assignee'];
        }
        if (!empty($f['subject'])) {
            $where[] = 'r.subject_id = ?';
            $p[] = $f['subject'];
        }
        if (!empty($f['from'])) {
            $where[] = 'r.created_at >= ?';
            $p[] = $f['from'];
        }
        if (!empty($f['to'])) {
            $where[] = 'r.created_at <= ?';
            $p[] = $f['to'];
        }
        if ($type === 'event_report') {
            $sql = 'SELECT r.*, s.name AS event_name, s.start_at, s.event_length, s.city AS event_city, s.team_id, t.name AS team_name,
                       u.first_name, u.last_name, u.email, u.mobile, u.city
                    FROM reports r
                    JOIN sub_events s ON s.id = r.subject_id
                    JOIN teams t ON t.id = s.team_id
                    JOIN users u ON u.id = r.assignee_id';
            if (!empty($f['teams'])) {
                [$in, $ip] = DB::in((array) $f['teams']);
                $where[] = "s.team_id IN $in";
                $p = array_merge($p, $ip);
            }
        } else {
            $sql = 'SELECT r.*, n.first_name AS mentee_first_name, n.last_name AS mentee_last_name, n.email AS mentee_email,
                       u.first_name, u.last_name, u.email, u.mobile, u.city
                    FROM reports r
                    JOIN users n ON n.id = r.subject_id
                    JOIN users u ON u.id = r.assignee_id';
            if (!empty($f['teams'])) {
                [$in, $ip] = DB::in((array) $f['teams']);
                $where[] = "EXISTS (SELECT 1 FROM team_members tm WHERE tm.user_id = r.assignee_id AND tm.team_id IN $in)";
                $p = array_merge($p, $ip);
            }
        }
        $order = !empty($f['status']) && $f['status'] === 'submitted' ? 'r.submitted_at DESC' : 'r.created_at DESC';
        $rows = DB::fetchAll($sql . ' WHERE ' . implode(' AND ', $where) . " ORDER BY $order", $p);
        $teamMap = null;
        foreach ($rows as &$r) {
            $r['submit'] = $r['submit_json'] ? (json_decode($r['submit_json'], true) ?: []) : [];
            $r['assignee_name'] = trim($r['first_name'] . ' ' . $r['last_name']);
            if ($type === 'mentor') {
                $r['mentee_name'] = trim($r['mentee_first_name'] . ' ' . $r['mentee_last_name']);
                $teamMap ??= self::teamsOfUsers(array_column($rows, 'assignee_id'));
                $r['team_names'] = $teamMap[$r['assignee_id']] ?? [];
            } else {
                $r['team_names'] = [$r['team_name']];
            }
        }
        return $rows;
    }

    private static function teamsOfUsers(array $userIds): array
    {
        $userIds = array_values(array_unique($userIds));
        if (!$userIds) {
            return [];
        }
        [$in, $p] = DB::in($userIds);
        $out = [];
        foreach (DB::fetchAll("SELECT m.user_id, t.name FROM team_members m JOIN teams t ON t.id = m.team_id WHERE m.user_id IN $in", $p) as $r) {
            $out[$r['user_id']][] = $r['name'];
        }
        return $out;
    }

    /** Submitted reports for a subject (used for "past reports" and lesson sequence). */
    public static function submittedFor(string $type, string $subjectId): array
    {
        $rows = DB::fetchAll(
            "SELECT r.*, u.first_name, u.last_name FROM reports r JOIN users u ON u.id = r.assignee_id WHERE r.type = ? AND r.subject_id = ? AND r.status = 'submitted' ORDER BY r.submitted_at DESC",
            [$type, $subjectId]
        );
        foreach ($rows as &$r) {
            $r['submit'] = $r['submit_json'] ? (json_decode($r['submit_json'], true) ?: []) : [];
            $r['assignee_name'] = trim($r['first_name'] . ' ' . $r['last_name']);
        }
        return $rows;
    }

    public static function pendingOlderThan(string $type, int $hours, string $notifKind): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - $hours * 3600);
        return DB::fetchAll(
            "SELECT r.* FROM reports r WHERE r.type = ? AND r.status = 'pending' AND r.created_at <= ?
             AND NOT EXISTS (SELECT 1 FROM report_notifications n WHERE n.report_id = r.id AND n.kind = ?)",
            [$type, $cutoff, $notifKind]
        );
    }

    public static function markNotified(string $reportId, string $kind): void
    {
        if (!DB::fetchColumn('SELECT 1 FROM report_notifications WHERE report_id = ? AND kind = ?', [$reportId, $kind])) {
            DB::insert('report_notifications', ['report_id' => $reportId, 'kind' => $kind, 'sent_at' => now()]);
        }
    }

    /** Submitted reports not yet synced to SharePoint. */
    public static function unsynced(string $type, int $maxAttempts = 10, int $limit = 50): array
    {
        $rows = DB::fetchAll(
            "SELECT * FROM reports WHERE type = ? AND status = 'submitted' AND sharepoint_entry_id IS NULL AND sharepoint_attempts < ? ORDER BY submitted_at LIMIT $limit",
            [$type, $maxAttempts]
        );
        foreach ($rows as &$r) {
            $r['submit'] = $r['submit_json'] ? (json_decode($r['submit_json'], true) ?: []) : [];
        }
        return $rows;
    }

    public static function markSynced(string $id, string $entryId): void
    {
        DB::update('reports', ['sharepoint_entry_id' => $entryId, 'sharepoint_error' => null], 'id = ?', [$id]);
    }

    public static function markSyncFailed(string $id, string $error): void
    {
        DB::execute('UPDATE reports SET sharepoint_attempts = sharepoint_attempts + 1, sharepoint_error = ? WHERE id = ?', [substr($error, 0, 2000), $id]);
    }

    public static function pendingCountFor(string $userId): int
    {
        return (int) DB::fetchColumn("SELECT COUNT(*) FROM reports WHERE assignee_id = ? AND status = 'pending'", [$userId]);
    }
}
