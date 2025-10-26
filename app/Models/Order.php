<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
  use HasFactory;

  protected $primaryKey = 'order_id';

  protected $fillable = [
    'user_id',
    'business_id',
    'total',
    'delivery_date',
    'delivery_time',
    'payment_method_id',
  ];

  public $timestamps = true;

  protected $casts = [
    'delivery_date' => 'date',
    'created_at'    => 'datetime',
    'updated_at'    => 'datetime',
  ];


  public function getRouteKeyName()
  {
    return 'order_id';
  }

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id', 'user_id');
  }

  public function business()
  {
    return $this->belongsTo(BusinessDetail::class, 'business_id', 'business_id');
  }

  public function paymentMethod()
  {
    return $this->belongsTo(PaymentMethod::class, 'payment_method_id', 'payment_method_id');
  }

  public function items()
  {
    return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
  }

  public function deliveryAddress()
  {
    return $this->hasOne(DeliveryAddress::class, 'order_id', 'order_id');
  }

  public function paymentDetails() // The name must match what you used: $order->paymentDetails
  {
    // Assumes the foreign key in 'payment_details' table is 'order_id'
    // Assumes the primary key in 'orders' table is 'order_id'
    return $this->hasMany(PaymentDetail::class, 'order_id', 'order_id');
  }

  public function review()
  {
    return $this->hasOne(Review::class, 'order_id', 'order_id');
  }

  public function preorderDetail()
  {
    return $this->hasOne(PreOrder::class, 'order_id', 'order_id');
  }

  protected static function booted(): void
  {
    static::deleting(function ($order) {
      // Delete related order items
      $order->orderItems()->delete();

      // Delete related payment details
      $order->paymentDetails()->delete();

      // Note: deliveryAddresses and preOrders have ON DELETE CASCADE in DB.
      // If they didn't, you would add:
      // $order->deliveryAddresses()->delete();
      // $order->preOrders()->delete();
    });
  }
}
