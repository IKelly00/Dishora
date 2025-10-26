<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = DB::table('roles')->pluck('role_id', 'role_name');

        $userRoles = [
            'johndoe' => 'Customer',
            'adminjane' => 'Admin',
            'vendoruser' => 'Vendor',
        ];

        foreach ($userRoles as $username => $roleName) {
            $user = User::where('username', $username)->first();

            if ($user && isset($roles[$roleName])) {
                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $user->user_id, 'role_id' => $roles[$roleName]]
                );
            }
        }
    }
}
