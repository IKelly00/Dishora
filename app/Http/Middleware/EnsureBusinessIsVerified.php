<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class EnsureBusinessIsVerified
{
  public function handle($request, Closure $next)
  {
    $user = Auth::user();
    $vendor = $user->vendor ?? null;
    $activeBusinessId = Session::get('active_business_id');

    if ($vendor && $activeBusinessId) {
      $business = $vendor->businessDetails()->where('business_id', $activeBusinessId)->first();

      if ($business && $business->verification_status === 'Pending') {
        return redirect()->route('vendor.dashboard');
      }
    }

    return $next($request);
  }
}
