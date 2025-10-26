<?php

namespace App\Http\Controllers\role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class RoleController extends Controller
{
  public function switchRole(Request $request)
  {
    $targetRole = strtolower($request->input('target_role'));

    if (!in_array($targetRole, ['customer', 'vendor'])) {
      return back()->with('error', 'Invalid role.');
    }

    // Update active role in session
    session([
      'active_role' => $targetRole,
      'role_switched' => true,   // mark as switched
      'showRolePopup' => false,  // reset popup immediately
    ]);

    Log::info('Role switched', ['target_role' => $targetRole]);

    Log::info('After role switch, session snapshot', [
      'session_id'   => session()->getId(),
      'all_session'  => session()->all(),
    ]);
    // Redirect to dashboard
    $routeName = $targetRole . '.dashboard';
    if (!Route::has($routeName)) {
      $routeName = 'customer.dashboard';
    }

    return redirect()->route($routeName)->with('success', 'Role switched to ' . ucfirst($targetRole));
  }



  public function selectRole(Request $request)
  {
    $role = $request->input('role');
    session(['active_role' => $role]);

    // Mark that user has switched roles so modal doesn't show again
    session(['role_switched' => true]);

    $routeName = $role . '.dashboard';
    if (!Route::has($routeName)) {
      $routeName = 'customer.dashboard'; // fallback
    }

    return redirect()->route($routeName);
  }

  public function switchRolePopupReset(Request $request)
  {
    if ($request->reset) {
      session()->forget('showRolePopup');
      return response()->json(['status' => 'success']);
    }
    return response()->json(['status' => 'nothing']);
  }
}
