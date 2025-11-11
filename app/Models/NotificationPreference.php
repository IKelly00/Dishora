<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
  protected $table = 'notification_preferences';


  protected $primaryKey = 'preference_id';
  public $timestamps = false;
  protected $fillable = ['user_id', 'event_type', 'channel', 'enabled', 'updated_at'];
  public function user()
  {
    return $this->belongsTo(User::class, 'user_id', 'user_id');
  }
}
