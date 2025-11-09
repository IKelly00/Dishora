<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;
use App\Services\NotificationService;

use App\Models\{
  Order,
  OrderItem,
  DeliveryAddress,
  BusinessDetail,
  PaymentDetail,
  Product,
  Customer,
  PaymentMethod,
  CheckoutDraft,
  User,
  Vendor
};

class CheckoutCartController extends Controller
{
  /**
   * IMPORTANT: This controller orchestrates the cart checkout flow for a single-business
   * checkout. It performs the following responsibilities:
   *  - Reads and validates cart data from the session
   *  - Normalizes items using authoritative DB prices (prevents stale session prices)
   *  - Creates CheckoutDraft records (one per business) to represent in-progress flows
   *  - Starts PayMongo checkout sessions for online payment methods and stores
   *    the payment intent id (transaction_id) on the draft for later refund/verification
   *  - Immediately processes COD drafts by creating Order / OrderItem / DeliveryAddress
   *    / PaymentDetail records inside DB transactions
   *
   * SECURITY / SAFETY NOTES:
   *  - This controller assumes the session 'cart' contains only product IDs and quantities.
   *  - Prices are always taken from the database when creating drafts/orders.
   *  - All critical DB writes are wrapped in transactions and logged for easier debugging.
   */
  /**
   * Show checkout page for cart (single business) — now includes opening hours for JS.
   */
  /**
   * Render checkout view for a specific business.
   * Important notes:
   *  - Reads cart from session('cart') and filters items for the given businessId.
   *  - Uses DB product records (fresh price and availability) for display and calculations.
   *  - Computes aggregated 'cutoffMinutes' used by client-side scheduling code.
   *  - Logs entry, warnings if cart is empty, and returns the checkout view which must
   *    present the opening hours and available payment methods to the user.
   */
  public function proceed($businessId)
  {
    Log::info('Entered proceed()', ['method' => __METHOD__, 'user_id' => Auth::id(), 'business_id' => $businessId]);

    $allCart = collect(session('cart', []));

    if ($allCart->isEmpty()) {
      Log::warning('Cart is empty in proceed()', ['method' => __METHOD__, 'user_id' => Auth::id()]);
      return redirect()->route('customer.cart')->withErrors(['error' => 'Your cart is empty.']);
    }

    $productIds = $allCart->pluck('product_id')->unique()->values();
    $products = Product::with('business')->whereIn('product_id', $productIds)->get()->keyBy('product_id');

    $itemsForBusiness = $allCart->filter(function ($item) use ($businessId, $products) {
      return isset($products[$item['product_id']]) && ($products[$item['product_id']]->business_id == $businessId);
    })->map(function ($item) use ($products) {
      $p = $products[$item['product_id']] ?? null;
      $item['price'] = (float) ($p?->price ?? 0);
      $item['is_unavailable'] = !$p || !($p->is_available ?? true);
      return $item;
    })->values();

    if ($itemsForBusiness->isEmpty()) {
      Log::warning('No checkoutable items for vendor in proceed()', ['method' => __METHOD__, 'business_id' => $businessId, 'user_id' => Auth::id()]);
      return redirect()->route('customer.cart')->withErrors(['error' => 'No checkoutable items found for this vendor.']);
    }

    $checkoutCart = $itemsForBusiness->filter(fn($i) => empty($i['is_unavailable']))->values();
    if ($checkoutCart->isEmpty()) {
      Log::warning('All items unavailable for vendor in proceed()', ['method' => __METHOD__, 'business_id' => $businessId]);
      return redirect()->route('customer.cart')->withErrors(['error' => 'All items for this vendor are unavailable.']);
    }

    $total = $checkoutCart->sum(fn($item) => (float)$item['price'] * (int)$item['quantity']);

    $vendors = BusinessDetail::with(['paymentMethods' => fn($q) => $q->where('status', 'active')])
      ->where('business_id', $businessId)->get()->keyBy('business_id');

    $vendor = $vendors->get($businessId);

    $user = Auth::user();
    $customer = Customer::where('user_id', $user->user_id)->first();

    $opening = \App\Models\BusinessOpeningHour::where('business_id', $businessId)->get();

    $openingHours = [];
    foreach ($opening as $row) {
      $key = strtolower($row->day_of_week);
      $openingHours[$key] = [
        'opens_at'  => $row->opens_at ? substr($row->opens_at, 0, 8) : null,
        'closes_at' => $row->closes_at ? substr($row->closes_at, 0, 8) : null,
        'is_closed' => (bool) $row->is_closed,
      ];
    }

    $totalCutoffMinutes = 0;
    foreach ($checkoutCart as $it) {
      $prod = $products[$it['product_id']] ?? null;
      $cut = (int) ($prod?->cutoff_minutes ?? 0);
      $qty = (int) ($it['quantity'] ?? 1);
      $totalCutoffMinutes += $cut * $qty;
    }

    Log::info('Rendering cart checkout view', [
      'method' => __METHOD__,
      'user_id' => $user?->user_id,
      'business_id' => $businessId,
      'items_count' => $checkoutCart->count(),
      'total' => $total,
    ]);

    return view('content.customer.customer-cart-checkout', [
      'business_id' => $businessId,
      'cart' => $checkoutCart,
      'total' => $total,
      'products' => $products,
      'user' => $user,
      'fullName' => $user?->fullname,
      'contactNumber' => $customer?->contact_number,
      'vendor' => $vendor,
      'vendors' => $vendors,
      'openingHours' => $openingHours,
      'cutoffMinutes' => $totalCutoffMinutes,
    ]);
  }

