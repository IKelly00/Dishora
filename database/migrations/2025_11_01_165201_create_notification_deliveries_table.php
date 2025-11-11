<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('notification_deliveries', function (Blueprint $table) {
      $table->bigIncrements('delivery_id');
      $table->unsignedBigInteger('notification_id');
      $table->string('provider', 50)->nullable();
      $table->text('provider_response')->nullable();
      $table->boolean('success')->nullable();
      $table->timestamp('attempted_at')->nullable()->useCurrent();
      $table->foreign('notification_id')->references('notification_id')->on('notifications')->onDelete('CASCADE');
    });
  }
  public function down(): void
  {
    Schema::dropIfExists('notification_deliveries');
  }
};
