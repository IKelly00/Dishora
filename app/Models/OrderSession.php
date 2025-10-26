<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSession extends Model
{
  use HasFactory;

  protected $table = 'order_sessions';
  protected $primaryKey = 'order_session_id';

  protected $fillable = [
    'user_id',
    'session_id',
    'orders',
  ];

  protected $casts = [
    'orders' => 'array',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
