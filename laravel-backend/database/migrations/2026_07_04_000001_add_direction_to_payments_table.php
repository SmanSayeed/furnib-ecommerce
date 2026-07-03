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
            // Ledger direction: credit = money in (gateway payment, manual
            // "payment received"), debit = money out (manual refund/reduction).
            // Existing rows are all inbound gateway payments → credit.
            $table->string('direction', 8)->default('credit')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
