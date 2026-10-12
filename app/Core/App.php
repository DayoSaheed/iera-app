<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;
use Throwable;

final class App
{
    private static ?App $instance = null;
    public Router $router;
    public Request $request;

    public static function boot(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->router = new Router();
        $this->request = new Request();
        require APP_PATH . '/routes.php';
    }

    public function run(): void
    {
        try {
            Session::start();
            $response = $this->handle($this->request);
        } catch (HttpException $e) {
            $response = $this->errorResponse($e->status, $e->getMessage());
        } catch (Throwable $e) {
            Logger::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            if (Config::get('app.debug')) {
                $response = Response::html('<pre style="white-space:pre-wrap">' . e($e) . '</pre>', 500);
            } else {
                $response = $this->errorResponse(500, HttpException::text(500));
            }
        }
        $response->send();
    }

    private function handle(Request $request): Response
    {
        $match = $this->router->match($request->method(), $request->path());
        if ($match === null) {
            throw new HttpException(404);
        }
        $request->params = $match['params'];
        $middleware = $match['middleware'];

        // Not installed yet: send everything (except the wizard, health and assets) to the setup wizard
        $path = $request->path();
        if (!str_starts_with($path, '/setup') && $path !== '/api/health' && !\App\Services\Installer::isInstalled()) {
            return Response::redirect('/setup');
        }

        // Maintenance mode blocks everyone except system admins
        if (!in_array('always', $middleware, true) && Setting::bool('maintenance_mode')) {
            $u = Auth::user();
            if (!$u || !(int) ($u['is_sysadmin'] ?? 0)) {
                throw new HttpException(503, Setting::get('maintenance_message', HttpException::text(503)));
            }
        }

        // CSRF on state-changing requests
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !in_array('nocsrf', $middleware, true)) {
            if (!Csrf::verify($request->post('_token') ?? $request->header('X-CSRF-Token'))) {
                throw new HttpException(419);
            }
        }

        $redirect = Middleware::run($middleware, $request);
        if ($redirect instanceof Response) {
            return $redirect;
        }

        $handler = $match['handler'];
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $result = (new $class())->$method($request);
        } else {
            $result = $handler($request);
        }

        // Clear "old input" after a successful render
        unset($_SESSION['_old']);

        if ($result instanceof Response) {
            return $result;
        }
        return Response::html((string) $result);
    }

    private function errorResponse(int $status, string $message): Response
    {
        if ($this->request->wantsJson()) {
            return Response::json(['error' => $message], $status);
        }
        if ($status === 401) {
            Session::set('_intended', $this->request->fullUrl());
            Flash::info($message);
            return Response::redirect('/login');
        }
        $html = View::render('errors/error', ['status' => $status, 'message' => $message], 'blank');
        return new Response($html, $status);
    }
}
