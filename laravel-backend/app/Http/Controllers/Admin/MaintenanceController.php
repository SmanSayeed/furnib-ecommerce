<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner-only reversible Maintenance Lock. Toggling only flips a settings flag
 * the storefront reads — it NEVER deletes files or destroys data (the
 * destructive-backdoor idea is permanently rejected; see project rules). Every
 * toggle is audit-logged.
 */
class MaintenanceController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        return Inertia::render('system/maintenance', [
            'enabled' => (bool) $this->settings->get('maintenance', 'enabled'),
            'message' => (string) ($this->settings->get('maintenance', 'message') ?? ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $enabled = (bool) $data['enabled'];

        $this->settings->set('maintenance', 'enabled', $enabled);
        $this->settings->set('maintenance', 'message', $data['message'] ?? '');

        activity('Maintenance')
            ->event($enabled ? 'enabled' : 'disabled')
            ->withProperties(['enabled' => $enabled])
            ->log('Maintenance lock '.($enabled ? 'enabled' : 'disabled'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $enabled ? __('Maintenance mode enabled.') : __('Maintenance mode disabled.'),
        ]);

        return back();
    }
}
