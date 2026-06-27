<?php

declare(strict_types=1);

namespace App\Support\Dev;

use App\Models\ErrorLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use PDOException;
use Throwable;

/**
 * Persists application exceptions to the `error_logs` table so the owner can
 * review them from the developer console — even in production, where logs are
 * written to stderr and there is no readable log file.
 *
 * Best-effort: any failure here is swallowed so error capture never breaks the
 * real request, and database-layer exceptions are skipped to avoid recursing
 * (writing the row would re-trigger the same failure).
 */
final class ErrorLogger
{
    private const MESSAGE_LIMIT = 2000;

    /** Re-entrancy guard: never record an error raised while recording one. */
    private static bool $recording = false;

    public function record(Throwable $e, ?Request $request = null): void
    {
        if (self::$recording) {
            return;
        }

        // The DB is the storage target; if the error *is* a DB failure, writing
        // a row would just fail again. Skip these to avoid noise and recursion.
        if ($e instanceof QueryException || $e instanceof PDOException) {
            return;
        }

        self::$recording = true;

        try {
            ErrorLog::create([
                'level' => 'error',
                'message' => $this->clean($e->getMessage()),
                'exception_class' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'method' => $request?->getMethod(),
                'url' => $request !== null ? $this->clean($request->fullUrl()) : null,
            ]);
        } catch (Throwable) {
            // Never let error capture throw.
        } finally {
            self::$recording = false;
        }
    }

    private function clean(string $text): string
    {
        return Redactor::scrub(mb_substr($text, 0, self::MESSAGE_LIMIT));
    }
}
