<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;
use App\Core\Logger;
use App\Models\Application;
use App\Models\Setting;
use App\Models\User;

/**
 * Microsoft Teams connector. Every change recorded in the app (new applications, reports, registrations, status changes, posts, …)
 * is turned into an Adaptive Card and posted to a Teams channel through an incoming webhook / Workflows URL.
 * Messages go through the teams_outbox table: delivered immediately when possible, retried by the scheduler otherwise.
 */
final class Teams
{
    public const CATEGORIES = [
        'applications' => ['Applications & onboarding', 'New applications, submissions, interviews, documents, approvals and rejections.'],
        'people' => ['People & registrations', 'Sign-ups, registrations, profile changes, status changes, mentors and referrals.'],
        'operations' => ['Events, reports & teams', 'Events, attendance, event and mentor reports, vacations, teams.'],
        'community' => ['Community activity', 'Impact logs, community posts, stories, feedback and badges.'],
        'admin' => ['Admin & system changes', 'Settings, admins, templates, imports, bulk communications.'],
    ];
    /** Audit actions that are noise in a Teams channel. */
    private const SKIP = ['login', 'logout', 'login.failed', 'document.viewed', 'system.job_run', 'system.jobs_run_all', 'users.exported', 'system.backup_downloaded', 'stripe.webhook', 'stripe.session_created', 'system.test_mail', 'system.impersonate_start', 'system.impersonate_stop', 'system.teams_test', 'user.password_set', 'user.password_reset', 'community.post_like', 'community.post_pin', 'community.post_feature', 'community.post_hide'];
    /** Test hook: when set, called with the payload array instead of the webhook; return true for success. */
    public static $transport = null;
    private static int $depth = 0;

    public static function enabled(): bool
    {
        return Setting::bool('teams_enabled') && self::webhook() !== '';
    }

    public static function webhook(): string
    {
        return trim((string) Setting::get('teams_webhook_url'));
    }

    public static function categoryFor(string $action): ?string
    {
        if (in_array($action, self::SKIP, true)) {
            return null;
        }
        $prefix = explode('.', $action)[0];
        return match ($prefix) {
            'application', 'interview', 'onboarding' => 'applications',
            'user', 'mentee', 'users' => 'people',
            'event', 'sub_event', 'attendance', 'report', 'vacation', 'team' => 'operations',
            'community', 'impact', 'story', 'feedback', 'gamification', 'message' => 'community',
            'system', 'comm' => 'admin',
            default => 'operations',
        };
    }

    public static function categoryEnabled(string $category): bool
    {
        return Setting::bool('teams_notify_' . $category);
    }

