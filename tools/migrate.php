#!/usr/bin/env php
<?php
/** Apply pending SQL migrations from sql/migrations/*.sql (tracked in schema_migrations). Same as System Admin → "Apply database updates". */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
require dirname(__DIR__) . '/app/bootstrap.php';
$done = \App\Services\Migrator::apply();
foreach ($done as $v) { echo "Applied $v\n"; }
echo count($done) . " migration(s) applied.\n";
