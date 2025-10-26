<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DietarySeeder extends Seeder
{
  public function run()
  {
    $specifications = [
      'Vegetarian',
      'Vegan',
      'Pescatarian',
      'Keto',
      'Paleo',
      'Gluten-free',
      'Dairy-free',
      'Low-Carb',
      'Low-fat',
      'Low-Sodium',
      'Low-Calorie',
      'Moderate-Calorie',
      'High-Calorie',
      'Meal-Type',
      'Weight-Loss',
      'Weight-Gain',
      'Keto-Friendly',
      'Halal Registered',
    ];

    foreach ($specifications as $spec) {
      DB::table('dietary_specifications')->insert([
        'dietary_spec_name' => $spec,
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    }
  }
}
