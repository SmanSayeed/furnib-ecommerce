<?php

declare(strict_types=1);

use App\Services\Settings\SettingsService;
use Illuminate\Database\Migrations\Migration;

/**
 * Splits the single SSLCommerz store credential pair into mode-scoped keys
 * (sandbox_* / live_*). Copies any existing legacy pair into whichever mode is
 * currently active, so a live-configured install keeps working after deploy and
 * the admin can now add the other environment's keys without wiping these.
 */
return new class extends Migration
{
    public function up(): void
    {
        $settings = app(SettingsService::class);

        $mode = (bool) $settings->get('sslcommerz', 'sandbox', true) ? 'sandbox' : 'live';

        $legacyId = $settings->get('sslcommerz', 'store_id');
        if (filled($legacyId) && blank($settings->get('sslcommerz', $mode.'_store_id'))) {
            $settings->set('sslcommerz', $mode.'_store_id', $legacyId);
        }

        $legacyPassword = $settings->get('sslcommerz', 'store_passwd');
        if (filled($legacyPassword) && blank($settings->get('sslcommerz', $mode.'_store_passwd'))) {
            $settings->set('sslcommerz', $mode.'_store_passwd', $legacyPassword, isSecret: true);
        }
    }

    public function down(): void
    {
        // Non-destructive data move — nothing to reverse (legacy keys untouched).
    }
};
