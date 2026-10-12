<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');
define('APP_VERSION', '1.0.0');

// PSR-4 style autoloader for the App\ namespace
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require APP_PATH . '/Core/helpers.php';

$configFile = BASE_PATH . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = BASE_PATH . '/config/config.sample.php';
}
App\Core\Config::load(require $configFile);

date_default_timezone_set((string) App\Core\Config::get('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', App\Core\Config::get('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-errors.log');
