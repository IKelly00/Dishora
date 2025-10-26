<?php

namespace App\Http\Controllers\vendor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Auth, Log, DB};
use Illuminate\Http\Request;
use App\Models\{Product, ProductCategory, DietarySpecification, Vendor, Message, User, PaymentDetail, OrderItem, Review};
use Illuminate\Support\Collection;
use App\Events\MessageSent;

class VendorMenuController extends Controller
{
  const BIZ_PREFIX = '[BIZ_ID::';
  const BIZ_SUFFIX = ']';

  private function getVendor()
  {
    return Auth::user()?->vendor;
  }

  private function resolveBusinessContext($vendor): array
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

    if (!$business) {
      Log::warning('Business not found for vendor', compact('activeBusinessId'));
    } else {
      Log::info('Resolved businessStatus', compact('businessStatus'));
      Log::info('Show verification modal', compact('showVerificationModal'));
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
        'hasVendorAccess' => false,
        'showRolePopup'   => true,
      ], $extra);
    }

    return array_merge([
      'hasVendorAccess' => $vendor->businessDetails()->exists(),
      'showRolePopup'   => false,
    ], $this->resolveBusinessContext($vendor), $extra);
  }

  public function index()
  {
    Log::info('VendorMenuController index');
    Log::info('Session active_role:', ['role' => session('active_role')]);

    $vendor = $this->getVendor();
    $data = $this->buildViewData($vendor);

    if ($data['vendorStatus'] === 'Pending') {
      session(['active_role' => 'customer']);
    }

    // --- 1. Get and Verify Active Business ID ---
    $activeBusinessId = $data['activeBusinessId'] ?? null;

    if (!$activeBusinessId) {
      if (!$vendor->businessDetails()->exists()) {
        return view('content.vendor.no-business', $data);
      }
      return redirect()->back()->with('error', 'Please select a business to view the dashboard.');
    }

    $business = $vendor->businessDetails()
      ->where('business_id', $activeBusinessId)
      ->first();

    if (!$business) {
      session()->forget('active_business_id');
      return redirect()->back()->with('error', 'Business not found. Please select one.');
    }

    // --- 2. Fetch Dashboard Stats ---

    // --- (FIXED) Total Revenue ---
    // This now sums all "Paid" transactions from payment_details
    // linked to orders from this business.
    $totalRevenue = PaymentDetail::where('payment_status', 'Paid')
      ->whereHas('order', function ($query) use ($activeBusinessId) {
        $query->where('business_id', $activeBusinessId);
      })
      ->sum('amount_paid');

    // --- New Orders ---
    $newOrdersCount = OrderItem::whereHas('order', function ($query) use ($activeBusinessId) {
      $query->where('business_id', $activeBusinessId);
    })->where('order_item_status', 'Pending')->count();

    // --- Active Products ---
    $activeProductsCount = Product::where('business_id', $activeBusinessId)
      ->where('is_available', 1)
      ->count();

    // --- Average Rating ---
    $averageRating = Review::where('business_id', $activeBusinessId)->avg('rating');

    // --- Recent Reviews (or your other widget) ---
    $recentReviews = Review::with('customer')
      ->where('business_id', $activeBusinessId)
      ->orderBy('created_at', 'desc')
      ->limit(5)
      ->get();


    // --- 3. Return the View ---
    $viewData = [
      'business' => $business,
      'totalRevenue' => $totalRevenue,
      'newOrdersCount' => $newOrdersCount,
      'activeProductsCount' => $activeProductsCount,
      'averageRating' => number_format($averageRating, 1),
      'recentReviews' => $recentReviews, // Or 'topSellingProducts'
    ];

    return view('content.vendor.vendor-dashboard', array_merge($viewData, $data));
  }

  public function menu()
  {
    Log::info('VendorMenuController menu');

    $vendor = $this->getVendor();
    $data = $this->buildViewData($vendor);

    if (!$data['hasVendorAccess']) {
      return view('content.vendor.no-business', $data);
    }

    if (!$data['activeBusinessId']) {
      return redirect()->route('vendor.dashboard');
    }

    $business = $vendor->businessDetails()
      ->where('business_id', $data['activeBusinessId'])
      ->first();

    $data['businessName'] = $business?->business_name ?? 'Selected Business';
    $data['products'] = Product::where('business_id', $data['activeBusinessId'])->get();

    return view('content.vendor.vendor-menu', $data);
  }

  public function addMenu(Request $request)
  {
    Log::info('VendorMenuController addMenu');

    $vendor = $this->getVendor();

    $data = $this->buildViewData($vendor, [
      'dietarySpecs' => DietarySpecification::orderBy('dietary_spec_name')->get(),
      'categories'   => ProductCategory::orderBy('category_name')->get(),
      'cutoffHours'   => 0,
      'cutoffMinutes' => 0,
    ]);

    return view('content.vendor.vendor-add-menu', $data);
  }

  public function editMenu($id)
  {
    $vendor = $this->getVendor();

    $product = DB::table('products')->where('product_id', $id)->first();
    $categories = DB::table('product_categories')->get();
    $dietarySpecs = DB::table('dietary_specifications')->get();
    $selectedSpecs = DB::table('product_dietary_specifications')
      ->where('product_id', $id)
      ->pluck('dietary_specification_id')
      ->toArray();

    $cutoffHours = 0;
    $cutoffMinutes = 0;

    // Calculate hours and minutes if a cutoff time is set
    if (!is_null($product->cutoff_minutes)) {
      $cutoffHours = floor($product->cutoff_minutes / 60);
      $cutoffMinutes = $product->cutoff_minutes % 60;
    }

    return view('content.vendor.vendor-edit-menu', $this->buildViewData($vendor, [
      'product'       => $product,
      'categories'    => $categories,
      'dietarySpecs'  => $dietarySpecs,
      'selectedSpecs' => $selectedSpecs,
      'cutoffHours'   => $cutoffHours,
      'cutoffMinutes' => $cutoffMinutes,
    ]));
  }

  public function setActiveBusiness(Request $request)
  {
    Log::info('VendorMenuController setActiveBusiness');

    $request->validate([
      'business_id' => 'required|exists:business_details,business_id',
    ]);

    $businessId = $request->business_id;
    session(['active_business_id' => $businessId]);

    $vendor = $this->getVendor();
    $business = $vendor->businessDetails()
      ->where('business_id', $businessId)
      ->first();

    if (!$business) {
      Log::warning('Business not found for vendor', compact('businessId'));
      return back()->withErrors(['business_id' => 'Business not found.']);
    }

    session(['show_verification_modal' => $business->verification_status === 'Pending']);

    Log::info('active_business_id', compact('businessId'));
    Log::info('verification_status', ['verification_status' => $business->verification_status]);

    return back();
  }

  /**
   * Display the main messages page with a list of conversations.
   */
  public function messages()
  {
    Log::info('VendorMenuController messages'); // 🪵 Log entry point

    $vendor = $this->getVendor();
    $data = $this->buildViewData($vendor);

    // Get the active business to display its name
    $business = $vendor->businessDetails()
      ->where('business_id', $data['activeBusinessId'])
      ->first();
    $data['businessName'] = $business?->business_name ?? 'Your Business'; // Pass name to view

    // Redirect if no business is active or accessible
    if (!$data['hasVendorAccess'] || !$data['activeBusinessId']) {
      Log::warning('Vendor messages: No active business or access denied.'); // 🪵 Log redirect reason
      return redirect()->route('vendor.dashboard')->withErrors('No active business selected.');
    }

    $vendorUserId = $vendor->user_id;
    $activeBusinessId = $data['activeBusinessId'];

    // Define the prefix, escaping the '[' for SQL Server LIKE
    $prefixString = self::BIZ_PREFIX . $activeBusinessId . self::BIZ_SUFFIX;
    $prefixForLike = str_replace('[', '[[]', self::BIZ_PREFIX) . $activeBusinessId . self::BIZ_SUFFIX . '%'; // Escape '[' and add '%'

    // --- Start: Get Customer IDs ---

    // 1. Get all relevant message participants for this business using the escaped prefix
    $participants = DB::table('messages')
      ->where(function ($query) use ($vendorUserId) {
        $query->where('sender_id', $vendorUserId)
          ->orWhere('receiver_id', $vendorUserId);
      })
      ->where('message_text', 'LIKE', $prefixForLike) // Use the escaped prefix + wildcard
      ->select('sender_id', 'receiver_id')
      ->get();

    // 2. Process in PHP: Get unique customer IDs, excluding the vendor
    $customerIds = $participants
      ->pluck('sender_id')
      ->merge($participants->pluck('receiver_id'))
      ->unique()
      ->reject(function ($id) use ($vendorUserId) {
        return $id == $vendorUserId; // Use loose comparison just in case
      })
      ->values(); // Reset array keys

    // --- End: Get Customer IDs ---

    // Now, get the details for each customer and their last message
    $conversations = User::whereIn('user_id', $customerIds)->get()->map(function ($customer) use ($vendorUserId, $prefixString, $prefixForLike) {

      // Find the last message in the conversation, using the escaped prefix
      $lastMessage = Message::where(function ($query) use ($vendorUserId, $customer) {
        $query->where('sender_id', $vendorUserId)->where('receiver_id', $customer->user_id);
      })
        ->orWhere(function ($query) use ($vendorUserId, $customer) {
          $query->where('sender_id', $customer->user_id)->where('receiver_id', $vendorUserId);
        })
        ->where('message_text', 'LIKE', $prefixForLike) // Use the escaped prefix + wildcard
        ->orderBy('sent_at', 'desc')
        ->first();

      // Clean the message text (use the original, unescaped prefix string for replacement)
      if ($lastMessage) {
        $lastMessage->message_text = str_replace($prefixString, '', $lastMessage->message_text);

        // Check for unread messages (use the escaped prefix for the query)
        $customer->unread_count = Message::where('sender_id', $customer->user_id) // Messages sent BY the customer
          ->where('receiver_id', $vendorUserId) // TO the vendor
          ->where('message_text', 'LIKE', $prefixForLike) // For this business
          ->where('is_read', false) // That are unread
          ->count();
      } else {
        $customer->unread_count = 0; // No last message means no unread messages
      }

      $customer->last_message = $lastMessage; // Attach the cleaned last message
      return $customer; // Return the customer object with added properties

    })->sortByDesc(function ($customer) { // Sort by the timestamp of the last message
      return $customer->last_message?->sent_at;
    });

    // Add the prepared conversations to the data for the view
    $data['conversations'] = $conversations;
    $activeBusinessId = $data['activeBusinessId']; // Get the ID from the data array

    // Debug Logs (Keep temporarily) 🪵
    Log::debug('Vendor Messages - Vendor User ID:', ['id' => $vendorUserId]);
    Log::debug('Vendor Messages - Active Business ID:', ['id' => $activeBusinessId]);
    Log::debug('Vendor Messages - Calculated Prefix (Original):', ['prefix' => $prefixString]);
    Log::debug('Vendor Messages - Prefix for LIKE Query:', ['prefix_like' => $prefixForLike]);
    Log::debug('Vendor Messages - Found Participants:', $participants->toArray());
    Log::debug('Vendor Messages - Found Customer IDs (after reject):', $customerIds->toArray());
    Log::debug('Vendor Messages - Final Conversations Count:', ['count' => $conversations->count()]);
    Log::debug('Vendor Messages - Final Conversations Data:', $conversations->toArray());
    Log::debug('Vendor Messages - Data being passed to view:', $data);

    // Return the view with all the necessary data
    return view('content.vendor.vendor-messages', $data + ['activeBusinessId' => $activeBusinessId]);
  }

  /**
   * [AJAX] Fetch a specific message thread for the active business.
   */
  public function getMessageThread(Request $request)
  {
    $request->validate(['customer_id' => 'required|exists:users,user_id']);

    $vendor = $this->getVendor();
    $vendorUserId = $vendor->user_id;
    $customerId = $request->customer_id;

    $context = $this->resolveBusinessContext($vendor);
    $prefix = self::BIZ_PREFIX . $context['activeBusinessId'] . self::BIZ_SUFFIX;

    // 1. Fetch all messages between the two users
    $allMessages = Message::where(function ($query) use ($customerId, $vendorUserId) {
      $query->where('sender_id', $customerId)
        ->where('receiver_id', $vendorUserId);
    })->orWhere(function ($query) use ($customerId, $vendorUserId) {
      $query->where('sender_id', $vendorUserId)
        ->where('receiver_id', $customerId);
    })
      ->with('sender:user_id,fullname') // Use 'fullname' as in your users table
      ->orderBy('sent_at', 'asc')
      ->get();

    // 2. Filter in PHP for this business's context
    $filteredMessages = $allMessages->filter(function ($message) use ($prefix) {
      return str_starts_with($message->message_text, $prefix);
    });

    // 3. Mark these messages as read (only those sent *to* the vendor)
    $messageIdsToMarkAsRead = $filteredMessages
      ->where('receiver_id', $vendorUserId)
      ->where('is_read', false)
      ->pluck('message_id');

    if ($messageIdsToMarkAsRead->isNotEmpty()) {
      Message::whereIn('message_id', $messageIdsToMarkAsRead)->update(['is_read' => true]);
    }

    // 4. Clean the text before returning
    $cleanedMessages = new Collection();
    foreach ($filteredMessages as $message) {
      $message->message_text = str_replace($prefix, '', $message->message_text);
      $cleanedMessages->push($message);
    }

    return response()->json($cleanedMessages->values());
  }

  /**
   * [AJAX] Send a reply from the vendor to a customer.
   */
  public function sendMessage(Request $request)
  {
    $request->validate([
      'customer_id' => 'required|exists:users,user_id',
      'message_text' => 'required|string|max:1000',
    ]);

    $vendor = $this->getVendor();
    $vendorUserId = $vendor->user_id;
    $context = $this->resolveBusinessContext($vendor);
    $activeBusinessId = $context['activeBusinessId'];

    if (!$activeBusinessId) {
      return response()->json(['error' => 'No active business selected.'], 400);
    }

    $prefixedMessage = self::BIZ_PREFIX . $activeBusinessId . self::BIZ_SUFFIX . $request->message_text;

    // Create the message WITH the prefix
    $message = Message::create([
      'sender_id' => $vendorUserId,
      'receiver_id' => $request->customer_id,
      'message_text' => $prefixedMessage, // Save prefixed message
      'sent_at' => now(),
      'is_read' => false,
    ]);

    // --- Broadcasting ---
    // Create a copy for broadcasting
    $messageForBroadcast = $message->replicate();
    $messageForBroadcast->message_text = $prefixedMessage;
    broadcast(new MessageSent($messageForBroadcast))->toOthers(); // Use toOthers()
    Log::debug('Vendor sent message, event dispatched.'); // Log dispatch

    // --- Prepare AJAX Response ---
    // Clean the message text for the *AJAX* response
    $message->message_text = $request->message_text;
    $message->load('sender:user_id,fullname');

    return response()->json($message, 201);
  }
}
