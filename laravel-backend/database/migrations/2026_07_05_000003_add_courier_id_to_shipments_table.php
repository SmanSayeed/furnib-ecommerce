<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // The chosen courier. Nullable + nullOnDelete so deleting a courier
            // never breaks historical shipments — the `courier` string keeps the
            // name snapshot that the shipping label/PDF prints.
            $table->foreignId('courier_id')->nullable()->after('order_id')
                ->constrained('couriers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('courier_id');
        });
    }
};
