<?php
declare(strict_types=1);

namespace App\Core;

class HttpException extends \RuntimeException
{
    public function __construct(public int $status, string $message = '')
    {
        parent::__construct($message ?: self::text($status), $status);
    }

    public static function text(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Please sign in to continue',
            403 => 'You do not have permission to do that',
            404 => 'Page not found',
            419 => 'Your session expired. Please try again.',
            429 => 'Too many attempts. Please wait a few minutes.',
            503 => 'The application is under maintenance',
            default => 'Something went wrong',
        };
    }
}
