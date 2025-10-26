<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
  protected $primaryKey = 'vendor_id';

  protected $dateFormat = 'Y-m-d H:i:s.v';


  protected $fillable = [
    'user_id',
    'fullname',
    'phone_number',
    'registration_status',
  ];


  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function businessDetails()
  {
    return $this->hasMany(BusinessDetail::class, 'vendor_id');
  }

  protected static function booted(): void
  {
    static::deleting(function ($vendor) {
      // Use ->each to trigger deleting events on each BusinessDetail
      $vendor->businessDetails()->each(function ($business) {
        $business->delete();
      });
    });
  }
}
