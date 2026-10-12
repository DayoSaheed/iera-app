<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Community feed, groups, direct messages, community events, stories and feedback (iERA Connect). */
final class Community
{
    public const GROUPS = [
        'global' => ['Global Volunteers', 'globe', 'Field outreach & dawah events worldwide'],
        'uk' => ['UK Volunteers', 'flag', 'Campus dawah & UK-based activities'],
        'online' => ['Online Volunteers', 'message-square', 'Digital content & online dawah support'],
        'africa' => ['Africa Team', 'globe', 'Volunteers across the African continent'],
        'asia' => ['Asia Team', 'globe', 'Asia Pacific & South Asian volunteers'],
        'europe' => ['Europe Team', 'globe', 'European dawah network'],
        'north-america' => ['North America Team', 'globe', 'USA & Canada volunteers'],
        'university' => ['University Volunteers', 'graduation-cap', 'Campus societies & student outreach'],
        'new-muslim' => ['New Muslim Support', 'heart-handshake', 'Supporting new Muslims through their journey'],
        'content' => ['Content & Media Team', 'video', 'Video, graphics, social media & storytelling'],
    ];
    public const POST_TYPES = ['update' => null, 'achievement' => 'award', 'milestone' => 'star', 'badge' => 'star', 'story' => 'book', 'report' => 'clipboard', 'training' => 'graduation-cap', 'event' => 'calendar'];
    public const EVENT_TYPES = ['dawah' => ['Dawah Event', 'landmark'], 'training' => ['Training', 'graduation-cap'], 'campaign' => ['Campaign', 'megaphone'], 'university' => ['University', 'graduation-cap'], 'online_mission' => ['Online Mission', 'message-square'], 'meetup' => ['Meetup', 'users']];
    public const STORY_CATEGORIES = ['Shahadah Stories', 'Volunteer Stories', 'New Muslim Support', 'Outreach Updates', 'Training Updates', 'Campaign Stories', 'Country Reports', 'Other'];
    public const CONTINENTS = ['Africa', 'Asia', 'Europe', 'North America', 'South America', 'Oceania', 'Global'];
    public const ISSUE_TYPES = ['Bug' => 'alert-triangle', 'Design Issue' => 'edit', 'Broken Feature' => 'x-circle', 'Suggestion' => 'zap', 'Performance Issue' => 'activity', 'Mobile Issue' => 'phone', 'Other' => 'file-text'];
    public const FEEDBACK_STATUSES = ['New', 'Investigating', 'In Progress', 'Fixed', 'Closed', 'Need More Info'];
    public const FEEDBACK_MESSAGES = ['Fixed' => 'This issue has been fixed!', 'Investigating' => 'Our team is looking into this.', 'In Progress' => 'Being worked on right now.', 'Need More Info' => 'We need more info — check your submission.', 'Closed' => 'This issue has been closed.', 'New' => 'Received — thanks for reporting!'];

    private static function j(array &$r, string $k): void
    {
        $r[$k] = !empty($r[$k . '_json']) ? (json_decode((string) $r[$k . '_json'], true) ?: []) : [];
        unset($r[$k . '_json']);
    }

    // ---- posts ----------------------------------------------------------

    public static function posts(int $limit = 50, bool $includeHidden = false): array
    {
        $rows = DB::fetchAll('SELECT * FROM community_posts' . ($includeHidden ? '' : ' WHERE hidden = 0') . " ORDER BY pinned DESC, id DESC LIMIT $limit");
        foreach ($rows as &$r) {
            self::j($r, 'images');
            self::j($r, 'likes');
        }
        return $rows;
    }

    public static function post(int $id): ?array
    {
        $r = DB::fetch('SELECT * FROM community_posts WHERE id = ?', [$id]);
        if ($r) {
            self::j($r, 'images');
            self::j($r, 'likes');
        }
        return $r;
    }

    public static function createPost(array $u, string $content, array $imageIds, string $type = 'update'): int
    {
        DB::insert('community_posts', ['user_id' => $u['id'], 'author_name' => User::fullName($u) ?: $u['email'], 'content' => $content, 'images_json' => json_encode(array_values($imageIds)), 'type' => isset(self::POST_TYPES[$type]) ? $type : 'update', 'likes_json' => '[]', 'created_at' => now()]);
        return DB::lastInsertId();
    }

