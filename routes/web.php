<?php
// routes/web.php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;

use App\Http\Controllers\Notifications\NotificationController;

// === Models ===
use App\Models\User;

// === Controllers ===
use App\Http\Controllers\Login\LoginController;
use App\Http\Controllers\Register\RegisterController;

// Dashboard Controllers
use App\Http\Controllers\dashboard\AdminDashboardController;

// Vendor Controllers
use App\Http\Controllers\vendor\VendorMenuController;
use App\Http\Controllers\vendor\MenuController;
use App\Http\Controllers\vendor\PreorderOrderController;
use App\Http\Controllers\vendor\PendingOrderController;
use App\Http\Controllers\vendor\VendorScheduleController;
use App\Http\Controllers\vendor\VendorReviewController;
use App\Http\Controllers\vendor\VendorBusinessDetail;


// Customer Controllers
use App\Http\Controllers\customer\CustomerMenuController;
use App\Http\Controllers\customer\StartSellingController;
use App\Http\Controllers\customer\CustomerLocationController;
use App\Http\Controllers\customer\ProfileController;
use App\Http\Controllers\customer\CartController;
use App\Http\Controllers\customer\PreorderController;
use App\Http\Controllers\customer\CheckoutCartController;
use App\Http\Controllers\customer\CheckoutPreorderController;
use App\Http\Controllers\customer\OrdersController;
use App\Http\Controllers\customer\OrderHistoryController;
use App\Http\Controllers\customer\CustomerScheduleController;
use App\Http\Controllers\customer\ReviewController;
use App\Http\Controllers\customer\CustomerProfileController;

//Super admin Controllers
use App\Http\Controllers\SuperAdmin\SuperAdminLoginController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\BusinessController;
use App\Http\Controllers\SuperAdmin\SuperAdminUserController;
use App\Http\Controllers\SuperAdmin\SuperAdminVendorController;

// Payment Controllers
use App\Http\Controllers\payment\PaymentController;
use App\Http\Controllers\payment\PayMongoWebhookController;

// Role Controller
use App\Http\Controllers\role\RoleController;

// Password Reset Controllers
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;


// ====================================================================
// === AUTHENTICATION ROUTES (LOGIN / REGISTER / LOGOUT) ==============
// ====================================================================

// Login routes
Route::get('/', [LoginController::class, 'loginForm']);
Route::get('/login', [LoginController::class, 'loginForm'])->name('loginForm');
Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
Route::post('/select-role', [LoginController::class, 'selectRole'])->name('select.role');

// Register routes
Route::get('/register', [RegisterController::class, 'registerForm'])->name('registerForm');
Route::post('/register', [RegisterController::class, 'register'])->name('register');

