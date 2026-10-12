<?php
/**
 * Router for PHP's built-in server (local development only):
 *   php -S 127.0.0.1:8085 -t public tools/dev-server.php
 * Serves files from public/ directly and routes everything else to public/index.php (what .htaccess does on Apache).
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/../public' . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/../public/index.php';
