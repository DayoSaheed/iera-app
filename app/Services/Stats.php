<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\Team;

/** Analytics for the superadmin dashboard and the da'ee activity report. */
final class Stats
{
    /**
     * @param string|null $teamId null = all teams
     * @param int|null $year filter reports/events by year (null = all time)
     * @param int|null $month 1-12 (requires year)
     */
    public static function overview(?string $teamId, ?int $year, ?int $month): array
    {
        [$from, $to] = self::range($year, $month);
        $teamIds = $teamId ? [$teamId] : null;

        $out = [
            'range' => ['from' => $from, 'to' => $to],
            'volunteers' => self::usersByStatus('volunteer', $teamIds),
            'newmuslims' => self::newMuslimsByStatus($teamIds),
            'events' => self::eventCounts($teamIds, $from, $to),
            'event_reports' => self::reportCounts('event_report', $teamIds, $from, $to),
            'mentor_reports' => self::reportCounts('mentor', $teamIds, $from, $to),
            'event_stats' => self::eventStats($teamIds, $from, $to),
            'attendance' => self::attendance($teamIds, $from, $to),
            'per_team' => self::perTeam($from, $to),
            'monthly' => self::monthlySeries($teamIds, $year ?? (int) date('Y')),
        ];
        return $out;
    }

    public static function range(?int $year, ?int $month): array
    {
        if (!$year) {
            return ['2000-01-01 00:00:00', '2100-01-01 00:00:00'];
        }
        if ($month) {
            $from = sprintf('%04d-%02d-01 00:00:00', $year, $month);
            $to = date('Y-m-d H:i:s', strtotime($from . ' +1 month'));
        } else {
            $from = "$year-01-01 00:00:00";
            $to = ($year + 1) . '-01-01 00:00:00';
        }
        return [$from, $to];
    }

    private static function teamFilter(?array $teamIds, string $userCol): array
    {
        if ($teamIds === null) {
            return ['', []];
        }
        [$in, $p] = DB::in($teamIds);
        return [" AND EXISTS (SELECT 1 FROM team_members tm WHERE tm.user_id = $userCol AND tm.team_id IN $in)", $p];
    }

