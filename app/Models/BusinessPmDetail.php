<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessPmDetail extends Model
{
  use HasFactory;

  // Table name
  protected $table = 'business_pm_details';

  // Primary key
  protected $primaryKey = 'business_pm_details_id';

  protected $fillable = [
    'business_payment_method_id',
    'account_number',
    'account_name',
    'is_active',
  ];

  // Casts
  protected $casts = [
    'is_active' => 'boolean',
  ];

  /**
   * Relationships
   */
  public function paymentMethod()
  {
    return $this->belongsTo(BusinessPaymentMethod::class, 'business_payment_method_id', 'business_payment_method_id');
  }
}
