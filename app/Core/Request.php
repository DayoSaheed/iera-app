<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public array $params = [];
    private ?array $json = null;

    public function method(): string
    {
        $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($m === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $m;
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        if ($this->isJson()) {
            return $this->json()[$key] ?? $default;
        }
        return $_POST[$key] ?? $default;
    }

    /** POST (or JSON body) with GET fallback */
    public function input(string $key, mixed $default = null): mixed
    {
        $v = $this->post($key);
        return $v ?? $this->get($key, $default);
    }

    /** Trimmed string input */
    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        if (is_array($v)) {
            return $default;
        }
        return trim((string) $v);
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function arr(string $key): array
    {
        $v = $this->input($key, []);
        return is_array($v) ? $v : ($v === null || $v === '' ? [] : [$v]);
    }

    public function all(): array
    {
        return $this->isJson() ? $this->json() : array_merge($_GET, $_POST);
    }

    public function file(string $key): ?array
    {
        $f = $_FILES[$key] ?? null;
        return $f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $f : null;
    }

    public function files(string $key): array
    {
        $f = $_FILES[$key] ?? null;
        if (!$f) {
            return [];
        }
        if (!is_array($f['name'])) {
            return $f['error'] === UPLOAD_ERR_OK ? [$f] : [];
        }
        $out = [];
        foreach ($f['name'] as $i => $name) {
            if ($f['error'][$i] === UPLOAD_ERR_OK) {
                $out[] = [
                    'name' => $name,
                    'type' => $f['type'][$i],
                    'tmp_name' => $f['tmp_name'][$i],
                    'error' => $f['error'][$i],
                    'size' => $f['size'][$i],
                ];
            }
        }
        return $out;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    public function isJson(): bool
    {
        return str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
    }

    public function json(): array
    {
        if ($this->json === null) {
            $raw = file_get_contents('php://input') ?: '';
            $this->json = json_decode($raw, true) ?: [];
        }
        return $this->json;
    }

    public function rawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    public function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function ip(): string
    {
        return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    }

    public function fullUrl(): string
    {
        return $this->path() . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
    }
}
