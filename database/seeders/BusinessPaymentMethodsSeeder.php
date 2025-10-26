<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessPaymentMethodsSeeder extends Seeder
{
  public function run(): void
  {
    // Make sure business_id=1 exists in business_details
    // and payment_method_id 3,4,6 exist in payment_methods before running this.

    $rows = [
      [
        'business_id' => 1,
        'payment_method_id' => 1,
        'created_at' => '2025-09-07 15:23:17.730',
        'updated_at' => '2025-09-07 15:23:17.730',
      ],
      [
        'business_id' => 1,
        'payment_method_id' => 4,
        'created_at' => '2025-09-07 15:23:17.967',
        'updated_at' => '2025-09-07 15:23:17.967',
      ],
      [
        'business_id' => 1,
        'payment_method_id' => 4,
        'created_at' => '2025-09-07 15:23:18.200',
        'updated_at' => '2025-09-07 15:23:18.200',
      ],
    ];

    // Idempotent: re-running won't create duplicates due to the unique index
    foreach ($rows as $row) {
      DB::table('business_payment_methods')->updateOrInsert(
        [
          'business_id' => $row['business_id'],
          'payment_method_id' => $row['payment_method_id'],
        ],
        [
          'created_at' => $row['created_at'],
          'updated_at' => $row['updated_at'],
        ]
      );
    }
  }
}
