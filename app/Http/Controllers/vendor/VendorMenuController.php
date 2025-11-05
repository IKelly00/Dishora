<?php

namespace App\Http\Controllers\vendor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Auth, Log, DB};
use Illuminate\Http\Request;
use App\Models\{Product, ProductCategory, DietarySpecification, Vendor, Message, User, PaymentDetail, OrderItem, Review, BusinessDetail};
use Illuminate\Support\Collection;
use App\Events\MessageSent;

class VendorMenuController extends Controller
{

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
    $totalRevenue = PaymentDetail::where('payment_status', 'Paid')
      ->whereHas('order', function ($query) use ($activeBusinessId) {
        $query->where('business_id', $activeBusinessId);
      })
      ->sum('amount_paid');

    $newOrdersCount = OrderItem::whereHas('order', function ($query) use ($activeBusinessId) {
      $query->where('business_id', $activeBusinessId);
    })->where('order_item_status', 'Pending')->count();

    $activeProductsCount = Product::where('business_id', $activeBusinessId)
      ->where('is_available', 1)
      ->count();

    $averageRating = Review::where('business_id', $activeBusinessId)->avg('rating');

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
      'recentReviews' => $recentReviews,
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
    Log::info('VendorMenuController messages');

    // --- 1. Get the Vendor ---
    $vendorUserId = Auth::id();
    if (!$vendorUserId) {
      Log::warning('Vendor messages: No authenticated user.');
      return redirect()->route('login')->withErrors('Please log in.');
    }
    $vendor = Vendor::where('user_id', $vendorUserId)->first();
    if (!$vendor) {
      Log::warning('Vendor messages: No vendor record for user.', ['user_id' => $vendorUserId]);
      return redirect()->route('vendor.dashboard')->withErrors('Vendor account not found.');
    }

    // --- 2. Get the base data from buildViewData ---
    // This sets up session, activeBusinessId, etc.
    $data = $this->buildViewData($vendor);

    // --- 3. Check access ---
    if (!$data['hasVendorAccess'] || !$data['activeBusinessId']) {
      Log::warning('Vendor messages: No active business or access denied.');
      return redirect()->route('vendor.dashboard')->withErrors('No active business selected.');
    }

    // --- 4. Get data needed for this view ---
    $activeBusinessId = $data['activeBusinessId'];

    // --- Start: Get Customer IDs ---
    $participants = DB::table('messages')
      ->where(function ($query) use ($activeBusinessId) {
        $query->where('sender_id', $activeBusinessId)
          ->where('sender_role', 'business');
      })->orWhere(function ($query) use ($activeBusinessId) {
        $query->where('receiver_id', $activeBusinessId)
          ->where('receiver_role', 'business');
      })
      ->select('sender_id', 'receiver_id')
      ->get();

    $customerIds = $participants
      ->flatMap(function ($participant) use ($activeBusinessId) {
        if ($participant->sender_id == $activeBusinessId) {
          return [$participant->receiver_id];
        }
        return [$participant->sender_id];
      })
      ->unique()
      ->values();
    Log::debug('[messages] Found Customer IDs', ['ids' => $customerIds->toArray()]);
    // --- End: Get Customer IDs ---

    $conversations = User::whereIn('user_id', $customerIds)->get()->map(function ($customer) use ($activeBusinessId) {

      $lastMessage = Message::where(function ($query) use ($customer, $activeBusinessId) {
        $query->where('sender_id', $customer->user_id)
          ->where('sender_role', 'customer')
          ->where('receiver_id', $activeBusinessId)
          ->where('receiver_role', 'business');
      })->orWhere(function ($query) use ($customer, $activeBusinessId) {
        $query->where('sender_id', $activeBusinessId)
          ->where('sender_role', 'business')
          ->where('receiver_id', $customer->user_id)
          ->where('receiver_role', 'customer');
      })
        ->orderBy('sent_at', 'desc')
        ->first();

      $customer->unread_count = Message::where('sender_id', $customer->user_id)
        ->where('sender_role', 'customer')
        ->where('receiver_id', $activeBusinessId)
        ->where('receiver_role', 'business')
        ->where('is_read', false)
        ->count();

      $customer->last_message = $lastMessage;
      return $customer;
    })->sortByDesc(function ($customer) {
      return $customer->last_message?->sent_at;
    });

    // --- 5. Prepare the final view data ---

    // Get the business name (which buildViewData doesn't add to the top level)
    $business = $vendor->businessDetails()
      ->where('business_id', $activeBusinessId)
      ->first();
    $businessName = $business?->business_name ?? 'Your Business';

