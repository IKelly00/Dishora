<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\{BusinessDetail, Customer, PaymentMethod, ProductCategory, Vendor, Product, Message, User};
use App\Services\VendorLocatorService;
use Illuminate\Support\Facades\{Auth, Log, DB};
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Events\MessageSent;

class CustomerMenuController extends Controller
{
  public function __construct(protected VendorLocatorService $locator) {}

  private function getVendor(): ?Vendor
  {
    return Auth::user()?->vendor;
  }

  /**
   * Resolve the active business and related status flags for a vendor.
   */
  private function resolveBusinessContext(Vendor $vendor): array
  {
    $vendorStatus = $vendor->registration_status ?? null;

    $activeBusinessId = session('active_business_id');

    // Auto-select first business if none chosen yet
    if (!$activeBusinessId && $vendor->businessDetails()->exists()) {
      $activeBusinessId = $vendor->businessDetails()
        ->orderBy('business_id')
        ->value('business_id');

      session(['active_business_id' => $activeBusinessId]);
      Log::info('Auto-selected first business', compact('activeBusinessId'));
    }

    $business = $vendor->businessDetails()
      ->where('business_id', $activeBusinessId)
      ->first();

    $businessStatus = $business?->verification_status ?? 'Unknown';
    $showVerificationModal = $businessStatus === 'Pending';

    // Reset "vendor status modal shown" when status first becomes Pending
    if ($vendorStatus === 'Pending' && session('last_vendor_status') !== 'Pending') {
      session(['vendor_status_modal_shown' => false]);
    }

    session(['last_vendor_status' => $vendorStatus]);

    if (!$business) {
      Log::warning('Business not found for vendor', compact('activeBusinessId'));
    } else {
      Log::info('Resolved businessStatus', compact('businessStatus'));
      Log::info('Show verification modal', compact('showVerificationModal'));
      Log::info('Vendor Status Session', ['vendor_status_modal_shown' => session('vendor_status_modal_shown')]);
    }

    return [
      'activeBusinessId'        => $activeBusinessId,
      'businessStatus'          => $businessStatus,
      'showVerificationModal'   => $showVerificationModal,
      'vendorStatus'            => $vendorStatus,
      'showVendorStatusModal'   => $vendorStatus === 'Pending',
      'showVendorRejectedModal' => $vendorStatus === 'Rejected',
    ];
  }

  /**
   * Build base view data depending on whether the user has a vendor profile.
   */
  private function buildViewData(?Vendor $vendor, array $extra = []): array
  {
    if (!$vendor) {
      return array_merge([
        'hasVendorAccess'         => false,
        'showRolePopup'           => true,
        'vendorStatus'            => session('vendorStatus', null),
        'showVendorStatusModal'   => session('vendorStatus') === 'Pending',
        'showVendorRejectedModal' => session('vendorStatus') === 'Rejected',
        'hasShownVendorModal'     => session('vendor_status_modal_shown', false),
      ], $extra);
    }

    return array_merge([
      'hasVendorAccess'     => $vendor->businessDetails()->exists(),
      'showRolePopup'       => false,
      'hasShownVendorModal' => session('vendor_status_modal_shown', false),
    ], $this->resolveBusinessContext($vendor), $extra);
  }

  /**
   * Customer dashboard: nearby vendors, categories, past order products, deals.
   */
  public function index()
  {
    if (strtolower(session('active_role', 'customer')) !== 'customer') {
      abort(403, 'Unauthorized action.');
    }

    $user     = Auth::user();
    $vendor   = $this->getVendor();
    $customer = Customer::where('user_id', $user->user_id)->first();

    $vendors = collect();

    // If customer has location, show nearby vendors filtered by availability
    if ($customer && $customer->latitude && $customer->longitude) {
      $nearbyVendors = $this->locator->getNearbyVendors(
        $customer->customer_id,
        $customer->latitude,
        $customer->longitude
      );
      $vendors = $this->filterVendorsWithProducts($nearbyVendors);
    }

    $categories = ProductCategory::all();

    // Products the user has ordered before that are currently available
    $userId = $user->user_id;
    $pastOrderProducts = Product::with(['category', 'dietarySpecifications', 'business'])
      ->where('is_available', true)
      ->whereHas('orderItems.order', function ($query) use ($userId) {
        $query->where('user_id', $userId);
      })
      ->distinct()
      ->get();

    $deals = ProductCategory::all();

    $viewData = $this->buildViewData($vendor, compact('vendors', 'categories', 'pastOrderProducts', 'deals'));

    return view('content.customer.customer-dashboard', $viewData);
  }

