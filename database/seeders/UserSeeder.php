<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
  public function run(): void
  {
    /* User::updateOrCreate(
      ['username' => 'johndoe'],
      [
        'fullname' => 'John Doe',
        'email' => 'john@example.com',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'is_verified' => true,
        'verification_token' => null,
      ]
    ); */

    User::updateOrCreate(
      ['username' => 'janedoe'],
      [
        'fullname' => 'Jane Doe',
        'email' => 'jane@example.com',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'is_verified' => true,
        'verification_token' => null,
      ]
    );

    User::updateOrCreate(
      ['username' => 'janejane'],
      [
        'fullname' => 'Jane Jane',
        'email' => 'janejane@example.com',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'is_verified' => true,
        'verification_token' => null,
      ]
    );
  }
}
