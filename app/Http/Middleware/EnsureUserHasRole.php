<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, Auth};

class EnsureUserHasRole
{
  public function handle(Request $request, Closure $next, $role)
  {
    Log::info('Role middleware check', [
      'session_id'         => session()->getId(),
      'expected_role'      => $role,
      'session_active_role' => session('active_role')
    ]);

    $activeRole = strtolower(session('active_role', 'customer'));

    if ($activeRole !== $role) {
      // Optional: log unauthorized access
      Log::warning('Blocked access to ' . $role . ' route', [
        'activeRole' => $activeRole,
        'attemptedRoute' => $request->path()
      ]);

      // Redirect instead of aborting
      return redirect()->route($activeRole . '.dashboard')
        ->with('error', 'You are not authorized to access that section.');
    }

    return $next($request);
  }
}
