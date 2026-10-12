<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\EmailLog;

/**
 * Low-level mail transport. Drivers: sendgrid (HTTP API), smtp, mail (PHP mail()), log.
 *
 * $message = [
 *   'to' => ['a@x.org', ['email' => 'b@x.org', 'name' => 'B']],
 *   'cc' => [], 'bcc' => [],
 *   'subject' => '...', 'html' => '...', 'text' => '...',
 *   'template_id' => 'd-...', 'template_data' => [...],   // SendGrid dynamic template (optional)
 *   'template_key' => 'welcome',                          // for the email log
 * ]
 */
final class Mailer
{
    /** Error message of the most recent send() in this request, null when it succeeded. */
    public static ?string $lastError = null;

    public static function send(array $message): bool
    {
        self::$lastError = null;
        $cfg = \App\Models\Setting::mail();
        $driver = $cfg['driver'];
        $to = self::normalize($message['to'] ?? []);
        if (!$to) {
            self::$lastError = 'No valid recipient address';
            return false;
        }
        $message['to'] = $to;
        $message['driver'] = $driver;
        $message['cc'] = self::normalize($message['cc'] ?? []);
        $message['bcc'] = self::normalize($message['bcc'] ?? []);
        $message['subject'] ??= '';
        $message['html'] ??= nl2br(e($message['text'] ?? ''));
        $message['text'] ??= strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $message['html']));

        try {
            $ok = match ($driver) {
                'sendgrid' => self::viaSendgrid($message, $cfg),
                'smtp' => self::viaSmtp($message, $cfg),
                'mail' => self::viaMail($message, $cfg),
                default => self::viaLog($message),
            };
            $error = $ok ? null : 'Driver reported failure';
        } catch (\Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
            Logger::error('Mail failed: ' . $error, ['subject' => $message['subject']]);
        }
        self::$lastError = $error;
        EmailLog::record($message, $ok ? 'sent' : 'failed', $error);
        return $ok;
    }

    private static function normalize(mixed $list): array
    {
        if (is_string($list)) {
            $list = array_filter(array_map('trim', explode(',', $list)));
        }
        $out = [];
        $seen = [];
        foreach ((array) $list as $item) {
            if (is_string($item)) {
                $item = ['email' => $item, 'name' => ''];
            }
            $email = strtolower(trim((string) ($item['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $out[] = ['email' => $email, 'name' => (string) ($item['name'] ?? '')];
        }
        return $out;
    }

    private static function from(array $cfg): array
    {
        return ['email' => (string) $cfg['from_email'], 'name' => (string) $cfg['from_name']];
    }

    // ---- sendgrid ----------------------------------------------------

    private static function viaSendgrid(array $m, array $cfg): bool
    {
        $key = (string) $cfg['sendgrid_api_key'];
        if ($key === '') {
            throw new \RuntimeException('SendGrid API key is not configured');
        }
        $personalization = ['to' => $m['to']];
        if ($m['cc']) {
            $personalization['cc'] = $m['cc'];
        }
        if ($m['bcc']) {
            $personalization['bcc'] = $m['bcc'];
        }
        $payload = [
            'personalizations' => [$personalization],
            'from' => self::from($cfg),
        ];
        if (!empty($m['reply_to'])) {
            $payload['reply_to'] = ['email' => $m['reply_to']];
        }
        if (!empty($m['template_id'])) {
            $payload['template_id'] = $m['template_id'];
            $payload['personalizations'][0]['dynamic_template_data'] = (object) ($m['template_data'] ?? []);
        } else {
            $payload['subject'] = $m['subject'];
            $payload['content'] = [
                ['type' => 'text/plain', 'value' => $m['text']],
                ['type' => 'text/html', 'value' => $m['html']],
            ];
        }
        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            return true;
        }
        throw new \RuntimeException('SendGrid HTTP ' . $code . ' ' . ($err ?: substr((string) $body, 0, 300)));
    }

    // ---- php mail() --------------------------------------------------

    private static function viaMail(array $m, array $cfg): bool
    {
        $from = self::from($cfg);
        $boundary = 'b' . bin2hex(random_bytes(8));
        $headers = [
            'From: ' . self::addr($from),
            'Reply-To: ' . ($m['reply_to'] ?? $from['email']),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if ($m['cc']) {
            $headers[] = 'Cc: ' . implode(', ', array_map([self::class, 'addr'], $m['cc']));
        }
        if ($m['bcc']) {
            $headers[] = 'Bcc: ' . implode(', ', array_map([self::class, 'addr'], $m['bcc']));
        }
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($m['text']))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($m['html']))
            . "--$boundary--";
        $to = implode(', ', array_map([self::class, 'addr'], $m['to']));
        $subject = '=?UTF-8?B?' . base64_encode($m['subject']) . '?=';
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    private static function addr(array $a): string
    {
        return $a['name'] !== '' ? '"' . addslashes($a['name']) . '" <' . $a['email'] . '>' : $a['email'];
    }

    // ---- smtp --------------------------------------------------------

    private static function viaSmtp(array $m, array $mailCfg): bool
    {
        $cfg = $mailCfg['smtp'];
        $host = (string) ($cfg['host'] ?? 'localhost');
        $port = (int) ($cfg['port'] ?? 587);
        $enc = (string) ($cfg['encryption'] ?? 'tls');
        $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $sock = @stream_socket_client($remote, $errno, $errstr, 20);
        if (!$sock) {
            throw new \RuntimeException("SMTP connect failed: $errstr");
        }
        stream_set_timeout($sock, 20);
        $read = static function () use ($sock): string {
            $data = '';
            while ($line = fgets($sock, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = static function (string $c, array $okCodes) use ($sock, $read): string {
            fwrite($sock, $c . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $okCodes, true)) {
                throw new \RuntimeException('SMTP error after ' . explode(' ', $c)[0] . ': ' . trim($r));
            }
            return $r;
        };
        $read();
        $cmd('EHLO ' . (parse_url((string) Config::get('app.url'), PHP_URL_HOST) ?: 'localhost'), [250]);
        if ($enc === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('SMTP STARTTLS failed');
            }
            $cmd('EHLO ' . $host, [250]);
        }
        if (!empty($cfg['user'])) {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode((string) $cfg['user']), [334]);
            $cmd(base64_encode((string) $cfg['pass']), [235]);
        }
        $from = self::from($mailCfg);
        $cmd('MAIL FROM:<' . $from['email'] . '>', [250]);
        foreach (array_merge($m['to'], $m['cc'], $m['bcc']) as $r) {
            $cmd('RCPT TO:<' . $r['email'] . '>', [250, 251]);
        }
        $cmd('DATA', [354]);
        $boundary = 'b' . bin2hex(random_bytes(8));
        $headers = [
            'From: ' . self::addr($from),
            'To: ' . implode(', ', array_map([self::class, 'addr'], $m['to'])),
            'Subject: =?UTF-8?B?' . base64_encode($m['subject']) . '?=',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if ($m['cc']) {
            $headers[] = 'Cc: ' . implode(', ', array_map([self::class, 'addr'], $m['cc']));
        }
        if (!empty($m['reply_to'])) {
            $headers[] = 'Reply-To: ' . $m['reply_to'];
        }
        $body = implode("\r\n", $headers) . "\r\n\r\n"
            . "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($m['text']))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($m['html']))
            . "--$boundary--";
        $body = preg_replace('/^\./m', '..', $body);
        fwrite($sock, $body . "\r\n.\r\n");
        $r = $read();
        if ((int) substr($r, 0, 3) !== 250) {
            throw new \RuntimeException('SMTP rejected message: ' . trim($r));
        }
        fwrite($sock, "QUIT\r\n");
        fclose($sock);
        return true;
    }

    // ---- log ---------------------------------------------------------

    private static function viaLog(array $m): bool
    {
        $line = sprintf(
            "[%s] To: %s | Subject: %s\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            implode(', ', array_column($m['to'], 'email')),
            $m['subject'],
            $m['text'],
            str_repeat('-', 60)
        );
        @file_put_contents(STORAGE_PATH . '/logs/mail.log', $line, FILE_APPEND | LOCK_EX);
        return true;
    }
}
