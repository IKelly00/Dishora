<?php

namespace App\Http\Controllers\Register;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;

class RegisterController extends Controller
{
  public function registerForm()
  {
    return view('content.register.register');
  }

  public function register(Request $request)
  {
    $request->validate([
      'fullname' => 'required|string|max:255',
      'username' => 'required|string|max:255|unique:users,username',
      'email' => 'required|email|unique:users,email',
      'password' => 'required|string|min:8|confirmed',
      'user_address' => 'nullable|string|max:255',
      'contact_number' => 'nullable|string|max:20',
    ]);

    // Create the user
    $user = User::create([
      'fullname' => $request->fullname,
      'username' => $request->username,
      'email' => $request->email,
      'password' => Hash::make($request->password),
    ]);

    // Assign default role
    // $customerRole = Role::where('role_name', 'Customer')->first();
    // $user->roles()->attach($customerRole->role_id);

    // Create customer profile
    Customer::create([
      'user_id' => $user->user_id,
      'user_address' => $request->user_address ?? null,
      'contact_number' => $request->contact_number ?? null,
    ]);

    // Fire Laravel's email verification event
    event(new Registered($user));

    // Log in the user
    auth()->login($user);

    // Redirect to email verification notice
    return redirect()->route('verification.notice');
  }
}
