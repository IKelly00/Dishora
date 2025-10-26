<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
  protected $table = 'order_items';
  protected $primaryKey = 'order_item_id';

  protected $fillable = [
    'order_id',
    'product_id',
    'product_name',
    'product_description',
    'quantity',
    'price_at_order_time',
    'order_item_status',
    'order_item_note',
    'is_pre_order'
  ];

  public function order()
  {
    return $this->belongsTo(Order::class, 'order_id', 'order_id');
  }

  public function product()
  {
    return $this->belongsTo(Product::class, 'product_id', 'product_id');
  }
}
