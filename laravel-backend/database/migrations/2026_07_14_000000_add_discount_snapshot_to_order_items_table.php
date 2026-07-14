<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records the discount on each order line so it survives the order.
     *
     * `original_price` is NULL — not "equal to price" — when the line was not
     * discounted. That keeps "was this discounted?" a single non-null check and
     * leaves every pre-existing row valid without a backfill.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // The regular price at order time; only set when a discount applied. Paisa.
            $table->unsignedBigInteger('original_price')->nullable()->after('price');
            // (original_price − price) × qty. Paisa.
            $table->unsignedBigInteger('discount_amount')->default(0)->after('original_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['original_price', 'discount_amount']);
        });
    }
};
