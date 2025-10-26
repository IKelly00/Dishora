<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessOpeningHour extends Model
{
  use HasFactory;

  protected $table = 'business_opening_hours';
  protected $primaryKey = 'business_opening_hours_id';

  protected $dateFormat = 'Y-m-d H:i:s.v';


  protected $fillable = [
    'business_id',
    'day_of_week',
    'opens_at',
    'closes_at',
    'is_closed'
  ];
}
