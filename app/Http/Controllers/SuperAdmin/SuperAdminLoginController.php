<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log};

class SuperAdminLoginController extends Controller
{
  public function showLoginForm()
  {
    if (Auth::guard('superadmin')->check()) {
      return redirect()->route('super-admin.dashboard');
    }
    return view('content.superadmin.superadmin-login');
  }

  public function login(Request $request)
  {
    $credentials = $request->validate([
      'email' => 'required|email',
      'password' => 'required',
    ]);

    if (Auth::guard('superadmin')->attempt($credentials)) {

      /** @var \App\Models\User|null $user */
      $user = Auth::guard('superadmin')->user();
      Log::info([
        'user_id_raw' => $user->user_id,
        'user_id_int' => (int) $user->user_id,
        'username' => $user->username,
        'isTheSuperAdmin' => $user->isTheSuperAdmin(),
      ]);

      // Check if the logged-in user is THE super admin.
      if ($user->isTheSuperAdmin()) {
        $request->session()->regenerate();
        return redirect()->intended(route('super-admin.dashboard'));
      }

      // If not the super admin, kick them out immediately.
      Auth::guard('superadmin')->logout();
      return back()->withErrors([
        'email' => 'You do not have permission to access this area.',
      ]);
    }

    return back()->withErrors([
      'email' => 'The provided credentials do not match our records.',
    ]);
  }

  public function logout(Request $request)
  {
    Auth::guard('superadmin')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('super-admin.login'); // now matches the GET route name
  }
}
