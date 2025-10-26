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
    Schema::create('business_opening_hours', function (Blueprint $table) {
      $table->id('business_opening_hours_id');
      $table->unsignedBigInteger('business_id');
      $table->enum('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
      $table->time('opens_at')->nullable();
      $table->time('closes_at')->nullable();
      $table->boolean('is_closed')->default(false);
      $table->timestamps();

      $table->foreign('business_id')->references('business_id')->on('business_details')->onDelete('cascade');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('business_opening_hours');
  }
};
