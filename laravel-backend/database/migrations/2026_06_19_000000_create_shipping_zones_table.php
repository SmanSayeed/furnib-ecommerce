<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Shipping cost in integer minor units (paisa) — see Money value object.
            $table->unsignedBigInteger('cost')->default(0);
            $table->boolean('status')->default(true);
            $table->unsignedInteger('position_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'position_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
