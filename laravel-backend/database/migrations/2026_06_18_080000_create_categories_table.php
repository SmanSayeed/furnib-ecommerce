<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('details')->nullable();
            $table->string('header_image')->nullable();
            $table->string('thumbnail_image')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('position_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('og_image')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'position_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
