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
    Schema::create('order_sessions', function (Blueprint $table) {
      $table->id('order_session_id');
      $table->unsignedBigInteger('user_id')->nullable(); // nullable for guests
      $table->string('session_id'); // store session ID or unique token
      $table->json('orders'); // store cart items as JSON
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('order_sessions');
  }
};
