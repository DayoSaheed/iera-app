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
            // ---- Appearance ----
            'brand_primary' => ['Primary colour', '#d4006a', 'color', 'Appearance', 'Buttons, links and highlights (magenta).'],
            'brand_accent' => ['Accent colour', '#f6c400', 'color', 'Appearance', 'Secondary highlights and calls to action (yellow).'],
            'brand_dark' => ['Sidebar colour', '#0b0b0f', 'color', 'Appearance', 'Sidebar and dark surfaces (black).'],
            'brand_logo' => ['Logo', '', 'image', 'Appearance', 'PNG or SVG, shown in the sidebar and on the sign-in page. Square or wide, max 2 MB.'],
            'login_background' => ['Sign-in / registration background image', '', 'image', 'Appearance', 'JPG or PNG, at least 1600px wide, max 4 MB. A dark overlay keeps the text readable.'],
            'login_tagline' => ['Sign-in page tagline', 'Connecting humanity with God, one conversation at a time.', 'text', 'Appearance', 'Short line shown under the logo on the sign-in page.'],
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
            // ---- Email delivery (editable by the admin; overrides config.php) ----
            'mail_driver' => ['Mail delivery method', 'log', 'select:smtp|sendgrid|mail|log', 'Email', 'smtp = your mail server (recommended on cPanel), sendgrid = SendGrid API, mail = PHP mail(), log = write to storage/logs/mail.log only (nothing is really sent).'],
            'mail_from_email' => ['From address', '', 'email', 'Email', 'Must be a mailbox on your domain, e.g. no-reply@iera.ca.'],
            'mail_from_name' => ['From name', 'iERA', 'text', 'Email', ''],
            'smtp_host' => ['SMTP host', 'localhost', 'text', 'Email', 'On cPanel usually localhost or mail.yourdomain.'],
            'smtp_port' => ['SMTP port', '465', 'number', 'Email', '465 with SSL, 587 with TLS.'],
            'smtp_encryption' => ['SMTP encryption', 'ssl', 'select:ssl|tls|none', 'Email', ''],
            'smtp_user' => ['SMTP username', '', 'text', 'Email', 'Usually the full mailbox address.'],
            'smtp_pass' => ['SMTP password', '', 'secret', 'Email', 'Stored encrypted. Leave blank to keep the current value.'],
            'sendgrid_api_key' => ['SendGrid API key', '', 'secret', 'Email', 'Only for the sendgrid method. Stored encrypted.'],
            // ---- Stripe Identity ----
            'stripe_secret_key' => ['Stripe secret key', '', 'secret', 'Stripe Identity', 'sk_live_... Stored encrypted.'],
            'stripe_webhook_secret' => ['Stripe webhook signing secret', '', 'secret', 'Stripe Identity', 'whsec_... Webhook URL: /webhooks/stripe'],
            // ---- Microsoft SharePoint ----
            'ms_tenant_id' => ['Microsoft tenant ID', '', 'text', 'SharePoint', ''],
            'ms_client_id' => ['Microsoft app (client) ID', '', 'text', 'SharePoint', ''],
            'ms_client_secret' => ['Microsoft client secret', '', 'secret', 'SharePoint', 'Stored encrypted.'],
            'sp_street_dawah_site_id' => ['Street Dawah list: site ID', '', 'text', 'SharePoint', ''],
            'sp_street_dawah_list_id' => ['Street Dawah list: list ID', '', 'text', 'SharePoint', ''],
            'sp_nm_tracker_site_id' => ['New Muslim tracker: site ID', '', 'text', 'SharePoint', ''],
            'sp_nm_tracker_list_id' => ['New Muslim tracker: list ID', '', 'text', 'SharePoint', ''],
            // ---- Scheduler ----
            'cron_secret' => ['Scheduler web key', '', 'secret', 'System', 'Lets an external monitor trigger the jobs: GET /cron/run?key=... every 5 minutes. Jobs can also be run from the Scheduled jobs page.'],
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
            $v = self::$cache[$key];
            return self::isSecret($key) ? \App\Core\Crypto::decrypt((string) $v) : $v;
        }
        return $default ?? (self::definitions()[$key][1] ?? null);
    }

    public static function isSecret(string $key): bool
    {
        return (self::definitions()[$key][2] ?? '') === 'secret';
    }

    /** Setting value, falling back to a config.php key when the setting is empty. */
    public static function orConfig(string $settingKey, string $configKey, mixed $default = ''): mixed
    {
        $v = self::get($settingKey);
        if ($v !== null && $v !== '') {
            return $v;
        }
        $c = \App\Core\Config::get($configKey);
        return $c !== null && $c !== '' ? $c : $default;
    }

    /** Brand colours with derived shades for CSS variables. */
    public static function brand(): array
    {
        $primary = self::hex((string) self::get('brand_primary'), '#d4006a');
        $accent = self::hex((string) self::get('brand_accent'), '#f6c400');
        $dark = self::hex((string) self::get('brand_dark'), '#0b0b0f');
        return [
            'primary' => $primary, 'primary_600' => self::shade($primary, -0.15), 'primary_50' => self::tint($primary, 0.9),
            'accent' => $accent, 'accent_50' => self::tint($accent, 0.84), 'dark' => $dark,
        ];
    }

    public static function logoUrl(): string
    {
        $logo = (string) self::get('brand_logo', '');
        return $logo !== '' ? url($logo) : url('/assets/img/logo.svg');
    }

    private static function hex(string $v, string $default): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : $default;
    }

    private static function shade(string $hex, float $amount): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $f = fn(int $c) => max(0, min(255, (int) round($c * (1 + $amount))));
        return sprintf('#%02x%02x%02x', $f($r), $f($g), $f($b));
    }

    private static function tint(string $hex, float $amount): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $f = fn(int $c) => max(0, min(255, (int) round($c + (255 - $c) * $amount)));
        return sprintf('#%02x%02x%02x', $f($r), $f($g), $f($b));
    }

    /** Resolved mail configuration (settings first, then config.php). */
    public static function mail(): array
    {
        return [
            'driver' => (string) self::orConfig('mail_driver', 'mail.driver', 'log'),
            'from_email' => (string) self::orConfig('mail_from_email', 'mail.from_email', ''),
            'from_name' => (string) self::orConfig('mail_from_name', 'mail.from_name', ''),
            'sendgrid_api_key' => (string) self::orConfig('sendgrid_api_key', 'mail.sendgrid_api_key', ''),
            'smtp' => [
                'host' => (string) self::orConfig('smtp_host', 'mail.smtp.host', 'localhost'),
                'port' => (int) self::orConfig('smtp_port', 'mail.smtp.port', 587),
                'user' => (string) self::orConfig('smtp_user', 'mail.smtp.user', ''),
                'pass' => (string) self::orConfig('smtp_pass', 'mail.smtp.pass', ''),
                'encryption' => (string) self::orConfig('smtp_encryption', 'mail.smtp.encryption', 'tls'),
            ],
        ];
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
        if ($value !== null && $value !== '' && self::isSecret($key)) {
            $value = \App\Core\Crypto::encrypt($value);
        }
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
