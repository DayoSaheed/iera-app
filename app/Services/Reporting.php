<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\Application;

/** Volunteer metrics: activity, consistency, training, dormancy and risk level (per volunteer and aggregated). */
final class Reporting
{
    public const ACTIVITY_COLORS = ['Street Dawah' => '#0a0a0a', 'University Outreach' => '#2b2b2b', 'Online Support' => '#404040', 'Content Creation' => '#575757', 'Admin Support' => '#6b6b6b', 'Event Support' => '#808080', 'New Muslim Support' => '#969696', 'Other' => '#b5b5b5'];

    /** Metrics for one volunteer given their reports (newest first). */
    public static function metrics(array $app, array $reports, int $completions, int $modules, int $awardXp = 0, int $stories = 0, int $published = 0): array
    {
        $g = Gamification::compute($reports, $completions, $modules, $app, $awardXp);
        $now = time();
        $sum = fn(callable $f) => array_sum(array_map(fn($r) => (float) $r['hours'], array_filter($reports, $f)));
        $weekAgo = date('Y-m-d', $now - 7 * 86400);
        $monthAgo = date('Y-m-d', $now - 30 * 86400);
        $last = $reports ? max(array_column($reports, 'report_date')) : null;
        $days = $last ? (int) floor(($now - strtotime($last)) / 86400) : 999;
        $weeks = [];
        for ($i = 11; $i >= 0; $i--) {
            $from = date('Y-m-d', $now - ($i + 1) * 7 * 86400);
            $to = date('Y-m-d', $now - $i * 7 * 86400);
            $h = $sum(fn($r) => $r['report_date'] >= $from && $r['report_date'] < $to);
            $weeks[] = ['label' => 'W' . (12 - $i), 'hours' => $h, 'active' => $h > 0];
        }
        $activeWeeks = count(array_filter($weeks, fn($w) => $w['active']));
        $consistency = (int) round($activeWeeks / 12 * 100);
        $breakdown = [];
        foreach ($reports as $r) {
            $breakdown[$r['activity_type'] ?: 'Other'] = ($breakdown[$r['activity_type'] ?: 'Other'] ?? 0) + (float) $r['hours'];
        }
        arsort($breakdown);
        $dormant = $days >= 30;
        $bursty = $activeWeeks > 0 && $consistency < 40;
        $risk = $dormant || ($bursty && $reports) ? 'high' : (($days > 14 || $consistency < 50) ? 'medium' : 'low');
        return $g + [
            'app' => $app, 'hoursThisWeek' => $sum(fn($r) => $r['report_date'] >= $weekAgo), 'hoursThisMonth' => $sum(fn($r) => $r['report_date'] >= $monthAgo),
            'hoursCalendarMonth' => $sum(fn($r) => $r['report_date'] >= date('Y-m-01')), 'lastActive' => $last, 'daysSinceActive' => $days, 'isDormant' => $dormant,
            'weeks' => $weeks, 'activeWeeks' => $activeWeeks, 'consistency' => $consistency, 'isBursty' => $bursty,
            'completionRate' => $modules > 0 ? (int) round($completions / $modules * 100) : 0, 'breakdown' => $breakdown, 'stories' => $stories, 'storiesPublished' => $published, 'risk' => $risk,
        ];
    }

    public static function forApplication(array $app): array
    {
        $reports = Gamification::reports($app['user_id'], 2000);
        return self::metrics($app, $reports, (int) DB::fetchColumn('SELECT COUNT(*) FROM training_completions WHERE user_id = ?', [$app['user_id']]), (int) DB::fetchColumn('SELECT COUNT(*) FROM training_modules WHERE is_active = 1'),
            (int) DB::fetchColumn('SELECT COALESCE(SUM(xp_awarded),0) FROM training_awards WHERE user_id = ?', [$app['user_id']]), (int) DB::fetchColumn('SELECT COUNT(*) FROM stories WHERE submitted_by = ?', [$app['user_id']]),
            (int) DB::fetchColumn("SELECT COUNT(*) FROM stories WHERE submitted_by = ? AND status = 'published'", [$app['user_id']]));
    }

    /** Metrics for many applications in a handful of queries. */
    public static function forApplications(array $apps): array
    {
        if (!$apps) {
            return [];
        }
        [$in, $p] = DB::in(array_column($apps, 'user_id'));
        $byUser = $comp = $awards = $stories = $published = [];
        foreach (DB::fetchAll("SELECT user_id, report_date, hours, activity_type FROM volunteer_reports WHERE user_id IN $in ORDER BY report_date DESC", $p) as $r) {
            $byUser[$r['user_id']][] = $r;
        }
        foreach (DB::fetchAll("SELECT user_id, COUNT(*) c FROM training_completions WHERE user_id IN $in GROUP BY user_id", $p) as $r) { $comp[$r['user_id']] = (int) $r['c']; }
        foreach (DB::fetchAll("SELECT user_id, SUM(xp_awarded) x FROM training_awards WHERE user_id IN $in GROUP BY user_id", $p) as $r) { $awards[$r['user_id']] = (int) $r['x']; }
        foreach (DB::fetchAll("SELECT submitted_by, COUNT(*) c, SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) pub FROM stories WHERE submitted_by IN $in GROUP BY submitted_by", $p) as $r) { $stories[$r['submitted_by']] = (int) $r['c']; $published[$r['submitted_by']] = (int) $r['pub']; }
        $modules = (int) DB::fetchColumn('SELECT COUNT(*) FROM training_modules WHERE is_active = 1');
        $out = [];
        foreach ($apps as $a) {
            $out[] = self::metrics($a, $byUser[$a['user_id']] ?? [], $comp[$a['user_id']] ?? 0, $modules, $awards[$a['user_id']] ?? 0, $stories[$a['user_id']] ?? 0, $published[$a['user_id']] ?? 0);
        }
        return $out;
    }

    public static function aggregate(array $metrics): array
    {
        $approved = array_filter($metrics, fn($m) => $m['app']['status'] === 'Approved');
        $allReports = DB::fetchAll('SELECT report_date, hours, activity_type FROM volunteer_reports');
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $k = date('Y-m', strtotime("first day of -$i months"));
            $months[$k] = ['label' => date('M', strtotime($k . '-01')), 'hours' => 0.0];
        }
        $byType = [];
        foreach ($allReports as $r) {
            $k = substr($r['report_date'], 0, 7);
            if (isset($months[$k])) {
                $months[$k]['hours'] += (float) $r['hours'];
            }
            $byType[$r['activity_type'] ?: 'Other'] = ($byType[$r['activity_type'] ?: 'Other'] ?? 0) + (float) $r['hours'];
        }
        arsort($byType);
        $cats = [];
        foreach (['Global', 'Canada', 'Online'] as $c) {
            $rows = array_filter($approved, fn($m) => $m['app']['volunteer_category'] === $c);
            $cats[$c] = ['volunteers' => count($rows), 'hours' => array_sum(array_column($rows, 'hours')), 'active' => count(array_filter($rows, fn($m) => $m['hoursThisMonth'] > 0))];
        }
        return [
            'approved' => count($approved), 'hours' => array_sum(array_map(fn($r) => (float) $r['hours'], $allReports)), 'activeMonth' => count(array_filter($approved, fn($m) => $m['hoursThisMonth'] > 0)),
            'dormant' => array_values(array_filter($approved, fn($m) => $m['isDormant'])), 'highRisk' => count(array_filter($approved, fn($m) => $m['risk'] === 'high')),
            'months' => array_values($months), 'byType' => $byType, 'categories' => $cats,
        ];
    }
}
