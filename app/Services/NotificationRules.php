<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;
use App\Core\Mailer;
use App\Models\Application;
use App\Models\Notification;
use App\Models\User;

/** Admin-configured routing rules: who gets told (email / in-app / WhatsApp placeholder) when something happens. */
final class NotificationRules
{
    public const TRIGGERS = [
        'category_global' => ['New Global volunteer', 'globe'], 'category_uk' => ['New UK volunteer', 'flag'], 'category_online' => ['New Online volunteer', 'globe'],
        'issue_reported' => ['Issue / bug reported', 'alert-triangle'], 'profile_completed' => ['Profile completed', 'check-circle'],
        'training_completed' => ['Training completed', 'graduation-cap'], 'story_submitted' => ['Story submitted', 'book'], 'custom' => ['Custom rule', 'settings'],
    ];
    public const CHANNELS = ['email' => 'Email', 'in_app' => 'In-app notification', 'dashboard_alert' => 'Dashboard alert', 'whatsapp' => 'WhatsApp (placeholder)'];
    public const DEFAULT_SUBJECT = 'New Volunteer Signup: {{full_name}} - {{category}}';
    public const VARS = ['full_name', 'email', 'phone', 'category', 'country', 'city', 'university', 'skills', 'dawah_experience', 'hours_per_week', 'signup_date', 'profile_completion', 'admin_review_link', 'title', 'description'];

    public static function rules(bool $activeOnly = false, ?string $trigger = null): array
    {
        $sql = 'SELECT * FROM notification_rules WHERE 1=1' . ($activeOnly ? ' AND is_active = 1' : '') . ($trigger ? ' AND trigger_type = ?' : '') . ' ORDER BY id DESC';
        $rows = DB::fetchAll($sql, $trigger ? [$trigger] : []);
        foreach ($rows as &$r) {
            $r['channels'] = $r['channels_json'] ? (json_decode($r['channels_json'], true) ?: []) : ['email'];
        }
        return $rows;
    }

    public static function rule(int $id): ?array
    {
        $r = DB::fetch('SELECT * FROM notification_rules WHERE id = ?', [$id]);
        if ($r) {
            $r['channels'] = $r['channels_json'] ? (json_decode($r['channels_json'], true) ?: []) : ['email'];
        }
        return $r;
    }

