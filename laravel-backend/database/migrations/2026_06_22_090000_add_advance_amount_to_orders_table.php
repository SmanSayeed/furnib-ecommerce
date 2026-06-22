<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `advance_amount` = the advance REQUIRED for the order (computed server-side at
 * checkout from the product's advance rule). Distinct from `advance_paid`, which
 * is what the customer has actually paid so far. Integer minor units (paisa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('advance_amount')->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('advance_amount');
        });
    }
};
