<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessPaymentMethod extends Model
{
  use HasFactory;

  protected $table = 'business_payment_methods';
  protected $primaryKey = 'business_payment_method_id';

  protected $fillable = [
    'business_id',
    'payment_method_id',
  ];

  public function details()
  {
    // Assumes App\Models\BusinessPmDetail model exists
    // This links one pivot record to one detail record.
    return $this->hasOne(BusinessPmDetail::class, 'business_payment_method_id', 'business_payment_method_id');
  }

  /**
   * Relationship: Has many BusinessPmDetail records.
   */
  public function businessPmDetails()
  {
    // Assumes App\Models\BusinessPmDetail model exists
    return $this->hasMany(BusinessPmDetail::class, 'business_payment_method_id', 'business_payment_method_id');
  }

  // Optional: Relationships back to parent tables
  public function businessDetail()
  {
    return $this->belongsTo(BusinessDetail::class, 'business_id', 'business_id');
  }
  public function paymentMethod()
  {
    return $this->belongsTo(PaymentMethod::class, 'payment_method_id', 'payment_method_id');
  }

  protected static function booted(): void
  {
    static::deleting(function ($pivotRecord) {
      // Delete related BusinessPmDetail records
      // Mass delete is okay here if BusinessPmDetail has no further dependencies
      $pivotRecord->businessPmDetails()->delete();
    });
  }
}
