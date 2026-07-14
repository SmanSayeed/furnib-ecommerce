<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin's own note on an order.
 *
 * Distinct from the two notes that already exist and cannot serve this purpose:
 *  - `notes`        — the CUSTOMER's checkout note (read-only for staff)
 *  - `pending_note` — gated to status=pending, and auto-nulled on any forward
 *                     transition, so it cannot outlive a confirmation
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('admin_note')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('admin_note');
        });
    }
};
