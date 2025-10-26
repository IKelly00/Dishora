<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
  public function run()
  {
    $methods = [
      [
        'method_name' => 'GCash',
        'description' => 'Pay using GCash mobile wallet',
        'status'      => 'active',
      ],
      [
        'method_name' => 'Maya',
        'description' => 'Pay via Maya (formerly PayMaya)',
        'status'      => 'active',
      ],
      [
        'method_name' => 'Cash on Delivery',
        'description' => 'Pay with cash upon delivery',
        'status'      => 'active',
      ],
      [
        'method_name' => 'Credit/Debit Card',
        'description' => 'Pay using Visa, Mastercard, or JCB',
        'status'      => 'active',
      ],
    ];

    foreach ($methods as $method) {
      PaymentMethod::updateOrCreate(
        ['method_name' => $method['method_name']],
        $method
      );
    }
  }
}
