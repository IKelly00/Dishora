<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;

class ProfileController extends Controller
{
  public function editProfile()
  {
    $vendor = User::where('user_id', auth()->user_id())->with('user')->firstOrFail();
    return view('customer.customer.profile', compact('vendor'));
  }

  public function updateProfile(Request $request)
  {
    $request->validate([
      'fullname' => 'required|string|max:150',
      'phone_number' => 'nullable|string|max:20',
    ]);

    $vendor = User::where('user_id', auth()->user_id())->firstOrFail();
    $vendor->update($request->only('fullname', 'phone_number'));

    return redirect()->route('customer.profile.edit')->with('success', 'Profile updated successfully.');
  }
}
