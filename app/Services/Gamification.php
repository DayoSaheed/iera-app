<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\Application;

/** XP, levels, weekly streaks and badges (computed, never stored). */
final class Gamification
{
    public const XP = ['application' => 50, 'profile' => 30, 'training_module' => 100, 'report' => 20, 'photo' => 10, 'streak_bonus' => 15];
    public const LEVELS = [
        1 => ['New Recruit', 0, 99, '#5b6168'],
        2 => ['Active Volunteer', 100, 299, '#1d4ed8'],
        3 => ['Dawah Contributor', 300, 599, '#15803d'],
        4 => ['Dawah Leader', 600, 999, '#7c3aed'],
        5 => ['Elite Ambassador', 1000, 9999, '#b45309'],
    ];
    public const BADGES = [
        'first_report' => ['target', 'First Report', 'Submitted your first activity report'],
        'streak_3' => ['flame', '3 Week Streak', 'Maintained a 3-week streak'],
        'streak_5' => ['zap', '5 Week Streak', 'Maintained a 5-week streak'],
        'hours_10' => ['clock', '10 Hours Logged', 'Logged over 10 volunteer hours'],
        'hours_50' => ['trophy', '50 Hours Logged', 'Logged over 50 volunteer hours'],
        'photo_upload' => ['image', 'Storyteller', 'Uploaded your first photo/story'],
        'training_done' => ['graduation-cap', 'Fully Trained', 'Completed all training missions'],
        'first_training' => ['book', 'Learning Begins', 'Completed your first mission'],
        'global' => ['globe', 'Global Impact', 'Volunteer from outside the UK'],
        'uk_champ' => ['flag', 'University Champion', 'UK campus volunteer'],
        'digital' => ['message-square', 'Digital Warrior', 'Online volunteer'],
        'level_3' => ['star', 'Rising Star', 'Reached Level 3'],
        'level_5' => ['award', 'Elite Ambassador', 'Reached Level 5'],
        'verified' => ['check-circle', 'Verified Volunteer', 'Identity verified and onboarding complete'],
    ];
    public const ACTIVITY_TYPES = ['Street Dawah', 'University Outreach', 'Online Support', 'Content Creation', 'Admin Support', 'Event Support', 'New Muslim Support', 'Other'];
    public const ACTIVITY_ICONS = ['Street Dawah' => 'megaphone', 'University Outreach' => 'graduation-cap', 'Online Support' => 'message-square', 'Content Creation' => 'edit', 'Admin Support' => 'clipboard', 'Event Support' => 'calendar', 'New Muslim Support' => 'heart-handshake', 'Other' => 'pin'];

    /** Everything the volunteer pages need for one user, in one call. */
    public static function stats(string $userId, ?array $app = null): array
    {
        $reports = self::reports($userId);
        $completions = (int) DB::fetchColumn('SELECT COUNT(*) FROM training_completions WHERE user_id = ?', [$userId]);
        $modules = (int) DB::fetchColumn('SELECT COUNT(*) FROM training_modules WHERE is_active = 1');
        return self::compute($reports, $completions, $modules, $app);
    }

    public static function reports(string $userId, int $limit = 500): array
    {
        return DB::fetchAll("SELECT * FROM volunteer_reports WHERE user_id = ? ORDER BY report_date DESC, id DESC LIMIT $limit", [$userId]);
    }

