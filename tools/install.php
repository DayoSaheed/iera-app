#!/usr/bin/env php
<?php
/**
 * First-time setup from the command line:
 *   php tools/install.php            -> creates tables (if missing) and loads defaults
 *   php tools/install.php --fresh    -> drops and recreates all tables (DANGEROUS)
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Services\Seeder;

$fresh = in_array('--fresh', $argv, true);
$schema = file_get_contents(BASE_PATH . '/sql/schema.sql');
if (DB::driver() === 'sqlite') {
    $schema = \App\Services\SchemaPort::toSqlite($schema);
}
if ($fresh) {
    echo "Dropping all tables...\n";
    $tables = DB::driver() === 'sqlite'
        ? array_column(DB::fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"), 'name')
        : array_map('current', DB::fetchAll('SHOW TABLES'));
    if (DB::driver() !== 'sqlite') { DB::pdo()->exec('SET FOREIGN_KEY_CHECKS=0'); }
    foreach ($tables as $t) { DB::pdo()->exec("DROP TABLE IF EXISTS `$t`"); }
    if (DB::driver() !== 'sqlite') { DB::pdo()->exec('SET FOREIGN_KEY_CHECKS=1'); }
}
echo "Creating tables...\n";
foreach (array_filter(array_map('trim', explode(";\n", preg_replace('/^--.*$/m', '', $schema)))) as $stmt) {
    if ($stmt === '' || str_starts_with($stmt, '--')) { continue; }
    DB::pdo()->exec($stmt);
}
echo "Loading defaults...\n";
print_r(Seeder::run());
echo "Done. Next: php tools/create-admin.php you@example.org\n";
