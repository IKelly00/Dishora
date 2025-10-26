<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

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
  User
};

class CheckoutCartController extends Controller
{
  /**
   * Show checkout page for cart (single business) — now includes opening hours for JS.
   */
  public function proceed($businessId)
  {
    // Get full cart from session
    $allCart = collect(session('cart', []));

    if ($allCart->isEmpty()) {
      return redirect()->route('customer.cart')->withErrors(['error' => 'Your cart is empty.']);
    }

    // Load products for all items in cart
    $productIds = $allCart->pluck('product_id')->unique()->values();
    $products = Product::with('business')->whereIn('product_id', $productIds)->get()->keyBy('product_id');

    // Filter items that belong to the requested business
    $itemsForBusiness = $allCart->filter(function ($item) use ($businessId, $products) {
      return isset($products[$item['product_id']]) && ($products[$item['product_id']]->business_id == $businessId);
    })->map(function ($item) use ($products) {
      $p = $products[$item['product_id']] ?? null;
      // Always use the current DB price for checkout display (avoid stale session price)
      $item['price'] = (float) ($p?->price ?? 0);
      $item['is_unavailable'] = !$p || !($p->is_available ?? true);
      return $item;
    })->values();

    if ($itemsForBusiness->isEmpty()) {
      return redirect()->route('customer.cart')->withErrors(['error' => 'No checkoutable items found for this vendor.']);
    }

    // Keep only available items
    $checkoutCart = $itemsForBusiness->filter(fn($i) => empty($i['is_unavailable']))->values();
    if ($checkoutCart->isEmpty()) {
      return redirect()->route('customer.cart')->withErrors(['error' => 'All items for this vendor are unavailable.']);
    }

    // compute total for this business
    $total = $checkoutCart->sum(fn($item) => (float)$item['price'] * (int)$item['quantity']);

    // load vendor/payment methods
    $vendors = BusinessDetail::with(['paymentMethods' => fn($q) => $q->where('status', 'active')])
      ->where('business_id', $businessId)->get()->keyBy('business_id');

    $user = Auth::user();
    $customer = Customer::where('user_id', $user->user_id)->first();

    // --- NEW: load opening hours for this business and transform for JS ----
    $opening = \App\Models\BusinessOpeningHour::where('business_id', $businessId)->get();

    $openingHours = [];
    foreach ($opening as $row) {
      $key = strtolower($row->day_of_week); // monday, tuesday, ...
      $openingHours[$key] = [
        'opens_at'  => $row->opens_at ? substr($row->opens_at, 0, 8) : null,   // "08:00:00"
        'closes_at' => $row->closes_at ? substr($row->closes_at, 0, 8) : null,  // "20:00:00"
        'is_closed' => (bool) $row->is_closed,
      ];
    }

    // --- NEW: compute total cutoff minutes for the items in this checkout (sum cutoff_minutes * qty)
    $totalCutoffMinutes = 0;
    foreach ($checkoutCart as $it) {
      $prod = $products[$it['product_id']] ?? null;
      $cut = (int) ($prod?->cutoff_minutes ?? 0);
      $qty = (int) ($it['quantity'] ?? 1);
      $totalCutoffMinutes += $cut * $qty;
    }

    // Pass single business's items (not grouped by business) to blade
    return view('content.customer.customer-cart-checkout', [
      'business_id' => $businessId,
      'cart' => $checkoutCart,            // collection of items for this business only
      'total' => $total,
      'products' => $products,
      'user' => $user,
      'fullName' => $user?->fullname,
      'contactNumber' => $customer?->contact_number,
      'vendors' => $vendors,              // keyed by business_id (only contains $businessId)
      'openingHours' => $openingHours,    // <-- for the JS that populates delivery_time
      'cutoffMinutes' => $totalCutoffMinutes,
    ]);
  }

