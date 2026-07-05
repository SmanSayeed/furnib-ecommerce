<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // display name (shown on labels/PDF)
            $table->string('slug')->unique();       // stable identifier
            // Which integration drives this courier. 'manual' = no API (booked by
            // hand); the rest call their provider API.
            $table->string('driver')->default('manual'); // manual | steadfast | redx | pathao
            $table->boolean('is_active')->default(true);
            // At most one default: auto-booked on order confirm when set.
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position_order')->default(0);
            // Per-courier credentials/settings, encrypted at rest. Never leaves the
            // server; the CRUD form only exposes "is set" flags.
            $table->text('config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'position_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};
