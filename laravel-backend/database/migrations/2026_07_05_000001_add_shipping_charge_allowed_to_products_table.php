<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // When false, this product never incurs any delivery charge — no zone
            // base and no per-unit extra. Default true keeps every existing
            // product's behaviour unchanged.
            $table->boolean('shipping_charge_allowed')->default(true)->after('stock_status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('shipping_charge_allowed');
        });
    }
};
