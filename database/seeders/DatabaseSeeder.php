<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
  /**
   * Seed the application's database.
   */
  public function run(): void
  {
    $this->call([
      RoleSeeder::class,
      //UserSeeder::class,
      //CustomerSeeder::class,
      //VendorSeeder::class,
      //UserRoleSeeder::class,
      ProductCategorySeeder::class,
      DietarySeeder::class,
      PaymentMethodSeeder::class,
      //BusinessPaymentMethodsSeeder::class,
      SuperAdminUserSeeder::class
    ]);
  }
}
