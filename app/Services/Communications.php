<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;
use App\Core\Mailer;
use App\Models\Application;
use App\Models\Notification;
use App\Models\User;

/** Bulk communications: reusable email templates, filtered sends, scheduled sends, automatic triggers and the per-volunteer log. */
final class Communications
{
    public const CATEGORIES = ['onboarding', 'event', 'weekly update', 'reminder', 'chaser', 'recognition', 'general', 'application_received', 'application_approved', 'application_rejected'];
    public const TRANSACTIONAL = ['application_received', 'application_approved', 'application_rejected'];
    /** template category => [in-app notification category, link] */
    public const MAP = [
        'application_received' => ['application', '/dashboard'], 'application_approved' => ['application', '/dashboard'], 'application_rejected' => ['application', '/dashboard'],
        'event' => ['events', '/events'], 'reminder' => ['events', '/events'], 'onboarding' => ['application', '/onboarding'], 'general' => ['general', '/dashboard'],
        'weekly update' => ['general', '/dashboard'], 'recognition' => ['streak', '/leaderboard'], 'chaser' => ['reports', '/impact'],
    ];
    /** template category => legacy communication_preferences key the volunteer can switch off */
    public const PREF_KEY = ['event' => 'event_updates', 'reminder' => 'event_updates', 'chaser' => 'activity_reminders', 'weekly update' => 'weekly_updates', 'recognition' => 'recognition', 'general' => 'general_announcements', 'onboarding' => 'general_announcements'];
    public const ICONS = ['event' => 'calendar', 'reminder' => 'bell', 'chaser' => 'clock', 'weekly update' => 'bar-chart', 'recognition' => 'trophy', 'general' => 'megaphone', 'onboarding' => 'compass', 'application_received' => 'check-circle', 'application_approved' => 'award', 'application_rejected' => 'x-circle'];
    public const PLACEHOLDERS = ['first_name', 'last_name', 'event_name', 'event_date', 'event_location', 'hours_this_month', 'total_hours', 'xp_total', 'dashboard_link', 'apply_link', 'rejection_reason'];
    public const SAMPLE = ['first_name' => 'Sarah', 'last_name' => 'Ahmed', 'event_name' => 'Street Dawah London', 'event_date' => '2026-06-01', 'event_location' => 'Oxford Street, London', 'hours_this_month' => '12', 'total_hours' => '48', 'xp_total' => '1200', 'dashboard_link' => '#', 'apply_link' => '#', 'rejection_reason' => 'Application did not meet current requirements.'];

    // ---- templates ---------------------------------------------------------------

    public static function templates(): array
    {
        return DB::fetchAll('SELECT * FROM comm_templates ORDER BY updated_at DESC, id DESC');
    }

    public static function template(int $id): ?array
    {
        return DB::fetch('SELECT * FROM comm_templates WHERE id = ?', [$id]);
    }

    public static function activeTemplate(string $category): ?array
    {
        return DB::fetch("SELECT * FROM comm_templates WHERE category = ? AND status = 'active' ORDER BY updated_at DESC LIMIT 1", [$category]);
    }

    /** @return array{0:?int,1:array} [id, errors] */
    public static function saveTemplate(array $in, array $admin, ?int $id = null): array
    {
        $errors = [];
        $name = mb_substr(trim((string) ($in['internal_name'] ?? '')), 0, 200);
        $subject = mb_substr(trim((string) ($in['subject_line'] ?? '')), 0, 255);
        $body = trim((string) ($in['body_html'] ?? ''));
        $cat = in_array($in['category'] ?? '', self::CATEGORIES, true) ? $in['category'] : 'general';
        if ($name === '') { $errors['internal_name'] = ['Give the template an internal name.']; }
        if ($subject === '') { $errors['subject_line'] = ['A subject line is required.']; }
        if ($body === '') { $errors['body_html'] = ['The email body cannot be empty.']; }
        if ($errors) {
            return [null, $errors];
        }
        $who = User::fullName($admin) ?: $admin['email'];
        $data = ['internal_name' => $name, 'category' => $cat, 'subject_line' => $subject, 'body_html' => $body, 'plain_text_summary' => mb_substr(trim((string) ($in['plain_text_summary'] ?? '')), 0, 2000) ?: null,
            'status' => ($in['status'] ?? 'draft') === 'active' ? 'active' : 'draft', 'last_edited_by_email' => $admin['email'], 'last_edited_by_name' => $who, 'updated_at' => now()];
        if ($id) {
            DB::update('comm_templates', $data, 'id = ?', [$id]);
            return [$id, []];
        }
        DB::insert('comm_templates', $data + ['created_by_email' => $admin['email'], 'created_by_name' => $who, 'created_at' => now()]);
        return [(int) DB::lastInsertId(), []];
    }