  /**
   * Filter to businesses that have at least one available product,
   * add distance/vendor info, and include encrypted IDs.
   */
  public function filterVendorsWithProducts($nearbyVendors)
  {
    $businessIds = $nearbyVendors->pluck('business_id');

    return BusinessDetail::with(['products' => function ($q) {
      $q->where('is_available', true);
    }])
      ->withCount(['products' => function ($q) {
        $q->where('is_available', true);
      }])
      ->whereIn('business_id', $businessIds)
      ->get()
      ->filter(fn($biz) => $biz->products_count > 0)
      ->map(function ($business) use ($nearbyVendors) {
        $vendorRow = $nearbyVendors->firstWhere('business_id', $business->business_id);

        if ($vendorRow) {
          $business->distance = $vendorRow->distance ?? null;
          $business->vendor_id = $vendorRow->vendor_id;
          $business->fullname = $vendorRow->fullname;
          $business->phone_number = $vendorRow->phone_number;
          $business->verification_status = $vendorRow->verification_status ?? null;

          $business->encrypted_business_id = Crypt::encryptString($business->business_id);
          $business->encrypted_vendor_id = Crypt::encryptString($business->vendor_id);
        }

        return $business;
      })
      ->values();
  }

  /**
   * [AJAX] Get products by category (or all if 'all' or no category specified).
   */
  public function getProductsByCategory(Request $request)
  {
    $categoryId = $request->get('category_id');

    $productsQuery = Product::with(['category', 'dietarySpecifications'])
      ->where('is_available', true);

    if ($categoryId && $categoryId !== 'all') {
      $productsQuery->where('product_category_id', $categoryId);
    }

    $products = $productsQuery->get();

    return response()->json([
      'success'  => true,
      'products' => $products
    ]);
  }

  /**
   * Start selling setup screen for customers who want to become vendors.
   */
  public function customerStartSelling()
  {
    Log::info('CustomerMenuController customerStartSelling');

    $user = Auth::user();

    $paymentMethods = PaymentMethod::all();
    $vendor = $this->getVendor();

    $viewData = $this->buildViewData($vendor, [
      'fullname'       => $user->fullname,
      'paymentMethods' => $paymentMethods,
    ]);

    return view('content.customer.customer-start-selling', $viewData);
  }

  /**
   * Show a selected business' public page with its products.
   */
  public function selectedBusiness($businessId, $vendorId)
  {
    try {
      $businessId = Crypt::decryptString($businessId);
      $vendorId   = Crypt::decryptString($vendorId);
    } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
      abort(404, 'Invalid ID');
    }

    $business = BusinessDetail::with('products', 'vendor.user')
      ->where('business_id', $businessId)
      ->where('vendor_id', $vendorId)
      ->first();

    if (!$business) {
      abort(404, 'Business not found or does not belong to the vendor.');
    }

    if ($business->products->isEmpty()) {
      abort(404, 'This business currently has no products set up.');
    }

    $data = [
      'business' => $business,
      'products' => $business->products,
    ];

    $vendor   = $this->getVendor();
    $viewData = $this->buildViewData($vendor, $data);

