<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->longText('details')->nullable();
            $table->string('product_video')->nullable();
            $table->string('main_image')->nullable();
            $table->string('social_thumbnail_image')->nullable();
            $table->unsignedBigInteger('price')->default(0); // minor units (paisa)
            $table->unsignedBigInteger('discount_price')->nullable();
            $table->boolean('is_advance_payment')->default(false);
            $table->enum('advance_payment_type', ['full', 'partial'])->nullable();
            $table->enum('partial_amount_type', ['percentage', 'amount'])->nullable();
            $table->unsignedBigInteger('partial_amount')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->unsignedInteger('position_order')->default(0);
            $table->enum('product_status', ['draft', 'published', 'disabled'])->default('draft');
            $table->unsignedInteger('stock_amount')->default(0);
            $table->boolean('stock_status')->default(true);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('og_image')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_status', 'position_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
