<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Applies the SQL files in sql/migrations/ that are not yet recorded in schema_migrations.
 * Used by tools/migrate.php (CLI) and by System Admin → "Apply database updates" (no shell needed on cPanel).
 */
final class Migrator
{
    /** @return string[] versions not yet applied, in order */
    public static function pending(): array
    {
        try {
            $applied = array_column(DB::fetchAll('SELECT version FROM schema_migrations'), 'version');
        } catch (\Throwable) {
            $applied = [];
        }
        $files = glob(BASE_PATH . '/sql/migrations/*.sql') ?: [];
        sort($files);
        $out = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (!in_array($version, $applied, true)) {
                $out[] = $version;
            }
        }
        return $out;
    }

    /** @return string[] versions applied in this call */
    public static function apply(): array
    {
        DB::pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(64) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL)');
        $done = [];
        foreach (self::pending() as $version) {
            $sql = (string) file_get_contents(BASE_PATH . '/sql/migrations/' . $version . '.sql');
            if (DB::driver() === 'sqlite') {
                $sql = preg_replace('/\s+AFTER\s+\w+/i', '', SchemaPort::toSqlite($sql));
            }
            foreach (array_filter(array_map('trim', explode(";\n", preg_replace('/^--.*$/m', '', $sql)))) as $stmt) {
                if ($stmt === '' || str_starts_with($stmt, '--')) {
                    continue;
                }
                try {
                    DB::pdo()->exec($stmt);
                } catch (\PDOException $e) {
                    // Column/table already there (e.g. applied by hand): keep going so the version gets recorded.
                    if (!preg_match('/duplicate column|already exists|Duplicate key name/i', $e->getMessage())) {
                        throw $e;
                    }
                }
            }
            DB::insert('schema_migrations', ['version' => $version, 'applied_at' => now()]);
            $done[] = $version;
        }
        return $done;
    }
}
