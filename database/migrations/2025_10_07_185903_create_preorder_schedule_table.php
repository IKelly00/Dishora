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
    Schema::create('preorder_schedule', function (Blueprint $table) {
      $table->id('schedule_id');
      $table->foreignId('business_id')->constrained('business_details', 'business_id')->onDelete('cascade');
      $table->date('available_date');
      $table->integer('max_orders')->comment('Max number of total pre-orders for this day');
      $table->integer('current_order_count')->default(0);
      $table->boolean('is_active')->default(true);
      $table->timestamps();

      // Ensures a vendor can't have two schedules for the same day
      $table->unique(['business_id', 'available_date']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('preorder_schedule');
  }
};