    /** @return array{0:?int,1:array} */
    public static function save(array $in, ?int $id = null): array
    {
        $name = mb_substr(trim((string) ($in['rule_name'] ?? '')), 0, 150);
        $email = strtolower(trim((string) ($in['recipient_email'] ?? '')));
        $channels = array_values(array_intersect((array) ($in['channels'] ?? []), array_keys(self::CHANNELS)));
        $errors = [];
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['rule_name'] = ['Rule name and recipient email are required.']; }
        if (!$channels) { $errors['channels'] = ['Select at least one notification channel.']; }
        if ($errors) {
            return [null, $errors];
        }
        $data = ['rule_name' => $name, 'trigger_type' => isset(self::TRIGGERS[$in['trigger_type'] ?? '']) ? $in['trigger_type'] : 'custom', 'recipient_name' => mb_substr(trim((string) ($in['recipient_name'] ?? '')), 0, 150) ?: null, 'recipient_email' => $email,
            'recipient_whatsapp' => mb_substr(trim((string) ($in['recipient_whatsapp'] ?? '')), 0, 40) ?: null, 'channels_json' => json_encode($channels), 'email_subject_template' => mb_substr(trim((string) ($in['email_subject_template'] ?? '')), 0, 255) ?: null,
            'email_body_template' => trim((string) ($in['email_body_template'] ?? '')) ?: null, 'is_active' => !empty($in['is_active']) ? 1 : 0, 'priority' => in_array($in['priority'] ?? '', ['low', 'normal', 'high'], true) ? $in['priority'] : 'normal', 'updated_at' => now()];
        if ($id) {
            DB::update('notification_rules', $data, 'id = ?', [$id]);
            return [$id, []];
        }
        DB::insert('notification_rules', $data + ['created_at' => now()]);
        return [(int) DB::lastInsertId(), []];
    }

    public static function delete(int $id): void
    {
        DB::delete('notification_rules', 'id = ?', [$id]);
    }

    public static function setActive(int $id, bool $on): void
    {
        DB::update('notification_rules', ['is_active' => $on ? 1 : 0, 'updated_at' => now()], 'id = ?', [$id]);
    }

    /** Mustache-lite: {{#key}}…{{/key}} blocks shown when key is truthy, then {{key}} substitution. */
    public static function render(string $text, array $vars): string
    {
        $text = preg_replace_callback('/\{\{#([a-z0-9_]+)\}\}(.*?)\{\{\/\1\}\}/is', fn($m) => !empty($vars[$m[1]]) && $vars[$m[1]] !== 'false' ? $m[2] : '', $text) ?? $text;
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', fn($m) => e((string) ($vars[$m[1]] ?? '')), $text) ?? $text;
    }

    public static function signupVars(array $app): array
    {
        $pct = Application::completionPercent($app);
        return [
            'full_name' => $app['name'] ?: $app['email'], 'email' => $app['email'], 'phone' => $app['phone'] ?: '—', 'category' => $app['volunteer_category'] ?: '—', 'country' => $app['country'] ?: '—', 'city' => $app['city'] ?: '—',
            'university' => (string) $app['university'], 'skills' => $app['skills'] ? implode(', ', $app['skills']) : '—', 'dawah_experience' => $app['dawah_experience_length'] ?: '—', 'hours_per_week' => $app['hours_per_week'] ?: '—',
            'signup_date' => fmt_datetime($app['created_at']), 'profile_completion' => (string) $pct, 'admin_review_link' => rtrim((string) Config::get('app.url'), '/') . '/admin/applications/' . $app['id'], 'incomplete_note' => $pct < 30 ? 'true' : '',
        ];
    }

    public static function defaultBody(): string
    {
        $row = fn(string $l, string $k) => '<tr><td style="padding:6px 10px;color:#666">' . $l . '</td><td style="padding:6px 10px"><strong>{{' . $k . '}}</strong></td></tr>';
        return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #e5e5e5;border-radius:10px;padding:20px"><h2 style="margin-top:0">New Volunteer Signup</h2><table style="width:100%;border-collapse:collapse">'
            . $row('Full Name', 'full_name') . $row('Email', 'email') . $row('Phone / WhatsApp', 'phone') . $row('Category', 'category') . $row('Country', 'country') . $row('City', 'city') . '{{#university}}' . $row('University', 'university') . '{{/university}}'
            . $row('Skills', 'skills') . $row('Dawah Experience', 'dawah_experience') . $row('Availability', 'hours_per_week') . $row('Signed Up', 'signup_date') . '<tr><td style="padding:6px 10px;color:#666">Profile Completion</td><td style="padding:6px 10px"><strong>{{profile_completion}}%</strong></td></tr></table>'
            . '{{#incomplete_note}}<p style="background:#fff7d6;padding:10px;border-radius:8px">Full profile not yet completed.</p>{{/incomplete_note}}'
            . '<p><a href="{{admin_review_link}}" style="display:inline-block;background:#d4006a;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none">View in Admin Panel</a></p><p style="color:#999;font-size:12px">iERA Connect · Automated Notification</p></div>';
    }

    /** New application: fire the category_* rules. */
    public static function fireSignup(array $app): int
    {
        $trigger = 'category_' . strtolower($app['volunteer_category'] ?: 'global');
        $vars = self::signupVars($app);
        return self::fire($trigger, $vars, $app, ($app['name'] ?: $app['email']) . ' (' . $app['volunteer_category'] . ') signed up. Profile: ' . $vars['profile_completion'] . '%.', 'user-plus');
    }

    /** Fire every active rule of a trigger type with the given variables; returns the number of deliveries attempted. */
    public static function fire(string $trigger, array $vars, ?array $app, string $summary, string $icon = 'bell'): int
    {
        $n = 0;
        foreach (self::rules(true, $trigger) as $rule) {
            $subject = self::render($rule['email_subject_template'] ?: self::DEFAULT_SUBJECT, $vars);
            foreach ($rule['channels'] as $ch) {
                $status = 'sent';
                $err = null;
                try {
                    if ($ch === 'email') {
                        $body = self::render($rule['email_body_template'] ?: self::defaultBody(), $vars);
                        if (!Mailer::send(['to' => [$rule['recipient_email']], 'subject' => $subject, 'html' => $body, 'template_key' => 'rule:' . $rule['id']])) {
                            $status = 'failed';
                            $err = Mailer::$lastError;
                        }
                    } elseif ($ch === 'in_app' || $ch === 'dashboard_alert') {
                        $target = User::findByEmail($rule['recipient_email']);
                        if ($target) {
                            Notification::push($target['id'], $subject, $summary, ['category' => 'admin', 'icon' => $icon, 'link' => $app ? '/admin/applications/' . $app['id'] : '/admin', 'source' => 'rule:' . $rule['id']]);
                        } else {
                            $status = 'failed';
                            $err = 'No user account for ' . $rule['recipient_email'];
                        }
                    } else {
                        $status = 'pending';
                        $err = 'Chat integration not connected yet.';
                    }
                } catch (\Throwable $e) {
                    $status = 'failed';
                    $err = $e->getMessage();
                }
                DB::insert('notification_logs', ['rule_id' => $rule['id'], 'rule_name' => $rule['rule_name'], 'trigger_type' => $trigger, 'channel' => $ch, 'recipient_email' => $rule['recipient_email'], 'recipient_name' => $rule['recipient_name'],
                    'volunteer_email' => $app['email'] ?? ($vars['email'] ?? null), 'volunteer_name' => $app['name'] ?? ($vars['full_name'] ?? null), 'volunteer_category' => $app['volunteer_category'] ?? null, 'subject' => mb_substr($subject, 0, 255),
                    'status' => $status, 'error_message' => $err, 'sent_at' => $status === 'sent' ? now() : null, 'created_at' => now()]);
                $n++;
            }
        }
        return $n;
    }

    public static function logs(string $status = '', int $limit = 200): array
    {
        return DB::fetchAll('SELECT * FROM notification_logs' . ($status ? ' WHERE status = ?' : '') . " ORDER BY id DESC LIMIT $limit", $status ? [$status] : []);
    }
}
