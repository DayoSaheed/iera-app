<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;
use App\Core\Str;

/** First-run installation helpers used by the setup wizard. */
final class Installer
{
    public static function lockPath(): string
    {
        return (string) Config::get('app.install_lock', STORAGE_PATH . '/install.lock');
    }

    public static function configPath(): string
    {
        return (string) Config::get('app.config_path', BASE_PATH . '/config/config.php');
    }

    public static function isInstalled(): bool
    {
        if (is_file(self::lockPath())) {
            return true;
        }
        try {
            return (bool) DB::fetchColumn("SELECT 1 FROM users WHERE type = 'superadmin' LIMIT 1");
        } catch (\Throwable) {
            return false;
        }
    }

    public static function lock(): void
    {
        @file_put_contents(self::lockPath(), 'installed ' . now() . "\n");
    }

    public static function requirements(): array
    {
        $checks = [
            ['PHP 8.1 or newer', version_compare(PHP_VERSION, '8.1.0', '>='), 'PHP ' . PHP_VERSION],
        ];
        foreach (['pdo_mysql' => 'PDO MySQL', 'mbstring' => 'Multibyte strings', 'openssl' => 'OpenSSL', 'curl' => 'cURL', 'fileinfo' => 'Fileinfo', 'json' => 'JSON'] as $ext => $label) {
            $checks[] = [$label . ' extension', extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'missing: enable it in cPanel → Select PHP Version'];
        }
        $checks[] = ['GD extension (optional, image thumbnails)', true, extension_loaded('gd') ? 'loaded' : 'not loaded (optional)'];
        foreach (['uploads', 'logs', 'cache', 'backups'] as $d) {
            $path = STORAGE_PATH . '/' . $d;
            $ok = is_dir($path) ? is_writable($path) : @mkdir($path, 0750, true);
            $checks[] = ["storage/$d writable", (bool) $ok, $ok ? 'ok' : 'make the folder writable'];
        }
        $checks[] = ['config/config.php present', is_file(BASE_PATH . '/config/config.php'), is_file(BASE_PATH . '/config/config.php') ? 'found' : 'missing: copy config.sample.php to config.php, or enter the database details in the next step'];
        return $checks;
    }

    /** @return array{ok:bool, message:string, tables:int} */
    public static function databaseStatus(): array
    {
        try {
            DB::fetchColumn('SELECT 1');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Cannot connect: ' . $e->getMessage(), 'tables' => 0];
        }
        try {
            $tables = DB::driver() === 'sqlite'
                ? DB::fetchAll("SELECT name FROM sqlite_master WHERE type='table'")
                : DB::fetchAll('SHOW TABLES');
            $n = count($tables);
            $hasUsers = (bool) DB::fetchColumn(DB::driver() === 'sqlite' ? "SELECT 1 FROM sqlite_master WHERE type='table' AND name='users'" : "SHOW TABLES LIKE 'users'");
            return ['ok' => $hasUsers, 'message' => $hasUsers ? "Connected, $n table(s) found." : "Connected, but the application tables are missing ($n table(s) in the database).", 'tables' => $n];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'tables' => 0];
        }
    }

    /** Create the tables from sql/schema.sql. */
    public static function createTables(): int
    {
        $sql = (string) file_get_contents(BASE_PATH . '/sql/schema.sql');
        if (DB::driver() === 'sqlite') {
            $sql = SchemaPort::toSqlite($sql);
        }
        $n = 0;
        foreach (array_filter(array_map('trim', explode(";\n", preg_replace('/^--.*$/m', '', $sql) ?? $sql))) as $stmt) {
            if ($stmt !== '') {
                DB::pdo()->exec($stmt);
                $n++;
            }
        }
        return $n;
    }

    /** Test a MySQL connection with the given details without touching the app config. */
    public static function testConnection(array $db): ?string
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['name']);
            new \PDO($dsn, $db['user'], $db['pass'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]);
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /** Write config/config.php. Returns null on success or the file contents when the folder is not writable. */
    public static function writeConfig(array $app, array $db): ?string
    {
        $template = [
            'app' => [
                'name' => $app['name'] ?: 'iERA App',
                'url' => rtrim($app['url'], '/'),
                'env' => 'production',
                'debug' => false,
                'timezone' => $app['timezone'] ?: 'America/Toronto',
                'key' => $app['key'] ?: bin2hex(random_bytes(32)),
                'session_minutes' => 120,
            ],
            'db' => [
                'driver' => (string) Config::get('db.driver', 'mysql'),
                'host' => $db['host'] ?: 'localhost',
                'port' => (int) ($db['port'] ?: 3306),
                'name' => $db['name'],
                'user' => $db['user'],
                'pass' => $db['pass'],
                'charset' => 'utf8mb4',
            ],
        ];
        if ($template['db']['driver'] === 'sqlite') {
            $template['db']['path'] = (string) Config::get('db.path');
        }
        $php = "<?php\n/**\n * iERA App configuration (generated by the setup wizard on " . now() . ").\n * Email, Stripe, SharePoint and scheduler settings are managed in System Admin → Settings.\n */\nreturn " . var_export($template, true) . ";\n";
        $file = self::configPath();
        if (@file_put_contents($file, $php) === false) {
            return $php;
        }
        return null;
    }

    public static function detectUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return ($https ? 'https://' : 'http://') . $host;
    }

    public static function configIsSample(): bool
    {
        return !is_file(BASE_PATH . '/config/config.php') || str_starts_with((string) Config::get('app.key'), 'CHANGE-ME');
    }
}
