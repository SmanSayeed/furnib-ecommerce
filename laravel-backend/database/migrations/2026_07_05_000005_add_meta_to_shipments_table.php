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
            // Booking-time courier metadata snapshot — e.g. the RedX
            // delivery_area_id, or the Pathao city/zone/area ids selected when the
            // consignment is booked. Steadfast needs none of this and leaves it
            // null. Encrypted at rest like the raw payload (may carry identifiers).
            $table->text('meta')->nullable()->after('raw_payload');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
