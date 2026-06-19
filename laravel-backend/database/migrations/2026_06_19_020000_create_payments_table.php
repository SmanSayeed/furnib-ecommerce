<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('gateway')->default('sslcommerz');
            // Intended amount in integer minor units (paisa); reconciled against
            // the gateway-validated amount before a payment is accepted.
            $table->unsignedBigInteger('amount');
            $table->string('type')->default('full'); // full | partial | shipping
            $table->string('tran_id', 40)->unique();  // our id — uniqueness gives idempotency
            $table->string('val_id')->nullable();      // gateway validation id (server-verified)
            $table->string('status')->default('pending'); // pending | success | failed
            // Encrypted at rest (cast: encrypted:array) — may contain gateway PII.
            $table->text('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