    public static function duplicateTemplate(int $id, array $admin): ?int
    {
        $t = self::template($id);
        if (!$t) {
            return null;
        }
        [$newId] = self::saveTemplate(['internal_name' => 'COPY OF ' . $t['internal_name'], 'category' => $t['category'], 'subject_line' => $t['subject_line'], 'body_html' => $t['body_html'], 'plain_text_summary' => $t['plain_text_summary'], 'status' => 'draft'], $admin);
        return $newId;
    }

    public static function templateUsed(int $id): bool
    {
        return (bool) DB::fetch('SELECT id FROM comm_sends WHERE template_id = ? LIMIT 1', [$id]);
    }

    // ---- rendering ---------------------------------------------------------------

    public static function render(string $text, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', fn($m) => (string) ($vars[$m[1]] ?? ''), $text) ?? $text;
    }

    public static function vars(array $app, array $eventData = []): array
    {
        $first = $app['first_name'] ?: (explode(' ', trim((string) $app['name']))[0] ?? '');
        $last = $app['last_name'] ?: trim((string) substr((string) $app['name'], strlen((string) $first)));
        $reports = Gamification::reports($app['user_id'], 2000);
        $monthStart = date('Y-m-01');
        $hoursMonth = array_sum(array_map(fn($r) => (float) $r['hours'], array_filter($reports, fn($r) => $r['report_date'] >= $monthStart)));
        $stats = Gamification::compute($reports, (int) DB::fetchColumn('SELECT COUNT(*) FROM training_completions WHERE user_id = ?', [$app['user_id']]), 0, $app, (int) DB::fetchColumn('SELECT COALESCE(SUM(xp_awarded),0) FROM training_awards WHERE user_id = ?', [$app['user_id']]));
        $base = rtrim((string) Config::get('app.url'), '/');
        $fmt = fn(float $h) => rtrim(rtrim(number_format($h, 1), '0'), '.');
        return [
            'first_name' => $first ?: 'Volunteer', 'last_name' => $last, 'full_name' => $app['name'], 'email' => $app['email'],
            'event_name' => (string) ($eventData['event_name'] ?? ''), 'event_date' => (string) ($eventData['event_date'] ?? ''), 'event_location' => (string) ($eventData['event_location'] ?? ''),
            'hours_this_month' => $fmt($hoursMonth), 'total_hours' => $fmt($stats['hours']), 'xp_total' => (string) $stats['xp'],
            'dashboard_link' => $base . '/dashboard', 'apply_link' => $base . '/apply', 'rejection_reason' => (string) ($eventData['rejection_reason'] ?? ''),
        ];
    }

    public static function toText(string $html): string
    {
        $t = preg_replace('/<(br|\/p|\/div|\/h[1-6]|\/li|\/tr)\s*\/?>/i', "\n", $html) ?? $html;
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/[ \t]+/", ' ', $t) ?? $t;
        $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
        return trim($t);
    }

    // ---- recipient filtering -------------------------------------------------------

