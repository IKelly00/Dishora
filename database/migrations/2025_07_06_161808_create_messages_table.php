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
    Schema::create('messages', function (Blueprint $table) {
      $table->id('message_id');
      $table->unsignedBigInteger('sender_id');
      $table->unsignedBigInteger('receiver_id');
      $table->text('message_text')->nullable();
      $table->dateTime('sent_at')->default(DB::raw('CURRENT_TIMESTAMP'));
      $table->boolean('is_read')->default(false);

      $table->foreign('sender_id')->references('user_id')->on('users');
      $table->foreign('receiver_id')->references('user_id')->on('users');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('messages');
  }
};
