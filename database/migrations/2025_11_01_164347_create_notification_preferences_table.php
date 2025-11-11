<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('notification_preferences', function (Blueprint $table) {
      $table->bigIncrements('preference_id');
      $table->unsignedBigInteger('user_id');
      $table->string('event_type', 100);
      $table->string('channel', 50);
      $table->boolean('enabled')->default(true);
      $table->timestamp('updated_at')->nullable()->useCurrent();
      $table->foreign('user_id')->references('user_id')->on('users')->onDelete('CASCADE');
      $table->unique(['user_id', 'event_type', 'channel'], 'uc_notification_pref');
    });
  }
  public function down(): void
  {
    Schema::dropIfExists('notification_preferences');
  }
};
