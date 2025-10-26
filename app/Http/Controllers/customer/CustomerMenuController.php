<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\{BusinessDetail, Customer, PaymentMethod, ProductCategory, Vendor, Product, Message};
use App\Services\VendorLocatorService;
use Illuminate\Support\Facades\{Auth, Log};
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Events\MessageSent;
use Illuminate\Support\Str;

class CustomerMenuController extends Controller
{
  const BIZ_PREFIX = '[BIZ_ID::';
  const BIZ_SUFFIX = ']';

  public function __construct(protected VendorLocatorService $locator) {}

  private function getVendor(): ?Vendor
  {
    return Auth::user()?->vendor;
  }

  private function resolveBusinessContext(Vendor $vendor): array
  {
    $vendorStatus = $vendor->registration_status ?? null;

    $activeBusinessId = session('active_business_id');

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

    // Reset the flag only if vendorStatus transitioned back to "Pending"
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

  private function buildViewData(?Vendor $vendor, array $extra = []): array
  {
    if (!$vendor) {
      return array_merge([
        'hasVendorAccess'        => false,
        'showRolePopup'          => true,
        'vendorStatus'           => session('vendorStatus', null),
        'showVendorStatusModal'  => session('vendorStatus') === 'Pending',
        'showVendorRejectedModal' => session('vendorStatus') === 'Rejected',
        'hasShownVendorModal'    => session('vendor_status_modal_shown', false),
      ], $extra);
    }

    return array_merge([
      'hasVendorAccess' => $vendor->businessDetails()->exists(),
      'showRolePopup' => false,
      'hasShownVendorModal' => session('vendor_status_modal_shown', false),
    ], $this->resolveBusinessContext($vendor), $extra);
  }

  public function index()
  {
    // Check that this is a customer session
    if (strtolower(session('active_role', 'customer')) !== 'customer') {
      abort(403, 'Unauthorized action.');
    }

    $user     = Auth::user();
    $vendor   = $this->getVendor();
    $customer = Customer::where('user_id', $user->user_id)->first();

    $vendors = collect();

    // ✅ Only fetch vendors if we have stored customer coordinates
    if ($customer && $customer->latitude && $customer->longitude) {
      // Step 1: get raw nearby vendors
      $nearbyVendors = $this->locator->getNearbyVendors(
        $customer->customer_id,
        $customer->latitude,
        $customer->longitude
      );

      // Step 2: filter them to only vendors with available products
      // (using the reusable method we moved into VendorLocatorService)
      $vendors = $this->filterVendorsWithProducts($nearbyVendors);
    }

    // Product categories
    $categories = ProductCategory::all();

    // All available products (initial load, for "All categories" view)
    $products   = Product::with(['category', 'dietarySpecifications'])
      ->where('is_available', true)
      ->get();

    // Your "deals" (currently set as ProductCategory, maybe rename later)
    $deals = ProductCategory::all();

    // Merge vendor-related view meta/context info
    $viewData = $this->buildViewData($vendor, compact('vendors', 'categories', 'products', 'deals'));

    // Return the dashboard
    return view('content.customer.customer-dashboard', $viewData);
  }

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

          // Encrypt IDs for links
          $business->encrypted_business_id = Crypt::encryptString($business->business_id);
          $business->encrypted_vendor_id = Crypt::encryptString($business->vendor_id);
        }

