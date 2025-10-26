<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('business_details', function (Blueprint $table) {
      $table->id('business_id');
      $table->string('business_image', 255)->nullable();
      $table->unsignedBigInteger('vendor_id');
      $table->string('business_name', 150)->unique();
      $table->text('business_description');
      $table->string('business_type', 150);
      $table->text('business_location')->nullable();
      $table->string('valid_id_type', 50)->nullable();
      $table->string('valid_id_no', 50)->nullable();
      $table->string('business_permit_no', 50)->nullable();
      $table->string('bir_reg_no', 50)->nullable();
      $table->string('business_permit_file', 255)->nullable();
      $table->string('valid_id_file', 255)->nullable();
      $table->string('bir_reg_file', 255)->nullable();
      $table->string('mayor_permit_file', 255)->nullable();
      $table->string('business_duration', 255)->nullable();
      $table->double('latitude')->nullable();
      $table->double('longitude')->nullable();
      $table->enum('verification_status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
      $table->integer('preorder_lead_time_hours')->default(48);
      $table->string('remarks', 255)->nullable();
      $table->timestamps();

      $table->foreign('vendor_id')->references('vendor_id')->on('vendors');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('business_details');
  }
};