    return view('content.customer.customer-selected-business', $viewData);
  }

  /**
   * List all approved vendors with at least one product.
   */
  public function vendors()
  {
    $vendors = BusinessDetail::with('products')
      ->where('verification_status', 'Approved')
      ->whereHas('products')
      ->withAvg('reviews', 'rating')
      ->withCount('reviews')
      ->get();

    $vendor = $this->getVendor();
    $viewData = $this->buildViewData($vendor, compact('vendors'));

    return view('content.customer.customer-vendors', $viewData);
  }

  /**
   * Remember that we've shown the vendor status modal to this user.
   */
  public function dismissStatusModal(Request $request)
  {
    session(['vendor_status_modal_shown' => true]);
    return response()->json(['success' => true]);
  }

  /**
   * Show the customer's main inbox page.
   */
  public function messages()
  {
    $customerId = Auth::id();
    Log::info('[Customer messages] Fetching conversations', ['customer_id' => $customerId]);

    $messages = Message::where(function ($query) use ($customerId) {
      $query->where('sender_id', $customerId)
        ->where('sender_role', 'customer');
    })->orWhere(function ($query) use ($customerId) {
      $query->where('receiver_id', $customerId)
        ->where('receiver_role', 'customer');
    })
      ->orderBy('sent_at', 'desc')
      ->get();

    // Group by counterparty business_id
    $conversationsByBusinessId = $messages->groupBy(function ($message) use ($customerId) {
      if ($message->sender_id == $customerId && $message->sender_role == 'customer') {
        return $message->receiver_id;
      }
      return $message->sender_id;
    });

    if ($conversationsByBusinessId->isEmpty()) {
      Log::info('[Customer messages] No conversations found.');
      return view('content.customer.customer-messages', ['conversations' => collect()]);
    }

    $businessDetails = BusinessDetail::whereIn('business_id', $conversationsByBusinessId->keys())->get();

    $conversations = $businessDetails->map(function ($business) use ($conversationsByBusinessId, $customerId) {
      $businessMessages = $conversationsByBusinessId->get($business->business_id);
      $lastMessage = $businessMessages->first();

      // Count unread messages for the customer for this business
      $business->unread_count = $businessMessages
        ->where('receiver_id', $customerId)
        ->where('receiver_role', 'customer')
        ->where('is_read', false)
        ->count();

      $business->last_message = $lastMessage;
      $business->latest_message_time = $lastMessage?->sent_at;
      $business->business_image_url = $business->business_image ? secure_asset($business->business_image) : secure_asset('images/no-image.jpg');

      return $business;
    })->sortByDesc('latest_message_time');

    $vendor = $this->getVendor();
    $viewData = $this->buildViewData($vendor, ['conversations' => $conversations]);

    return view('content.customer.customer-messages', $viewData);
  }

  /**
   * [AJAX] Fetch a specific message thread for a business and mark unread as read.
   */
  public function getMessageThread($business_id)
  {
    $customerId = Auth::id();
    Log::debug('[Customer getMessageThread]', ['business_id' => $business_id, 'customer_id' => $customerId]);

    $messages = Message::where(function ($query) use ($customerId, $business_id) {
      $query->where('sender_id', $customerId)
        ->where('sender_role', 'customer')
        ->where('receiver_id', $business_id)
        ->where('receiver_role', 'business');
    })->orWhere(function ($query) use ($customerId, $business_id) {
      $query->where('sender_id', $business_id)
        ->where('sender_role', 'business')
        ->where('receiver_id', $customerId)
        ->where('receiver_role', 'customer');
    })
      ->orderBy('sent_at', 'asc')
      ->get();

    Log::debug('[Customer getMessageThread] Found messages', ['count' => $messages->count()]);

    // Mark messages addressed to the customer as read
    $messageIdsToMarkAsRead = $messages
      ->where('receiver_id', $customerId)
      ->where('receiver_role', 'customer')
      ->where('is_read', false)
      ->pluck('message_id');

    if ($messageIdsToMarkAsRead->isNotEmpty()) {
      Message::whereIn('message_id', $messageIdsToMarkAsRead)->update(['is_read' => true]);
    }

    // Add sender object for UI convenience (customer or business)
    $customer = User::find($customerId);
    $business = BusinessDetail::find($business_id);

    $messages->map(function ($message) use ($customer, $business) {
      $message->sender = $message->sender_role == 'customer' ? $customer : $business;
      return $message;
    });

    return response()->json($messages->values());
  }

  /**
   * [AJAX] Send a new message from the customer to a vendor and notify the vendor.
   */
  public function sendMessage(Request $request)
  {
    Log::debug('[Customer sendMessage]', $request->all());

    $request->validate([
      'business_id'  => 'required|exists:business_details,business_id',
      'message_text' => 'required|string|max:1000',
    ]);

    $customerId = Auth::id();

    $message = Message::create([
      'sender_id'     => $customerId,
      'sender_role'   => 'customer',
      'receiver_id'   => $request->business_id,
      'receiver_role' => 'business',
      'message_text'  => $request->message_text,
      'sent_at'       => now(),
      'is_read'       => false,
    ]);
    Log::debug('[Customer sendMessage] Message created', ['id' => $message->message_id]);

    // Notify other listeners (e.g., websockets)
    broadcast(new MessageSent($message))->toOthers();
    Log::debug('Customer sent message, event dispatched.');

    // Best-effort vendor notification; errors should not block sending
    try {
      $business = \App\Models\BusinessDetail::query()
        ->with(['vendor.user'])
        ->find($request->business_id);

      if ($business && optional($business->vendor)->user) {
        $vendorUser   = $business->vendor->user;
        $customerUser = Auth::user();
        $notify       = app(\App\Services\NotificationService::class);

        $notify->createNotification([
          'user_id'         => $vendorUser->user_id,
          'actor_user_id'   => $customerUser->user_id,
          'event_type'      => 'NEW_MESSAGE',
          'reference_table' => 'messages',
          'reference_id'    => $message->message_id,
          'business_id'     => $business->business_id,
          'recipient_role'  => 'vendor',
          'payload'         => [
            'title'       => "New message from {$customerUser->fullname}",
            'excerpt'     => 'You have a new message from a customer.',
            'sender_name' => $customerUser->fullname,
            'url'         => '/vendor/messages',
          ],
        ]);
      } else {
        Log::warning('[sendMessage] Could not find vendor user to notify for new message', [
          'business_id' => $request->business_id ?? null,
          'message_id'  => $message->message_id ?? null,
        ]);
      }
    } catch (\Throwable $e) {
      Log::error('[sendMessage] Failed to send new message notification', [
        'error'       => $e->getMessage(),
        'business_id' => $request->business_id ?? null,
        'message_id'  => $message->message_id ?? null,
      ]);
    }

    // Attach sender object for the AJAX response
    $customer = User::find($customerId);
    $message->sender = $customer;

    return response()->json($message, 201);
  }
}