        return $business;
      })
      ->values();
  }

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
      'success' => true,
      'products' => $products
    ]);
  }

  public function customerStartSelling()
  {
    Log::info('CustomerMenuController customerStartSelling');

    $user = Auth::user();

    $paymentMethods = PaymentMethod::all();
    $vendor = $this->getVendor();

    $viewData = $this->buildViewData($vendor, [
      'fullname'        => $user->fullname,
      'paymentMethods'  => $paymentMethods,
    ]);

    return view('content.customer.customer-start-selling', $viewData);
  }

  public function selectedBusiness($businessId, $vendorId)
  {
    try {
      $businessId = Crypt::decryptString($businessId);
      $vendorId   = Crypt::decryptString($vendorId);
    } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
      abort(404, 'Invalid ID');
    }

    // Load business with ALL its products (no availability filter)
    $business = BusinessDetail::with('products', 'vendor.user')
      ->where('business_id', $businessId)
      ->where('vendor_id', $vendorId)
      ->first();

    if (!$business) {
      abort(404, 'Business not found or does not belong to the vendor.');
    }

    // If the business has zero products, abort
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

  public function vendors()
  {
    $vendors = BusinessDetail::with('products') // eager load products
      ->where('verification_status', 'Approved')
      ->whereHas('products') // only include businesses that actually have products
      ->get();

    $vendor = $this->getVendor();
    $viewData = $this->buildViewData($vendor, compact('vendors'));

    return view('content.customer.customer-vendors', $viewData);
  }

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
    Log::info('Customer messages: Fetching conversations for user', ['customer_id' => $customerId]);

    // 1. Get all messages involving the customer
    $messages = Message::where('sender_id', $customerId)
      ->orWhere('receiver_id', $customerId)
      ->select('message_id', 'message_text', 'sender_id', 'receiver_id', 'sent_at', 'is_read') // Select all needed columns
      ->orderBy('sent_at', 'desc')
      ->get();

    // 2. Extract potential conversations: unique pairs of (business_id, vendor_user_id)
    $potentialConversations = new Collection(); // Use a Collection for easier handling

    foreach ($messages as $message) {
      if (Str::startsWith($message->message_text, self::BIZ_PREFIX)) {
        $businessId = Str::between($message->message_text, self::BIZ_PREFIX, self::BIZ_SUFFIX);

        if (is_numeric($businessId)) {
          $businessId = (int)$businessId;
          // Determine the "other party" (potential vendor user ID)
          $otherPartyUserId = ($message->sender_id == $customerId) ? $message->receiver_id : $message->sender_id;

          // Store unique key: business_id -> vendor_user_id
          // Using business_id as key ensures we only check each business once
          if (!$potentialConversations->has($businessId)) {
            $potentialConversations->put($businessId, $otherPartyUserId);
          }
        }
      }
    }

    Log::debug('Customer messages: Found potential conversations (BusinessID => OtherPartyUserID)', ['pairs' => $potentialConversations->toArray()]);

    if ($potentialConversations->isEmpty()) {
      Log::info('Customer messages: No valid message prefixes found, returning empty view.');
      return view('content.customer.customer-messages', ['conversations' => collect()]);
    }

    // 3. Fetch the BusinessDetail models AND verify the vendor association
    $validBusinessIds = $potentialConversations->keys()->toArray();

    // Fetch businesses and eager load vendor.user only for potentially valid conversations
    $businessDetails = BusinessDetail::whereIn('business_id', $validBusinessIds)
      ->with(['vendor.user:user_id,fullname']) // Eager load vendor and their user details
      ->get()
      // Filter: Keep only businesses where the vendor's user_id matches the 'otherPartyUserId' we found
      ->filter(function ($business) use ($potentialConversations) {
        // Check if vendor and user relationships loaded correctly AND
        // if the associated vendor's user_id matches the ID stored in our potential conversations map
        return $business->vendor
          && $business->vendor->user
          && $business->vendor->user->user_id == $potentialConversations->get($business->business_id);
      });

    Log::debug('Customer messages: Verified BusinessDetail records after vendor check', ['count' => $businessDetails->count(), 'ids' => $businessDetails->pluck('business_id')->toArray()]);

    if ($businessDetails->isEmpty()) {
      Log::info('Customer messages: No conversations matched valid vendor association.');
      return view('content.customer.customer-messages', ['conversations' => collect()]);
    }

    // 4. Add last message preview and unread count for the VERIFIED conversations
    foreach ($businessDetails as $convo) {
      // We already confirmed vendor.user exists in the filter step
      $vendorUserId = $convo->vendor->user_id;
      $prefix = self::BIZ_PREFIX . $convo->business_id . self::BIZ_SUFFIX;

      // Find the latest message *for this specific business prefix* between customer and verified vendor user
      // We can reuse the $messages collection fetched earlier for efficiency, filtering it here.
      $lastMessage = $messages->filter(function ($msg) use ($prefix, $vendorUserId, $customerId) {
        // Check if message has the right prefix
        if (!Str::startsWith($msg->message_text, $prefix)) return false;
        // Check if it's between the customer and the correct vendor
        return ($msg->sender_id == $customerId && $msg->receiver_id == $vendorUserId)
          || ($msg->sender_id == $vendorUserId && $msg->receiver_id == $customerId);
      })->first(); // Since $messages was sorted desc, the first match is the latest

      if ($lastMessage) {
        $convo->last_message_preview = Str::after($lastMessage->message_text, self::BIZ_SUFFIX);
        $convo->latest_message_time = $lastMessage->sent_at; // Store time for sorting
      } else {
        // This case should ideally not happen if we found the business based on messages, but good for safety
        $convo->last_message_preview = 'No messages found for this context.';
        $convo->latest_message_time = null;
      }

      // Count unread messages (sent BY VENDOR, TO CUSTOMER, for THIS BUSINESS) using the filtered messages collection
      $convo->unread_count = $messages->filter(function ($msg) use ($prefix, $vendorUserId, $customerId) {
        return $msg->sender_id == $vendorUserId // Sent by vendor
          && $msg->receiver_id == $customerId // To customer
          && !$msg->is_read                   // Is unread
          && Str::startsWith($msg->message_text, $prefix); // Has correct prefix
      })->count();


      // Add necessary IDs and image URL for the view's data attributes
      $convo->vendor_user_id = $vendorUserId; // Pass the verified vendor user ID
      $convo->business_image_url = $convo->business_image ? secure_asset($convo->business_image) : secure_asset('images/no-image.jpg');
    }

    // Sort verified conversations by the latest message time, descending
    $sortedConversations = $businessDetails->sortByDesc('latest_message_time');

    Log::info('Customer messages: Prepared verified conversations for view', ['count' => $sortedConversations->count()]);

    // 5. Return the view with the sorted conversation data
    return view('content.customer.customer-messages', [
      'conversations' => $sortedConversations
    ]);
  }

  /**
   * [AJAX] Fetch a specific message thread for a business.
   */
  public function getMessageThread($business_id, $vendor_user_id)
  {
    $customerId = Auth::id();
    $prefix = self::BIZ_PREFIX . $business_id . self::BIZ_SUFFIX;

    // 1. Fetch all messages between the customer and the vendor
    $allMessages = Message::where(function ($query) use ($customerId, $vendor_user_id) {
      $query->where('sender_id', $customerId)
        ->where('receiver_id', $vendor_user_id);
    })->orWhere(function ($query) use ($customerId, $vendor_user_id) {
      $query->where('sender_id', $vendor_user_id)
        ->where('receiver_id', $customerId);
    })
      ->with('sender:user_id,fullname') // 'fullname' matches your user schema
      ->orderBy('sent_at', 'asc')
      ->get();

    // 2. Filter in PHP to get only messages for this business
    $filteredMessages = $allMessages->filter(function ($message) use ($prefix) {
      return str_starts_with($message->message_text, $prefix);
    });

    // 3. Mark messages as read (only those sent *to* the customer)
    $messageIdsToMarkAsRead = $filteredMessages
      ->where('receiver_id', $customerId)
      ->where('is_read', false)
      ->pluck('message_id');

    if ($messageIdsToMarkAsRead->isNotEmpty()) {
      Message::whereIn('message_id', $messageIdsToMarkAsRead)->update(['is_read' => true]);
    }

    // 4. Clean the prefix from the text before returning
    $cleanedMessages = new Collection();
    foreach ($filteredMessages as $message) {
      // Remove the prefix
      $message->message_text = str_replace($prefix, '', $message->message_text);
      $cleanedMessages->push($message);
    }

    return response()->json($cleanedMessages->values());
  }

  /**
   * [AJAX] Send a new message from the customer to a vendor.
   */
  public function sendMessage(Request $request)
  {
    $request->validate([
      'receiver_id' => 'required|exists:users,user_id',
      'business_id' => 'required|exists:business_details,business_id',
      'message_text' => 'required|string|max:1000',
    ]);

    $prefixedMessage = self::BIZ_PREFIX . $request->business_id . self::BIZ_SUFFIX . $request->message_text;

    // Create the message WITH the prefix
    $message = Message::create([
      'sender_id' => Auth::id(),
      'receiver_id' => $request->receiver_id,
      'message_text' => $prefixedMessage, // Save prefixed message
      'sent_at' => now(),
      'is_read' => false,
    ]);

    // --- Broadcasting ---
    // Create a copy of the message specifically for broadcasting
    // The event constructor will handle loading sender and cleaning text
    $messageForBroadcast = $message->replicate(); // Use replicate or fresh query if needed
    $messageForBroadcast->message_text = $prefixedMessage; // Ensure event gets original text
    broadcast(new MessageSent($messageForBroadcast))->toOthers(); // Use toOthers()
    Log::debug('Customer sent message, event dispatched.'); // Log dispatch

    // --- Prepare AJAX Response ---
    // Clean the message text for the *AJAX* response
    $message->message_text = $request->message_text; // Use original, clean text
    $message->load('sender:user_id,fullname'); // Ensure sender is loaded for AJAX

    return response()->json($message, 201);
  }
}
