<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_shipping_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            // Extra delivery cost for this product in this zone, per unit, in
            // integer minor units (paisa) — see the Money value object.
            $table->unsignedBigInteger('extra_cost')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'shipping_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_shipping_charges');
    }
};
