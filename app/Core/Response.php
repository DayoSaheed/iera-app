<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/html; charset=UTF-8']
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        if (!preg_match('#^https?://#', $to)) {
            $to = url($to);
        }
        return new self('', $status, ['Location' => $to]);
    }

    public static function download(string $content, string $filename, string $mime = 'application/octet-stream'): self
    {
        return new self($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function file(string $path, string $filename, string $mime, bool $inline = true): self
    {
        $disposition = ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $filename) . '"';
        return new self((string) file_get_contents($path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
    }
}
