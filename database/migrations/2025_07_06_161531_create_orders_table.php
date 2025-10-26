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
    Schema::create('orders', function (Blueprint $table) {
      $table->id('order_id');
      $table->unsignedBigInteger('user_id');
      $table->unsignedBigInteger('business_id');
      $table->decimal('total', 10, 2);
      $table->date('delivery_date');
      $table->string('delivery_time');
      $table->unsignedBigInteger('payment_method_id');
      $table->timestamps();

      $table->foreign('user_id')->references('user_id')->on('users');
      $table->foreign('business_id')->references('business_id')->on('business_details');
      $table->foreign('payment_method_id')->references('payment_method_id')->on('payment_methods');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('orders');
  }
};
