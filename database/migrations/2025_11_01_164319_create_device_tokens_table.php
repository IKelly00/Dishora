<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('device_tokens', function (Blueprint $table) {
      $table->bigIncrements('device_token_id');
      $table->unsignedBigInteger('user_id');
      $table->string('provider', 50);
      $table->string('token', 500);
      $table->string('platform', 50)->nullable();
      $table->string('sns_endpoint_arn')->nullable();
      $table->timestamp('last_seen')->nullable();
      $table->boolean('is_active')->default(true);
      $table->timestamp('created_at')->nullable()->useCurrent();
      $table->foreign('user_id')->references('user_id')->on('users')->onDelete('CASCADE');
      $table->index(['user_id', 'is_active']);
    });
  }
  public function down(): void
  {
    Schema::dropIfExists('device_tokens');
  }
};
