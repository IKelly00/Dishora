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
    Schema::create('checkout_drafts', function (Blueprint $table) {
      $table->id('checkout_draft_id');
      $table->unsignedBigInteger('user_id');
      $table->unsignedBigInteger('payment_method_id');
      $table->string('transaction_id')->nullable(); // PayMongo source/payment_intent id
      $table->decimal('total', 10, 2);
      $table->json('cart');      // cart snapshot
      $table->json('delivery');  // delivery info snapshot
      $table->json('item_notes')->nullable();
      $table->boolean('is_cod')->default(false);
      $table->timestamp('processed_at')->nullable();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('checkout_drafts');
  }
};
