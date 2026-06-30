<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // First-party attribution identifiers captured at checkout so the
            // later confirm-time server-side conversions (TikTok Events API +
            // GA4 Measurement Protocol) attribute to the customer, not the admin.
            // None are secret.
            $table->string('ttp')->nullable()->after('fbc');           // TikTok _ttp cookie
            $table->string('ttclid')->nullable()->after('ttp');         // TikTok click id
            $table->string('ga_client_id')->nullable()->after('ttclid'); // GA4 _ga client id
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ttp', 'ttclid', 'ga_client_id']);
        });
    }
};
