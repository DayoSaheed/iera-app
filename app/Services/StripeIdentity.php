<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;

/** Stripe Identity document verification (no SDK, plain HTTPS calls). */
final class StripeIdentity
{
    public static function enabled(): bool
    {
        return Setting::bool('stripe_identity_enabled') && (bool) Config::get('stripe.enabled') && Config::get('stripe.secret_key');
    }

    /**
     * Start or resume a verification. Returns ['url' => ...] or ['message' => ...].
     */
    public static function startOrResume(array $user, string $returnUrl): array
    {
        if ($user['stripe_verification_id']) {
            $session = self::api('GET', '/v1/identity/verification_sessions/' . $user['stripe_verification_id']);
            $status = $session['status'] ?? 'canceled';
            self::applyStatus($user['id'], $status);
            if ($status === 'requires_input' && !empty($session['url'])) {
                return ['url' => $session['url']];
            }
            if ($status === 'processing') {
                return ['message' => 'Your identity verification is being processed. Please check back later.'];
            }
            if ($status === 'verified') {
                return ['message' => 'Your identity is already verified.'];
            }
        }
        $session = self::api('POST', '/v1/identity/verification_sessions', [
            'type' => 'document',
            'return_url' => $returnUrl,
            'metadata[user_id]' => $user['id'],
            'provided_details[email]' => $user['email'],
        ]);
        if (empty($session['id'])) {
            throw new \RuntimeException('Stripe did not return a verification session.');
        }
        User::save($user['id'], ['stripe_verification_id' => $session['id'], 'stripe_status' => $session['status'] ?? 'requires_input']);
        AuditLog::log('stripe.session_created', 'user', $user['id'], $session['id']);
        return ['url' => $session['url'] ?? ''];
    }

    /** Update the user from a Stripe status, never downgrading an approved volunteer. */
    public static function applyStatus(string $userId, string $status): void
    {
        $user = User::find($userId, false);
        if (!$user) {
            return;
        }
        $data = ['stripe_status' => $status];
        if ($status === 'verified' && in_array($user['status'], ['new', 'enrolled'], true)) {
            $data['status'] = 'id_verified';
        }
        User::save($userId, $data);
    }

    /** Verify the Stripe-Signature header and return the decoded event, or null. */
    public static function parseWebhook(string $payload, ?string $sigHeader): ?array
    {
        $secret = (string) Config::get('stripe.webhook_secret');
        if ($secret === '' || !$sigHeader) {
            return null;
        }
        $parts = [];
        foreach (explode(',', $sigHeader) as $kv) {
            [$k, $v] = array_pad(explode('=', trim($kv), 2), 2, '');
            $parts[$k][] = $v;
        }
        $t = $parts['t'][0] ?? '';
        if ($t === '' || abs(time() - (int) $t) > 300) {
            return null;
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        $ok = false;
        foreach ($parts['v1'] ?? [] as $sig) {
            if (hash_equals($expected, $sig)) {
                $ok = true;
            }
        }
        if (!$ok) {
            return null;
        }
        return json_decode($payload, true) ?: null;
    }

    public static function handleEvent(array $event): void
    {
        $type = $event['type'] ?? '';
        $obj = $event['data']['object'] ?? [];
        if (!str_starts_with($type, 'identity.verification_session.')) {
            return;
        }
        $userId = $obj['metadata']['user_id'] ?? null;
        $sessionId = $obj['id'] ?? null;
        if (!$userId && $sessionId) {
            $userId = \App\Core\DB::fetchColumn('SELECT id FROM users WHERE stripe_verification_id = ?', [$sessionId]);
        }
        if (!$userId) {
            return;
        }
        self::applyStatus((string) $userId, (string) ($obj['status'] ?? 'requires_input'));
        AuditLog::log('stripe.webhook', 'user', (string) $userId, $type, null);
    }

    private static function api(string $method, string $path, array $params = []): array
    {
        $ch = curl_init('https://api.stripe.com' . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . Config::get('stripe.secret_key')],
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $data = json_decode((string) $body, true) ?: [];
        if ($code >= 400 || $err) {
            Logger::error('Stripe API error', ['code' => $code, 'err' => $err, 'body' => substr((string) $body, 0, 500)]);
            throw new \RuntimeException($data['error']['message'] ?? ('Stripe error ' . $code));
        }
        return $data;
    }
}
