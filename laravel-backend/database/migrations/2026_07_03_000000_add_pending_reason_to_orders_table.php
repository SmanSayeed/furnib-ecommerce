<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pending order now carries a reason so the team knows why it is still open
 * (new order, waiting on a call, payment pending, needs an expert call, or a
 * free-text "other"). Orders never auto-confirm — even a fully paid order stays
 * pending until an admin manually confirms it — so this reason drives the queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Kept as a plain string (not a DB enum) so new reasons can be added
            // without a schema migration; the allowed set is enforced in the app
            // (Order::PENDING_REASONS + the FormRequest).
            $table->string('pending_reason', 32)->default('new_order')->after('status');
            $table->text('pending_note')->nullable()->after('pending_reason');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['pending_reason', 'pending_note']);
        });
    }
};