    public static function toggleLike(int $postId, string $userId): void
    {
        $p = self::post($postId);
        if (!$p) {
            return;
        }
        $likes = in_array($userId, $p['likes'], true) ? array_values(array_diff($p['likes'], [$userId])) : array_merge($p['likes'], [$userId]);
        DB::update('community_posts', ['likes_json' => json_encode($likes)], 'id = ?', [$postId]);
    }

    public static function comments(array $postIds): array
    {
        if (!$postIds) {
            return [];
        }
        [$in, $p] = DB::in($postIds);
        $out = [];
        foreach (DB::fetchAll("SELECT * FROM post_comments WHERE post_id IN $in ORDER BY id ASC", $p) as $c) {
            $out[$c['post_id']][] = $c;
        }
        return $out;
    }

    /** Activity stream from impact reports, training completions and awards. */
    public static function activity(int $limit = 8): array
    {
        $items = [];
        foreach (DB::fetchAll('SELECT r.hours, r.activity_type, r.created_at, u.first_name, u.last_name, u.email FROM volunteer_reports r JOIN users u ON u.id = r.user_id ORDER BY r.id DESC LIMIT 30') as $r) {
            $items[] = ['icon' => 'clipboard', 'user' => self::displayName($r), 'text' => 'logged ' . rtrim(rtrim(number_format((float) $r['hours'], 1), '0'), '.') . 'h of ' . $r['activity_type'], 'at' => $r['created_at']];
        }
        foreach (DB::fetchAll('SELECT c.completed_at, u.first_name, u.last_name, u.email FROM training_completions c JOIN users u ON u.id = c.user_id ORDER BY c.id DESC LIMIT 20') as $r) {
            $items[] = ['icon' => 'graduation-cap', 'user' => self::displayName($r), 'text' => 'completed a training mission', 'at' => $r['completed_at']];
        }
        foreach (DB::fetchAll('SELECT a.badge, a.created_at, u.first_name, u.last_name, u.email FROM training_awards a JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 20') as $r) {
            $items[] = ['icon' => 'award', 'user' => self::displayName($r), 'text' => 'earned the "' . $r['badge'] . '" badge', 'at' => $r['created_at']];
        }
        usort($items, fn($a, $b) => strcmp($b['at'], $a['at']));
        return array_slice($items, 0, $limit);
    }

    private static function displayName(array $r): string
    {
        $n = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        return $n !== '' ? $n : ucfirst(explode('.', explode('@', (string) $r['email'])[0])[0]);
    }

    // ---- groups ----------------------------------------------------------

    public static function memberships(string $userId): array
    {
        return array_column(DB::fetchAll('SELECT group_slug FROM group_memberships WHERE user_id = ?', [$userId]), 'group_slug');
    }

    public static function groupCounts(): array
    {
        $out = [];
        foreach (DB::fetchAll('SELECT group_slug, COUNT(*) c FROM group_memberships GROUP BY group_slug') as $r) {
            $out[$r['group_slug']] = (int) $r['c'];
        }
        return $out;
    }

    public static function toggleGroup(string $userId, string $slug): bool
    {
        if (!isset(self::GROUPS[$slug])) {
            return false;
        }
        if (DB::fetch('SELECT user_id FROM group_memberships WHERE user_id = ? AND group_slug = ?', [$userId, $slug])) {
            DB::delete('group_memberships', 'user_id = ? AND group_slug = ?', [$userId, $slug]);
            return false;
        }
        DB::insert('group_memberships', ['user_id' => $userId, 'group_slug' => $slug, 'group_name' => self::GROUPS[$slug][0], 'created_at' => now()]);
        return true;
    }

    // ---- messages ----------------------------------------------------------

