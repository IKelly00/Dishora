<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCategory;

class ProductCategorySeeder extends Seeder
{
  public function run()
  {
    $categories = [
      'Solo',
      'Family Bundle',
      'Bulk Orders',
    ];

    foreach ($categories as $name) {
      ProductCategory::firstOrCreate(['category_name' => $name]);
    }
  }
}
