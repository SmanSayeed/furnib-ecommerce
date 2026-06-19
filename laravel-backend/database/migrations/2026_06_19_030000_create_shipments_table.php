<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            // One shipment per order.
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('courier')->default('steadfast');
            $table->string('consignment_id')->nullable();
            $table->string('tracking_code')->nullable();
            $table->string('status')->default('pending');
            $table->string('recipient_name');
            $table->string('recipient_phone', 20);
            $table->text('recipient_address');
            // Cash-on-delivery amount in integer minor units (paisa).
            $table->unsignedBigInteger('cod_amount')->default(0);
            $table->text('note')->nullable();
            // Encrypted at rest (cast: encrypted:array).
            $table->text('raw_payload')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
