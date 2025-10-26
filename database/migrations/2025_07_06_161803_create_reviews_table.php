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
    Schema::create('reviews', function (Blueprint $table) {
      $table->id('review_id');
      $table->unsignedBigInteger('customer_id');
      $table->unsignedBigInteger('business_id');
      $table->integer('rating');
      $table->string('comment', 255)->nullable();
      $table->timestamps();

      $table->foreign('customer_id')->references('user_id')->on('users');
      $table->foreign('business_id')->references('business_id')->on('business_details');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('reviews');
  }
};
