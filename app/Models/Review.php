<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
  protected $table = 'reviews';
  protected $primaryKey = 'review_id';
  public $timestamps = true;

  protected $fillable = [
    'customer_id',
    'business_id',
    'rating',
    'comment',
  ];

  public function customer()
  {
    return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
  }

  public function business()
  {
    return $this->belongsTo(BusinessDetail::class, 'business_id', 'business_id');
  }

  public function user()
  {
    return $this->hasOneThrough(
      User::class,
      Customer::class,
      'customer_id', // Foreign key on customers table
      'user_id',     // Foreign key on users table
      'customer_id', // Local key on reviews table
      'user_id'      // Local key on customers table
    );
  }
}
