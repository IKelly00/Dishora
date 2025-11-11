<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
  protected $table = 'device_tokens';

  protected $primaryKey = 'device_token_id';
  public $timestamps = false;
  protected $fillable = ['user_id', 'provider', 'token', 'platform', 'last_seen', 'is_active', 'created_at'];
  public function user()
  {
    return $this->belongsTo(User::class, 'user_id', 'user_id');
  }
}
