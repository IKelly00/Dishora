<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('notifications', function (Blueprint $table) {
      $table->bigIncrements('notification_id');
      $table->unsignedBigInteger('user_id');
      $table->unsignedBigInteger('actor_user_id')->nullable();
      $table->string('event_type', 100);
      $table->string('reference_table', 100)->nullable();
      $table->unsignedBigInteger('reference_id')->nullable();
      $table->unsignedBigInteger('business_id')->nullable();
      $table->string('recipient_role', 32)->nullable();
      $table->boolean('is_global')->default(false);
      $table->text('payload')->nullable(); // JSON
      $table->boolean('is_read')->default(false);
      $table->string('channel', 50)->default('in_app');
      $table->timestamp('created_at')->nullable()->useCurrent();
      $table->timestamp('expires_at')->nullable();


      $table->foreign('user_id')->references('user_id')->on('users');
      $table->index(['user_id', 'is_read', 'created_at']);
    });
  }
  public function down(): void
  {
    Schema::dropIfExists('notifications');
  }
};
