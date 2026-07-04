<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            // Nullable: OTP / non-order notifications carry no order.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');   // sms | email | …
            $table->string('event');     // confirmed | shipped | delivered | cancelled | returned
            $table->string('recipient'); // mobile or email the message went to
            $table->text('message')->nullable();
            $table->string('provider')->nullable();            // automas | …
            $table->string('provider_message_id')->nullable(); // gateway id
            $table->string('status')->default('sent');         // sent | failed
            $table->string('status_code')->nullable();         // provider numeric code
            $table->string('error')->nullable();
            $table->timestamps();

            // One notification per order+event+channel — the idempotency guard.
            $table->unique(['order_id', 'event', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
