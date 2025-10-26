<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDetail extends Model
{
  protected $table = 'payment_details';
  protected $primaryKey = 'payment_detail_id';

  protected $fillable = [
    'payment_method_id',
    'order_id',
    'transaction_id',
    'amount_paid',
    'payment_status',
    'payment_reference',
    'paid_at',
  ];

  protected $casts = [
    'amount_paid' => 'decimal:2',
    'paid_at' => 'datetime',
  ];

  public function order()
  {
    return $this->belongsTo(Order::class, 'order_id', 'order_id');
  }

  public function paymentMethod()
  {
    return $this->belongsTo(PaymentMethod::class, 'payment_method_id', 'payment_method_id');
  }
}
