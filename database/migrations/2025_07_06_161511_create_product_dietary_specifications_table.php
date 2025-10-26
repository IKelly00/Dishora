<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('product_dietary_specifications', function (Blueprint $table) {
      $table->unsignedBigInteger('product_id');
      $table->unsignedBigInteger('dietary_specification_id');
      $table->primary(['product_id', 'dietary_specification_id']);
      $table->timestamps();

      $table->foreign('product_id')->references('product_id')->on('products');
      $table->foreign('dietary_specification_id')->references('dietary_specification_id')->on('dietary_specifications');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('product_dietary_specifications');
  }
};
