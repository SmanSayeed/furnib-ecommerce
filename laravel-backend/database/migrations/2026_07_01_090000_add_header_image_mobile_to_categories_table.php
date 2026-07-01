<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Mobile variant of the category header. `header_image` remains the
            // desktop image; the storefront falls back to it when this is null.
            $table->string('header_image_mobile')->nullable()->after('header_image');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('header_image_mobile');
        });
    }
};
