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
    Schema::create('delivery_addresses', function (Blueprint $table) {
      $table->id('delivery_address_id');
      $table->unsignedBigInteger('order_id');
      $table->unsignedBigInteger('user_id');
      $table->string('phone_number', 20)->nullable();
      $table->string('region', 255)->nullable();
      $table->string('province', 255)->nullable();
      $table->string('city', 255)->nullable();
      $table->string('barangay', 255)->nullable();
      $table->string('postal_code', 20)->nullable();
      $table->text('street_name')->nullable();
      $table->text('full_address')->nullable();
      $table->timestamps();

      $table->foreign('order_id')->references('order_id')->on('orders')->onDelete('cascade');
      $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
      $table->index('province');
      $table->index('city');
      $table->index('postal_code');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('delivery_addresses');
  }
};
