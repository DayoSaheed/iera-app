<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Str;

/** HTML-escape a value for output. Arrays/objects are JSON encoded. */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Build an absolute URL within the app. */
function url(string $path = ''): string
{
    $base = rtrim((string) Config::get('app.url', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function old(string $key, mixed $default = ''): string
{
    $old = $_SESSION['_old'] ?? [];
    return e($old[$key] ?? $default);
}

/** Format a date/time string for display. */
function fmt_date(?string $value, string $format = 'M j, Y'): string
{
    if (!$value) {
        return '';
    }
    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Throwable) {
        return (string) $value;
    }
}

function fmt_datetime(?string $value): string
{
    return fmt_date($value, 'M j, Y, g:i A');
}

/** "street_dawah" => "Street Dawah" */
function start_case(?string $value): string
{
    return Str::startCase((string) $value);
}

function array_get(array $array, string $key, mixed $default = null): mixed
{
    return $array[$key] ?? $default;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Inline SVG icon. */
function icon(string $name, string $class = '', int $size = 20): string
{
    return \App\Core\Icons::svg($name, $class, $size);
}
