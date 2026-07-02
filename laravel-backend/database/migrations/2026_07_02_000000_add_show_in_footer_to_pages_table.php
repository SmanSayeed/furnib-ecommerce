<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a published page is listed in the storefront footer "About Us"
     * column. Defaults to true so every published page shows automatically; the
     * admin can hide individual (non-system) pages from Footer details.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->boolean('show_in_footer')->default(true)->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('show_in_footer');
        });
    }
};
