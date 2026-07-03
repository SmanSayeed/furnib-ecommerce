<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Auto-generated, non-sensitive reason a payment ended the way it did
            // (e.g. "Cancelled by customer at the payment gateway"). Never asked
            // of the customer — the server fills it in.
            $table->string('note', 255)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