    public static function compute(array $reports, int $completions, int $modules, ?array $app): array
    {
        $photos = count(array_filter($reports, fn($r) => !empty($r['photo_doc_id'])));
        $xp = ($app ? self::XP['application'] : 0) + $completions * self::XP['training_module'] + count($reports) * self::XP['report'] + $photos * self::XP['photo'];
        $hours = array_sum(array_map(fn($r) => (float) $r['hours'], $reports));
        $streak = self::streak($reports);
        $level = self::level($xp);
        $earned = [];
        foreach (self::BADGES as $id => $b) {
            $ok = match ($id) {
                'first_report' => count($reports) >= 1,
                'streak_3' => $streak >= 3,
                'streak_5' => $streak >= 5,
                'hours_10' => $hours >= 10,
                'hours_50' => $hours >= 50,
                'photo_upload' => $photos > 0,
                'training_done' => $modules > 0 && $completions >= $modules,
                'first_training' => $completions >= 1,
                'global' => ($app['volunteer_category'] ?? '') === 'Global',
                'uk_champ' => ($app['volunteer_category'] ?? '') === 'UK',
                'digital' => ($app['volunteer_category'] ?? '') === 'Online',
                'level_3' => $xp >= 300,
                'level_5' => $xp >= 1000,
                'verified' => ($app['id_verification_status'] ?? '') === 'approved',
                default => false,
            };
            if ($ok) {
                $earned[] = $id;
            }
        }
        return [
            'xp' => $xp, 'hours' => $hours, 'reports' => count($reports), 'photos' => $photos, 'completions' => $completions, 'modules' => $modules,
            'streak' => $streak, 'level' => $level, 'level_title' => self::LEVELS[$level][0], 'level_color' => self::LEVELS[$level][3],
            'progress' => self::progress($xp), 'to_next' => self::toNext($xp), 'badges' => $earned,
        ];
    }

    public static function level(int $xp): int
    {
        $lvl = 1;
        foreach (self::LEVELS as $n => [, $min]) {
            if ($xp >= $min) {
                $lvl = $n;
            }
        }
        return $lvl;
    }

    public static function progress(int $xp): int
    {
        [, $min, $max] = self::LEVELS[self::level($xp)];
        return (int) min(100, round(($xp - $min) / ($max - $min) * 100));
    }

    public static function toNext(int $xp): int
    {
        $l = self::level($xp);
        return $l === 5 ? 0 : self::LEVELS[$l][2] - $xp + 1;
    }

    /** Consecutive Monday-weeks with at least one report, counted back from this or last week. */
    public static function streak(array $reports): int
    {
        if (!$reports) {
            return 0;
        }
        $weeks = [];
        foreach ($reports as $r) {
            $weeks[self::weekKey((string) $r['report_date'])] = true;
        }
        krsort($weeks);
        $keys = array_keys($weeks);
        $current = self::weekKey(date('Y-m-d'));
        $last = self::weekKey(date('Y-m-d', strtotime('-7 days')));
        if ($keys[0] !== $current && $keys[0] !== $last) {
            return 0;
        }
        $n = 0;
        $expect = $keys[0];
        foreach ($keys as $k) {
            if ($k !== $expect) {
                break;
            }
            $n++;
            $expect = date('Y-m-d', strtotime($expect . ' -7 days'));
        }
        return $n;
    }

    public static function weekKey(string $date): string
    {
        $t = strtotime($date);
        $dow = (int) date('w', $t);
        $diff = $dow === 0 ? -6 : 1 - $dow;
        return date('Y-m-d', strtotime(($diff >= 0 ? '+' : '') . $diff . ' days', $t));
    }

    /** Leaderboard / member directory rows for approved volunteers. */
    public static function board(array $apps): array
    {
        if (!$apps) {
            return [];
        }
        $ids = array_column($apps, 'user_id');
        [$in, $p] = DB::in($ids);
        $byUser = [];
        foreach (DB::fetchAll("SELECT * FROM volunteer_reports WHERE user_id IN $in ORDER BY report_date DESC", $p) as $r) {
            $byUser[$r['user_id']][] = $r;
        }
        $comp = [];
        foreach (DB::fetchAll("SELECT user_id, COUNT(*) c FROM training_completions WHERE user_id IN $in GROUP BY user_id", $p) as $r) {
            $comp[$r['user_id']] = (int) $r['c'];
        }
        $modules = (int) DB::fetchColumn('SELECT COUNT(*) FROM training_modules WHERE is_active = 1');
        $out = [];
        foreach ($apps as $a) {
            $out[] = ['app' => $a] + self::compute($byUser[$a['user_id']] ?? [], $comp[$a['user_id']] ?? 0, $modules, $a);
        }
        return $out;
    }
}
