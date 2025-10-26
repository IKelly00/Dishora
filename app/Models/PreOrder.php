<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreOrder extends Model
{
  use HasFactory;

  protected $table = 'pre_orders';
  protected $primaryKey = 'pre_order_id';

  // allow all fields we actually write from the controller
  protected $fillable = [
    'order_id',
    'total_advance_required',
    'advance_paid_amount',
    'amount_due',
    'payment_transaction_id',
    'payment_option',
    'receipt_url',
    'preorder_status',
  ];

  // your table *does* have created_at / updated_at, so enable them
  public $timestamps = true;

  public function order()
  {
    return $this->belongsTo(Order::class, 'order_id', 'order_id');
  }

  // Preorder belongsTo an order item (if you store order_item_id)
  public function orderItem()
  {
    return $this->belongsTo(OrderItem::class, 'order_item_id', 'order_item_id');
  }
}
