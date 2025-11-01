<?php

namespace App\Http\Controllers\Login;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\OrderSession;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
  public function loginForm(Request $request)
  {
    Log::info('Login form triggered. Checking session...', [
      'cart' => session('cart'),
      'active_role' => session('active_role')
    ]);

    // 1. Get cart/preorder data from the session
    $orders = [
      'cart' => session('cart', []),
      'preorder' => session('preorder', []),
    ];

    // 2. Get current user ID and session ID *before* logging out
    $userId = Auth::id();
    $sessionId = $request->session()->getId();

    // 3. Save cart to DB only if it's not empty
    if (!empty($orders['cart']) || !empty($orders['preorder'])) {

      if ($userId) {
        // User is authenticated. Save the cart against their user_id.
        // This allows retrieving it when they log in again.
        OrderSession::updateOrCreate(
          ['user_id' => $userId], // Find by user_id
          ['orders' => $orders, 'session_id' => $sessionId] // Update orders
        );
      } else {
        // User is a guest. Save the cart against their session_id.
        // You will need separate logic on login to merge this cart.
        OrderSession::updateOrCreate(
          ['session_id' => $sessionId], // Find by session_id
          ['orders' => $orders, 'user_id' => null] // Update orders
        );
      }
    }

    // 4. Perform a full logout and session invalidation
    // This is the "session refresh" you wanted.

    // Logs the user out of the application guard
    Auth::logout();

    // Invalidates the user's session, clearing all data (cart, active_role, etc.)
    $request->session()->invalidate();

    // Regenerates the CSRF token for the new guest session
    $request->session()->regenerateToken();

    // 5. Return the login view with "no-cache" headers
    // This prevents the browser from caching authenticated pages.

    $response = response()->view('content.login.login');

    return $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0')
      ->header('Pragma', 'no-cache')
      ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
  }

  public function login(Request $request)
  {
    // Validate input
    $request->validate([
      'login' => 'required|string',
      'password' => 'required|string',
      'remember' => 'nullable|boolean'
    ]);

    $credentials = $request->only('login', 'password');
    $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

    Log::info('LoginController triggered', ['login' => $credentials['login']]);

    $user = User::where($field, $credentials['login'])->first();

    if (! $user) {
      return back()->withErrors(['login' => 'The provided credentials do not match our records.'])->withInput();
    }

    // Safe diagnostic (no full hash log)
    $hash = $user->password ?? '';
    $len = strlen($hash);
    $prefix = substr($hash, 0, 4);
    Log::info('Found user for login', ['user_id' => $user->user_id, 'hash_prefix' => $prefix, 'len' => $len, 'field' => $field]);

    $plain = $credentials['password'];

    // --- 1) Try native password_verify (works for valid bcrypt / crypt hashes) ----
    // But first trim suspicious whitespace/newlines which often break format.
    $cleanHash = preg_replace('/\s+/u', '', $hash); // remove whitespace/newlines
    $cleanLen = strlen($cleanHash);

    if ($cleanHash !== $hash) {
      Log::warning('Password hash had whitespace/newline; using trimmed version for verification', ['user_id' => $user->user_id]);
    }

    // Attempt PHP's password_verify on the cleaned hash (this will return true/false, not throw)
    if ($cleanLen > 0 && password_verify($plain, $cleanHash)) {
      // Optionally rehash & save using Laravel's hasher (recommended).
      if (Hash::needsRehash($cleanHash)) {
        try {
          $user->password = Hash::make($plain);
          $user->save();
          Log::info('Password rehashed to current algorithm after successful verify', ['user_id' => $user->user_id]);
        } catch (\Exception $e) {
          Log::warning('Failed to save rehashed password (login will still proceed)', ['user_id' => $user->user_id, 'error' => $e->getMessage()]);
        }
      }

      Auth::login($user, $request->boolean('remember'));
      $request->session()->regenerate();
      Log::info('User logged in via password_verify fallback', ['user_id' => $user->user_id]);
      return $this->postLoginSuccess($request, $user);
    }

    // --- 2) Legacy check (example: md5) -----------------------------------------
    // If your old system used MD5 or SHA1, add checks here. Only enable the ones you used.
    // Example MD5:
    if (strlen($hash) === 32 && md5($plain) === $hash) {
      Log::warning('User authenticated via legacy MD5 hash. Consider rehashing.', ['user_id' => $user->user_id]);

      // Option: rehash to bcrypt now so next time normal Laravel flow works
      try {
        $user->password = Hash::make($plain);
        $user->save();
        Log::info('Legacy password rehashed to bcrypt', ['user_id' => $user->user_id]);
      } catch (\Exception $e) {
        Log::warning('Failed to save rehashed password for legacy user', ['user_id' => $user->user_id, 'error' => $e->getMessage()]);
      }

      Auth::login($user, $request->boolean('remember'));
      $request->session()->regenerate();
      return $this->postLoginSuccess($request, $user);
    }

    // --- 3) As a last attempt, try Laravel's normal attempt (may throw if hash malformed) ---
    // Wrap in try-catch to avoid runtime exceptions bubbling up.
    try {
      if (Auth::attempt([$field => $credentials['login'], 'password' => $plain], $request->boolean('remember'))) {
        $request->session()->regenerate();
        $user = Auth::user(); // refresh
        Log::info('User authenticated using Auth::attempt normal flow', ['user_id' => $user->user_id]);
        return $this->postLoginSuccess($request, $user);
      }
    } catch (\RuntimeException $e) {
      // For example: "This password does not use the Bcrypt algorithm."
      Log::warning('Auth::attempt threw exception; handled in fallback flow', [
        'user_id' => $user->user_id,
        'message' => $e->getMessage()
      ]);
      // We already tried password_verify and legacy checks. At this point we fail below.
    }

    // All checks failed
    return back()->withErrors([
      'login' => 'The provided credentials do not match our records.',
    ])->withInput();
  }

  /**
   * Shared post-login success handling extracted for readability.
   * Put your session/cart/role logic here.
   */
  protected function postLoginSuccess(Request $request, $user)
  {
    // Default role
    $activeRole = 'customer';
    if (!empty($user->username) && strtolower($user->username) === 'admin') {
      $activeRole = 'superadmin';
    }
    session(['active_role' => $activeRole]);

    $vendorStatus = optional($user->vendor)->registration_status;
    session(['vendorStatus' => $vendorStatus]);

    $roleSwitched = session('role_switched') === true;
    $showRolePopup = $vendorStatus === 'Approved' && !$roleSwitched && $activeRole === 'customer';
    session(['showRolePopup' => $showRolePopup]);

    // Restore cart + preorder
    $savedSession = OrderSession::where('user_id', $user->user_id)->first();
    if ($savedSession) {
      $orders = $savedSession->orders ?? [];
      session(['cart' => $orders['cart'] ?? [], 'preorder' => $orders['preorder'] ?? []]);
      $savedSession->delete();
    }

    $routeName = $activeRole . '.dashboard';
    if (!Route::has($routeName)) {
      $routeName = 'customer.dashboard';
    }

    return redirect()->route($routeName);
  }


  public function checkVendorStatus()
  {

    //Log::info('checkVendorStatus method');

    $user = Auth::user();

    if (!$user) {
      return response()->json([
        'vendorStatus' => 'Guest',
        'activeRole'   => null,
      ]);
    }

    // if user has no vendor record, return explicit 'None'
    $vendorStatus = optional($user->vendor)->registration_status ?? 'None';
    $activeRole = session('active_role') ?? 'customer';

    return response()->json([
      'status'       => 'ok',
      'vendorStatus' => $vendorStatus,   // 'Approved'|'Pending'|'Rejected'|'None'
      'currentRole'  => $activeRole,     // matches client expectation
    ]);
  }

  protected function credentials(Request $request)
  {
    $login = $request->input('login');
    $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

    return [
      $field => $login,
      'password' => $request->input('password'),
    ];
  }

  public function selectRole(Request $request)
  {
    $role = $request->input('role');
    session(['active_role' => $role]);

    return redirect()->route(strtolower($role) . '.dashboard');
  }

  public function logout(Request $request)
  {
    session()->forget('active_role');
    session()->forget('vendor_status_modal_shown');

    // Save both cart + preorder back into OrderSession.orders
    $orders = [
      'cart' => session('cart', []),
      'preorder' => session('preorder', []),
    ];

    if (!empty($orders['cart']) || !empty($orders['preorder'])) {
      OrderSession::updateOrCreate(
        ['user_id' => Auth::id() ?? null, 'session_id' => session()->getId()],
        ['orders' => $orders]
      );
    }

    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
  }
}
