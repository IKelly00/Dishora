<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('products', function (Blueprint $table) {
      $table->id('product_id');
      $table->unsignedBigInteger('business_id');
      $table->unsignedBigInteger('product_category_id')->nullable();
      $table->string('item_name', 100);
      $table->decimal('price', 10, 2);
      $table->integer('cutoff_minutes')->nullable();;
      $table->boolean('is_available')->default(true);
      $table->boolean('is_pre_order')->default(false);
      $table->decimal('advance_amount', 10, 2)->default(0.00);
      $table->string('image_url', 255)->nullable();
      $table->text('description')->nullable();
      $table->timestamps();

      $table->foreign('business_id')->references('business_id')->on('business_details');
      $table->foreign('product_category_id')->references('product_category_id')->on('product_categories');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('products');
  }
};
