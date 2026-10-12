<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Str;

final class SubEvent
{
    public const FIELDS = ['event_id', 'team_id', 'name', 'description', 'city', 'min_participants', 'start_at', 'event_length', 'status'];

    public static function find(string $id): ?array
    {
        return DB::fetch('SELECT * FROM sub_events WHERE id = ?', [$id]);
    }

    public static function create(array $data): string
    {
        $id = Str::uuid();
        $row = array_intersect_key($data, array_flip(self::FIELDS));
        $row['id'] = $id;
        $row['created_at'] = now();
        $row['updated_at'] = now();
        DB::insert('sub_events', $row);
        return $id;
    }

    public static function update(string $id, array $data): void
    {
        $row = array_intersect_key($data, array_flip(self::FIELDS));
        $row['updated_at'] = now();
        DB::update('sub_events', $row, 'id = ?', [$id]);
    }

    /** Active sub events for a team, future (default) or past. */
    public static function forTeam(string $teamId, bool $past = false, int $limit = 500): array
    {
        $cmp = $past ? '<' : '>=';
        $order = $past ? 'DESC' : 'ASC';
        return DB::fetchAll(
            "SELECT * FROM sub_events WHERE team_id = ? AND status = 'active' AND start_at $cmp ? ORDER BY start_at $order LIMIT $limit",
            [$teamId, now()]
        );
    }

    /** Active sub events for many teams (or all when null), within a date window. */
    public static function window(?array $teamIds, string $from, string $to): array
    {
        $sql = "SELECT s.*, t.name AS team_name FROM sub_events s JOIN teams t ON t.id = s.team_id WHERE s.status = 'active' AND s.start_at >= ? AND s.start_at <= ?";
        $p = [$from, $to];
        if ($teamIds !== null) {
            if (!$teamIds) {
                return [];
            }
            [$in, $ip] = DB::in($teamIds);
            $sql .= " AND s.team_id IN $in";
            $p = array_merge($p, $ip);
        }
        return DB::fetchAll($sql . ' ORDER BY s.start_at', $p);
    }

    public static function futureCountForEvent(string $eventId): int
    {
        return (int) DB::fetchColumn("SELECT COUNT(*) FROM sub_events WHERE event_id = ? AND status = 'active' AND start_at >= ?", [$eventId, now()]);
    }

    public static function futureForEvent(string $eventId): array
    {
        return DB::fetchAll("SELECT * FROM sub_events WHERE event_id = ? AND status = 'active' AND start_at >= ? ORDER BY start_at", [$eventId, now()]);
    }

    public static function existsAt(string $eventId, string $startAt): bool
    {
        return (bool) DB::fetchColumn('SELECT 1 FROM sub_events WHERE event_id = ? AND start_at = ?', [$eventId, $startAt]);
    }

    public static function deactivateFutureOfEvent(string $eventId): int
    {
        return DB::execute("UPDATE sub_events SET status = 'inactive', updated_at = ? WHERE event_id = ? AND start_at >= ?", [now(), $eventId, now()]);
    }

    /** Sub events that ended and have no report generated yet. */
    public static function endedWithoutReports(): array
    {
        $all = DB::fetchAll("SELECT * FROM sub_events WHERE status = 'active' AND report_generated_at IS NULL AND start_at < ?", [now()]);
        $out = [];
        foreach ($all as $s) {
            $end = strtotime($s['start_at']) + (int) round((float) $s['event_length'] * 3600);
            if ($end <= time()) {
                $out[] = $s;
            }
        }
        return $out;
    }

    public static function markReported(string $id): void
    {
        DB::update('sub_events', ['report_generated_at' => now()], 'id = ?', [$id]);
    }

    // ---- attendance --------------------------------------------------

    public static function attendance(string $subEventId): array
    {
        $rows = DB::fetchAll(
            'SELECT a.*, u.first_name, u.last_name, u.email, u.gender, u.mobile FROM sub_event_attendance a JOIN users u ON u.id = a.user_id WHERE a.sub_event_id = ? ORDER BY u.first_name',
            [$subEventId]
        );
        foreach ($rows as &$r) {
            $r['times'] = $r['times_json'] ? (json_decode($r['times_json'], true) ?: []) : [];
            $r['name'] = trim($r['first_name'] . ' ' . $r['last_name']);
        }
        return $rows;
    }

    /** sub_event_id => [attendance rows] for many sub events */
    public static function attendanceMap(array $subEventIds): array
    {
        if (!$subEventIds) {
            return [];
        }
        [$in, $p] = DB::in($subEventIds);
        $out = [];
        foreach (DB::fetchAll("SELECT a.*, u.first_name, u.last_name FROM sub_event_attendance a JOIN users u ON u.id = a.user_id WHERE a.sub_event_id IN $in ORDER BY u.first_name", $p) as $r) {
            $r['times'] = $r['times_json'] ? (json_decode($r['times_json'], true) ?: []) : [];
            $r['name'] = trim($r['first_name'] . ' ' . $r['last_name']);
            $out[$r['sub_event_id']][] = $r;
        }
        return $out;
    }

    public static function isAttending(string $subEventId, string $userId): bool
    {
        return (bool) DB::fetchColumn('SELECT 1 FROM sub_event_attendance WHERE sub_event_id = ? AND user_id = ?', [$subEventId, $userId]);
    }

    public static function attend(string $subEventId, string $userId, array $times, ?string $addedBy = null): void
    {
        $json = json_encode(array_values($times));
        if (self::isAttending($subEventId, $userId)) {
            DB::update('sub_event_attendance', ['times_json' => $json], 'sub_event_id = ? AND user_id = ?', [$subEventId, $userId]);
            return;
        }
        DB::insert('sub_event_attendance', [
            'sub_event_id' => $subEventId,
            'user_id' => $userId,
            'times_json' => $json,
            'added_by' => $addedBy,
            'created_at' => now(),
        ]);
    }

    public static function withdraw(string $subEventId, string $userId): void
    {
        DB::delete('sub_event_attendance', 'sub_event_id = ? AND user_id = ?', [$subEventId, $userId]);
    }

    /** Sub event ids a user is attending among a set. */
    public static function attendingIds(string $userId, array $subEventIds): array
    {
        if (!$subEventIds) {
            return [];
        }
        [$in, $p] = DB::in($subEventIds);
        return array_column(DB::fetchAll("SELECT sub_event_id FROM sub_event_attendance WHERE user_id = ? AND sub_event_id IN $in", array_merge([$userId], $p)), 'sub_event_id');
    }

    public static function endTime(array $s): int
    {
        return strtotime($s['start_at']) + (int) round((float) $s['event_length'] * 3600);
    }

    /** Time slot options for registration: full event + rolling 2 hour windows. */
    public static function timeSlots(array $s): array
    {
        $start = strtotime($s['start_at']);
        $len = (float) $s['event_length'];
        $end = $start + (int) round($len * 3600);
        $slots = [date('g:i A', $start) . ' - ' . date('g:i A', $end) . ' (Full Event)'];
        for ($h = 0; $h <= $len - 2; $h++) {
            $a = $start + $h * 3600;
            $slots[] = date('g:i A', $a) . ' - ' . date('g:i A', $a + 7200);
        }
        return $slots;
    }
}