    /** Spec filterVolunteers: status, volunteer_category, country, inactive_days, max_hours_this_month, min_training_pct. */
    public static function filterVolunteers(array $f): array
    {
        $apps = Application::list(array_filter(['status' => !empty($f['status']) ? [$f['status']] : null, 'category' => $f['volunteer_category'] ?? null]), 'a.full_name ASC', 5000);
        if (!empty($f['country'])) {
            $apps = array_values(array_filter($apps, fn($a) => strcasecmp((string) $a['country'], (string) $f['country']) === 0));
        }
        $needReports = !empty($f['inactive_days']) || isset($f['max_hours_this_month']) && $f['max_hours_this_month'] !== '';
        $byUser = [];
        if ($needReports && $apps) {
            [$in, $p] = DB::in(array_column($apps, 'user_id'));
            foreach (DB::fetchAll("SELECT user_id, report_date, hours FROM volunteer_reports WHERE user_id IN $in ORDER BY report_date DESC", $p) as $r) {
                $byUser[$r['user_id']][] = $r;
            }
        }
        if (!empty($f['inactive_days'])) {
            $days = (int) $f['inactive_days'];
            $cut = date('Y-m-d', strtotime("-$days days"));
            $apps = array_values(array_filter($apps, function ($a) use ($byUser, $cut, $days) {
                $reps = $byUser[$a['user_id']] ?? [];
                if (!$reps) {
                    return (time() - strtotime($a['created_at'])) / 86400 >= $days;
                }
                return $reps[0]['report_date'] < $cut;
            }));
        }
        if (isset($f['max_hours_this_month']) && $f['max_hours_this_month'] !== '') {
            $max = (float) $f['max_hours_this_month'];
            $monthStart = date('Y-m-01');
            $apps = array_values(array_filter($apps, fn($a) => array_sum(array_map(fn($r) => (float) $r['hours'], array_filter($byUser[$a['user_id']] ?? [], fn($r) => $r['report_date'] >= $monthStart))) <= $max));
        }
        if (isset($f['min_training_pct']) && $f['min_training_pct'] !== '') {
            $min = (float) $f['min_training_pct'];
            $modules = max(1, (int) DB::fetchColumn('SELECT COUNT(*) FROM training_modules WHERE is_active = 1'));
            $apps = array_values(array_filter($apps, fn($a) => ((int) DB::fetchColumn('SELECT COUNT(*) FROM training_completions WHERE user_id = ?', [$a['user_id']])) / $modules * 100 < $min));
        }
        return $apps;
    }

    // ---- sends -------------------------------------------------------------------

    public static function createSend(array $template, array $emails, array $filters, string $sendType, ?string $scheduledAt, ?array $admin, array $eventData = []): int
    {
        $emails = array_values(array_unique(array_filter(array_map(fn($e) => strtolower(trim((string) $e)), $emails))));
        DB::insert('comm_sends', [
            'template_id' => $template['id'], 'template_name' => $template['internal_name'], 'category' => $template['category'],
            'filters_json' => json_encode(array_filter($filters, fn($v) => $v !== '' && $v !== null)), 'recipient_count' => count($emails), 'recipient_emails_json' => json_encode($emails),
            'event_data_json' => $eventData ? json_encode($eventData) : null, 'scheduled_at' => $scheduledAt, 'send_type' => $sendType, 'status' => 'pending',
            'created_by_email' => $admin['email'] ?? 'system', 'created_by_name' => $admin ? (User::fullName($admin) ?: $admin['email']) : 'Automatic Trigger', 'created_at' => now(),
        ]);
        return (int) DB::lastInsertId();
    }

    public static function send(int $id): ?array
    {
        $r = DB::fetch('SELECT * FROM comm_sends WHERE id = ?', [$id]);
        if ($r) {
            $r['filters'] = $r['filters_json'] ? (json_decode($r['filters_json'], true) ?: []) : [];
            $r['recipient_emails'] = $r['recipient_emails_json'] ? (json_decode($r['recipient_emails_json'], true) ?: []) : [];
            $r['event_data'] = $r['event_data_json'] ? (json_decode($r['event_data_json'], true) ?: []) : [];
        }
        return $r;
    }

    public static function pendingSends(): array
    {
        return array_map(fn($r) => self::send((int) $r['id']), DB::fetchAll("SELECT id FROM comm_sends WHERE status = 'pending' ORDER BY scheduled_at ASC, id ASC LIMIT 200"));
    }

    public static function recentSends(int $limit = 50): array
    {
        return DB::fetchAll("SELECT * FROM comm_sends WHERE status <> 'pending' ORDER BY id DESC LIMIT $limit");
    }

