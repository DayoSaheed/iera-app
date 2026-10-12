<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** In-app notifications (bell in the top bar). Categories mirror the email categories so preferences can mute either channel. */
final class Notification
{
    public const CATEGORIES = [
        'general' => 'General', 'application' => 'Application and onboarding', 'events' => 'Events', 'training' => 'Training',
        'community' => 'Community and messages', 'reports' => 'Reports', 'streak' => 'Streaks and XP', 'admin' => 'Admin alerts',
    ];

    /** @return int new id, or 0 when the user muted in-app notifications for this category */
    public static function push(string $userId, string $title, ?string $message = null, array $opts = []): int
    {
        $category = $opts['category'] ?? 'general';
        if (!self::allows($userId, $category, 'in_app')) {
            return 0;
        }
        DB::insert('notifications', [
            'user_id' => $userId, 'category' => $category, 'type' => $opts['type'] ?? 'info', 'icon' => $opts['icon'] ?? null,
            'title' => mb_substr($title, 0, 200), 'message' => $message, 'link' => $opts['link'] ?? null,
            'is_read' => 0, 'source' => $opts['source'] ?? null, 'created_at' => now(),
        ]);
        return DB::lastInsertId();
    }

    /** Same notification to many users. */
    public static function pushMany(array $userIds, string $title, ?string $message = null, array $opts = []): int
    {
        $n = 0;
        foreach (array_unique($userIds) as $id) {
            if ($id && self::push((string) $id, $title, $message, $opts)) {
                $n++;
            }
        }
        return $n;
    }

    public static function allows(string $userId, string $category, string $channel): bool
    {
        $col = $channel === 'email' ? 'email' : 'in_app';
        try {
            $v = DB::fetchColumn("SELECT $col FROM notification_preferences WHERE user_id = ? AND category = ?", [$userId, $category]);
        } catch (\Throwable) {
            return true;
        }
        return $v === null || $v === false || (int) $v === 1;
    }

    public static function setPreference(string $userId, string $category, bool $inApp, bool $email): void
    {
        DB::delete('notification_preferences', 'user_id = ? AND category = ?', [$userId, $category]);
        DB::insert('notification_preferences', ['user_id' => $userId, 'category' => $category, 'in_app' => $inApp ? 1 : 0, 'email' => $email ? 1 : 0]);
    }

    public static function preferences(string $userId): array
    {
        $out = [];
        foreach (self::CATEGORIES as $k => $label) {
            $out[$k] = ['label' => $label, 'in_app' => true, 'email' => true];
        }
        try {
            foreach (DB::fetchAll('SELECT * FROM notification_preferences WHERE user_id = ?', [$userId]) as $r) {
                if (isset($out[$r['category']])) {
                    $out[$r['category']]['in_app'] = (int) $r['in_app'] === 1;
                    $out[$r['category']]['email'] = (int) $r['email'] === 1;
                }
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    public static function unreadCount(string $userId): int
    {
        try {
            return (int) DB::fetchColumn('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function recent(string $userId, int $limit = 50, bool $unreadOnly = false): array
    {
        try {
            return DB::fetchAll('SELECT * FROM notifications WHERE user_id = ?' . ($unreadOnly ? ' AND is_read = 0' : '') . " ORDER BY id DESC LIMIT $limit", [$userId]);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function markRead(string $userId, ?int $id = null): void
    {
        if ($id) {
            DB::update('notifications', ['is_read' => 1, 'read_at' => now()], 'id = ? AND user_id = ?', [$id, $userId]);
            try { DB::update('comm_logs', ['in_app_read' => 1, 'read_at' => now()], 'notification_id = ? AND in_app_read = 0', [$id]); } catch (\Throwable) {}
        } else {
            DB::update('notifications', ['is_read' => 1, 'read_at' => now()], 'user_id = ? AND is_read = 0', [$userId]);
            try { DB::update('comm_logs', ['in_app_read' => 1, 'read_at' => now()], 'user_id = ? AND in_app_read = 0', [$userId]); } catch (\Throwable) {}
        }
    }

    public static function purge(int $days = 90): void
    {
        DB::delete('notifications', 'is_read = 1 AND created_at < ?', [date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
