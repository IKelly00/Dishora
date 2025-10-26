<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreorderSchedule extends Model
{
  use HasFactory;
  protected $table = 'preorder_schedule';
  protected $primaryKey = 'schedule_id';

  protected $fillable = [
    'business_id',
    'available_date',
    'max_orders',
    'current_order_count',
    'is_active'
  ];

  public function orders()
  {
    return $this->hasMany(Order::class, 'business_id', 'business_id');
  }
}
