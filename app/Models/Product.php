<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
  protected $table = 'products';

  protected $primaryKey = 'product_id';

  protected $fillable = [
    'business_id',
    'product_category_id',
    'item_name',
    'price',
    'cutoff_minutes',
    'is_available',
    'is_pre_order',
    'advance_amount',
    'image_url',
    'description'
  ];

  protected $casts = [
    'is_available' => 'boolean',
    'is_pre_order' => 'boolean',
    'price' => 'decimal:2',
  ];

  public function business()
  {
    return $this->belongsTo(BusinessDetail::class, 'business_id');
  }

  public function category()
  {
    return $this->belongsTo(ProductCategory::class, 'category_id', 'category_id');
  }

  public function dietarySpecifications()
  {
    return $this->belongsToMany(DietarySpecification::class, 'product_dietary_specifications', 'product_id', 'dietary_specification_id');
  }

  public function orderItems()
  {
    return $this->hasMany(OrderItem::class, 'product_id', 'product_id');
  }

  public static function getCutoffOptions(): array
  {
    return [
      '' => 'No Cutoff (Order anytime)',
      '30' => '30 Minutes',
      '60' => '1 Hour',
      '90' => '1 Hour 30 Minutes',
      '120' => '2 Hours',
      '180' => '3 Hours',
      '240' => '4 Hours',
      '300' => '5 Hours',
      '360' => '6 Hours',
    ];
  }

  protected static function booted(): void
  {
    static::deleting(function ($product) {
      // Delete related order items (can be mass-deleted)
      $product->orderItems()->delete();

      // Detach dietary specifications (many-to-many needs detach)
      $product->dietarySpecifications()->detach();
    });
  }
}
