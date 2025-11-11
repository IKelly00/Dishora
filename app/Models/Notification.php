<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
  protected $table = 'notifications';
  protected $primaryKey = 'notification_id';
  public $timestamps = false;

  protected $fillable = [
    'user_id',
    'actor_user_id',
    'event_type',
    'reference_table',
    'reference_id',
    'payload',
    'is_read',
    'channel',
    'created_at',
    'expires_at',
    'business_id',
    'recipient_role',
    'is_global',
  ];

  protected $casts = [
    'is_read' => 'boolean',
    'is_global' => 'boolean',
    'created_at' => 'datetime',
    'expires_at' => 'datetime',
  ];

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id', 'user_id');
  }
}
