<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\View;
use App\Models\EmailTemplate;
use App\Models\Setting;

/**
 * Sends templated emails. Templates live in the email_templates table (editable in System Admin).
 * Placeholders use {{name}} syntax. Values are HTML-escaped unless the key ends with _html
 * (e.g. details_html) or is one of the built-in HTML blocks (details, list, mentee_details, mentor_details).
 */
final class EmailService
{
    private const HTML_KEYS = ['details', 'list', 'mentee_details', 'mentor_details', 'body'];

    /**
     * @param string $key template key
     * @param array|string $to recipients
     * @param array $vars placeholder values
     * @param array $opts cc, bcc, reply_to
     */
    public static function send(string $key, array|string $to, array $vars = [], array $opts = []): bool
    {
        $tpl = EmailTemplate::get($key);
        if (!$tpl && isset(Seeder::emailTemplates()[$key])) {
            // Template added in a newer version: install the default so existing sites keep working.
            Seeder::installEmailTemplate($key);
            $tpl = EmailTemplate::get($key);
        }
        if (!$tpl) {
            Logger::warning('Email template missing: ' . $key);
            return false;
        }
        if (!(int) $tpl['enabled']) {
            return false;
        }
        $vars += [
            'app_name' => (string) Config::get('app.name', 'iERA App'),
            'app_url' => rtrim((string) Config::get('app.url'), '/'),
            'org_name' => (string) Setting::get('org_name'),
            'support_email' => (string) Setting::get('support_email'),
            'year' => date('Y'),
        ];
        $subject = self::interpolate($tpl['subject'], $vars, true);
        $bodyInner = self::interpolate($tpl['body_html'], $vars, false);
        $html = View::partial('emails/layout', ['body' => $bodyInner, 'subject' => $subject, 'vars' => $vars]);

        $message = [
            'to' => $to,
            'cc' => $opts['cc'] ?? [],
            'bcc' => $opts['bcc'] ?? [],
            'subject' => $subject,
            'html' => $html,
            'reply_to' => $opts['reply_to'] ?? (string) Setting::get('support_email'),
            'template_key' => $key,
        ];
        if (Config::get('mail.driver') === 'sendgrid' && !empty($tpl['sendgrid_template_id'])) {
            $message['template_id'] = $tpl['sendgrid_template_id'];
            $message['template_data'] = $vars;
        }
        return Mailer::send($message);
    }

    /** Send the same template to many recipients individually (no address leaking). */
    public static function sendEach(string $key, array $recipients, callable $varsFor): int
    {
        $n = 0;
        foreach ($recipients as $r) {
            $email = is_array($r) ? ($r['email'] ?? '') : (string) $r;
            if (!$email) {
                continue;
            }
            if (self::send($key, [$email], $varsFor($r))) {
                $n++;
            }
        }
        return $n;
    }

    public static function interpolate(string $text, array $vars, bool $plain): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($vars, $plain) {
            $k = $m[1];
            $v = $vars[$k] ?? '';
            if (is_array($v)) {
                $v = implode(', ', $v);
            }
            $v = (string) $v;
            if ($plain) {
                return strip_tags($v);
            }
            if (str_ends_with($k, '_html') || in_array($k, self::HTML_KEYS, true)) {
                return $v;
            }
            return e($v);
        }, $text);
    }

    /** Build a "Label: value" HTML block from a form template and user data. */
    public static function detailsHtml(array $fields, array $data, array $skip = ['ids', 'confirmation_text']): string
    {
        $rows = '';
        foreach ($fields as $f) {
            $name = $f['name'] ?? '';
            if (!$name || in_array($name, $skip, true) || in_array($f['type'] ?? '', ['section', 'text_copy', 'typography', 'upload'], true)) {
                continue;
            }
            $v = $data[$name] ?? ($data['profile'][$name] ?? null);
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            if (!empty($f['options']) && is_array($f['options'])) {
                $labels = [];
                foreach ((array) $v as $val) {
                    $labels[] = (string) ($val);
                    foreach ($f['options'] as $o) {
                        if ((string) ($o['value'] ?? '') === (string) $val) {
                            array_pop($labels);
                            $labels[] = (string) ($o['label'] ?? $val);
                            break;
                        }
                    }
                }
                $v = implode(', ', $labels);
            } elseif (is_array($v)) {
                $v = implode(', ', $v);
            } elseif (($f['type'] ?? '') === 'checkbox') {
                $v = $v ? 'Yes' : 'No';
            }
            $rows .= '<tr><td style="padding:4px 8px;color:#555;vertical-align:top"><strong>' . e($f['label'] ?? $name) . '</strong></td><td style="padding:4px 8px">' . nl2br(e((string) $v)) . '</td></tr>';
        }
        return '<table style="border-collapse:collapse;font-size:14px">' . $rows . '</table>';
    }

    public static function adminRecipients(string $extraKey = ''): array
    {
        $list = Setting::emails('admin_notification_emails');
        if ($extraKey) {
            $list = array_merge($list, Setting::emails($extraKey));
        }
        return array_values(array_unique($list));
    }
}
