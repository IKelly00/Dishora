<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SuperAdminUserSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // Check if the admin user already exists to prevent errors on re-seeding
    if (User::find(1)) {
      $this->command->info('Super Admin user already exists.');
      return;
    }

    // Temporarily allow identity insert for the users table (SQL Server specific)
    DB::unprepared('SET IDENTITY_INSERT [dbo].[users] ON;');

    // Create the Super Admin User with user_id = 1
    User::create([
      'user_id' => 1,
      'username' => 'admin',
      'fullname' => 'Super Administrator',
      'email' => 'superadmin@dishora.com',
      'password' => Hash::make('password'), // CHANGE THIS to a secure password!
      'email_verified_at' => now(),
      'is_verified' => 1,
    ]);

    // Disable identity insert
    DB::unprepared('SET IDENTITY_INSERT [dbo].[users] OFF;');

    $this->command->info('Super Admin user created successfully with user_id = 1.');
  }
}
