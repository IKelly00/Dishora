<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class CustomerSeeder extends Seeder
{
  public function run(): void
  {
    // Fetch all users except the admin username/email
    $users = User::where('username', '!=', 'adminjane')->get();

    foreach ($users as $user) {
      DB::table('customers')->updateOrInsert(
        ['user_id' => $user->user_id],
        [
          'user_address' => 'Default Address for ' . $user->fullname,
          'latitude' => 13.614417150000001,
          'longitude' => 123.20315781102568,
          'contact_number' => '000-000-0000',
        ]
      );
    }
  }
}
