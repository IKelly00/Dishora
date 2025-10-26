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
    Schema::create('business_payment_methods', function (Blueprint $table) {
      $table->id('business_payment_method_id');

      $table->unsignedBigInteger('business_id');
      $table->unsignedBigInteger('payment_method_id');

      $table->timestamps();

      // Foreign keys
      $table->foreign('business_id')->references('business_id')->on('business_details')->onDelete('cascade');
      $table->foreign('payment_method_id')->references('payment_method_id')->on('payment_methods')->onDelete('cascade');

      // Optional: prevent duplicates
      $table->unique(['business_id', 'payment_method_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('business_payment_methods');
  }
};
