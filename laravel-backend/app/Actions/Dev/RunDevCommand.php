<?php

declare(strict_types=1);

namespace App\Actions\Dev;

use App\Support\Dev\DevCommands;
use App\Support\Dev\Redactor;
use DomainException;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs a single allow-listed artisan command for the developer console. The
 * caller supplies a command `id` (resolved against {@see DevCommands}), never a
 * raw command — so arbitrary execution is impossible. Destructive commands need
 * explicit confirmation. Output is secret-redacted and every run is audit-logged.
 */
final class RunDevCommand
{
    /**
     * @return array{id:string, label:string, exit_code:int, output:string}
     */
    public function handle(string $id, bool $confirmed = false): array
    {
        $entry = DevCommands::get($id);

        if ($entry === null) {
            throw new DomainException("Unknown command: {$id}");
        }

        if ($entry['destructive'] && ! $confirmed) {
            throw new DomainException("Command '{$id}' is destructive and requires confirmation.");
        }

        $exitCode = Artisan::call($entry['command'], $entry['args']);
        $output = Redactor::scrub(Artisan::output());

        activity('Developer')
            ->event('command')
            ->withProperties([
                'id' => $id,
                'command' => $entry['command'],
                'destructive' => $entry['destructive'],
                'exit_code' => $exitCode,
            ])
            ->log("Ran dev command: {$entry['command']}");

        return [
            'id' => $id,
            'label' => $entry['label'],
            'exit_code' => $exitCode,
            'output' => trim($output),
        ];
    }
}
