<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Jobs;
use App\Services\StripeIdentity;

final class WebhookController extends Controller
{
    public function stripe(Request $req): Response
    {
        $event = StripeIdentity::parseWebhook($req->rawBody(), $req->header('Stripe-Signature'));
        if (!$event) {
            return $this->json(['error' => 'invalid signature'], 400);
        }
        StripeIdentity::handleEvent($event);
        return $this->json(['received' => true]);
    }

    /** Fallback for hosts without cron: GET /cron/run?key=SECRET (configure an external pinger). */
    public function cron(Request $req): Response
    {
        $secret = (string) \App\Models\Setting::orConfig('cron_secret', 'cron.secret', '');
        if ($secret === '' || !hash_equals($secret, $req->str('key'))) {
            return $this->json(['error' => 'forbidden'], 403);
        }
        return $this->json(['ran' => Jobs::runDue()]);
    }
}
