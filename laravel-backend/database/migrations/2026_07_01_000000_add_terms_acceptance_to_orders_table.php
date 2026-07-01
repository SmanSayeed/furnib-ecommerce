<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Compliance #11 — proof the customer accepted the Terms & Conditions,
            // Privacy Policy and Return & Refund Policy at checkout. Captured
            // server-side (never trusted from the client) for an audit trail.
            $table->timestamp('terms_accepted_at')->nullable()->after('ga_client_id');
            $table->string('terms_ip')->nullable()->after('terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_ip']);
        });
    }
};
