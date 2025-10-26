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
    Schema::create('customers', function (Blueprint $table) {
      $table->id('customer_id');
      $table->unsignedBigInteger('user_id');
      $table->string('user_image', 255)->nullable();
      $table->text('user_address')->nullable();
      $table->double('latitude')->nullable();
      $table->double('longitude')->nullable();
      $table->string('contact_number', 20)->nullable();
      $table->timestamps();

      $table->foreign('user_id')->references('user_id')->on('users');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('customers');
  }
};
