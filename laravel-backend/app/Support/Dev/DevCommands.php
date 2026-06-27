<?php

declare(strict_types=1);

namespace App\Support\Dev;

/**
 * The ONLY artisan commands the developer console may run. The UI sends a
 * command `id` (never a raw command string), which is resolved here — so there
 * is no path to run an arbitrary command. Destructive entries require an
 * explicit typed confirmation before they run.
 *
 * Deliberately excluded:
 * - `route:cache` / `optimize` — this app has closure routes (health, auth/me)
 *   which cannot be route-cached; caching them errors. The deploy entrypoint
 *   skips route:cache for the same reason.
 * - `migrate:fresh`, `migrate:rollback`, `db:wipe` — destructive/irreversible.
 */
final class DevCommands
{
    /**
     * @var array<string, array{command:string, args:array<string,mixed>, label:string, group:string, destructive:bool}>
     */
    private const COMMANDS = [
        // Cache — clear (safe, instant)
        'optimize-clear' => ['command' => 'optimize:clear', 'args' => [], 'label' => 'Clear all caches', 'group' => 'cache', 'destructive' => false],
        'config-clear' => ['command' => 'config:clear', 'args' => [], 'label' => 'Clear config cache', 'group' => 'cache', 'destructive' => false],
        'route-clear' => ['command' => 'route:clear', 'args' => [], 'label' => 'Clear route cache', 'group' => 'cache', 'destructive' => false],
        'view-clear' => ['command' => 'view:clear', 'args' => [], 'label' => 'Clear compiled views', 'group' => 'cache', 'destructive' => false],
        'cache-clear' => ['command' => 'cache:clear', 'args' => [], 'label' => 'Clear application cache', 'group' => 'cache', 'destructive' => false],
        'event-clear' => ['command' => 'event:clear', 'args' => [], 'label' => 'Clear cached events', 'group' => 'cache', 'destructive' => false],

        // Cache — build (safe; route:cache intentionally absent — closures)
        'config-cache' => ['command' => 'config:cache', 'args' => [], 'label' => 'Cache config', 'group' => 'cache', 'destructive' => false],
        'view-cache' => ['command' => 'view:cache', 'args' => [], 'label' => 'Cache views', 'group' => 'cache', 'destructive' => false],

        // Database
        'migrate-status' => ['command' => 'migrate:status', 'args' => [], 'label' => 'Migration status', 'group' => 'database', 'destructive' => false],
        'migrate' => ['command' => 'migrate', 'args' => ['--force' => true], 'label' => 'Run migrations', 'group' => 'database', 'destructive' => true],

        // Ops
        'storage-link' => ['command' => 'storage:link', 'args' => [], 'label' => 'Link storage', 'group' => 'ops', 'destructive' => false],
        'queue-restart' => ['command' => 'queue:restart', 'args' => [], 'label' => 'Restart queue workers', 'group' => 'ops', 'destructive' => false],
        'schedule-list' => ['command' => 'schedule:list', 'args' => [], 'label' => 'List scheduled tasks', 'group' => 'ops', 'destructive' => false],
        'about' => ['command' => 'about', 'args' => [], 'label' => 'System info', 'group' => 'ops', 'destructive' => false],
    ];

    public static function has(string $id): bool
    {
        return isset(self::COMMANDS[$id]);
    }

    /**
     * @return array{command:string, args:array<string,mixed>, label:string, group:string, destructive:bool}|null
     */
    public static function get(string $id): ?array
    {
        return self::COMMANDS[$id] ?? null;
    }

    public static function isDestructive(string $id): bool
    {
        return self::COMMANDS[$id]['destructive'] ?? false;
    }

    /**
     * UI-facing catalogue (no internal command strings leaked beyond the label).
     *
     * @return array<int, array{id:string, label:string, group:string, destructive:bool}>
     */
    public static function catalogue(): array
    {
        $out = [];
        foreach (self::COMMANDS as $id => $c) {
            $out[] = ['id' => $id, 'label' => $c['label'], 'group' => $c['group'], 'destructive' => $c['destructive']];
        }

        return $out;
    }
}
