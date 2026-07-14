<?php

declare(strict_types=1);

use App\Models\Courier;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Migrations\Migration;

/**
 * Courier credentials have lived in two stores: the courier's encrypted `config`
 * (Shipping → Couriers) and the legacy `steadfast` settings group (Settings →
 * Integrations). An earlier seed migration copied settings → config, but ONLY if
 * the couriers table happened to be empty at migrate time — so keys entered on
 * the server afterwards never landed in `config`.
 *
 * Courier::credential() now resolves the legacy fallback at read time, so nothing
 * is broken without this. This migration simply promotes `config` to the single
 * store of record, per courier, filling only the keys it is missing.
 *
 * Deliberately non-fatal: if the legacy secrets were encrypted under a different
 * APP_KEY, decryption fails — and a data cleanup must never block a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $settings = app(SettingsService::class);

            $legacy = [
                'api_key' => $settings->get('steadfast', 'api_key'),
                'secret_key' => $settings->get('steadfast', 'secret_key'),
            ];

            $legacy = array_filter($legacy, static fn ($v): bool => is_string($v) && $v !== '');

            if ($legacy === []) {
                return;
            }

            Courier::query()
                ->where('driver', Courier::DRIVER_STEADFAST)
                ->get()
                ->each(function (Courier $courier) use ($legacy): void {
                    $config = $courier->safeConfig();
                    $changed = false;

                    foreach ($legacy as $key => $value) {
                        if (blank($config[$key] ?? null)) {
                            $config[$key] = $value;
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        $courier->update(['config' => $config]);
                    }
                });
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the legacy settings rows are left untouched, and the
        // copied config values are indistinguishable from ones typed in by hand.
    }
};
