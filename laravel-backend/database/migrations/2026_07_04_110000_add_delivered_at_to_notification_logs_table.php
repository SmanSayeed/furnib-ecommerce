<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            // Set from a provider delivery report (DLR). Status also moves to
            // 'delivered' / 'undelivered' when the DLR arrives.
            $table->timestamp('delivered_at')->nullable()->after('status_code');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
    }
};
