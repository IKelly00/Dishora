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
    Schema::create('business_pm_details', function (Blueprint $table) {
      $table->id('business_pm_details_id');
      $table->unsignedBigInteger('business_payment_method_id');
      $table->string('account_number', 255);
      $table->string('account_name', 255);
      $table->boolean('is_active')->default(true);
      $table->timestamps();

      $table->foreign('business_payment_method_id')->references('business_payment_method_id')->on('business_payment_methods');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('business_pm_details');
  }
};
