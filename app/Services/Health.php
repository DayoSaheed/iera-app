<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Setting;

/**
 * Plain-language list of things an administrator should fix, with where to fix them.
 * Shown on the super admin dashboard and in System Admin. Each issue: level (error|warning|info), title, detail, href, action.
 */
final class Health
{
    public static function issues(): array
    {
        $out = [];
        $add = function (string $level, string $title, string $detail, string $href, string $action) use (&$out): void {
            $out[] = compact('level', 'title', 'detail', 'href', 'action');
        };

        // Database schema
        $pending = Migrator::pending();
        if ($pending) {
            $add('error', 'Database update required', count($pending) . ' database change' . (count($pending) > 1 ? 's' : '') . ' from this version (' . implode(', ', $pending) . ') have not been applied. Some records, such as the email log, cannot be saved until then.', '/system', 'Apply database updates');
        }

        // Email delivery
        $mail = Setting::mail();
        if ($mail['driver'] === 'log') {
            $add('error', 'Emails are not being sent', 'The delivery method is "log": sign-in codes and notifications are only written to storage/logs/mail.log. Choose smtp (recommended on cPanel) or sendgrid.', '/system/settings#Email', 'Set up email');
        } elseif ($mail['driver'] === 'smtp' && ($mail['smtp']['host'] === '' || $mail['smtp']['user'] === '' || $mail['smtp']['pass'] === '')) {
            $add('error', 'SMTP settings are incomplete', 'Host, username and password are all needed. On cPanel use the mailbox you created (host localhost, port 465, SSL).', '/system/settings#Email', 'Complete SMTP settings');
        } elseif ($mail['driver'] === 'sendgrid' && $mail['sendgrid_api_key'] === '') {
            $add('error', 'SendGrid API key is missing', 'The sendgrid method cannot send anything without an API key.', '/system/settings#Email', 'Add the API key');
        }
        $since = date('Y-m-d H:i:s', time() - 86400);
        $failed = EmailLog::countSince($since, 'failed');
        if ($failed > 0) {
            $last = DB::fetch("SELECT error FROM email_log WHERE status = 'failed' ORDER BY id DESC LIMIT 1");
            $add('warning', $failed . ' email' . ($failed > 1 ? 's' : '') . ' failed in the last 24 hours', 'Latest error: ' . ($last['error'] ?? 'unknown') . '. Check the delivery settings and send a test email.', '/system/email-log', 'Open email log');
        }
        if (!filter_var((string) Setting::get('mail_from_email'), FILTER_VALIDATE_EMAIL) && !filter_var((string) Config::get('mail.from_email'), FILTER_VALIDATE_EMAIL)) {
            $add('warning', 'Sender address not set', 'Emails need a valid "from" address, ideally a mailbox on your own domain so they are not marked as spam.', '/system/settings#Email', 'Set the sender');
        }

        // Scheduler
        $lastCron = DB::fetchColumn('SELECT MAX(started_at) FROM job_runs');
        if (!$lastCron) {
            $add('warning', 'The scheduler has never run', 'Reminders, report generation and weekly emails depend on cron. Add the cron command in cPanel, or run the jobs manually.', '/system/jobs', 'Set up cron');
        } elseif (strtotime((string) $lastCron) < time() - 3600) {
            $add('warning', 'The scheduler has not run since ' . fmt_datetime((string) $lastCron), 'The cron job seems to have stopped. Check the cron command in cPanel.', '/system/jobs', 'Check scheduled jobs');
        }

        // Server configuration
        if (Config::get('app.debug') && Config::get('app.env') === 'production') {
            $add('error', 'Debug mode is on in production', 'Error details are shown to visitors. Set "debug" to false in config/config.php.', '/system', 'See health checks');
        }
        if (str_starts_with((string) Config::get('app.key'), 'CHANGE-ME') || strlen((string) Config::get('app.key')) < 32) {
            $add('error', 'Application key is weak or default', 'Sessions, remember-me tokens and stored secrets depend on it. Set a long random "key" in config/config.php.', '/system', 'See health checks');
        }
        $url = (string) Config::get('app.url');
        if (!str_starts_with($url, 'https://') && Config::get('app.env') === 'production') {
            $add('warning', 'App URL is not HTTPS', 'Links in emails use ' . $url . '. Set "url" in config/config.php to the https address of the site.', '/system', 'See health checks');
        }
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host && $url && parse_url($url, PHP_URL_HOST) !== preg_replace('/:\d+$/', '', $host)) {
            $add('warning', 'App URL does not match this site', 'The site is opened as ' . $host . ' but config/config.php says ' . $url . '. Links in emails will point to the wrong place.', '/system', 'See health checks');
        }
        if (!is_writable(STORAGE_PATH . '/uploads') || !is_writable(STORAGE_PATH . '/logs') || !is_writable(BASE_PATH . '/public/uploads')) {
            $add('error', 'Storage folders are not writable', 'Uploads, logs and branding images cannot be saved. Give storage/ and public/uploads/ write permission (755 or 775) in cPanel File Manager.', '/system', 'See health checks');
        }

        if ((string) Setting::get('connect_onboarding_admin_email') === '' && !Setting::emails('admin_notification_emails')) {
            $add('warning', 'No one receives volunteer onboarding alerts', 'Set the onboarding admin email (ID uploads, submitted applications, interviews) or the admin notification emails.', '/system/settings#Onboarding', 'Set onboarding admin');
        }
        // Content and organisation setup
        if ((string) Setting::get('support_email') === 'admin@example.org' || (string) Setting::get('support_email') === '') {
            $add('info', 'Support email still has the example value', 'It is shown in the footer and used as the reply-to address.', '/system/settings#General', 'Edit general settings');
        }
        if (count(EmailTemplate::all()) < 20) {
            $add('warning', 'Some email templates are missing', 'Notifications for missing templates are skipped. Load the defaults to add them (existing templates are kept).', '/system', 'Load missing defaults');
        }
        if ((int) DB::fetchColumn("SELECT COUNT(*) FROM teams WHERE status = 'approved'") === 0) {
            $add('info', 'No teams yet', 'Volunteers are organised in teams; events and reports belong to a team. Create the first one.', '/admin/teams/new', 'Create a team');
        }
        if ((int) DB::fetchColumn("SELECT COUNT(*) FROM users WHERE type = 'superadmin'") < 2) {
            $add('info', 'Only one administrator', 'Add a second super admin so the organisation is not locked out if one account is lost.', '/system/admins', 'Add an admin');
        }
        if ((string) Setting::get('brand_logo', '') === '') {
            $add('info', 'Default logo in use', 'Upload your organisation logo and a sign-in background image.', '/system/settings#Appearance', 'Upload branding');
        }
        usort($out, fn($a, $b) => ['error' => 0, 'warning' => 1, 'info' => 2][$a['level']] <=> ['error' => 0, 'warning' => 1, 'info' => 2][$b['level']]);
        return $out;
    }
}
