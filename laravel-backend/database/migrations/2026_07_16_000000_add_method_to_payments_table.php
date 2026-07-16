<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The channel a MANUAL payment came through — bKash / Nagad / Rocket / bank / cash
 * / other. Null for gateway (SSLCommerz) rows, which already carry their own
 * tran_id/val_id. The transaction id / bank reference goes in the existing `note`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('method')->nullable()->after('gateway');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('method');
        });
    }
};
