<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dev;

use App\Actions\Dev\RunDevCommand;
use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use App\Support\Dev\DevCommands;
use App\Support\Dev\Redactor;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner-only developer console. Runs a fixed allow-list of artisan commands
 * ({@see DevCommands}) by id, shows system info + health, and (later) logs.
 * Gated by `developer.access`, which only the owner role holds.
 */
class DeveloperController extends Controller
{
    public function __construct(private readonly RunDevCommand $runner) {}

    public function index(Request $request): Response
    {
        return Inertia::render('dev/index', [
            'commands' => DevCommands::catalogue(),
            'system' => $this->systemInfo(),
            'health' => $this->health(),
            // Flashed result of the last command run (set by run()).
            'result' => $request->session()->get('devResult'),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string'],
            'confirmed' => ['boolean'],
        ]);

        try {
            $result = $this->runner->handle($data['id'], (bool) ($data['confirmed'] ?? false));
        } catch (DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => $result['exit_code'] === 0 ? 'success' : 'warning',
            'message' => $result['label'].' — exit '.$result['exit_code'],
        ]);

        return back()->with('devResult', $result);
    }

    /**
     * Recent captured exceptions (already redacted at capture time).
     */
    public function errors(): Response
    {
        $errors = ErrorLog::query()
            ->latest('created_at')
            ->limit(200)
            ->get(['id', 'level', 'message', 'exception_class', 'file', 'line', 'method', 'url', 'created_at'])
            ->map(fn (ErrorLog $e): array => [
                'id' => $e->id,
                'level' => $e->level,
                'message' => $e->message,
                'exception' => $e->exception_class,
                'location' => $e->file !== null ? $e->file.':'.$e->line : null,
                'method' => $e->method,
                'url' => $e->url,
                'at' => $e->created_at?->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('dev/errors', ['errors' => $errors]);
    }

    public function clearErrors(): RedirectResponse
    {
        ErrorLog::query()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Error log cleared.')]);

        return back();
    }

    /**
     * Tail of the local log file (redacted). Empty in production, where logs
     * are streamed to stderr — the DB-backed Errors tab is used there instead.
     */
    public function logs(): Response
    {
        return Inertia::render('dev/logs', $this->logTail());
    }

    /**
     * @return array{available: bool, path: string, lines: string}
     */
    private function logTail(): array
    {
        $path = storage_path('logs/laravel.log');
        $rel = 'storage/logs/laravel.log';

        if (! is_file($path)) {
            return ['available' => false, 'path' => $rel, 'lines' => ''];
        }

        $content = rescue(function () use ($path): string {
            $size = (int) filesize($path);
            $read = min($size, 256 * 1024); // last 256KB is plenty for a tail
            $fh = fopen($path, 'rb');

            if ($fh === false) {
                return '';
            }

            if ($read > 0) {
                fseek($fh, -$read, SEEK_END);
            }

            $data = (string) fread($fh, max($read, 1));
            fclose($fh);

            $lines = preg_split('/\r?\n/', $data) ?: [];

            return implode("\n", array_slice($lines, -400));
        }, '', false);

        return ['available' => true, 'path' => $rel, 'lines' => Redactor::scrub($content)];
    }

    /**
     * @return array<string, mixed>
     */
    private function systemInfo(): array
    {
        return [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'env' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'url' => (string) config('app.url'),
            'timezone' => (string) config('app.timezone'),
            'db' => (string) config('database.default'),
            'cache' => (string) config('cache.default'),
            'queue' => (string) config('queue.default'),
            'session' => (string) config('session.driver'),
            'maintenance' => app()->isDownForMaintenance(),
        ];
    }

    /**
     * Lightweight health pings (no secrets surfaced).
     *
     * @return array<string, bool>
     */
    private function health(): array
    {
        return [
            'database' => rescue(function (): bool {
                DB::connection()->getPdo();

                return true;
            }, false, false),
            'cache' => rescue(function (): bool {
                Cache::put('dev_health_ping', '1', 5);

                return Cache::get('dev_health_ping') === '1';
            }, false, false),
        ];
    }
}
