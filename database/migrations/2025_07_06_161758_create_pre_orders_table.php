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
    Schema::create('pre_orders', function (Blueprint $table) {
      $table->id('pre_order_id');

      $table->unsignedBigInteger('order_id');
      $table->decimal('total_advance_required', 10, 2);
      $table->decimal('advance_paid_amount', 10, 2);
      $table->decimal('amount_due', 10, 2);
      $table->string('payment_transaction_id', 255)->nullable();
      $table->string('payment_option', 255)->nullable();
      $table->string('preorder_status')->default('pending_payment');
      $table->string('receipt_url', 255)->nullable();
      $table->timestamps();

      // Foreign key linking to orders table
      $table->foreign('order_id')
        ->references('order_id')
        ->on('orders')
        ->onDelete('cascade');

      // Ensure one pre-order per order
      $table->unique('order_id', 'UQ_pre_orders_order_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('pre_orders');
  }
};
