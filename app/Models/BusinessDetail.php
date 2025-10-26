<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessDetail extends Model
{
  use HasFactory;

  protected $table = 'business_details';
  protected $primaryKey = 'business_id';


  protected $dateFormat = 'Y-m-d H:i:s.v';

  protected $fillable = [
    'vendor_id',
    'business_image',
    'business_name',
    'business_description',
    'business_type',
    'business_location',
    'opening_hours',
    'valid_id_type',
    'valid_id_no',
    'business_permit_no',
    'bir_reg_no',
    'business_permit_file',
    'valid_id_file',
    'bir_reg_file',
    'mayor_permit_file',
    'latitude',
    'longitude',
    'business_duration',
    'verification_status',
    'remarks',
    'uploaded_at',
  ];


  public function openingHours()
  {
    return $this->hasMany(BusinessOpeningHour::class, 'business_id', 'business_id');
  }

  public function paymentMethods()
  {
    return $this->belongsToMany(
      PaymentMethod::class,
      'business_payment_methods',   // pivot table
      'business_id',                // FK on pivot pointing to BusinessDetail
      'payment_method_id',          // FK on pivot pointing to PaymentMethod
      'business_id',                // Local key on BusinessDetail
      'payment_method_id'           // Local key on PaymentMethod
    )->where('payment_methods.status', 'active');
  }

  public function products()
  {
    return $this->hasMany(Product::class, 'business_id', 'business_id');
  }

  public function preorderSchedule()
  {
    return $this->hasMany(PreorderSchedule::class, 'business_id', 'business_id');
  }

  public function vendor()
  {
    return $this->belongsTo(\App\Models\Vendor::class, 'vendor_id', 'vendor_id');
  }

  public function orders()
  {
    return $this->hasMany(Order::class, 'business_id', 'business_id');
  }

  public function reviews()
  {
    return $this->hasMany(Review::class, 'business_id', 'business_id');
  }

  public function businessPaymentMethods()
  {
    // Assumes you have a model App\Models\BusinessPaymentMethod for the pivot table
    return $this->hasMany(BusinessPaymentMethod::class, 'business_id', 'business_id');
  }

  protected static function booted(): void
  {
    static::deleting(function ($business) {
      // Trigger deleting event for each Product
      $business->products()->each(function ($product) {
        $product->delete();
      });

      // Trigger deleting event for each Order
      $business->orders()->each(function ($order) {
        $order->delete();
      });

      // Reviews (mass-delete OK if no further dependencies)
      $business->reviews()->delete();

      // *** Explicitly trigger delete for BusinessPaymentMethod pivot records ***
      // This ensures the deleting event on BusinessPaymentMethod model fires
      $business->businessPaymentMethods()->each(function ($pivotRecord) {
        $pivotRecord->delete();
      });

      // Other relations with DB cascade (openingHours, preorderSchedule)
      // No explicit action needed IF DB cascade is reliable.
      // If you still face issues, you might need to delete them explicitly too:
      // $business->openingHours()->delete();
      // $business->preorderSchedule()->delete();
    });
  }
}