    // This is the data *specific* to this view
    $viewData = [
      'conversations' => $conversations,
      'businessName' => $businessName,
    ];

    // --- 6. Return the view, merging base data and view-specific data ---
    // This matches the pattern in your 'index' method
    return view('content.vendor.vendor-messages', array_merge($viewData, $data));
  }

  /**
   * [AJAX] Fetch a specific message thread.
   */
  public function getMessageThread(Request $request)
  {
    Log::debug('[getMessageThread] Request', $request->all());
    $request->validate(['customer_id' => 'required|exists:users,user_id']);

    // --- Start: Inlined Logic ---
    $vendorUserId = Auth::id();
    $vendor = $vendorUserId ? Vendor::where('user_id', $vendorUserId)->first() : null;
    if (!$vendor) {
      return response()->json(['error' => 'Vendor not found.'], 404);
    }
    // Use session to get active business ID, consistent with buildViewData
    $activeBusinessId = session('active_business_id');
    // --- End: Inlined Logic ---

    $customerId = $request->customer_id;

    if (!$activeBusinessId) {
      return response()->json(['error' => 'No active business selected.'], 400);
    }
    Log::debug('[getMessageThread] Context', ['customer_id' => $customerId, 'business_id' => $activeBusinessId]);

    $messages = Message::where(function ($query) use ($customerId, $activeBusinessId) {
      $query->where('sender_id', $customerId)
        ->where('sender_role', 'customer')
        ->where('receiver_id', $activeBusinessId)
        ->where('receiver_role', 'business');
    })->orWhere(function ($query) use ($customerId, $activeBusinessId) {
      $query->where('sender_id', $activeBusinessId)
        ->where('sender_role', 'business')
        ->where('receiver_id', $customerId)
        ->where('receiver_role', 'customer');
    })
      ->orderBy('sent_at', 'asc')
      ->get();
    Log::debug('[getMessageThread] Found messages', ['count' => $messages->count()]);

    // Mark messages as read
    $messageIdsToMarkAsRead = $messages
      ->where('receiver_id', $activeBusinessId)
      ->where('receiver_role', 'business')
      ->where('is_read', false)
      ->pluck('message_id');

    if ($messageIdsToMarkAsRead->isNotEmpty()) {
      Message::whereIn('message_id', $messageIdsToMarkAsRead)->update(['is_read' => true]);
    }

    // --- MANUALLY ADD SENDER INFO ---
    $customer = User::find($customerId);
    $business = BusinessDetail::find($activeBusinessId);

    $messages->map(function ($message) use ($customer, $business) {
      if ($message->sender_role == 'customer') {
        $message->sender = $customer;
      } else {
        $message->sender = $business;
      }
      return $message;
    });
    // --- END MANUAL ADD ---

    return response()->json($messages->values());
  }

  /**
   * [AJAX] Send a reply from the vendor.
   */
  public function sendMessage(Request $request)
  {
    Log::debug('[sendMessage] Request', $request->all());
    $request->validate([
      'customer_id' => 'required|exists:users,user_id',
      'message_text' => 'required|string|max:1000',
    ]);

    // --- Start: Inlined Logic ---
    $vendorUserId = Auth::id();
    $vendor = $vendorUserId ? Vendor::where('user_id', $vendorUserId)->first() : null;
    if (!$vendor) {
      return response()->json(['error' => 'Vendor not found.'], 404);
    }
    // Use session to get active business ID, consistent with buildViewData
    $activeBusinessId = session('active_business_id');
    // --- End: Inlined Logic ---

    if (!$activeBusinessId) {
      return response()->json(['error' => 'No active business selected.'], 400);
    }

    $message = Message::create([
      'sender_id' => $activeBusinessId,
      'sender_role' => 'business',
      'receiver_id' => $request->customer_id,
      'receiver_role' => 'customer',
      'message_text' => $request->message_text,
      'sent_at' => now(),
      'is_read' => false,
    ]);
    Log::debug('[sendMessage] Message created', ['id' => $message->message_id]);

    broadcast(new MessageSent($message))->toOthers();
    Log::debug('[sendMessage] Broadcast event dispatched.');

    // --- MANUALLY ADD SENDER FOR AJAX RESPONSE ---
    $business = BusinessDetail::find($activeBusinessId);
    $message->sender = $business;
    // --- END MANUAL ADD ---

    return response()->json($message, 201);
  }
}
