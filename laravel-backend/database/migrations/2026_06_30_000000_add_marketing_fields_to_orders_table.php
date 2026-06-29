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
            // First-party Meta attribution cookies captured at checkout, so the
            // later admin-confirm Purchase can be attributed to the customer
            // (not the admin's browser). client_ip reuses the existing
            // customer_ip column. None are secret.
            $table->string('fbp')->nullable()->after('user_agent');
            $table->string('fbc')->nullable()->after('fbp');
            // Idempotency stamp: the Purchase conversion fires exactly once, when
            // the order is first confirmed. Null = not yet sent.
            $table->timestamp('marketing_purchase_sent_at')->nullable()->after('fbc');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fbp', 'fbc', 'marketing_purchase_sent_at']);
        });
    }
};