    public static function unreadMessages(string $userId): int
    {
        try {
            return (int) DB::fetchColumn('SELECT COUNT(*) FROM direct_messages WHERE to_user_id = ? AND is_read = 0', [$userId]);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function sendMessage(array $from, string $toUserId, string $content): int
    {
        DB::insert('direct_messages', ['from_user_id' => $from['id'], 'from_name' => User::fullName($from) ?: $from['email'], 'to_user_id' => $toUserId, 'content' => $content, 'created_at' => now()]);
        $id = DB::lastInsertId();
        Notification::push($toUserId, 'New message from ' . (User::fullName($from) ?: $from['email']), mb_substr($content, 0, 140), ['category' => 'community', 'icon' => 'message-circle', 'link' => '/messages']);
        return $id;
    }

    // ---- community events ----------------------------------------------------

    public static function events(?string $status = null, int $limit = 100): array
    {
        $sql = 'SELECT * FROM community_events WHERE status <> ?';
        $p = ['cancelled'];
        if ($status) {
            $sql .= ' AND status = ?';
            $p[] = $status;
        }
        $rows = DB::fetchAll($sql . " ORDER BY event_date ASC LIMIT $limit", $p);
        foreach ($rows as &$r) {
            self::j($r, 'rsvp');
        }
        return $rows;
    }

    public static function event(int $id): ?array
    {
        $r = DB::fetch('SELECT * FROM community_events WHERE id = ?', [$id]);
        if ($r) {
            self::j($r, 'rsvp');
        }
        return $r;
    }

    public static function toggleRsvp(int $eventId, string $userId): bool
    {
        $e = self::event($eventId);
        if (!$e) {
            return false;
        }
        $in = in_array($userId, $e['rsvp'], true);
        $rsvp = $in ? array_values(array_diff($e['rsvp'], [$userId])) : array_merge($e['rsvp'], [$userId]);
        DB::update('community_events', ['rsvp_json' => json_encode($rsvp), 'updated_at' => now()], 'id = ?', [$eventId]);
        return !$in;
    }

    // ---- stories --------------------------------------------------------------

    public static function stories(array $f = [], int $limit = 200): array
    {
        $sql = 'SELECT * FROM stories WHERE 1=1';
        $p = [];
        if (!empty($f['status'])) {
            $sql .= ' AND status = ?';
            $p[] = $f['status'];
        }
        if (!empty($f['submitted_by'])) {
            $sql .= ' AND submitted_by = ?';
            $p[] = $f['submitted_by'];
        }
        $rows = DB::fetchAll($sql . " ORDER BY featured DESC, published_date DESC, id DESC LIMIT $limit", $p);
        foreach ($rows as &$r) {
            self::j($r, 'tags');
        }
        return $rows;
    }

    public static function story(int $id): ?array
    {
        $r = DB::fetch('SELECT * FROM stories WHERE id = ?', [$id]);
        if ($r) {
            self::j($r, 'tags');
        }
        return $r;
    }

    // ---- feedback --------------------------------------------------------------

    public static function feedback(array $f = [], int $limit = 100): array
    {
        $sql = 'SELECT * FROM feedback_reports WHERE 1=1';
        $p = [];
        if (!empty($f['submitted_by'])) {
            $sql .= ' AND submitted_by = ?';
            $p[] = $f['submitted_by'];
        }
        if (!empty($f['issue_type'])) {
            $sql .= ' AND issue_type = ?';
            $p[] = $f['issue_type'];
        }
        if (!empty($f['status'])) {
            $sql .= ' AND status = ?';
            $p[] = $f['status'];
        }
        $rows = DB::fetchAll($sql . " ORDER BY id DESC LIMIT $limit", $p);
        foreach ($rows as &$r) {
            self::j($r, 'votes');
            self::j($r, 'comments');
        }
        return $rows;
    }

    public static function feedbackItem(int $id): ?array
    {
        $r = DB::fetch('SELECT * FROM feedback_reports WHERE id = ?', [$id]);
        if ($r) {
            self::j($r, 'votes');
            self::j($r, 'comments');
        }
        return $r;
    }

    public static function toggleVote(int $id, string $userId): void
    {
        $f = self::feedbackItem($id);
        if (!$f) {
            return;
        }
        $votes = in_array($userId, $f['votes'], true) ? array_values(array_diff($f['votes'], [$userId])) : array_merge($f['votes'], [$userId]);
        DB::update('feedback_reports', ['votes_json' => json_encode($votes), 'updated_at' => now()], 'id = ?', [$id]);
    }

    public static function addFeedbackComment(int $id, array $u, string $text, string $role = 'user'): void
    {
        $f = self::feedbackItem($id);
        if (!$f) {
            return;
        }
        $f['comments'][] = ['author_id' => $u['id'], 'author_name' => User::fullName($u) ?: $u['email'], 'author_role' => $role, 'text' => $text, 'posted_at' => now()];
        DB::update('feedback_reports', ['comments_json' => json_encode($f['comments'], JSON_UNESCAPED_UNICODE), 'updated_at' => now()], 'id = ?', [$id]);
    }
}
