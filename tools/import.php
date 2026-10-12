#!/usr/bin/env php
<?php
/** Import a mongoexport JSON file: php tools/import.php <collection> <file.json> */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
require dirname(__DIR__) . '/app/bootstrap.php';
$c = $argv[1] ?? ''; $f = $argv[2] ?? '';
if (!in_array($c, \App\Services\MongoImporter::COLLECTIONS, true) || !is_file($f)) {
    fwrite(STDERR, "Usage: php tools/import.php <" . implode('|', \App\Services\MongoImporter::COLLECTIONS) . "> <file.json>\n"); exit(1);
}
$r = \App\Services\MongoImporter::importFile($c, $f);
echo json_encode($r, JSON_PRETTY_PRINT), "\n";