    /** Humanise an audit action key: "application.id_doc_approved" → "Application: ID document approved". */
    public static function describe(string $action): string
    {
        $map = [
            'application.created' => 'New volunteer application started', 'application.submitted' => 'Volunteer application submitted', 'application.status' => 'Application status changed',
            'application.agreement_signed' => 'Volunteer agreement signed', 'application.activation' => 'Application activated', 'application.id_doc_approved' => 'ID document approved',
            'application.id_doc_rejected' => 'ID document rejected', 'application.deleted' => 'Volunteer deleted', 'interview.booked' => 'Interview booked',
            'user.registered' => 'Registration completed', 'user.self_created' => 'New account created', 'user.created_by_admin' => 'Account created by an admin', 'user.status_changed' => 'User status changed',
            'user.profile_updated' => 'Profile updated', 'user.referred' => 'New Muslim referred', 'user.training_confirmed' => 'Training confirmed', 'user.reactivated' => 'User re-activated',
            'user.deleted' => 'User deleted', 'user.updated_by_admin' => 'User updated by an admin', 'user.comment_added' => 'Admin comment added', 'user.auto_paused' => 'User paused for inactivity', 'user.auto_inactive' => 'New Muslim marked inactive',
            'mentee.accepted' => 'Mentor accepted a mentee', 'mentee.rejected' => 'Mentor declined a mentee', 'mentee.mentors_changed' => 'Mentors assigned',
            'event.created' => 'Event created', 'event.updated' => 'Event updated', 'sub_event.updated' => 'Event occurrence updated', 'attendance.marked' => 'Attendance marked', 'attendance.unmarked' => 'Attendance removed',
            'attendance.self' => 'Volunteer registered for an event', 'attendance.withdrawn' => 'Volunteer withdrew from an event', 'report.submitted' => 'Report submitted', 'vacation.requested' => 'Vacation requested', 'vacation.decided' => 'Vacation request decided',
            'team.created' => 'Team created', 'team.updated' => 'Team updated', 'team.member_added' => 'Team member added', 'team.member_removed' => 'Team member removed',
            'impact.logged' => 'Impact report logged', 'story.submitted' => 'Story submitted', 'feedback.submitted' => 'Feedback / issue reported', 'community.post_created' => 'Community post', 'community.post_deleted' => 'Community post deleted',
            'event.rsvp' => 'Event interest registered', 'gamification.award' => 'Badge awarded', 'comm.send' => 'Bulk communication sent',
            'system.settings_saved' => 'Settings changed', 'system.admin_granted' => 'Admin access granted', 'system.admin_revoked' => 'Admin access revoked', 'system.migrate' => 'Database updated', 'system.import' => 'Data imported',
            'system.email_template_saved' => 'Email template saved', 'system.form_template_saved' => 'Form template saved', 'system.maintenance' => 'Maintenance mode changed', 'system.sysadmin_toggled' => 'System admin flag changed', 'system.seeded' => 'Sample data seeded', 'system.installed' => 'Application installed',
        ];
        if (isset($map[$action])) {
            return $map[$action];
        }
        return ucfirst(str_replace(['.', '_'], [': ', ' '], $action));
    }

    /** Called by AuditLog::log for every recorded change. Never throws. */
    public static function fromAudit(string $action, ?string $entityType, ?string $entityId, mixed $details, ?string $actorId): void
    {
        try {
            if (self::$depth > 0 || !self::enabled()) {
                return;
            }
            $cat = self::categoryFor($action);
            if ($cat === null || !self::categoryEnabled($cat)) {
                return;
            }
            self::$depth++;
            $facts = [];
            [$subject, $link] = self::resolveEntity($entityType, $entityId);
            if ($subject) {
                $facts[ucfirst((string) $entityType)] = $subject;
            }
            $meta = is_string($details) ? (json_decode($details, true) ?? ['Details' => $details]) : (is_array($details) ? $details : []);
            foreach ($meta as $k => $v) {
                if (count($facts) >= 9) {
                    break;
                }
                if (is_scalar($v) && (string) $v !== '') {
                    $facts[ucfirst(str_replace('_', ' ', (string) $k))] = mb_substr(is_bool($v) ? ($v ? 'Yes' : 'No') : (string) $v, 0, 160);
                } elseif (is_array($v) && $v && array_is_list($v) && is_scalar($v[0])) {
                    $facts[ucfirst(str_replace('_', ' ', (string) $k))] = mb_substr(implode(', ', array_map('strval', $v)), 0, 160);
                }
            }
            $actor = $actorId ? User::find($actorId) : null;
            self::notify($cat, self::describe($action), $facts, $link, $actor ? (User::fullName($actor) ?: $actor['email']) : 'System', $action);
        } catch (\Throwable $e) {
            Logger::warning('Teams: ' . $e->getMessage());
        } finally {
            self::$depth = max(0, self::$depth - 1);
        }
    }

