<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders a uniform JSON error envelope for /api/* requests:
 * { "error": { "code", "message", "details?" } }.
 * Internal exception details are hidden unless APP_DEBUG is on.
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return self::make(422, 'validation_error', 'The given data was invalid.', $e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return self::make(401, 'unauthenticated', 'Unauthenticated.');
        }

        if ($e instanceof AuthorizationException) {
            return self::make(403, 'forbidden', 'This action is unauthorized.');
        }

        if ($e instanceof ModelNotFoundException) {
            return self::make(404, 'not_found', 'Resource not found.');
        }

        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        $code = match ($status) {
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            429 => 'too_many_requests',
            default => $status >= 500 ? 'server_error' : 'http_error',
        };

        $message = $status >= 500
            ? (config('app.debug') ? $e->getMessage() : 'Server error.')
            : ($e->getMessage() !== '' ? $e->getMessage() : 'Request failed.');

        return self::make($status, $code, $message);
    }

    /**
     * @param  array<string,mixed>|null  $details
     */
    private static function make(int $status, string $code, string $message, ?array $details = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }
}
