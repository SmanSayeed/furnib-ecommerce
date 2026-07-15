<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an order came from: 'storefront' (public checkout, the default) or
 * 'admin' (created by staff on a customer's behalf), plus which staff member
 * created it. Defaulted, so every existing order reads 'storefront' — no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('source')->default('storefront')->after('payment_status');
            $table->foreignId('created_by')->nullable()->after('source')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('source');
        });
    }
};