// ---------------------------------------------------------------
// === SUPER ADMIN ROUTES ============================================
// ---------------------------------------------------------------
Route::prefix('super-admin')->name('super-admin.')->group(function () {

  // Login routes
  Route::get('/login', [SuperAdminLoginController::class, 'showLoginForm'])->name('login');
  Route::post('/login', [SuperAdminLoginController::class, 'login'])->name('login.post');
  Route::post('/logout', [SuperAdminLoginController::class, 'logout'])->name('logout');

  // Protected routes
  Route::middleware(['auth:superadmin', 'is.superadmin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Businesses CRUD
    Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
    Route::get('/business/{id}', [BusinessController::class, 'show'])->name('business.view');
    Route::get('/business/{id}/edit', [BusinessController::class, 'edit'])->name('business.edit');
    Route::put('/business/{id}', [BusinessController::class, 'update'])->name('business.update');
    Route::delete('/business/{id}', [BusinessController::class, 'destroy'])->name('business.destroy');

    // Approve / Reject (POST)
    Route::post('/business/{id}/approve', [BusinessController::class, 'approve'])->name('business.approve');
    Route::post('/business/{id}/reject', [BusinessController::class, 'reject'])->name('business.reject');

    // Users CRUD
    Route::get('/users', [SuperAdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [SuperAdminUserController::class, 'create'])->name('users.create');
    Route::post('/users', [SuperAdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [SuperAdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [SuperAdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [SuperAdminUserController::class, 'destroy'])->name('users.destroy');

    Route::get('/users/{user}', [SuperAdminUserController::class, 'show'])->name('users.show');

    Route::get('/vendors', [SuperAdminVendorController::class, 'index'])->name('vendors.index');
    Route::get('/vendor/{id}', [SuperAdminVendorController::class, 'show'])->name('vendor.view');
    Route::post('/vendor/{id}/approve', [SuperAdminVendorController::class, 'approveRegistration'])->name('vendor.approve_reg');
    Route::post('/vendor/{id}/reject', [SuperAdminVendorController::class, 'rejectRegistration'])->name('vendor.reject_reg');
    Route::delete('/vendor/{id}', [SuperAdminVendorController::class, 'destroy'])->name('vendor.destroy');
  });
});


// ====================================================================
// === AUTH + VERIFIED ROUTES (PROTECTED AREA) ========================
// ====================================================================

Route::middleware(['auth', 'verified'])->group(function () {

  // === ADMIN DASHBOARD ===
  Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

  // ---------------------------------------------------------------
  // === CUSTOMER ROUTES ==========================================
  // ---------------------------------------------------------------
  Route::middleware('role:customer')->group(function () {

    // Dashboard
    Route::get('/customer/dashboard', [CustomerMenuController::class, 'index'])->name('customer.dashboard');

    // API: Get products by category
    Route::get('/api/products/by-category', [CustomerMenuController::class, 'getProductsByCategory'])->name('api.products.by-category');

    // Start Selling
    Route::get('/customer/start-selling', [CustomerMenuController::class, 'customerStartSelling'])->name('customer.start.selling');
    Route::post('/customer/start-selling', [StartSellingController::class, 'store'])->name('customer.start.selling.store');

    // Profile Management
    Route::get('/customer/profile', [ProfileController::class, 'editProfile'])->name('customer.profile.edit');
    Route::put('/customer/profile', [ProfileController::class, 'updateProfile'])->name('customer.profile.update');

    // Vendor & Business Selection
    Route::get('/customer/selected-business/{businessId}/{vendorId}', [CustomerMenuController::class, 'selectedBusiness'])->name('customer.selectedBusiness');
    Route::get('/customer/vendors', [CustomerMenuController::class, 'vendors'])->name('customer.vendors');

    // Cart
    Route::get('/customer/cart', [CartController::class, 'showCart'])->name('customer.cart');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
    Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/restore-draft', [CartController::class, 'restoreDraft'])->name('cart.restoreDraft');

    // Checkout
    Route::get('/checkout/proceed/{business_id}', [CheckoutCartController::class, 'proceed'])->name('checkout.proceed');
    Route::post('/checkout', [CheckoutCartController::class, 'store'])->name('checkout.store');
    Route::post('/checkout/finalize', [PaymentController::class, 'finalizeFlow'])->name('checkout.finalize');

    // Preorder
    Route::get('/customer/preorder', [PreorderController::class, 'showPreorder'])->name('customer.preorder');
    Route::post('/preorder/add', [PreorderController::class, 'add'])->name('preorder.add');
    Route::post('/preorder/update', [PreorderController::class, 'update'])->name('preorder.update');
    Route::post('/preorder/remove', [PreorderController::class, 'remove'])->name('preorder.remove');
    Route::get('/checkout/preorder/proceed/{business_id}', [CheckoutPreorderController::class, 'proceed'])->name('checkout.preorder.proceed');
    Route::post('/checkout/preorder/store', [CheckoutPreorderController::class, 'store'])->name('checkout.preorder.store');
    Route::get('/preorder-schedule/{business}/availability', [CheckoutPreorderController::class, 'getAvailability'])->name('preorder.availability');
    Route::post('/preorder/{order}/confirm', [CheckoutPreorderController::class, 'confirmPreorder'])->name('preorder.confirm');
    Route::get('/upload/receipt/{order_id}', [CheckoutPreorderController::class, 'uploadReceipt'])->name('preorder.receipt');

    // Orders
    Route::get('/customer/orders', [OrdersController::class, 'index'])->name('customer.orders.index');
    Route::post('/orders/cancel/{order_id}', [OrdersController::class, 'cancel'])->name('orders.cancel');
    Route::get('/customer/order-history', [OrderHistoryController::class, 'index'])->name('customer.orderhist.index');
    Route::post('/orders/cancel/{order_id}', [CheckoutCartController::class, 'cancelOrder'])->name('orders.cancel');

    // Schedule & Vendor Modal
    Route::post('/dismiss-vendor-status-modal', [CustomerMenuController::class, 'dismissStatusModal'])->name('dismiss.vendor.status.modal');
    Route::get('/api/customer-schedule/{business_id}/availability', [CustomerScheduleController::class, 'getAvailability'])->name('api.customer.schedule.availability');

    // Review
    Route::post('/feedback/store', [ReviewController::class, 'store'])->name('feedback.store');
    Route::get('/feedback/{business_id}', [ReviewController::class, 'index'])->name('feedback.index');

    //Messages
    Route::get('/customer/messages', [CustomerMenuController::class, 'messages'])->name('customer.messages');
    Route::get('/customer/messages/thread/{business_id}', [CustomerMenuController::class, 'getMessageThread'])->name('customer.messages.thread');
    Route::post('/messages/send', [CustomerMenuController::class, 'sendMessage'])->name('customer.messages.send');

    // Profile
    Route::get('/profile/edit', [CustomerProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/update', [CustomerProfileController::class, 'update'])->name('profile.update');
  });

  // ---------------------------------------------------------------
  // === VENDOR ROUTES ============================================
  // ---------------------------------------------------------------
  Route::middleware('role:vendor')->group(function () {

    // Dashboard & Business Setup
    Route::get('/vendor/dashboard', [VendorMenuController::class, 'index'])->name('vendor.dashboard');
    Route::post('/vendor/set-business', [VendorMenuController::class, 'setActiveBusiness'])->name('vendor.setBusiness');

    // Verified Business Only
    Route::middleware('verified.business')->group(function () {

      // Menu Management
      Route::get('/vendor/menu', [VendorMenuController::class, 'menu'])->name('vendor.menu');
      Route::get('/vendor/add-menu', [VendorMenuController::class, 'addMenu'])->name('vendor.add.menu');
      Route::get('/vendor/edit-menu/{id}', [VendorMenuController::class, 'editMenu'])->name('vendor.edit.menu');
      Route::post('/vendor/menu/store', [MenuController::class, 'store'])->name('vendor.menu.store');
      Route::put('/vendor/menu/update/{id}', [MenuController::class, 'update'])->name('vendor.menu.update');

      // Orders
      Route::get('/vendor/orders/cart', [PendingOrderController::class, 'activeOrders'])->name('vendor.orders-cart');
      Route::patch('/orders/{order}/status', [PendingOrderController::class, 'updateStatus'])->name('orders.updateStatus');
      Route::get('/orders/{order}', [PendingOrderController::class, 'show'])->name('orders.show');
      Route::get('/orders', [PendingOrderController::class, 'getAllOrders'])->name('orders.index');
      Route::get('/orders/status/{status}', [PendingOrderController::class, 'getOrdersByStatus'])->name('orders.by-status');
      Route::delete('/orders/{order}', [PendingOrderController::class, 'destroy'])->name('orders.destroy');

      // Preorders
      Route::get('/vendor/orders/preorder', [PreorderOrderController::class, 'preorderOrders'])->name('vendor.orders-preorder');
      Route::patch('/vendor/preorders/{id}/status', [PreorderOrderController::class, 'updateStatus'])->name('preorders.updateStatus');

      // Schedule Management
      Route::get('/vendor/schedule', [VendorScheduleController::class, 'index'])->name('vendor.schedule.index');
      Route::post('/vendor/schedule', [VendorScheduleController::class, 'store'])->name('vendor.schedule.store');
      Route::get('/vendor/schedule/events', [VendorScheduleController::class, 'getScheduleEvents'])->name('vendor.schedule.events');
      Route::delete('/vendor/schedule/{scheduleId}', [VendorScheduleController::class, 'destroy'])->name('vendor.schedule.destroy');

      Route::get('/vendor/feedback', [VendorReviewController::class, 'index'])->name('vendor.feedback.index');

      // Messages
      Route::get('vendor/messages', [VendorMenuController::class, 'messages'])->name('vendor.messages');
      Route::get('/vendor/messages/thread', [VendorMenuController::class, 'getMessageThread'])->name('vendor.messages.thread');
      Route::post('/vendor/messages/send', [VendorMenuController::class, 'sendMessage'])->name('vendor.messages.send');

      // Business Detail
      Route::get('/business/edit/{business_id?}', [VendorBusinessDetail::class, 'edit'])->name('vendor.business.edit');
      Route::post('/business/update/{business_id?}', [VendorBusinessDetail::class, 'update'])->name('vendor.business.update');
    });
  });


  // ---------------------------------------------------------------
  // === SHARED ROUTES (CUSTOMER & VENDOR) =========================
  // ---------------------------------------------------------------



  // Role Switching
  Route::post('/switch-role', [RoleController::class, 'switchRole'])->name('role.switch');
  Route::post('/set-role-popup', [RoleController::class, 'switchRolePopupReset'])->name('set.role.popup');
  Route::post('/set-role-popup', [RoleController::class, 'switchRolePopupReset'])->name('set.role.popup');

  // Vendor Status & Location
  Route::get('/vendor/status-check', [LoginController::class, 'checkVendorStatus'])->name('vendor.status.check');
  Route::get('/nearby-vendors', [CustomerLocationController::class, 'getNearbyLive']);
  Route::get('/nearby-vendors/stored', [CustomerLocationController::class, 'getNearbyFromStored']);
  Route::post('/log-live-location', [CustomerLocationController::class, 'logLiveLocation']);

  // Payment Routes
  Route::get('/paymongo/success/{type}', [PaymentController::class, 'success'])->name('paymongo.success');
  Route::get('/paymongo/failed/{type}/{draft_id?}', [PaymentController::class, 'failed'])->name('paymongo.failed');
  Route::get('/checkout/status', [CheckoutCartController::class, 'status'])->name('api.checkout.status');
  Route::get('/payment/callback/success', [PaymentController::class, 'singlePaymentSuccess'])->name('payment.callback.success');
  Route::get('/payment/callback/failed/{draft_id?}', [PaymentController::class, 'singlePaymentFailed'])->name('payment.callback.failed');
});


// routes/web.php
Route::middleware('auth')->group(function () {
  // returns paginated notifications (uses NotificationController@index)
  Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

  // returns { unread: N } (NotificationController@unreadCount)
  Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread_count');

  // mark single notification read
  Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.mark_read');

  // mark all read
  Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.mark_all_read');
});


// ====================================================================
// === PAYMONGO WEBHOOK ==============================================
// ====================================================================
Route::post('/webhook/paymongo', [PayMongoWebhookController::class, 'handle'])->name('paymongo.webhook');


// ====================================================================
// === EMAIL VERIFICATION ROUTES =====================================
// ====================================================================

// Show verification notice
Route::get('/email/verify', function (Request $request) {
  $request->user()->sendEmailVerificationNotification();
  return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

// Verify email manually
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
  Log::info('Manual verification attempt', [
    'full_url' => $request->fullUrl(),
    'query' => $request->getQueryString(),
  ]);

  $user = User::findOrFail($id);

  // 1) Validate hash
  if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
    Log::warning('Hash mismatch', ['id' => $id, 'given' => $hash]);
    abort(403, 'Invalid verification link.');
  }

  // 2) Auto-login verified user
  Auth::login($user);

  // 3) Check if user has customer account
  $hasAccount = DB::table('customers')->where('user_id', $user->user_id)->exists();

  if ($hasAccount) {
    // Prevent reusing verified link
    if ($user->hasVerifiedEmail()) {
      return redirect()->route('login')->with('error', 'Your email has already been verified. Please log in to continue.');
    } else {
      // Mark verified if not yet
      if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
      }
    }

    session(['active_role' => 'customer']);
    Log::info('Email verification set role', ['active_role' => 'customer']);
    return redirect()->route('customer.dashboard');
  }

  // 4) If no customer account found
  Log::warning('No customer account found for verified user', ['user_id' => $user->user_id]);
  return response()->view('verification.no_account');
})->name('verification.verify');

// Verification failure page
Route::get('/verification/failed', function () {
  return view('auth.no_account');
})->name('verification.no_account');

// Resend verification email
Route::post('/email/verification-notification', function (Request $request) {
  $request->user()->sendEmailVerificationNotification();
  return back()->with('status', 'verification-link-sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');


// ====================================================================
// === PASSWORD RESET ROUTES =========================================
// ====================================================================

// Forgot password form
Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
  ->middleware('guest')->name('password.request');

// Handle email submission
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
  ->middleware('guest')->name('password.email');

// Show reset form
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
  ->middleware('guest')->name('password.reset');

// Handle password reset
Route::post('/reset-password', [NewPasswordController::class, 'store'])
  ->middleware('guest')->name('password.update');


Route::get('/test-vendor/{businessId}', function ($businessId) {
  $vendorId = App\Models\BusinessDetail::find($businessId)->vendor_id;
  $userId = App\Models\Vendor::find($vendorId)->user_id;

  return response()->json([
    'vendor_id' => $vendorId,
    'userId' => $userId,
    'business_found' => $vendorId ? true : false
  ]);
});
