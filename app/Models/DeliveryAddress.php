<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAddress extends Model
{
  protected $primaryKey = 'delivery_address_id';

  protected $fillable = [
    'order_id',
    'user_id',
    'phone_number',
    'region',
    'province',
    'city',
    'barangay',
    'postal_code',
    'street_name',
    'full_address',
  ];

  public function order()
  {
    return $this->belongsTo(Order::class, 'order_id');
  }
}
