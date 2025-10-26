<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('vendors', function (Blueprint $table) {
      $table->id('vendor_id');
      $table->unsignedBigInteger('user_id')->unique();
      $table->string('fullname', 150);
      $table->string('phone_number', 20)->nullable();
      $table->enum('registration_status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
      $table->timestamps();

      $table->foreign('user_id')->references('user_id')->on('users');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('vendors');
  }
};