    public static function usersByStatus(string $type, ?array $teamIds): array
    {
        [$tf, $p] = self::teamFilter($teamIds, 'u.id');
        $rows = DB::fetchAll("SELECT u.status, COUNT(*) c FROM users u WHERE u.type = ? $tf GROUP BY u.status", array_merge([$type], $p));
        $out = [];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    /** New Muslims counted via their mentor's team (or all when no team filter). */
    public static function newMuslimsByStatus(?array $teamIds): array
    {
        if ($teamIds === null) {
            return self::usersByStatus('newmuslim', null);
        }
        [$in, $p] = DB::in($teamIds);
        $rows = DB::fetchAll(
            "SELECT n.status, COUNT(DISTINCT n.id) c FROM users n
             JOIN mentor_assignments a ON a.mentee_id = n.id AND a.accepted_at IS NOT NULL
             JOIN team_members tm ON tm.user_id = a.mentor_id AND tm.team_id IN $in
             WHERE n.type = 'newmuslim' GROUP BY n.status",
            $p
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    public static function eventCounts(?array $teamIds, string $from, string $to): array
    {
        $where = "status = 'active'";
        $p = [];
        if ($teamIds !== null) {
            [$in, $ip] = DB::in($teamIds);
            $where .= " AND team_id IN $in";
            $p = $ip;
        }
        $now = now();
        return [
            'in_range' => (int) DB::fetchColumn("SELECT COUNT(*) FROM sub_events WHERE $where AND start_at >= ? AND start_at < ?", array_merge($p, [$from, $to])),
            'past' => (int) DB::fetchColumn("SELECT COUNT(*) FROM sub_events WHERE $where AND start_at < ?", array_merge($p, [$now])),
            'future' => (int) DB::fetchColumn("SELECT COUNT(*) FROM sub_events WHERE $where AND start_at >= ?", array_merge($p, [$now])),
            'this_month' => (int) DB::fetchColumn("SELECT COUNT(*) FROM sub_events WHERE $where AND start_at >= ? AND start_at < ?", array_merge($p, [date('Y-m-01 00:00:00'), date('Y-m-01 00:00:00', strtotime('+1 month'))])),
        ];
    }

    public static function reportCounts(string $type, ?array $teamIds, string $from, string $to): array
    {
        [$tf, $p] = self::teamFilter($teamIds, 'r.assignee_id');
        $rows = DB::fetchAll("SELECT r.status, COUNT(*) c FROM reports r WHERE r.type = ? AND r.created_at >= ? AND r.created_at < ? $tf GROUP BY r.status", array_merge([$type, $from, $to], $p));
        $out = ['pending' => 0, 'submitted' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    /** Sums of chats, quality chats, shahadas, materials, quran from submitted event reports. */
    public static function eventStats(?array $teamIds, string $from, string $to): array
    {
        [$tf, $p] = self::teamFilter($teamIds, 'r.assignee_id');
        $rows = DB::fetchAll("SELECT r.submit_json FROM reports r WHERE r.type = 'event_report' AND r.status = 'submitted' AND r.created_at >= ? AND r.created_at < ? $tf", array_merge([$from, $to], $p));
        $sum = ['chats' => 0, 'chats_quality' => 0, 'shahada' => 0, 'materials' => 0, 'quran' => 0, 'duration' => 0.0];
        foreach ($rows as $r) {
            $s = json_decode((string) $r['submit_json'], true) ?: [];
            foreach ($sum as $k => $_) {
                $sum[$k] += (float) ($s[$k] ?? 0);
            }
        }
        $sum['duration'] = round($sum['duration'], 1);
        return $sum;
    }

    public static function attendance(?array $teamIds, string $from, string $to): array
    {
        [$tf, $p] = self::teamFilter($teamIds, 'r.assignee_id');
        $rows = DB::fetchAll("SELECT r.submit_json FROM reports r WHERE r.type = 'event_report' AND r.status = 'submitted' AND r.created_at >= ? AND r.created_at < ? $tf", array_merge([$from, $to], $p));
        $out = ['yes' => 0, 'no' => 0];
        foreach ($rows as $r) {
            $s = json_decode((string) $r['submit_json'], true) ?: [];
            $out[($s['attend'] ?? 'yes') === 'no' ? 'no' : 'yes']++;
        }
        return $out;
    }

    /** Per-team summary rows for the bar chart and CSV. */
    public static function perTeam(string $from, string $to): array
    {
        $out = [];
        foreach (Team::all(null, 'approved') as $t) {
            $ids = [$t['id']];
            $out[] = [
                'id' => $t['id'],
                'name' => $t['name'],
                'volunteers_active' => self::usersByStatus('volunteer', $ids)['approved'] ?? 0,
                'newmuslims_active' => self::newMuslimsByStatus($ids)['approved'] ?? 0,
                'event_reports' => self::reportCounts('event_report', $ids, $from, $to),
                'mentor_reports' => self::reportCounts('mentor', $ids, $from, $to),
                'stats' => self::eventStats($ids, $from, $to),
                'events' => self::eventCounts($ids, $from, $to)['in_range'],
            ];
        }
        return $out;
    }

    /** Monthly series for a year: events, submitted reports, shahadas. */
    public static function monthlySeries(?array $teamIds, int $year): array
    {
        $series = [];
        for ($m = 1; $m <= 12; $m++) {
            [$from, $to] = self::range($year, $m);
            $series[] = [
                'month' => date('M', mktime(0, 0, 0, $m, 1, $year)),
                'events' => self::eventCounts($teamIds, $from, $to)['in_range'],
                'reports' => self::reportCounts('event_report', $teamIds, $from, $to)['submitted'],
                'shahada' => (int) self::eventStats($teamIds, $from, $to)['shahada'],
            ];
        }
        return $series;
    }

    /** Users grouped by Canadian province (from the province column or the "City, PR" suffix). */
    public static function byProvince(): array
    {
        $names = ['AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia', 'NT' => 'Northwest Territories', 'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon'];
        $rows = DB::fetchAll("SELECT type, status, city, province FROM users WHERE type IN ('volunteer', 'newmuslim') AND status NOT IN ('removed', 'rejected', 'new')");
        $out = [];
        foreach ($rows as $r) {
            $code = strtoupper(trim((string) $r['province']));
            if (!isset($names[$code])) {
                $code = '';
                if (preg_match('/,\s*([A-Za-z]{2})\s*$/', (string) $r['city'], $m) && isset($names[strtoupper($m[1])])) {
                    $code = strtoupper($m[1]);
                } else {
                    foreach ($names as $c => $n) {
                        if ($r['province'] && strcasecmp($n, (string) $r['province']) === 0) {
                            $code = $c;
                        }
                    }
                }
            }
            $key = $code ?: 'XX';
            $out[$key] ??= ['code' => $key, 'name' => $names[$key] ?? 'Unknown / outside Canada', 'volunteers' => 0, 'volunteers_active' => 0, 'newmuslims' => 0, 'newmuslims_active' => 0];
            if ($r['type'] === 'volunteer') {
                $out[$key]['volunteers']++;
                if ($r['status'] === 'approved') { $out[$key]['volunteers_active']++; }
            } else {
                $out[$key]['newmuslims']++;
                if (in_array($r['status'], ['approved', 'completed'], true)) { $out[$key]['newmuslims_active']++; }
            }
        }
        uasort($out, fn($a, $b) => ($b['volunteers'] + $b['newmuslims']) <=> ($a['volunteers'] + $a['newmuslims']));
        return array_values($out);
    }

    /** One card per team for the superadmin home. */
    public static function teamCards(): array
    {
        $from = date('Y-01-01 00:00:00');
        $to = date('Y-m-d H:i:s', strtotime('+1 year'));
        $cards = [];
        foreach (Team::withCounts(Team::all(null, 'approved')) as $t) {
            $ids = [$t['id']];
            $stats = self::eventStats($ids, $from, $to);
            $er = self::reportCounts('event_report', $ids, $from, $to);
            $mr = self::reportCounts('mentor', $ids, $from, $to);
            $cards[] = [
                'id' => $t['id'], 'name' => $t['name'], 'parent' => $t['parent_team'],
                'members' => (int) $t['members_count'], 'leads' => (int) $t['admins_count'], 'upcoming' => (int) $t['active_events'],
                'volunteers_active' => self::usersByStatus('volunteer', $ids)['approved'] ?? 0,
                'newmuslims' => self::newMuslimsByStatus($ids)['approved'] ?? 0,
                'shahada' => (int) $stats['shahada'], 'chats' => (int) $stats['chats'],
                'reports_submitted' => $er['submitted'] + $mr['submitted'], 'reports_pending' => $er['pending'] + $mr['pending'],
            ];
        }
        return $cards;
    }

    /** Da'ee activity: attendance and hours per approved da'ee in a date range. */
    public static function daeeActivity(string $from, string $to, ?array $teamIds): array
    {
        $where = "u.type = 'volunteer' AND u.status = 'approved' AND EXISTS (SELECT 1 FROM user_sub_groups s WHERE s.user_id = u.id AND s.sub_group = 'daee')";
        $p = [];
        if ($teamIds !== null) {
            [$in, $ip] = DB::in($teamIds);
            $where .= " AND EXISTS (SELECT 1 FROM team_members tm WHERE tm.user_id = u.id AND tm.team_id IN $in)";
            $p = $ip;
        }
        $daees = DB::fetchAll("SELECT u.id, u.first_name, u.last_name, u.email FROM users u WHERE $where ORDER BY u.first_name, u.last_name", $p);
        if (!$daees) {
            return [];
        }
        [$in, $ip] = DB::in(array_column($daees, 'id'));
        $rows = DB::fetchAll(
            "SELECT a.user_id, s.start_at, s.event_length, r.submit_json FROM sub_event_attendance a
             JOIN sub_events s ON s.id = a.sub_event_id AND s.status = 'active'
             LEFT JOIN reports r ON r.subject_id = s.id AND r.assignee_id = a.user_id AND r.type = 'event_report' AND r.status = 'submitted'
             WHERE a.user_id IN $in AND s.start_at >= ? AND s.start_at <= ?",
            array_merge($ip, [$from . ' 00:00:00', $to . ' 23:59:59'])
        );
        $agg = [];
        foreach ($rows as $r) {
            $submit = $r['submit_json'] ? (json_decode($r['submit_json'], true) ?: []) : [];
            if (($submit['attend'] ?? 'yes') === 'no') {
                continue;
            }
            $hours = isset($submit['duration']) && is_numeric($submit['duration']) ? (float) $submit['duration'] : (float) $r['event_length'];
            $a = &$agg[$r['user_id']];
            $a['events'] = ($a['events'] ?? 0) + 1;
            $a['hours'] = ($a['hours'] ?? 0) + $hours;
            $a['last'] = max($a['last'] ?? '', $r['start_at']);
            unset($a);
        }
        foreach ($daees as &$d) {
            $d['events_attended'] = $agg[$d['id']]['events'] ?? 0;
            $d['total_hours'] = round($agg[$d['id']]['hours'] ?? 0, 1);
            $d['last_active'] = $agg[$d['id']]['last'] ?? null;
            $d['no_activity'] = $d['events_attended'] === 0;
        }
        return $daees;
    }
}
