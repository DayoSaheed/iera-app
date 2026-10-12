<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:mixed, middleware:array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, mixed $handler, array $middleware = []): void
    {
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $p, mixed $h, array $m = []): void { $this->add('GET', $p, $h, $m); }
    public function post(string $p, mixed $h, array $m = []): void { $this->add('POST', $p, $h, $m); }
    public function any(string $p, mixed $h, array $m = []): void
    {
        $this->add('GET', $p, $h, $m);
        $this->add('POST', $p, $h, $m);
    }

    /** @return array{handler:mixed, params:array, middleware:array}|null */
    public function match(string $method, string $path): ?array
    {
        $methodMatched = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $methodMatched = true;
                continue;
            }
            $params = [];
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = urldecode($v);
                }
            }
            return ['handler' => $route['handler'], 'params' => $params, 'middleware' => $route['middleware']];
        }
        if ($methodMatched) {
            throw new HttpException(405, 'Method not allowed');
        }
        return null;
    }
}
