<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'app'): Response
    {
        return Response::html(View::render($template, $data, $layout));
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function back(Request $request, string $fallback = '/'): Response
    {
        $ref = $request->header('Referer');
        $base = rtrim((string) Config::get('app.url'), '/');
        if ($ref && ($base === '' || str_starts_with($ref, $base))) {
            return Response::redirect($ref);
        }
        return Response::redirect($fallback);
    }

    protected function user(): array
    {
        $u = Auth::user();
        if (!$u) {
            throw new HttpException(401);
        }
        return $u;
    }

    /** Store validation errors + old input and redirect back. */
    protected function failValidation(Request $request, array $errors, string $fallback): Response
    {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = array_filter($request->all(), fn($v) => !is_array($v) || count($v) < 50);
        Flash::error(reset($errors)[0] ?? 'Please correct the highlighted fields.');
        return $this->back($request, $fallback);
    }

    protected function notFound(string $message = 'Not found'): never
    {
        throw new HttpException(404, $message);
    }

    protected function forbidden(string $message = ''): never
    {
        throw new HttpException(403, $message);
    }

    protected function csv(array $rows, array $headers, string $filename): Response
    {
        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, array_values($headers));
        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($headers) as $key) {
                $v = $row[$key] ?? '';
                if (is_array($v)) {
                    $v = implode('; ', array_map(fn($x) => is_array($x) ? json_encode($x) : (string) $x, $v));
                }
                $line[] = (string) $v;
            }
            fputcsv($fh, $line);
        }
        rewind($fh);
        $content = "\xEF\xBB\xBF" . stream_get_contents($fh);
        fclose($fh);
        return Response::download($content, $filename, 'text/csv; charset=UTF-8');
    }
}