  /**
   * Store/submit the cart checkout.
   *
   * Payment method is expected to be provided per-business as payment_method[business_id]
   * but we also accept scalar "payment_method" for single-business checkout.
   */
  public function store(Request $request)
  {
    // Basic validation: delivery date must be today or later; phone in local format
    $request->validate([
      'delivery_date' => 'required|date|after_or_equal:today',
      // allow HH:MM or HH:MM:SS format — store as string
      'delivery_time' => ['required', 'regex:/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/'],
      'full_name' => 'required|string|max:255',
      'phone_number' => 'required|string|max:11|regex:/^09\d{9}$/',
      'region' => 'required|string|max:100',
      'province' => 'required|string|max:100',
      'city' => 'required|string|max:100',
      'barangay' => 'required|string|max:100',
      'postal_code' => 'required|string|max:20',
      'street_name' => 'required|string|max:255',
      // payment_method can be an array or scalar — presence required
      'payment_method' => 'required',
      'item_notes' => 'nullable|array',
      'business_id' => 'required' // ensure we receive the intended business id
    ]);

    $cartSession = collect(session('cart', []));
    if ($cartSession->isEmpty()) {
      return back()->withErrors(['error' => 'Your cart is empty.']);
    }

    $user = Auth::user();

    // Enrich and filter cart: attach business_id and availability, then remove unavailable
    $cart = $cartSession->map(function ($item) {
      $prod = Product::select('product_id', 'business_id', 'is_available', 'price', 'item_name')->find($item['product_id']);
      $item['business_id'] = $prod?->business_id;
      $item['is_unavailable'] = !$prod || !$prod->is_available;
      // Always overwrite with DB price so checkout_drafts/cart can't contain stale session prices
      $item['price'] = (float)($prod?->price ?? 0);
      return $item;
    })->filter(fn($i) => empty($i['is_unavailable']))->values();


    if ($cart->isEmpty()) {
      return back()->withErrors(['error' => 'All selected products became unavailable.']);
    }

    // --- NEW: restrict to the business_id submitted by the checkout form (single-business checkout) ---
    $businessIdFromForm = $request->input('business_id');
    if ($businessIdFromForm) {
      $cart = $cart->filter(function ($i) use ($businessIdFromForm) {
        return (string)($i['business_id'] ?? '') === (string)$businessIdFromForm;
      })->values();

      if ($cart->isEmpty()) {
        return back()->withErrors(['error' => 'No checkoutable items found for this vendor.']);
      }
    }

    // Group by business so we create one CheckoutDraft per business (will be only one when restricting)
    $cartByBusiness = $cart->groupBy('business_id');

    $onlineSessions = [];
    $codDrafts = [];
    $allDraftsInFlow = [];
    $hasOnline = false;
    $hasCod = false;

    // Determine selected payment_method(s) input shape
    $paymentInput = $request->input('payment_method');
    // selected pm ids array (values) — if scalar, make single-element array
    $selectedPmIds = is_array($paymentInput) ? array_values($paymentInput) : [$paymentInput];

    // Precompute whether exactly one online payment is selected across the flow
    $onlineCount = collect($selectedPmIds)->filter(function ($pmid) {
      $pm = PaymentMethod::find($pmid);
      if (!$pm) return false;
      $name = strtolower(trim($pm->method_name));
      return !in_array($name, ['cash on delivery', 'cod', 'card on delivery']);
    })->count();
    $globalIsSingleOnlinePayment = ($onlineCount === 1);

    DB::beginTransaction();
    try {
      // ensure previous cancelled trackers removed
      session()->forget('flow_cancelled_drafts');

      foreach ($cartByBusiness as $businessId => $items) {
        // Accept both forms:
        // 1) array input: payment_method[businessId] => id
        // 2) scalar input: payment_method => id (single-business)
        $pmId = $request->input("payment_method.$businessId") ?? $request->input('payment_method');

        if (empty($pmId)) {
          throw new \Exception("No payment method selected for business {$businessId}.");
        }

        $pm = PaymentMethod::findOrFail($pmId);
        $methodName = strtolower(trim($pm->method_name));

        $total = $items->sum(fn($i) => (float)$i['price'] * (int)$i['quantity']);

        $deliveryData = $request->only([
          'delivery_date',
          'delivery_time', // ensure this is included
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

        $itemNotes = $request->input('item_notes', []);

        $isCod = in_array($methodName, ['cash on delivery', 'cod', 'card on delivery']);

        // Create draft
        $draft = CheckoutDraft::create([
          'user_id' => $user->user_id,
          'payment_method_id' => $pmId,
          'total' => $total,
          'cart' => $items->values()->toArray(),
          'delivery' => $deliveryData,
          'item_notes' => $itemNotes[$businessId] ?? [],
          'is_cod' => $isCod,
        ]);

        $allDraftsInFlow[] = $draft;

        if ($isCod) {
          $hasCod = true;
          $codDrafts[] = $draft;
        } else {
          $hasOnline = true;

          // Build line items for PayMongo (amounts in cents)
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

          // Determine callback URLs
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

          // try to extract stable IDs and checkout_url robustly
          $sessionId = $data['data']['id'] ?? ($data['data']['attributes']['payment_intent']['id'] ?? null);
          $checkoutUrl = $data['data']['attributes']['checkout_url'] ?? null;

          if (empty($data['data']) || !$sessionId || !$checkoutUrl) {
            throw new Exception("PayMongo error: " . ($data['errors'][0]['detail'] ?? json_encode($data)));
          }

          // Save the session id (or payment_intent id)
          $draft->update(['transaction_id' => $sessionId]);

          $onlineSessions[] = [
            'draft_id' => $draft->checkout_draft_id,
            'business_name' => $business?->business_name,
            'checkout_url' => $checkoutUrl
          ];
        }
      }

      // Save draft ids in session to check status later and for processing after webhooks
      session([
        'checkout_flow_draft_ids' => collect($allDraftsInFlow)->pluck('checkout_draft_id')->all(),
        'checkout_flow_type' => 'cart'
      ]);

      DB::commit();

      return $this->handlePaymentScenarios($hasOnline, $hasCod, $onlineSessions, $codDrafts);
    } catch (Exception $e) {
      DB::rollBack();
      Log::error('Cart checkout failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
      return back()->withErrors(['error' => 'Checkout failed. Please try again.']);
    }
  }


  /**
   * Decides next steps depending on presence of online / cod drafts.
   */
  private function handlePaymentScenarios($hasOnlinePayment, $hasCodPayment, $onlinePaymentSessions, $codDrafts)
  {
    // Log what we received so you can inspect in storage/logs/laravel.log
    Log::info('handlePaymentScenarios called', [
      'hasOnlinePayment' => $hasOnlinePayment,
      'hasCodPayment' => $hasCodPayment,
      'raw_onlineSessions' => $onlinePaymentSessions,
      'cod_count' => count($codDrafts),
    ]);

    // Defensive: normalize / deduplicate online sessions by checkout_url (or draft_id)
    $unique = [];
    $dedupedOnlineSessions = [];
    foreach ($onlinePaymentSessions as $s) {
      // use checkout_url if available, fallback to draft_id
      $key = $s['checkout_url'] ?? ($s['draft_id'] ?? null);
      if ($key === null) {
        // keep entries with no key as-is but avoid silent duplicates
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

    // Log deduped array
    Log::info('handlePaymentScenarios: deduped online sessions', [
      'count' => count($dedupedOnlineSessions),
      'sessions' => $dedupedOnlineSessions,
    ]);

    // Only online payments
    if ($hasOnlinePayment && !$hasCodPayment) {
      if (count($dedupedOnlineSessions) === 1) {
        // single session -> direct redirect
        return redirect()->away($dedupedOnlineSessions[0]['checkout_url']);
      }

      // multiple -> show auto-open page
      return response()->view('payment.auto-open-multiple', ['checkoutSessions' => $dedupedOnlineSessions, 'type' => 'cart']);
    }

    // Only COD
    if (!$hasOnlinePayment && $hasCodPayment) {
      try {
        DB::transaction(function () use ($codDrafts) {
          foreach ($codDrafts as $draft) {
            $this->processCodOrderFromDraft($draft);
            $draft->update(['processed_at' => now()]);
          }
        });

        // remove processed items from session cart
        $this->removeProcessedItemsFromCart(collect($codDrafts));

        return redirect()->route('customer.orders.index')->with('success', 'Your Cash on Delivery order(s) have been placed!');
      } catch (Exception $e) {
        Log::error('COD only processing failed', ['error' => $e->getMessage()]);
        return back()->withErrors(['error' => 'COD order processing failed.']);
      }
    }

    // Mixed payments: process COD drafts immediately, keep online sessions to redirect user
    if ($hasOnlinePayment && $hasCodPayment) {
      try {
        DB::transaction(function () use ($codDrafts) {
          foreach ($codDrafts as $draft) {
            $this->processCodOrderFromDraft($draft);
            $draft->update(['processed_at' => now()]);
          }
        });

        $this->removeProcessedItemsFromCart(collect($codDrafts));

        // flag session so system knows it's a mixed flow
        session(['has_mixed_payment' => true]);

        if (count($dedupedOnlineSessions) === 1) {
          return redirect()->away($dedupedOnlineSessions[0]['checkout_url']);
        }

        return response()->view('payment.auto-open-multiple', ['checkoutSessions' => $dedupedOnlineSessions, 'type' => 'cart']);
      } catch (Exception $e) {
        Log::error('Mixed Payment: COD processing failed', ['error' => $e->getMessage()]);
        return back()->withErrors(['error' => 'Failed to process your COD orders. Please try again.']);
      }
    }

    return back()->withErrors(['error' => 'Invalid payment configuration.']);
  }


  /**
   * Create order records for COD drafts immediately.
   */
  public function processCodOrderFromDraft(CheckoutDraft $draft)
  {
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

    DB::transaction(function () use ($user, $draft, $businessId) {
      $order = Order::create([
        'user_id' => $user->user_id,
        'business_id' => $businessId,
        'total' => $draft->total,
        'delivery_date' => $draft->delivery['delivery_date'] ?? now()->toDateString(),
        'delivery_time' => $draft->delivery['delivery_time'] ?? null,
        'payment_method_id' => $draft->payment_method_id,
        'status' => 'pending' // initial status
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

      DeliveryAddress::create(array_merge($draft->delivery, [
        'order_id' => $order->order_id,
        'full_address' => collect($draft->delivery)->except('user_id')->filter()->reverse()->join(', ')
      ]));

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
   * Process online orders from a draft after webhook / callback confirms payment.
   */
  public function processOnlineOrderFromDraft(CheckoutDraft $draft)
  {
    if ($draft->is_cod) {
      throw new Exception("Attempted to process COD draft #{$draft->checkout_draft_id} as an online order.");
    }

    if ($draft->processed_at) {
      Log::info("Skipping already processed online draft #{$draft->checkout_draft_id}");
      return;
    }

    DB::transaction(function () use ($draft) {
      $businessId = collect($draft->cart)->pluck('business_id')->first();

      $order = Order::create([
        'user_id' => $draft->user_id,
        'business_id' => $businessId,
        'total' => $draft->total,
        'delivery_date' => $draft->delivery['delivery_date'] ?? now()->toDateString(), // ✅ FIXED
        'delivery_time' => $draft->delivery['delivery_time'] ?? now()->format('H:i:s'),
        'payment_method_id' => $draft->payment_method_id,
        'status' => 'paid',
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

      DeliveryAddress::create(array_merge($draft->delivery, [
        'order_id' => $order->order_id,
        'full_address' => collect($draft->delivery)
          ->except('user_id')
          ->filter()
          ->reverse()
          ->join(', '),
      ]));

      PaymentDetail::create([
        'order_id' => $order->order_id,
        'payment_method_id' => $draft->payment_method_id,
        'transaction_id' => $draft->transaction_id,
        'amount_paid' => $draft->total,
        'payment_status' => 'Paid',
        'paid_at' => now(),
      ]);

      $draft->update(['processed_at' => now()]);
      Log::info("Order #{$order->order_id} created successfully from online draft #{$draft->checkout_draft_id}");
    });
  }

  /**
   * Process any remaining drafts saved in session for a given user (used after return from payment flows).
   */
  public function processRemainingDrafts($userId)
  {
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
        // online drafts are processed by webhook/payment callback, so skip here
      } catch (Exception $e) {
        Log::error('Failed to process a remaining COD draft', ['draft_id' => $draft->checkout_draft_id, 'error' => $e->getMessage()]);
        continue;
      }
    }
  }

  /**
   * Status endpoint to report progress of online checkout sessions in the current flow.
   */
  public function status()
  {
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

  /**
   * Choose the PayMongo gateway type based on payment method metadata or method name.
   */
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
   * Remove processed cart items from session after successful order creation.
   *
   * @param \Illuminate\Support\Collection $processedDrafts
   */
  public function removeProcessedItemsFromCart($processedDrafts)
  {
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

  /**
   * Optional helper to clear a transaction_id from cart items in the session (if needed).
   * Kept for parity with Preorder controller flow.
   */
  private function clearTransactionFromSession($transactionId)
  {
    $currentCart = collect(session('cart', []));
    $cleaned = $currentCart->map(function ($item) use ($transactionId) {
      if (isset($item['transaction_id']) && $item['transaction_id'] === $transactionId) {
        unset($item['transaction_id']);
      }
      return $item;
    })->all();
    session(['cart' => $cleaned]);
  }
}
