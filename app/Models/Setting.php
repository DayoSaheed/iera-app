<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Key/value settings editable in System Admin. */
final class Setting
{
    private static ?array $cache = null;

    /** Defaults, also used to render the settings form. key => [label, default, type, group, help] */
    public static function definitions(): array
    {
        return [
            'org_name' => ['Organisation name', 'iERA Canada', 'text', 'General', ''],
            'support_email' => ['Support / contact email', 'admin@example.org', 'email', 'General', 'Shown in the footer and used as reply-to.'],
            'admin_notification_emails' => ['Admin notification emails', 'admin@example.org', 'textarea', 'Notifications', 'One address per line. Receives new volunteer / new Muslim registration alerts and system notices.'],
            'volunteer_manager_email' => ['Volunteer manager email', '', 'email', 'Notifications', 'CC on team related notices.'],
            'newmuslim_coordinator_email' => ['New Muslim coordinator email', '', 'email', 'Notifications', 'Receives new Muslim registrations and mentor escalations.'],
            'whatsapp_group_brothers' => ['WhatsApp group link (brothers)', '', 'text', 'New Muslim', 'Sent to male mentees when a mentor accepts them.'],
            'whatsapp_group_sisters' => ['WhatsApp group link (sisters)', '', 'text', 'New Muslim', 'Sent to female mentees when a mentor accepts them.'],
            'training_video_url' => ['Training video embed URL', 'https://player.vimeo.com/video/770157607?h=9597c76bc6', 'text', 'Volunteers', 'Vimeo/YouTube embed URL shown on the pending page.'],
            'docs_base_url' => ['Documents base URL', 'https://docs.iera.ca/pdfs/', 'text', 'New Muslim', 'Folder containing the PDF handbooks.'],
            'feedback_url' => ['Report issues link', '', 'text', 'General', 'Shown in the sidebar ("Report Issues").'],
            'chat_widget_code' => ['Chat widget embed code', '', 'textarea', 'New Muslim', 'Optional HTML/JS (e.g. Tawk.to) embedded on the New Muslim home page.'],
            'inactivity_days' => ['Days without login before a volunteer is paused', '120', 'number', 'Automation', ''],
            'inactivity_skip_from' => ['Skip inactivity check from (MM-DD)', '12-15', 'text', 'Automation', 'Winter break start.'],
            'inactivity_skip_to' => ['Skip inactivity check until (MM-DD)', '01-15', 'text', 'Automation', 'Winter break end.'],
            'min_events_per_month' => ['Minimum events per team per month', '2', 'number', 'Automation', 'Teams below this get a reminder to leads.'],
            'report_reminder_hours' => ['First pending report reminder (hours)', '6', 'number', 'Automation', ''],
            'report_reminder_hours_2' => ['Second pending report reminder (hours)', '24', 'number', 'Automation', ''],
            'event_reminder_days' => ['Event reminder (days before)', '2', 'number', 'Automation', ''],
            'login_password_enabled' => ['Allow password sign-in', '1', 'bool', 'Security', 'Users may set a password in their profile; email codes always work.'],
            'require_2fa_admins' => ['Require two-step sign-in for admins', '0', 'bool', 'Security', 'Superadmins and system admins must confirm each sign-in with an emailed code (or authenticator app when enabled).'],
            'maintenance_mode' => ['Maintenance mode', '0', 'bool', 'System', 'Only system admins can use the app.'],
            'maintenance_message' => ['Maintenance message', 'We are performing scheduled maintenance. Please check back shortly.', 'textarea', 'System', ''],
            'sharepoint_sync_enabled' => ['SharePoint sync enabled', '0', 'bool', 'Integrations', 'Requires credentials in config.php.'],
            'stripe_identity_enabled' => ['Stripe Identity enabled', '0', 'bool', 'Integrations', 'Requires keys in config.php. When off, the ID step is skipped.'],
            'reports_export_enabled' => ['Write JSON report snapshots', '1', 'bool', 'Integrations', 'Writes storage/exports/event_report.json and mentor.json every 6 hours for BI tools.'],
        ];
    }

    private static function load(): void
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (DB::fetchAll('SELECT setting_key, value FROM settings') as $r) {
                    self::$cache[$r['setting_key']] = $r['value'];
                }
            } catch (\Throwable) {
                // Database not ready yet (installer)
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        if (array_key_exists($key, self::$cache) && self::$cache[$key] !== null) {
            return self::$cache[$key];
        }
        return $default ?? (self::definitions()[$key][1] ?? null);
    }

    public static function bool(string $key): bool
    {
        return in_array((string) self::get($key), ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    /** Admin notification list as array of emails */
    public static function emails(string $key): array
    {
        $raw = (string) self::get($key, '');
        $list = preg_split('/[\s,;]+/', $raw) ?: [];
        return array_values(array_filter(array_map('trim', $list), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    public static function set(string $key, ?string $value): void
    {
        self::load();
        if (DB::fetchColumn('SELECT 1 FROM settings WHERE setting_key = ?', [$key])) {
            DB::update('settings', ['value' => $value, 'updated_at' => now()], 'setting_key = ?', [$key]);
        } else {
            DB::insert('settings', ['setting_key' => $key, 'value' => $value, 'updated_at' => now()]);
        }
        self::$cache[$key] = $value;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}
