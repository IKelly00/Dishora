<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutDraft extends Model
{
  protected $table = 'checkout_drafts';
  protected $primaryKey = 'checkout_draft_id';

  protected $fillable = [
    'user_id',
    'payment_method_id',
    'transaction_id',
    'total',
    'cart',
    'delivery',
    'item_notes',
    'is_cod',
    'processed_at'
  ];

  protected $casts = [
    'cart' => 'array',
    'delivery' => 'array',
    'item_notes' => 'array',
  ];
}
