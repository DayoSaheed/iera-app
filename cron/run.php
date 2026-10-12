#!/usr/bin/env php
<?php
/**
 * Scheduled job runner. Add to cPanel cron every 5 minutes:
 *   (every 5 minutes)  php /home/USER/iera-app/cron/run.php >/dev/null 2>&1
 * Options: --force (ignore time windows), --job=<name> (run one job)
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require dirname(__DIR__) . '/app/bootstrap.php';

$force = in_array('--force', $argv, true);
$only = null;
foreach ($argv as $a) {
    if (str_starts_with($a, '--job=')) { $only = substr($a, 6); }
}
$results = \App\Services\Jobs::runDue($force, $only);
foreach ($results as $job => $msg) {
    echo date('Y-m-d H:i:s') . "  $job: $msg\n";
}
if (!$results) {
    echo date('Y-m-d H:i:s') . "  nothing due\n";
}
