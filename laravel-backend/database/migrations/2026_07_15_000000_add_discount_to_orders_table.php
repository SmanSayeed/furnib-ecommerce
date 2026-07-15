<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order-level discount an admin can grant AFTER placement — separate from the
 * per-line product discounts snapshotted on order_items. Integer minor units
 * (paisa). The total invariant becomes: total = subtotal − discount + shipping.
 *
 * Additive and defaulted, so every existing order reads discount = 0 and its
 * total is unchanged — no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('discount')->default(0)->after('subtotal');
            $table->string('discount_note')->nullable()->after('discount');
            $table->foreignId('discount_by')->nullable()->after('discount_note')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_by');
            $table->dropColumn(['discount', 'discount_note']);
        });
    }
};
