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
      $table->string('sender_role')->nullable();

      $table->unsignedBigInteger('receiver_id');
      $table->string('receiver_role')->nullable();

      $table->text('message_text')->nullable();
      $table->string('image_url')->nullable();
      $table->dateTime('sent_at')->default(DB::raw('CURRENT_TIMESTAMP'));
      $table->boolean('is_read')->default(false);

      // Add indexes for speed
      $table->index(['sender_id', 'sender_role']);
      $table->index(['receiver_id', 'receiver_role']);
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
