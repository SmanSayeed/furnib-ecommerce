<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cheaper delivery for each unit after the first.
 *
 * One van goes out either way, so the 2nd and 3rd chair genuinely cost less to
 * carry than the 1st. When enabled:
 *
 *   extraForLine = extra_cost + multi_extra_cost × (qty − 1)
 *
 * Both columns are additive and the flag defaults to false, so every existing
 * product keeps charging `extra_cost × qty` exactly as it does today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('multi_qty_shipping_enabled')
                ->default(false)
                ->after('shipping_charge_allowed');
        });

        Schema::table('product_shipping_charges', function (Blueprint $table) {
            // Per-unit cost of each unit AFTER the first, in this zone (paisa).
            // NULL = not configured → the line falls back to extra_cost × qty.
            // 0 is a deliberate value: later units then ship free.
            $table->unsignedBigInteger('multi_extra_cost')->nullable()->after('extra_cost');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('multi_qty_shipping_enabled');
        });

        Schema::table('product_shipping_charges', function (Blueprint $table) {
            $table->dropColumn('multi_extra_cost');
        });
    }
};
