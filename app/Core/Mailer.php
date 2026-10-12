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
        $host = trim((string) ($cfg['host'] ?? 'localhost')) ?: 'localhost';
        $port = (int) ($cfg['port'] ?? 587) ?: 587;
        $enc = (string) ($cfg['encryption'] ?? 'tls');
        $local = in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
        $verify = !$local && (bool) ($cfg['verify_cert'] ?? true);
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify,
            'SNI_enabled' => true, 'peer_name' => $host,
        ]]);
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $warning = '';
        set_error_handler(static function (int $no, string $msg) use (&$warning): bool { $warning = $msg; return true; });
        try {
            $sock = stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        } finally {
            restore_error_handler();
        }
        if (!$sock) {
            throw new \RuntimeException(self::explainConnect($host, $port, $enc, $errstr ?: $warning, $warning));
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
            if ($data === '') {
                $meta = stream_get_meta_data($sock);
                throw new \RuntimeException(!empty($meta['timed_out']) ? 'No reply from the mail server within 20 seconds. If you chose encryption "ssl", the port is probably a STARTTLS port (use 587 with "tls"), or the other way round.' : 'The mail server closed the connection.');
            }
            return $data;
        };
        $cmd = static function (string $c, array $okCodes, ?string $label = null) use ($sock, $read): string {
            fwrite($sock, $c . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $okCodes, true)) {
                throw new \RuntimeException('SMTP error after ' . ($label ?? explode(' ', $c)[0]) . ': ' . trim($r));
            }
            return $r;
        };
        $greeting = $read();
        if ((int) substr($greeting, 0, 3) !== 220) {
            throw new \RuntimeException('Unexpected greeting from the mail server: ' . trim($greeting));
        }
        $ehlo = $cmd('EHLO ' . (parse_url((string) Config::get('app.url'), PHP_URL_HOST) ?: 'localhost'), [250]);
        if ($enc === 'tls') {
            $cmd('STARTTLS', [220]);
            $warning = '';
            set_error_handler(static function (int $no, string $msg) use (&$warning): bool { $warning = $msg; return true; });
            try {
                $ok = stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            } finally {
                restore_error_handler();
            }
            if ($ok !== true) {
                throw new \RuntimeException(self::explainTls($warning));
            }
            $ehlo = $cmd('EHLO ' . $host, [250]);
        }
        if (!empty($cfg['user'])) {
            $user = (string) $cfg['user'];
            $pass = (string) $cfg['pass'];
            $authErr = null;
            $plain = stripos($ehlo, 'PLAIN') !== false && stripos($ehlo, 'LOGIN') === false;
            try {
                if ($plain) {
                    $cmd('AUTH PLAIN ' . base64_encode("\0$user\0$pass"), [235], 'AUTH PLAIN');
                } else {
                    $cmd('AUTH LOGIN', [334]);
                    $cmd(base64_encode($user), [334], 'username');
                    $cmd(base64_encode($pass), [235], 'password');
                }
            } catch (\RuntimeException $e) {
                $authErr = $e->getMessage();
            }
            if ($authErr !== null) {
                $hint = str_contains($authErr, '535') || str_contains($authErr, '534') || str_contains($authErr, '530')
                    ? ' The username or password was rejected: use the full mailbox address (name@yourdomain) as the username and that mailbox password.'
                    : '';
                throw new \RuntimeException('SMTP login failed. ' . $authErr . $hint);
            }
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

    private static function explainConnect(string $host, int $port, string $enc, string $err, string $warning): string
    {
        $base = "Could not connect to $host:$port ($enc). ";
        $e = strtolower($err . ' ' . $warning);
        if (str_contains($e, 'certificate') || str_contains($e, 'verify') || str_contains($e, 'ssl operation')) {
            return $base . 'The server\'s SSL certificate does not match "' . $host . '". Use the host name printed on the certificate (often mail.yourdomain or the server\'s own hostname), or turn off "Verify SMTP certificate" in Settings → Email.';
        }
        if (str_contains($e, 'refused')) {
            return $base . 'Connection refused: nothing is listening on that port. On cPanel use port 465 with "ssl" or 587 with "tls".';
        }
        if (str_contains($e, 'timed out') || str_contains($e, 'timeout')) {
            return $base . 'Timed out: a firewall is blocking the port or the host name is wrong.';
        }
        if (str_contains($e, 'getaddrinfo') || str_contains($e, 'resolve') || str_contains($e, 'name or service')) {
            return $base . 'The host name could not be found. Check the spelling.';
        }
        return $base . trim($err ?: $warning) ?: 'Unknown error.';
    }

    private static function explainTls(string $warning): string
    {
        $w = strtolower($warning);
        if (str_contains($w, 'certificate') || str_contains($w, 'verify')) {
            return 'STARTTLS failed: the server\'s certificate does not match the SMTP host. Use the host name on the certificate, or turn off "Verify SMTP certificate" in Settings → Email.';
        }
        return 'STARTTLS failed: ' . (trim($warning) ?: 'the server did not complete the TLS handshake. Try port 465 with "ssl".');
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