    /** @return array{0:?string,1:?string} [display name, app link] */
    private static function resolveEntity(?string $type, ?string $id): array
    {
        if (!$type || !$id) {
            return [null, null];
        }
        try {
            switch ($type) {
                case 'application':
                    $a = Application::find($id);
                    return $a ? [$a['name'] . ' (' . $a['email'] . ', ' . $a['volunteer_category'] . ')', '/admin/applications/' . $id] : [null, '/admin/applications'];
                case 'user':
                    $u = User::find($id);
                    return $u ? [(User::fullName($u) ?: $u['email']) . ' (' . $u['email'] . ')', '/admin/users/' . $id] : [null, '/admin/users?type=all'];
                case 'event':
                    $e = DB::fetch('SELECT name, team_id FROM events WHERE id = ?', [$id]);
                    return $e ? [$e['name'], '/events/planning/' . $e['team_id']] : [null, null];
                case 'team':
                    $t = DB::fetch('SELECT name FROM teams WHERE id = ?', [$id]);
                    return $t ? [$t['name'], '/admin/teams/' . $id] : [null, null];
                case 'report':
                    return ['Report #' . substr($id, 0, 8), '/reports/' . $id];
                default:
                    return [null, null];
            }
        } catch (\Throwable) {
            return [null, null];
        }
    }

