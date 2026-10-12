#!/usr/bin/env php
<?php
/** Apply pending SQL migrations from sql/migrations/*.sql (tracked in schema_migrations). */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\DB;
$applied = array_column(DB::fetchAll('SELECT version FROM schema_migrations'), 'version');
$files = glob(BASE_PATH . '/sql/migrations/*.sql') ?: [];
sort($files);
$n = 0;
foreach ($files as $file) {
    $version = basename($file, '.sql');
    if (in_array($version, $applied, true)) { continue; }
    echo "Applying $version...\n";
    $sql = file_get_contents($file);
    if (DB::driver() === 'sqlite') { $sql = preg_replace('/\s+AFTER\s+\w+/i', '', \App\Services\SchemaPort::toSqlite($sql)); }
    foreach (array_filter(array_map('trim', explode(";\n", preg_replace('/^--.*$/m', '', $sql)))) as $stmt) { if ($stmt !== '' && !str_starts_with($stmt, '--')) { DB::pdo()->exec($stmt); } }
    DB::insert('schema_migrations', ['version' => $version, 'applied_at' => now()]);
    $n++;
}
echo "$n migration(s) applied.\n";