  /**
   * Store/submit the cart checkout.
   */
  /**
   * Accepts checkout form POST and creates CheckoutDraft(s) for each business found in cart.
   * Important notes:
   *  - Validates request fields and normalizes 'order_type' into each draft delivery payload.
   *  - Overwrites item prices using DB values to avoid stale client-side manipulation.
   *  - For online payments: creates a PayMongo checkout_session and stores the payment_intent id
   *    (transaction_id) on the draft to enable refunds and later association.
   *  - For COD drafts: marks them for immediate processing and converts drafts into Orders.
   *  - Stores 'checkout_flow_draft_ids' in session to track the flow across redirects/webhooks.
   *  - All write operations are performed inside DB transactions; any exception will rollback.
   */
  public function store(Request $request)
  {
    Log::info('Entered store()', ['method' => __METHOD__, 'user_id' => Auth::id()]);

    $request->validate([
      'order_type' => 'required|in:delivery,pickup',
      'delivery_date' => 'required|date|after_or_equal:today',
      'delivery_time' => ['required', 'regex:/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/'],
      'full_name' => 'required|string|max:255',
      'phone_number' => 'required|string|max:11|regex:/^09\d{9}$/',

      'region' => 'required_if:order_type,delivery|nullable|string|max:100',
      'province' => 'required_if:order_type,delivery|nullable|string|max:100',
      'city' => 'required_if:order_type,delivery|nullable|string|max:100',
      'barangay' => 'required_if:order_type,delivery|nullable|string|max:100',
      'postal_code' => 'required_if:order_type,delivery|nullable|string|max:20',
      'street_name' => 'required_if:order_type,delivery|nullable|string|max:255',

      'payment_method' => 'required',
      'item_notes' => 'nullable|array',
      'business_id' => 'required'
    ]);

    $cartSession = collect(session('cart', []));
    if ($cartSession->isEmpty()) {
      Log::warning('Attempt to store with empty cart session', ['method' => __METHOD__, 'user_id' => Auth::id()]);
      return back()->withErrors(['error' => 'Your cart is empty.']);
    }

    $user = Auth::user();

    $cart = $cartSession->map(function ($item) {
      $prod = Product::select('product_id', 'business_id', 'is_available', 'price', 'item_name')->find($item['product_id']);
      $item['business_id'] = $prod?->business_id;
      $item['is_unavailable'] = !$prod || !$prod->is_available;
      $item['price'] = (float)($prod?->price ?? 0);
      return $item;
    })->filter(fn($i) => empty($i['is_unavailable']))->values();

    if ($cart->isEmpty()) {
      Log::warning('All selected products became unavailable', ['method' => __METHOD__, 'user_id' => $user->user_id]);
      return back()->withErrors(['error' => 'All selected products became unavailable.']);
    }

    $businessIdFromForm = $request->input('business_id');
    if ($businessIdFromForm) {
      $cart = $cart->filter(function ($i) use ($businessIdFromForm) {
        return (string)($i['business_id'] ?? '') === (string)$businessIdFromForm;
      })->values();

      if ($cart->isEmpty()) {
        Log::warning('No checkoutable items found after restricting by business_id', ['method' => __METHOD__, 'business_id' => $businessIdFromForm]);
        return back()->withErrors(['error' => 'No checkoutable items found for this vendor.']);
      }
    }

    $cartByBusiness = $cart->groupBy('business_id');

    $onlineSessions = [];
    $codDrafts = [];
    $allDraftsInFlow = [];
    $hasOnline = false;
    $hasCod = false;

    $paymentInput = $request->input('payment_method');
    $selectedPmIds = is_array($paymentInput) ? array_values($paymentInput) : [$paymentInput];

    $onlineCount = collect($selectedPmIds)->filter(function ($pmid) {
      $pm = PaymentMethod::find($pmid);
      if (!$pm) return false;
      $name = strtolower(trim($pm->method_name));
      return !in_array($name, ['cash', 'cod', 'card on delivery']);
    })->count();
    $globalIsSingleOnlinePayment = ($onlineCount === 1);

    DB::beginTransaction();
    try {
      session()->forget('flow_cancelled_drafts');

      foreach ($cartByBusiness as $businessId => $items) {
        $pmId = $request->input("payment_method.$businessId") ?? $request->input('payment_method');

        if (empty($pmId)) {
          throw new \Exception("No payment method selected for business {$businessId}.");
        }

        $pm = PaymentMethod::findOrFail($pmId);
        $methodName = strtolower(trim($pm->method_name));

        $total = $items->sum(fn($i) => (float)$i['price'] * (int)$i['quantity']);

        $deliveryData = $request->only([
          'delivery_date',
          'delivery_time',
          'full_name',
          'phone_number',
          'street_name',
          'barangay',
          'city',
          'province',
          'region',
          'postal_code',
          'latitude',
          'longitude'
        ]);

        $deliveryData['user_id'] = $user->user_id;
        $deliveryData['order_type'] = $request->input('order_type');
        $itemNotes = $request->input('item_notes', []);

        $isCod = in_array($methodName, ['cash', 'cod', 'card on delivery']);

        $draft = CheckoutDraft::create([
          'user_id' => $user->user_id,
          'payment_method_id' => $pmId,
          'total' => $total,
          'cart' => $items->values()->toArray(),
          'delivery' => $deliveryData,
          'item_notes' => $itemNotes[$businessId] ?? [],
          'is_cod' => $isCod,
        ]);

        Log::info('CheckoutDraft created (cart)', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id, 'business_id' => $businessId, 'is_cod' => $isCod, 'total' => $total]);

        $allDraftsInFlow[] = $draft;

        if ($isCod) {
          $hasCod = true;
          $codDrafts[] = $draft;
        } else {
          $hasOnline = true;

          $line = $items->map(function ($i) {
            $prod = Product::find($i['product_id']);
            return [
              'name' => $prod?->item_name ?? 'Item',
              'amount' => intval(round((float)$i['price'] * 100)),
              'currency' => 'PHP',
              'quantity' => (int)$i['quantity'],
            ];
          })->all();

          $business = BusinessDetail::find($businessId);

          if ($globalIsSingleOnlinePayment) {
            $successUrl = route('payment.callback.success');
            $cancelUrl = route('payment.callback.failed', ['draft_id' => $draft->checkout_draft_id]);
          } else {
            $successUrl = route('paymongo.success', ['type' => 'cart']);
            $cancelUrl = route('paymongo.failed', ['type' => 'cart', 'draft_id' => $draft->checkout_draft_id]);
          }

          $selectedType = $this->getPayMongoGatewayType($methodName, $pm);

          $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'authorization' => 'Basic ' . base64_encode(config('services.paymongo.secret') . ':')
          ])->post('https://api.paymongo.com/v1/checkout_sessions', [
            'data' => [
              'attributes' => [
                'line_items' => $line,
                'payment_method_types' => [$selectedType],
                'description' => 'Order from ' . ($business?->business_name ?? 'Vendor'),
                'statement_descriptor' => 'MyMarketplace',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
              ]
            ]
          ]);

          $data = $response->json();

          $paymentIntentId = $data['data']['attributes']['payment_intent']['id'] ?? null;
          $checkoutUrl = $data['data']['attributes']['checkout_url'] ?? null;

          if (empty($data['data']) || !$paymentIntentId || !$checkoutUrl) {
            Log::error('PayMongo Error: Missing payment_intent_id or checkout_url', ['response' => $data]);
            throw new Exception("PayMongo error: " . ($data['errors'][0]['detail'] ?? json_encode($data)));
          }

          $draft->update(['transaction_id' => $paymentIntentId]);

          $onlineSessions[] = [
            'draft_id' => $draft->checkout_draft_id,
            'business_name' => $business?->business_name,
            'checkout_url' => $checkoutUrl
          ];

          Log::info('Online session prepared for draft', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id, 'checkout_url' => $checkoutUrl]);
        }
      }

      session([
        'checkout_flow_draft_ids' => collect($allDraftsInFlow)->pluck('checkout_draft_id')->all(),
        'checkout_flow_type' => 'cart'
      ]);

      DB::commit();

      Log::info('Cart store committed', ['method' => __METHOD__, 'user_id' => $user->user_id, 'draft_count' => count($allDraftsInFlow)]);

      return $this->handlePaymentScenarios($hasOnline, $hasCod, $onlineSessions, $codDrafts);
    } catch (Exception $e) {
      DB::rollBack();
      Log::error('Cart checkout failed', ['method' => __METHOD__, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
      return back()->withErrors(['error' => 'Checkout failed. Please try again.']);
    }
  }

  /**
   * Decide next steps based on a mix of online / COD drafts.
   * - Redirects to PayMongo checkout when appropriate (single or multiple sessions)
   * - Immediately processes COD drafts (inside a DB transaction) and removes processed items
   *   from the session cart.
   * - When mixed, COD drafts are processed and online sessions are returned for user action.
   * Logs the decision and deduplicates online sessions by checkout_url.
   */
  private function handlePaymentScenarios($hasOnlinePayment, $hasCodPayment, $onlinePaymentSessions, $codDrafts)
  {
    Log::info('handlePaymentScenarios called', [
      'method' => __METHOD__,
      'hasOnlinePayment' => $hasOnlinePayment,
      'hasCodPayment' => $hasCodPayment,
      'raw_onlineSessions' => $onlinePaymentSessions,
      'cod_count' => count($codDrafts),
    ]);

    $unique = [];
    $dedupedOnlineSessions = [];
    foreach ($onlinePaymentSessions as $s) {
      $key = $s['checkout_url'] ?? ($s['draft_id'] ?? null);
      if ($key === null) {
        $dedupedOnlineSessions[] = $s;
        continue;
      }
      if (!isset($unique[$key])) {
        $unique[$key] = true;
        $dedupedOnlineSessions[] = $s;
      } else {
        Log::warning('Duplicate online session deduped', ['session' => $s]);
      }
    }

    Log::info('handlePaymentScenarios: deduped online sessions', [
      'count' => count($dedupedOnlineSessions),
      'sessions' => $dedupedOnlineSessions,
    ]);

    if ($hasOnlinePayment && !$hasCodPayment) {
      if (count($dedupedOnlineSessions) === 1) {
        Log::info('Redirecting to single online checkout URL', ['checkout_url' => $dedupedOnlineSessions[0]['checkout_url']]);
        return redirect()->away($dedupedOnlineSessions[0]['checkout_url']);
      }

      return response()->view('payment.auto-open-multiple', ['checkoutSessions' => $dedupedOnlineSessions, 'type' => 'cart']);
    }

    if (!$hasOnlinePayment && $hasCodPayment) {
      try {
        DB::transaction(function () use ($codDrafts) {
          foreach ($codDrafts as $draft) {
            $this->processCodOrderFromDraft($draft);
            $draft->update(['processed_at' => now()]);
          }
        });

        $this->removeProcessedItemsFromCart(collect($codDrafts));

        Log::info('Processed COD-only drafts', ['count' => count($codDrafts)]);

        return redirect()->route('customer.orders.index')
          ->with('success', 'Your Cash order(s) have been placed!')
          ->header('Cache-Control', 'no-cache, no-store, must-revalidate') // HTTP 1.1.
          ->header('Pragma', 'no-cache') // HTTP 1.0.
          ->header('Expires', '0');
      } catch (Exception $e) {
        Log::error('COD only processing failed', ['method' => __METHOD__, 'error' => $e->getMessage()]);
        return back()->withErrors(['error' => 'COD order processing failed.']);
      }
    }

    if ($hasOnlinePayment && $hasCodPayment) {
      try {
        DB::transaction(function () use ($codDrafts) {
          foreach ($codDrafts as $draft) {
            $this->processCodOrderFromDraft($draft);
            $draft->update(['processed_at' => now()]);
          }
        });

        $this->removeProcessedItemsFromCart(collect($codDrafts));

        session(['has_mixed_payment' => true]);

        if (count($dedupedOnlineSessions) === 1) {
          return redirect()->away($dedupedOnlineSessions[0]['checkout_url']);
        }

        return response()->view('payment.auto-open-multiple', ['checkoutSessions' => $dedupedOnlineSessions, 'type' => 'cart']);
      } catch (Exception $e) {
        Log::error('Mixed Payment: COD processing failed', ['method' => __METHOD__, 'error' => $e->getMessage()]);
        return back()->withErrors(['error' => 'Failed to process your COD orders. Please try again.']);
      }
    }

    return back()->withErrors(['error' => 'Invalid payment configuration.']);
  }

  /**
   * Convert a COD CheckoutDraft into actual Order, OrderItems, DeliveryAddress and PaymentDetail.
   * Important:
   *  - This is idempotent-guarded: it checks processed_at to avoid double-processing.
   *  - It respects 'order_type' (delivery vs pickup) and will null-out address fields for pickup.
   *  - All DB inserts happen inside a transaction.
   */
  public function processCodOrderFromDraft(CheckoutDraft $draft)
  {
    Log::info('processCodOrderFromDraft called', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id]);

    if (!$draft->is_cod) {
      throw new Exception("Draft {$draft->checkout_draft_id} is not a COD order");
    }

    if ($draft->processed_at) {
      Log::warning('Attempting to process an already processed COD draft', ['draft_id' => $draft->checkout_draft_id]);
      return;
    }

    $user = User::find($draft->user_id);
    if (!$user) {
      throw new Exception("User not found for draft {$draft->checkout_draft_id}");
    }

    $businessId = collect($draft->cart)->pluck('business_id')->unique()->first();

    $orderType = $draft->delivery['order_type'] ?? 'delivery';

    $order = null;

    DB::transaction(function () use ($user, $draft, $businessId, $orderType, $order) {
      $order = Order::create([
        'user_id' => $user->user_id,
        'business_id' => $businessId,
        'total' => $draft->total,
        'delivery_date' => $draft->delivery['delivery_date'] ?? now()->toDateString(),
        'delivery_time' => $draft->delivery['delivery_time'] ?? null,
        'payment_method_id' => $draft->payment_method_id,
      ]);


      // Find user id to pass in Notif
      $vendorId = BusinessDetail::find($businessId)->vendor_id;
      $vendor_userid = Vendor::find($vendorId)->user_id;

      // Send notifications
      $notify = app(NotificationService::class);

      // 1. Notify vendor (for this business)
      $notify->createNotification([
        'user_id' => $vendor_userid,   // or vendor user_id if vendors have separate accounts
        'actor_user_id' => $user->user_id,
        'event_type' => 'order_created',
        'reference_table' => 'orders',
        'reference_id' => $order->order_id,
        'business_id' => $businessId,
        'recipient_role' => 'vendor',
        'payload' => [
          'order_id' => $order->order_id,
          'title' => "New Order with Id #{$order->order_id}",
          'excerpt' => 'A customer placed a new order.',
          'status' => 'Pending',
          'url' => "/vendor/vorders/{$order->order_id}",
        ],
      ]);

      // 2. Notify customer
      $notify->createNotification([
        'user_id' => $user->user_id,
        'actor_user_id' => $user->user_id,
        'event_type' => 'order_confirmed',
        'reference_table' => 'orders',
        'reference_id' => $order->order_id,
        'recipient_role' => 'customer',
        'payload' => [
          'order_id' => $order->order_id,
          'title' => "Your Order with Id #{$order->order_id} has been placed!",
          'excerpt' => 'Thank you for your order.',
          'status' => 'Pending',
          'url' => "/customer/orders",
        ],
      ]);

      foreach ($draft->cart as $item) {
        $prod = Product::find($item['product_id']);
        OrderItem::create([
          'order_id' => $order->order_id,
          'product_id' => $prod->product_id,
          'product_name' => $prod->item_name,
          'product_description' => $prod->description ?? null,
          'quantity' => (int)$item['quantity'],
          'price_at_order_time' => $item['price'],
          'order_item_note' => $draft->item_notes[$item['product_id']] ?? null,
          'order_item_status' => 'Pending'
        ]);
      }

      $delivery = $draft->delivery ?? [];

      $deliveryPayload = [
        'order_id'       => $order->order_id,
        'user_id'        => $draft->user_id,
        'full_name'      => $delivery['full_name'] ?? null,
        'phone_number'   => $delivery['phone_number'] ?? null,
        'region'         => $delivery['region'] ?? null,
        'province'       => $delivery['province'] ?? null,
        'city'           => $delivery['city'] ?? null,
        'barangay'       => $delivery['barangay'] ?? null,
        'street_name'    => $delivery['street_name'] ?? null,
        'postal_code'    => $delivery['postal_code'] ?? null,
        'latitude'       => $delivery['latitude'] ?? null,
        'longitude'      => $delivery['longitude'] ?? null,
        'payment_option' => $delivery['payment_option'] ?? null,
        'requires_receipt' => $delivery['requires_receipt'] ?? null,
        'order_type'     => $delivery['order_type'] ?? null,
        'full_address'   => null,
      ];

      if ($orderType === 'delivery') {
        $deliveryPayload['full_address'] = collect($delivery)
          ->except('user_id', 'order_type', 'payment_option', 'requires_receipt')
          ->filter()
          ->reverse()
          ->join(', ');
      } else {
        foreach (['region', 'province', 'city', 'barangay', 'street_name', 'postal_code', 'latitude', 'longitude', 'full_address'] as $k) {
          $deliveryPayload[$k] = null;
        }
      }

      DeliveryAddress::create($deliveryPayload);

      PaymentDetail::create([
        'order_id' => $order->order_id,
        'payment_method_id' => $draft->payment_method_id,
        'amount_paid' => 0,
        'payment_status' => 'Pending'
      ]);

      Log::info('COD order created successfully from draft', ['order_id' => $order->order_id, 'draft_id' => $draft->checkout_draft_id]);
    });
  }

  /**
   * Convert an online (paid) CheckoutDraft into an Order after payment confirmation.
   * Important:
   *  - Ensures draft->is_cod is false and checks processed_at to avoid duplicates.
   *  - Records PaymentDetail with transaction_id and marks payment as Paid.
   *  - Creates PreOrder / PaymentDetail records identically to the COD flow where applicable.
   */
  public function processOnlineOrderFromDraft(CheckoutDraft $draft)
  {
    Log::info('processOnlineOrderFromDraft called', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id]);

    if ($draft->is_cod) {
      throw new Exception("Attempted to process COD draft #{$draft->checkout_draft_id} as an online order.");
    }

    if ($draft->processed_at) {
      Log::info("Skipping already processed online draft #{$draft->checkout_draft_id}");
      return;
    }

    $orderType = $draft->delivery['order_type'] ?? 'delivery';

    DB::transaction(function () use ($draft, $orderType) {
      $businessId = collect($draft->cart)->pluck('business_id')->first();

      $order = Order::create([
        'user_id' => $draft->user_id,
        'business_id' => $businessId,
        'total' => $draft->total,
        'delivery_date' => $draft->delivery['delivery_date'] ?? now()->toDateString(),
        'delivery_time' => $draft->delivery['delivery_time'] ?? now()->format('H:i:s'),
        'payment_method_id' => $draft->payment_method_id,
      ]);

      foreach ($draft->cart as $item) {
        $product = Product::find($item['product_id']);
        OrderItem::create([
          'order_id' => $order->order_id,
          'product_id' => $product->product_id,
          'product_name' => $product->item_name,
          'product_description' => $product->description ?? null,
          'quantity' => (int)$item['quantity'],
          'price_at_order_time' => $item['price'],
          'order_item_status' => 'Pending',
          'order_item_note' => $draft->item_notes[$product->product_id] ?? null,
        ]);
      }

      $delivery = $draft->delivery ?? [];

      $deliveryPayload = [
        'order_id'       => $order->order_id,
        'user_id'        => $draft->user_id,
        'full_name'      => $delivery['full_name'] ?? null,
        'phone_number'   => $delivery['phone_number'] ?? null,
        'region'         => $delivery['region'] ?? null,
        'province'       => $delivery['province'] ?? null,
        'city'           => $delivery['city'] ?? null,
        'barangay'       => $delivery['barangay'] ?? null,
        'street_name'    => $delivery['street_name'] ?? null,
        'postal_code'    => $delivery['postal_code'] ?? null,
        'latitude'       => $delivery['latitude'] ?? null,
        'longitude'      => $delivery['longitude'] ?? null,
        'payment_option' => $delivery['payment_option'] ?? null,
        'requires_receipt' => $delivery['requires_receipt'] ?? null,
        'order_type'     => $delivery['order_type'] ?? null,
        'full_address'   => null,
      ];

      if ($orderType === 'delivery') {
        $deliveryPayload['full_address'] = collect($delivery)
          ->except('user_id', 'order_type', 'payment_option', 'requires_receipt')
          ->filter()
          ->reverse()
          ->join(', ');
      } else {
        foreach (['region', 'province', 'city', 'barangay', 'street_name', 'postal_code', 'latitude', 'longitude', 'full_address'] as $k) {
          $deliveryPayload[$k] = null;
        }
      }

      DeliveryAddress::create($deliveryPayload);

      PaymentDetail::create([
        'order_id' => $order->order_id,
        'payment_method_id' => $draft->payment_method_id,
        'transaction_id' => $draft->transaction_id,
        'amount_paid' => $draft->total,
        'payment_status' => 'Paid',
        'paid_at' => now(),
      ]);

      $draft->update(['processed_at' => now()]);

      // Find user id to pass in Notif
      $vendorId = BusinessDetail::find($businessId)->vendor_id;
      $vendor_userid = Vendor::find($vendorId)->user_id;

      // Send notifications
      $notify = app(NotificationService::class);

      // 1. Notify vendor (for this business)
      $notify->createNotification([
        'user_id' => $vendor_userid,   // or vendor user_id if vendors have separate accounts
        'actor_user_id' => $draft->user_id,
        'event_type' => 'order_created',
        'reference_table' => 'orders',
        'reference_id' => $order->order_id,
        'business_id' => $businessId,
        'recipient_role' => 'vendor',
        'payload' => [
          'order_id' => $order->order_id,
          'title' => "New Order #{$order->order_id}",
          'excerpt' => 'A customer placed a new order.',
          'status' => 'Pending',
          'url' => "/vendor/vorders/{$order->order_id}",
        ],
      ]);

      // 2. Notify customer
      $notify->createNotification([
        'user_id' => $draft->user_id,
        'actor_user_id' => $draft->user_id,
        'event_type' => 'order_confirmed',
        'reference_table' => 'orders',
        'reference_id' => $order->order_id,
        'recipient_role' => 'customer',
        'payload' => [
          'order_id' => $order->order_id,
          'title' => "Your Order #{$order->order_id} has been placed!",
          'excerpt' => 'Thank you for your order.',
          'status' => 'Pending',
          'url' => "/customer/orders",
        ],
      ]);

      foreach ($draft->cart as $item) {
        $prod = Product::find($item['product_id']);
        OrderItem::create([
          'order_id' => $order->order_id,
          'product_id' => $prod->product_id,
          'product_name' => $prod->item_name,
          'product_description' => $prod->description ?? null,
          'quantity' => (int)$item['quantity'],
          'price_at_order_time' => $item['price'],
          'order_item_note' => $draft->item_notes[$item['product_id']] ?? null,
          'order_item_status' => 'Pending'
        ]);
      }

      Log::info("Order #{$order->order_id} created successfully from online draft #{$draft->checkout_draft_id}");
    });
  }

  public function processRemainingDrafts($userId)
  {
    Log::debug('processRemainingDrafts called', ['method' => __METHOD__, 'user_id' => $userId]);

    $draftIdsInFlow = session('checkout_flow_draft_ids', []);
    if (empty($draftIdsInFlow)) {
      return;
    }

    $unprocessedDrafts = CheckoutDraft::whereIn('checkout_draft_id', $draftIdsInFlow)
      ->where('user_id', $userId)
      ->whereNull('processed_at')
      ->get();

    foreach ($unprocessedDrafts as $draft) {
      try {
        if ($draft->is_cod) {
          $this->processCodOrderFromDraft($draft);
          $draft->update(['processed_at' => now()]);
        }
      } catch (Exception $e) {
        Log::error('Failed to process a remaining COD draft', ['draft_id' => $draft->checkout_draft_id, 'error' => $e->getMessage()]);
        continue;
      }
    }
  }

  public function status()
  {
    Log::debug('status called', ['method' => __METHOD__]);

    $draftIdsInFlow = session('checkout_flow_draft_ids', []);
    if (empty($draftIdsInFlow)) {
      return response()->json(['status' => 'error'], 404);
    }

    $drafts = CheckoutDraft::whereIn('checkout_draft_id', $draftIdsInFlow)->get();
    $onlineDrafts = $drafts->where('is_cod', false);
    $cancelledDraftIds = session('flow_cancelled_drafts', []);

    $processedCount = 0;
    $pendingCount = 0;
    $cancelledCount = 0;

    $detailedStatuses = $onlineDrafts->map(function ($draft) use ($cancelledDraftIds, &$processedCount, &$pendingCount, &$cancelledCount) {
      $status = 'pending';
      if ($draft->processed_at) {
        $status = 'paid';
        $processedCount++;
      } elseif (in_array($draft->checkout_draft_id, $cancelledDraftIds)) {
        $status = 'cancelled';
        $cancelledCount++;
      } else {
        $pendingCount++;
      }

      return [
        'draft_id' => $draft->checkout_draft_id,
        'status' => $status
      ];
    })->values();

    $flowStatus = $pendingCount === 0 ? 'complete' : 'pending';

    return response()->json([
      'total_online' => $onlineDrafts->count(),
      'processed' => $processedCount,
      'pending' => $pendingCount,
      'cancelled' => $cancelledCount,
      'status' => $flowStatus,
      'details' => $detailedStatuses,
    ]);
  }

  private function getPayMongoGatewayType($methodName, $paymentMethod)
  {
    $validGatewayTypes = ['card', 'gcash', 'paymaya'];
    if ($paymentMethod->gateway_type && in_array($paymentMethod->gateway_type, $validGatewayTypes)) {
      return $paymentMethod->gateway_type;
    }

    switch (strtolower($methodName)) {
      case 'gcash':
        return 'gcash';
      case 'maya':
      case 'paymaya':
        return 'paymaya';
      default:
        return 'card';
    }
  }

  /**
   * Remove processed items from the user's session('cart').
   * - Collects product IDs from processed drafts and rejects them from session cart.
   * - Logs which product IDs were removed.
   */
  public function removeProcessedItemsFromCart($processedDrafts)
  {
    Log::debug('removeProcessedItemsFromCart called', ['method' => __METHOD__]);

    if (empty($processedDrafts) || collect($processedDrafts)->isEmpty()) {
      return;
    }

    $processedProductIds = [];
    foreach ($processedDrafts as $draft) {
      $processedProductIds = array_merge($processedProductIds, collect($draft->cart)->pluck('product_id')->all());
    }

    $currentCart = collect(session('cart', []));
    $finalCart = $currentCart->reject(fn($item) => in_array($item['product_id'], $processedProductIds))->values()->all();
    session(['cart' => $finalCart]);

    Log::info('Cleaned items from session cart.', ['removed_product_ids' => $processedProductIds]);
  }
}
