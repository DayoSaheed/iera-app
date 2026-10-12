<?php
declare(strict_types=1);

namespace App\Services;

/** Converts the MySQL schema to SQLite for local smoke tests. Production always uses MySQL. */
final class SchemaPort
{
    public static function toSqlite(string $sql): string
    {
        $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
        $sql = preg_replace('/^SET .*$/m', '', $sql) ?? $sql;
        $sql = preg_replace_callback('/INSERT IGNORE INTO schema_migrations \(version, applied_at\) VALUES (.*);$/m', fn($m) => 'INSERT OR IGNORE INTO schema_migrations (version, applied_at) VALUES ' . str_replace('UTC_TIMESTAMP()', "datetime('now')", $m[1]) . ';', $sql) ?? $sql;
        $sql = preg_replace('/\) ENGINE=InnoDB[^;]*;/', ');', $sql) ?? $sql;
        $sql = preg_replace('/INT NOT NULL AUTO_INCREMENT/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql) ?? $sql;
        // Remove the separate PRIMARY KEY (id) line for autoincrement tables
        $sql = preg_replace_callback('/CREATE TABLE IF NOT EXISTS (\w+) \((.*?)\n\);/s', function ($m) {
            $body = $m[2];
            if (str_contains($body, 'AUTOINCREMENT')) {
                $body = preg_replace('/,\s*PRIMARY KEY \(id\)/', '', $body) ?? $body;
            }
            // Drop named KEY / UNIQUE KEY index lines (SQLite does not allow them inline); keep UNIQUE constraints
            $lines = [];
            foreach (preg_split('/,\n/', $body) as $line) {
                $t = trim($line);
                if (preg_match('/^KEY \w+ \(/', $t)) {
                    continue;
                }
                if (preg_match('/^UNIQUE KEY \w+ (\(.*\))/', $t, $u)) {
                    $lines[] = '  UNIQUE ' . $u[1];
                    continue;
                }
                if (preg_match('/^CONSTRAINT \w+ (FOREIGN KEY.*)$/', $t, $f)) {
                    $lines[] = '  ' . $f[1];
                    continue;
                }
                $lines[] = $line;
            }
            return 'CREATE TABLE IF NOT EXISTS ' . $m[1] . " (" . implode(",\n", $lines) . "\n);";
        }, $sql) ?? $sql;
        $sql = str_replace(['UTC_TIMESTAMP()', 'LONGTEXT', 'DECIMAL(4,1)', 'TINYINT(1)', 'SMALLINT', 'DATETIME'], ["datetime('now')", 'TEXT', 'REAL', 'INTEGER', 'INTEGER', 'TEXT'], $sql);
        $sql = preg_replace('/VARCHAR\(\d+\)/', 'TEXT', $sql) ?? $sql;
        $sql = preg_replace('/CHAR\(36\)/', 'TEXT', $sql) ?? $sql;
        return $sql;
    }
}
