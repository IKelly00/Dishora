<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
  protected $table = 'notification_deliveries';

  protected $primaryKey = 'delivery_id';
  public $timestamps = false;

  protected $fillable = [
    'notification_id',
    'provider',
    'provider_response',
    'success',
    'attempted_at',
  ];

  protected $casts = [
    'success' => 'boolean',
    'attempted_at' => 'datetime',
  ];

  // Each delivery belongs to one notification
  public function notification()
  {
    return $this->belongsTo(Notification::class, 'notification_id', 'notification_id');
  }
}