    /** Deliver one send: email + in-app per recipient, honouring the volunteer's preferences. */
    public static function initiateSend(int $sendId): array
    {
        $send = self::send($sendId);
        $tpl = $send ? self::template((int) $send['template_id']) : null;
        if (!$send || !$tpl) {
            if ($send) {
                DB::update('comm_sends', ['status' => 'failed', 'result_json' => json_encode(['error' => 'Template not found'])], 'id = ?', [$sendId]);
                self::warnAdmins('Missing email template', 'A send for category "' . $send['category'] . '" could not fire because its template no longer exists. Please create and activate a template.');
            }
            return ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'error' => 'Template not found'];
        }
        $res = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        [$notifCat, $link] = self::MAP[$tpl['category']] ?? ['general', '/dashboard'];
        $prefKey = self::PREF_KEY[$tpl['category']] ?? null;
        $transactional = in_array($tpl['category'], self::TRANSACTIONAL, true);
        foreach ($send['recipient_emails'] as $email) {
            $app = Application::byEmail($email);
            if (!$app) {
                $res['skipped']++;
                continue;
            }
            if (!$transactional && $prefKey && isset($app['communication_preferences'][$prefKey]) && $app['communication_preferences'][$prefKey] === false) {
                $res['skipped']++;
                continue;
            }
            $emailOn = $transactional || Notification::allows($app['user_id'], $notifCat, 'email');
            $inAppOn = Notification::allows($app['user_id'], $notifCat, 'in_app');
            if (!$emailOn && !$inAppOn) {
                $res['skipped']++;
                continue;
            }
            $vars = self::vars($app, $send['event_data']);
            $subject = self::render($tpl['subject_line'], $vars);
            $html = self::render($tpl['body_html'], $vars);
            $plain = $tpl['plain_text_summary'] ? self::render($tpl['plain_text_summary'], $vars) : self::toText($html);
            $delivery = 'sent';
            if ($emailOn) {
                $ok = Mailer::send(['to' => [$email], 'subject' => $subject, 'html' => $html, 'text' => $plain, 'template_key' => 'comm:' . $tpl['id']]);
                if (!$ok) {
                    $delivery = 'failed';
                }
            }
            $notifId = 0;
            if ($inAppOn) {
                $notifId = Notification::push($app['user_id'], $subject, mb_substr($plain, 0, 500), ['category' => $notifCat, 'icon' => self::ICONS[$tpl['category']] ?? 'mail', 'link' => $link, 'source' => 'comm:' . $sendId]);
            }
            DB::insert('comm_logs', [
                'send_id' => $sendId, 'template_id' => $tpl['id'], 'template_name' => $tpl['internal_name'], 'category' => $tpl['category'], 'user_id' => $app['user_id'],
                'volunteer_email' => $email, 'volunteer_name' => $app['name'], 'sent_at' => now(), 'trigger_type' => $send['send_type'], 'delivery_status' => $delivery,
                'notification_id' => $notifId ?: null, 'in_app_read' => 0, 'subject_line_sent' => mb_substr($subject, 0, 255), 'body_html_sent' => $html,
            ]);
            $res[$delivery === 'failed' ? 'failed' : 'sent']++;
        }
        DB::update('comm_sends', ['status' => $res['failed'] > 0 && $res['sent'] === 0 ? 'failed' : 'sent', 'sent_at' => now(), 'result_json' => json_encode($res)], 'id = ?', [$sendId]);
        return $res;
    }

    /** Automatic trigger: uses the first active template of the category; warns admins when none exists. */
    public static function triggerAutomaticSend(string $trigger, array $emails, string $category, array $eventData = [], bool $warnIfMissing = true): ?int
    {
        if (!$emails) {
            return null;
        }
        $tpl = self::activeTemplate($category);
        if (!$tpl) {
            if ($warnIfMissing) {
                self::warnAdmins('Missing email template', 'Automatic trigger "' . $trigger . '" for category "' . $category . '" could not fire: no active template found. Create and activate one under Communications.');
            }
            return null;
        }
        $id = self::createSend($tpl, $emails, ['trigger' => $trigger], 'automatic', null, null, $eventData);
        self::initiateSend($id);
        return $id;
    }

    private static function warnAdmins(string $title, string $message): void
    {
        $ids = array_column(DB::fetchAll("SELECT id FROM users WHERE type = 'superadmin' OR id IN (SELECT user_id FROM user_roles WHERE role = 'admin')"), 'id');
        Notification::pushMany($ids, $title, $message, ['category' => 'admin', 'icon' => 'alert-triangle', 'type' => 'warning', 'link' => '/admin/communications']);
    }

    public static function cancelSend(int $id, string $reason): void
    {
        DB::update('comm_sends', ['status' => 'cancelled', 'cancellation_reason' => mb_substr($reason, 0, 1000)], "id = ? AND status = 'pending'", [$id]);
    }

    public static function rescheduleSend(int $id, string $at): void
    {
        DB::update('comm_sends', ['scheduled_at' => $at], "id = ? AND status = 'pending'", [$id]);
    }

    /** Cron: deliver scheduled sends whose time has come. */
    public static function runScheduled(): int
    {
        $n = 0;
        foreach (DB::fetchAll("SELECT id FROM comm_sends WHERE status = 'pending' AND send_type = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= ?", [now()]) as $r) {
            self::initiateSend((int) $r['id']);
            $n++;
        }
        return $n;
    }

    /** Cron (daily): 30-day inactivity nudge, and the zero-hours chaser on the last day of the month. */
    public static function inactivityCheck(): array
    {
        $approved = Application::list(['status' => ['Approved']], 'a.created_at ASC', 5000);
        $last = [];
        foreach (DB::fetchAll('SELECT user_id, MAX(report_date) d FROM volunteer_reports GROUP BY user_id') as $r) {
            $last[$r['user_id']] = $r['d'];
        }
        $cut = date('Y-m-d', strtotime('-30 days'));
        $inactive = [];
        foreach ($approved as $a) {
            $d = $last[$a['user_id']] ?? null;
            if (($d === null && (time() - strtotime($a['created_at'])) >= 30 * 86400) || ($d !== null && $d < $cut)) {
                $inactive[] = $a['email'];
            }
        }
        $out = ['inactive' => count($inactive), 'zero_hours' => 0, 'last_day' => date('t') === date('j')];
        if ($inactive) {
            self::triggerAutomaticSend('inactivity_nudge', $inactive, 'reminder');
        }
        if ($out['last_day']) {
            $monthStart = date('Y-m-01');
            $zero = array_column(array_filter($approved, fn($a) => ($last[$a['user_id']] ?? '0000') < $monthStart), 'email');
            $out['zero_hours'] = count($zero);
            if ($zero) {
                self::triggerAutomaticSend('zero_hours_chaser', $zero, 'chaser');
            }
        }
        return $out;
    }

    // ---- log ---------------------------------------------------------------------

    public static function logs(array $f = [], int $limit = 200): array
    {
        $sql = 'SELECT * FROM comm_logs WHERE 1=1';
        $p = [];
        if (!empty($f['q'])) { $sql .= ' AND (volunteer_email LIKE ? OR volunteer_name LIKE ?)'; $p[] = '%' . $f['q'] . '%'; $p[] = '%' . $f['q'] . '%'; }
        if (!empty($f['template'])) { $sql .= ' AND template_name LIKE ?'; $p[] = '%' . $f['template'] . '%'; }
        if (!empty($f['category'])) { $sql .= ' AND category = ?'; $p[] = $f['category']; }
        if (!empty($f['trigger'])) { $sql .= ' AND trigger_type = ?'; $p[] = $f['trigger']; }
        if (!empty($f['delivery'])) { $sql .= ' AND delivery_status = ?'; $p[] = $f['delivery']; }
        if (($f['read'] ?? '') === 'read') { $sql .= ' AND in_app_read = 1'; }
        if (($f['read'] ?? '') === 'unread') { $sql .= ' AND in_app_read = 0'; }
        return DB::fetchAll($sql . " ORDER BY id DESC LIMIT $limit", $p);
    }

    public static function log(int $id): ?array
    {
        return DB::fetch('SELECT * FROM comm_logs WHERE id = ?', [$id]);
    }

    /** Called when a volunteer reads a notification that came from a send. */
    public static function markRead(int $notificationId): void
    {
        DB::update('comm_logs', ['in_app_read' => 1, 'read_at' => now()], 'notification_id = ? AND in_app_read = 0', [$notificationId]);
    }
}