    /** Queue a card and try to deliver it straight away. Returns the outbox id (0 when Teams is off). */
    public static function notify(string $category, string $title, array $facts = [], ?string $link = null, ?string $actor = null, ?string $action = null, ?string $text = null): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $payload = self::card($title, $facts, $link, $actor, $text, $category);
        DB::insert('teams_outbox', ['category' => $category, 'action' => $action, 'title' => mb_substr($title, 0, 200), 'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'status' => 'pending', 'attempts' => 0, 'created_at' => now()]);
        $id = (int) DB::lastInsertId();
        if (!Setting::bool('teams_queue_only')) {
            self::deliver($id);
        }
        return $id;
    }

    /** Adaptive Card wrapped the way Teams webhooks / Workflows expect. */
    public static function card(string $title, array $facts, ?string $link, ?string $actor, ?string $text, string $category): array
    {
        $base = rtrim((string) Config::get('app.url'), '/');
        $body = [
            ['type' => 'TextBlock', 'size' => 'Small', 'weight' => 'Bolder', 'color' => 'Accent', 'text' => strtoupper((string) Config::get('app.name', 'iERA App')) . ' · ' . (self::CATEGORIES[$category][0] ?? ucfirst($category)), 'spacing' => 'None'],
            ['type' => 'TextBlock', 'size' => 'Medium', 'weight' => 'Bolder', 'text' => $title, 'wrap' => true],
        ];
        if ($text) {
            $body[] = ['type' => 'TextBlock', 'text' => $text, 'wrap' => true];
        }
        if ($facts) {
            $body[] = ['type' => 'FactSet', 'facts' => array_map(fn($k, $v) => ['title' => (string) $k, 'value' => (string) $v], array_keys($facts), $facts)];
        }
        $body[] = ['type' => 'TextBlock', 'size' => 'Small', 'isSubtle' => true, 'text' => ($actor ? 'By ' . $actor . ' · ' : '') . date('j M Y, H:i') . ' UTC', 'wrap' => true, 'spacing' => 'Small'];
        $card = ['$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json', 'type' => 'AdaptiveCard', 'version' => '1.4', 'msteams' => ['width' => 'Full'], 'body' => $body];
        if ($link) {
            $card['actions'] = [['type' => 'Action.OpenUrl', 'title' => 'Open in ' . Config::get('app.name', 'iERA App'), 'url' => str_starts_with($link, 'http') ? $link : $base . $link]];
        }
        return ['type' => 'message', 'summary' => $title, 'attachments' => [['contentType' => 'application/vnd.microsoft.card.adaptive', 'contentUrl' => null, 'content' => $card]]];
    }

    /** Post one outbox row to Teams. */
    public static function deliver(int $id): bool
    {
        $row = DB::fetch('SELECT * FROM teams_outbox WHERE id = ?', [$id]);
        if (!$row || $row['status'] === 'sent') {
            return (bool) $row;
        }
        $payload = json_decode($row['payload_json'], true) ?: [];
        [$ok, $err] = self::post($payload);
        DB::update('teams_outbox', ['attempts' => (int) $row['attempts'] + 1, 'status' => $ok ? 'sent' : ((int) $row['attempts'] + 1 >= 5 ? 'failed' : 'pending'), 'last_error' => $ok ? null : mb_substr((string) $err, 0, 1000), 'sent_at' => $ok ? now() : null], 'id = ?', [$id]);
        return $ok;
    }

    /** @return array{0:bool,1:?string} */
    private static function post(array $payload): array
    {
        if (self::$transport !== null) {
            try {
                return [(bool) call_user_func(self::$transport, $payload), null];
            } catch (\Throwable $e) {
                return [false, $e->getMessage()];
            }
        }
        $url = self::webhook();
        if ($url === '' || !preg_match('#^https://#i', $url)) {
            return [false, 'The Teams webhook URL is missing or is not an https:// address.'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) {
            return [false, 'Could not reach Teams: ' . $err];
        }
        if ($code >= 200 && $code < 300) {
            return [true, null];
        }
        return [false, 'Teams answered HTTP ' . $code . ($res ? ': ' . mb_substr(strip_tags((string) $res), 0, 300) : '')];
    }

    /** Scheduler: retry pending rows (also used by the "Retry" button). */
    public static function flush(int $limit = 50): array
    {
        $out = ['sent' => 0, 'failed' => 0];
        if (!self::enabled()) {
            return $out;
        }
        foreach (DB::fetchAll("SELECT id FROM teams_outbox WHERE status = 'pending' ORDER BY id ASC LIMIT $limit") as $r) {
            self::deliver((int) $r['id']) ? $out['sent']++ : $out['failed']++;
        }
        return $out;
    }

    public static function retryFailed(): int
    {
        return DB::update('teams_outbox', ['status' => 'pending', 'attempts' => 0], "status = 'failed'", []);
    }

    public static function test(array $admin): array
    {
        $id = self::notify('admin', 'Test message from ' . Config::get('app.name', 'iERA App'), ['Sent by' => User::fullName($admin) ?: $admin['email'], 'Server time' => now() . ' UTC'], '/system/teams', User::fullName($admin) ?: $admin['email'], 'system.teams_test', 'If you can see this card, the connection between the app and this channel works. Changes made in the app will appear here.');
        $row = $id ? DB::fetch('SELECT * FROM teams_outbox WHERE id = ?', [$id]) : null;
        return ['ok' => $row && $row['status'] === 'sent', 'error' => $row['last_error'] ?? 'Teams is not enabled or the webhook URL is missing.'];
    }

    public static function stats(): array
    {
        $day = date('Y-m-d H:i:s', time() - 86400);
        return [
            'pending' => (int) DB::fetchColumn("SELECT COUNT(*) FROM teams_outbox WHERE status = 'pending'"),
            'failed' => (int) DB::fetchColumn("SELECT COUNT(*) FROM teams_outbox WHERE status = 'failed'"),
            'sent_24h' => (int) DB::fetchColumn("SELECT COUNT(*) FROM teams_outbox WHERE status = 'sent' AND sent_at >= ?", [$day]),
            'last_sent' => DB::fetchColumn("SELECT MAX(sent_at) FROM teams_outbox WHERE status = 'sent'"),
            'last_error' => DB::fetchColumn("SELECT last_error FROM teams_outbox WHERE last_error IS NOT NULL ORDER BY id DESC LIMIT 1"),
        ];
    }

    public static function recent(int $limit = 60): array
    {
        return DB::fetchAll("SELECT id, category, action, title, status, attempts, last_error, created_at, sent_at FROM teams_outbox ORDER BY id DESC LIMIT $limit");
    }

    public static function purge(int $days = 30): void
    {
        DB::delete('teams_outbox', "status = 'sent' AND created_at < ?", [date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
