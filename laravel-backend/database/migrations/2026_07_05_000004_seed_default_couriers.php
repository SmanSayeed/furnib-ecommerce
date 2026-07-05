<?php

declare(strict_types=1);

use App\Models\Courier;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the built-in couriers so the shop has something to book with:
     *  - Steadfast (default, API): carries over any credentials already stored in
     *    the legacy `steadfast` settings, and becomes the auto-book default so the
     *    current confirm→book behaviour is preserved.
     *  - Manual: a no-API courier admins can select for hand-booked shipments.
     */
    public function up(): void
    {
        if (Courier::query()->exists()) {
            return;
        }

        $settings = app(SettingsService::class);
        $apiKey = $settings->get('steadfast', 'api_key');
        $secretKey = $settings->get('steadfast', 'secret_key');

        Courier::query()->create([
            'name' => 'Steadfast',
            'slug' => 'steadfast',
            'driver' => Courier::DRIVER_STEADFAST,
            'is_active' => true,
            'is_default' => true,
            'position_order' => 1,
            'config' => array_filter([
                'api_key' => $apiKey,
                'secret_key' => $secretKey,
            ], fn ($v): bool => filled($v)) ?: null,
        ]);

        Courier::query()->create([
            'name' => 'Manual / Store pickup',
            'slug' => 'manual',
            'driver' => Courier::DRIVER_MANUAL,
            'is_active' => true,
            'is_default' => false,
            'position_order' => 2,
            'config' => null,
        ]);
    }

    public function down(): void
    {
        Courier::query()->whereIn('slug', ['steadfast', 'manual'])->forceDelete();
    }
};
