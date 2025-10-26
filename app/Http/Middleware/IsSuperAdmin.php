<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsSuperAdmin
{
  public function handle(Request $request, Closure $next)
  {
    // Get the authenticated user for the 'superadmin' guard
    $user = $request->user('superadmin');

    // If no user or not the THE super admin, deny access
    if (! $user || ! $user->isTheSuperAdmin()) {
      abort(403, 'ACCESS DENIED');
    }

    return $next($request);
  }
}
