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
    Schema::create('payment_details', function (Blueprint $table) {
      $table->id('payment_detail_id');
      $table->unsignedBigInteger('payment_method_id');
      $table->unsignedBigInteger('order_id');
      $table->string('transaction_id', 200)->nullable();
      $table->decimal('amount_paid', 10, 2)->nullable();
      $table->enum('payment_status', ['Pending', 'Processing', 'Paid', 'Failed', 'Cancelled', 'Refunded', 'Chargeback'])->default('Pending');
      $table->string('payment_reference', 100)->nullable();
      $table->dateTime('paid_at')->nullable();
      $table->timestamps();

      $table->foreign('order_id')->references('order_id')->on('orders');
      $table->foreign('payment_method_id')->references('payment_method_id')->on('payment_methods');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('payment_details');
  }
};
