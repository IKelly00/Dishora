<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
  protected $table = 'payment_methods';
  protected $primaryKey = 'payment_method_id';

  protected $dateFormat = 'Y-m-d H:i:s.v';


  protected $fillable = [
    'method_name',
    'description',
    'status',
  ];


  public function orders()
  {
    return $this->hasMany(Order::class, 'payment_method_id');
  }

  public function business_details()
  {
    return $this->belongsToMany(BusinessDetail::class, 'business_payment_methods', 'payment_method_id', 'business_id');
  }
}
