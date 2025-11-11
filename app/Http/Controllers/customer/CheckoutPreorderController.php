<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
  User,
  PreOrder,
  PreorderSchedule
};

class CheckoutPreorderController extends Controller
{
  /**
   * Entry point for preorder checkout (renders checkout view for a business).
   */
  public function proceed($business_id)
  {
    Log::info('Entered proceed()', ['method' => __METHOD__, 'user_id' => Auth::id(), 'business_id' => $business_id]);

    // Load session preorders and product records
    $allPreorders = collect(session('preorder', []));
    $productIds = $allPreorders->pluck('product_id')->unique();
    $products = Product::with('business')->whereIn('product_id', $productIds)->get()->keyBy('product_id');

    // Keep only items that belong to the requested business
    $itemsForBusiness = $allPreorders->filter(function ($item) use ($business_id, $products) {
      return isset($products[$item['product_id']]) && $products[$item['product_id']]->business_id == $business_id;
    });

    if ($itemsForBusiness->isEmpty()) {
      Log::warning('No session items found for business in proceed()', ['method' => __METHOD__, 'business_id' => $business_id, 'user_id' => Auth::id()]);
      return redirect()->route('customer.preorder')->with('error', 'No items found for this business.');
    }

    Log::debug('Preparing checkout calculation', ['method' => __METHOD__, 'items_count' => $itemsForBusiness->count()]);

    // Calculate totals and advance requirements
    $checkoutPreorder = $itemsForBusiness;
    $total = 0.0;
    $totalAdvanceRequired = 0.0;
    $advance_breakdown = [];
    $requires_advance = false;

    foreach ($checkoutPreorder as $i) {
      if (!isset($products[$i['product_id']])) continue;

      $p = $products[$i['product_id']];
      $price = (float) ($p->price ?? 0.0);
      $advAmt = (float) ($p->advance_amount ?? 0.0);
      $qty = (int) ($i['quantity'] ?? 0);

      $total += $price * $qty;

      if ($advAmt > 0) {
        $currentAdvance = $advAmt * $qty;
        $totalAdvanceRequired += $currentAdvance;
        $requires_advance = true;
        $advance_breakdown[$p->item_name] = ['quantity' => $qty, 'advance_total' => $currentAdvance];
      }
    }

    // Remove unavailable items
    $checkoutPreorder = $checkoutPreorder->filter(function ($i) use ($products) {
      return isset($products[$i['product_id']]) && ($products[$i['product_id']]->is_available ?? false);
    })->values();

    if ($checkoutPreorder->isEmpty()) {
      Log::warning('All items unavailable after filtering in proceed()', ['method' => __METHOD__, 'business_id' => $business_id]);
      return redirect()->route('customer.preorder')->with('error', 'All items for this business are currently unavailable.');
    }

    // Load user, customer, vendor, and opening hours
    $user = Auth::user();
    $customer = Customer::where('user_id', $user->user_id)->first();
    $vendors = BusinessDetail::with(['paymentMethods' => fn($q) => $q->where('status', 'active')])
      ->where('business_id', $business_id)->get()->keyBy('business_id');
    $business = $vendors->get($business_id);

    $opening = \App\Models\BusinessOpeningHour::where('business_id', $business_id)->get();
    $openingHours = [];
    foreach ($opening as $row) {
      $key = strtolower($row->day_of_week);
      $openingHours[$key] = [
        'opens_at' => $row->opens_at ? substr($row->opens_at, 0, 8) : null,
        'closes_at' => $row->closes_at ? substr($row->closes_at, 0, 8) : null,
        'is_closed' => (bool) $row->is_closed,
      ];
    }

    Log::info('Rendering preorder checkout view', [
      'method' => __METHOD__,
      'user_id' => $user->user_id,
      'business_id' => $business_id,
      'total' => $total,
      'total_advance_required' => $totalAdvanceRequired,
    ]);

    return view('content.customer.customer-preorder-checkout', [
      'upload_mode'            => false,
      'business_id'            => $business_id,
      'preorders'              => $checkoutPreorder,
      'products'               => $products,
      'user'                   => $user,
      'fullName'               => $user?->fullname,
      'contactNumber'          => $customer?->contact_number,
      'vendors'                => $vendors,
      'business'               => $business,
      'total'                  => $total,
      'total_advance_required' => $totalAdvanceRequired,
      'advance_breakdown'      => $advance_breakdown,
      'requires_advance'       => $requires_advance,
      'openingHours'           => $openingHours,
      'order'                  => null,
      'preorder'               => null,
    ]);
  }

  /**
   * Clear transaction_id from session items (helper).
   */
  private function clearTransactionFromSession($transactionId)
  {
    if (empty($transactionId)) {
      return;
    }

    Log::debug('clearTransactionFromSession called', ['method' => __METHOD__, 'transaction_id' => $transactionId]);

    $currentPreorders = collect(session('preorder', []));
    $cleanedPreorders = $currentPreorders->map(function ($item) use ($transactionId) {
      if (isset($item['transaction_id']) && $item['transaction_id'] == $transactionId) {
        unset($item['transaction_id']);
        Log::debug('Cleared transaction_id from session item', ['method' => __METHOD__, 'product_id' => $item['product_id'] ?? null]);
      }
      return $item;
    })->all();

    session(['preorder' => $cleanedPreorders]);
    Log::info('Attempted to clear transaction from session', ['method' => __METHOD__, 'transaction_id' => $transactionId]);
  }

  /**
   * Store preorder checkout (COD + online).
   */
  public function store(Request $request)
  {
    Log::info('Entered store()', ['method' => __METHOD__, 'user_id' => Auth::id()]);

    $request->validate([
      'business_id'    => 'required|integer|exists:business_details,business_id',
      'order_type'     => 'required|in:delivery,pickup',
      'delivery_date'  => 'required|date|after_or_equal:tomorrow',
      'delivery_time'  => 'required|string',
      'full_name'      => 'required|string|max:255',
      'phone_number'   => ['required', 'regex:/^09\d{9}$/'],

      'region'         => 'required_if:order_type,delivery|nullable|string|max:100',
      'province'       => 'required_if:order_type,delivery|nullable|string|max:100',
      'city'           => 'required_if:order_type,delivery|nullable|string|max:100',
      'barangay'       => 'required_if:order_type,delivery|nullable|string|max:100',
      'postal_code'    => 'required_if:order_type,delivery|nullable|string|max:20',
      'street_name'    => 'required_if:order_type,delivery|nullable|string|max:255',

      'payment_option' => 'nullable|in:advance,full',
      'payment_method' => 'required|integer|exists:payment_methods,payment_method_id',
    ]);

    $user = Auth::user();
    $preorders = collect(session('preorder', []));

    if ($preorders->isEmpty()) {
      Log::warning('Attempt to store with empty session preorder', ['method' => __METHOD__, 'user_id' => $user->user_id]);
      return back()->withErrors(['error' => 'Your preorder list is empty.']);
    }

    $businessId = $request->business_id;
    $products = Product::whereIn('product_id', $preorders->pluck('product_id'))->get()->keyBy('product_id');

    $items = $preorders
      ->filter(
        fn($i) =>
        isset($products[$i['product_id']]) &&
          $products[$i['product_id']]->business_id == $businessId &&
          ($products[$i['product_id']]->is_available ?? false)
      )
      ->map(function ($i) use ($businessId, $products) {
        $p = $products[$i['product_id']];
        $i['business_id'] = $businessId;
        $i['price'] = (float) ($p->price ?? 0);
        return $i;
      })
      ->values();

    if ($items->isEmpty()) {
      Log::warning('No available items for vendor in store()', ['method' => __METHOD__, 'business_id' => $businessId]);
      return back()->withErrors(['error' => 'No available preorder products for this vendor.']);
    }

    $total = $items->sum(fn($i) => ($products[$i['product_id']]->price ?? 0) * $i['quantity']);
    $totalAdvanceRequired = $items->sum(fn($i) => ($products[$i['product_id']]->advance_amount ?? 0) * $i['quantity']);

    $deliveryData = $request->only([
      'delivery_date',
      'delivery_time',
      'full_name',
      'phone_number',
      'region',
      'province',
      'city',
      'barangay',
      'street_name',
      'postal_code',
      'latitude',
      'longitude'
    ]);
    $deliveryData['user_id'] = $user->user_id;
    $deliveryData['payment_option'] = $request->payment_option;

    $deliveryData['order_type'] = $request->input('order_type');

    $pmId = $request->payment_method;
    $pm = PaymentMethod::findOrFail($pmId);
    $methodName = strtolower(trim($pm->method_name));
    $isCod = in_array($methodName, ['cash', 'cod', 'card on delivery']);

    // Determine if this preorder needs an official receipt
    if ($totalAdvanceRequired > 0 || !$isCod) {
      // Requires receipt when paid online or when advance payment exists
      $deliveryData['requires_receipt'] = true;
    } else {
      // Pure COD with no advance payment => no receipt at checkout time
      $deliveryData['requires_receipt'] = false;
    }

    $itemNotes = $request->input('item_notes', []);

    // If COD chosen but advances required -> try to override to GCash
    if ($isCod && $totalAdvanceRequired <= 0 && !$deliveryData['requires_receipt']) {
      Log::info('COD chosen while advances required; attempting to override payment method', [
        'method' => __METHOD__,
        'user_id' => $user->user_id,
        'original_payment_method_id' => $pmId,
        'totalAdvanceRequired' => $totalAdvanceRequired
      ]);
    } else {
      $gcash = PaymentMethod::whereRaw('LOWER(method_name) = ?', ['gcash'])->first();
      if ($gcash) {
        $pmId = $gcash->payment_method_id;
        $pm = $gcash;
        $methodName = 'gcash';
        Log::info('Payment method overridden to GCash', ['method' => __METHOD__, 'gcash_id' => $pmId]);
      } else {
        Log::warning('GCash payment method not found to override COD', ['method' => __METHOD__]);
      }
    }

    DB::beginTransaction();
    try {
      Log::debug('Creating CheckoutDraft', ['method' => __METHOD__, 'user_id' => $user->user_id, 'business_id' => $businessId]);

      $paymentOption = $request->payment_option ?? ($totalAdvanceRequired <= 0 ? 'full' : 'advance');
      $deliveryData['payment_option'] = $paymentOption;

      if ($paymentOption === 'full') {
        $amountToCharge = $total;
      } else {
        $amountToCharge = $items->reduce(function ($carry, $i) use ($products) {
          $p = $products[$i['product_id']];
          $adv = (float)($p->advance_amount ?? 0);
          $qty = (int)$i['quantity'];
          return $carry + ($adv > 0 ? $adv * $qty : 0.0);
        }, 0.0);
      }

      $draft = CheckoutDraft::create([
        'user_id'           => $user->user_id,
        'payment_method_id' => $pmId,
        'total'             => $total,
        'cart'              => $items->toArray(),
        'delivery'          => $deliveryData,
        'item_notes'        => $itemNotes[$businessId] ?? [],
        'is_cod'            => $isCod,
      ]);

      Log::info('CheckoutDraft created', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id, 'amountToCharge' => $amountToCharge]);

      // Immediate processing for pure COD with no advance and no receipt requirement
      if ($isCod && $totalAdvanceRequired <= 0 && empty($deliveryData['requires_receipt'])) {
        Log::info('Processing immediate COD order from draft', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id]);
        $this->processCodOrderFromDraft($draft);
        $draft->update(['processed_at' => now()]);
        DB::commit();
        $this->removeProcessedItemsFromPreorder(collect([$draft]));
        return redirect()->route('customer.orders.index')
          ->with('success', 'Your preorder has been placed successfully (Cash).')
          ->header('Cache-Control', 'no-cache, no-store, must-revalidate') // HTTP 1.1.
          ->header('Pragma', 'no-cache') // HTTP 1.0.
          ->header('Expires', '0');
      }

      // Build PayMongo line items
      $lineItems = $items->map(function ($i) use ($paymentOption, $products) {
        $product = $products[$i['product_id']] ?? null;
        if (!$product) return null;

        $qty = (int)$i['quantity'];

        if ($paymentOption === 'full') {
          $unitAmount = (float)$product->price;
        } else {
          $unitAmount = (float)($product->advance_amount ?? 0);
          if ($unitAmount <= 0) return null;
        }

        if ($paymentOption === 'advance' && ($product->advance_amount ?? 0) > 0) {
          $label = sprintf("%s — Advance (₱%s)", $product->item_name, number_format($product->advance_amount, 2));
        } else {
          $label = sprintf("%s — Full (₱%s)", $product->item_name, number_format($product->price, 2));
        }

        return [
          'name'     => $label,
          'amount'   => intval(round($unitAmount * 100, 0)),
          'currency' => 'PHP',
          'quantity' => $qty,
        ];
      })->filter()->values()->all();

      // Fallback: if amountToCharge is zero, treat as full payment
      if ($amountToCharge <= 0) {
        $amountToCharge = $total;
        $lineItems = $items->map(function ($i) use ($products) {
          $product = $products[$i['product_id']];
          $qty = (int)$i['quantity'];
          return [
            'name' => sprintf("%s — Full (₱%s)", $product->item_name, number_format($product->price, 2)),
            'amount' => intval(round($product->price * $qty * 100, 0)),
            'currency' => 'PHP',
            'quantity' => 1,
          ];
        })->values()->all();
      }

      $business = BusinessDetail::find($businessId);
      $successUrl = route('payment.callback.success');
      $cancelUrl = route('payment.callback.failed', ['draft_id' => $draft->checkout_draft_id]);

      $response = Http::withHeaders([
        'accept'       => 'application/json',
        'content-type' => 'application/json',
        'authorization' => 'Basic ' . base64_encode(config('services.paymongo.secret') . ':'),
      ])->post('https://api.paymongo.com/v1/checkout_sessions', [
        'data' => ['attributes' => [
          'line_items'           => $lineItems,
          'payment_method_types' => [$this->getPayMongoGatewayType($methodName, $pm)],
          'description'          => 'Pre-Order payment for ' . ($business?->business_name ?? 'Vendor'),
          'statement_descriptor' => 'MyMarketplace',
          'success_url'          => $successUrl,
          'cancel_url'           => $cancelUrl,
          'metadata' => [
            'checkout_type' => 'preorder',
            'draft_id' => $draft->checkout_draft_id
          ]
        ]]
      ]);

      $data = $response->json();
      if (!isset($data['data'])) {
        Log::error('PayMongo response missing data', ['method' => __METHOD__, 'response' => $data]);
        throw new Exception("PayMongo error: " . ($data['errors'][0]['detail'] ?? 'Unknown'));
      }

      $checkoutUrl = $data['data']['attributes']['checkout_url'];
      $paymentIntentId = $data['data']['attributes']['payment_intent']['id'];

      $draft->update(['transaction_id' => $paymentIntentId]);

      // Mark related session items as pending with transaction id
      $productIdsInTransaction = $items->pluck('product_id')->all();
      $currentPreorders = collect(session('preorder', []));
      $updatedPreorders = $currentPreorders->map(function ($sessionItem) use ($productIdsInTransaction, $paymentIntentId) {
        if (in_array($sessionItem['product_id'], $productIdsInTransaction)) {
          $sessionItem['transaction_id'] = $paymentIntentId;
        }
        return $sessionItem;
      })->all();
      session(['preorder' => $updatedPreorders]);

      // Store flow info to session
      session([
        'checkout_flow_draft_ids' => [$draft->checkout_draft_id],
        'checkout_flow_type'      => 'preorder',
      ]);

      DB::commit();
      Log::info('Redirecting to PayMongo checkout', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id, 'amount_charged' => $amountToCharge]);
      return redirect()->away($checkoutUrl);
    } catch (Exception $e) {
      DB::rollBack();
      Log::error('Preorder checkout failed', [
        'method' => __METHOD__,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return back()->withErrors(['error' => 'Preorder checkout failed. Please try again.']);
    }
  }


  /**
   * Process a COD draft and create order/payment/preorder records.
   */
  public function processCodOrderFromDraft(CheckoutDraft $draft)
  {
    Log::info('processCodOrderFromDraft called', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id]);

    if (!$draft->is_cod) {
      throw new Exception("Draft {$draft->checkout_draft_id} is not a COD order");
    }

    if ($draft->processed_at) {
      Log::warning('Draft already processed', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id]);
      return;
    }

    $user = User::find($draft->user_id);
    if (!$user) {
      throw new Exception("User not found for draft {$draft->checkout_draft_id}");
    }

    $businessId = collect($draft->cart)->pluck('business_id')->unique()->first();
    $orderType = $draft->delivery['order_type'] ?? 'delivery';

    DB::transaction(function () use ($user, $draft, $businessId, $orderType) {
      $deliveryTime = $draft->delivery['delivery_time'] ?? now()->format('H:i:s');

      $order = Order::create([
        'user_id'           => $user->user_id,
        'business_id'       => $businessId,
        'total'             => $draft->total,
        'delivery_date'     => $draft->delivery['delivery_date'] ?? now()->toDateString(),
        'delivery_time'     => $deliveryTime,
        'payment_method_id' => $draft->payment_method_id,
      ]);

      $totalAdvanceRequired = 0;
      foreach ($draft->cart as $item) {
        $prod = Product::find($item['product_id']);
        OrderItem::create([
          'order_id'             => $order->order_id,
          'product_id'           => $prod->product_id,
          'product_name'         => $prod->item_name,
          'product_description'  => $prod->description ?? null,
          'quantity'             => (int)$item['quantity'],
          'price_at_order_time'  => $item['price'],
          'order_item_note'      => $draft->item_notes[$item['product_id']] ?? null,
          'order_item_status'    => 'Pending',
        ]);

        $totalAdvanceRequired += ((float)$prod->advance_amount * (int)$item['quantity']);
      }

      // Normalize delivery payload and ensure columns are set/nullable as required
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

      // If this is a delivery order, compute full_address and keep address fields.
      // Otherwise (e.g., pickup), explicitly null the address fields (only phone/name remain).
      if ($orderType === 'delivery') {
        $deliveryPayload['full_address'] = collect($delivery)
          ->except('user_id', 'order_type', 'payment_option', 'requires_receipt')
          ->filter()
          ->reverse()
          ->join(', ');
      } else {
        // ensure address-specific columns are null for non-delivery orders
        foreach (['region', 'province', 'city', 'barangay', 'street_name', 'postal_code', 'latitude', 'longitude', 'full_address'] as $k) {
          $deliveryPayload[$k] = null;
        }
      }

      DeliveryAddress::create($deliveryPayload);

      Log::debug('DeliveryAddress inserted', [
        'method' => __METHOD__,
        'order_id' => $order->order_id,
        'user_id' => $deliveryPayload['user_id'],
        'order_type' => $deliveryPayload['order_type'] ?? 'delivery',
      ]);

      $paymentDetail = PaymentDetail::create([
        'order_id' => $order->order_id,
        'payment_method_id' => $draft->payment_method_id,
        'amount_paid' => 0,
        'payment_status' => 'Pending'
      ]);

      $paymentOption = $draft->delivery['payment_option'] ?? 'advance';
      $hadOnlinePayment = !empty($draft->transaction_id);

      if ($hadOnlinePayment) {
        // Compute amountPaid based on payment option and cart
        if ($paymentOption === 'full') {
          $amountPaid = (float)$draft->total;
        } else {
          $amountPaid = 0.0;
          foreach ($draft->cart as $c) {
            $prod = Product::find($c['product_id']);
            $amountPaid += ((float)($prod->advance_amount ?? 0) * (int)$c['quantity']);
          }
          if ($amountPaid <= 0) $amountPaid = (float)$draft->total;
        }

        $amountDue = max((float)$draft->total - $amountPaid, 0.00);

        $paymentDetail->transaction_id = $draft->transaction_id;
        $paymentDetail->amount_paid = $amountPaid;
        $paymentDetail->payment_status = 'Paid';
        $paymentDetail->paid_at = now();
        $paymentDetail->save();

        if ($amountPaid >= (float)$draft->total) {
          $preorderStatus = 'Paid Full';
          $preorderPaymentOption = 'full';
        } elseif ($amountPaid > 0) {
          $preorderStatus = 'Paid Advance';
          $preorderPaymentOption = 'advance';
        } else {
          $preorderStatus = 'Pending';
          $preorderPaymentOption = $paymentOption;
        }

        PreOrder::create([
          'order_id' => $order->order_id,
          'total_advance_required' => $totalAdvanceRequired,
          'advance_paid_amount' => $amountPaid,
          'amount_due' => $amountDue,
          'payment_transaction_id' => $draft->transaction_id,
          'payment_option' => $preorderPaymentOption,
          'receipt_url' => null,
          'preorder_status' => $preorderStatus,
        ]);
      } else {
        // Pure offline COD
        $amountPaid = 0.00;
        $amountDue = max((float)$draft->total - $amountPaid, 0.00);

        $paymentDetail->payment_status = 'Pending';
        $paymentDetail->save();

        PreOrder::create([
          'order_id' => $order->order_id,
          'total_advance_required' => $totalAdvanceRequired,
          'advance_paid_amount' => $amountPaid,
          'amount_due' => $amountDue,
          'payment_transaction_id' => $draft->transaction_id,
          'payment_option' => $paymentOption,
          'receipt_url' => null,
          'preorder_status' => 'Pending',
        ]);
      }

      // [START] ADD NOTIFICATION
      try {
        $notify = app(\App\Services\NotificationService::class);

        // 1. Notify Vendor
        $order->load('business.vendor.user'); // Make sure relations are loaded
        if ($order->business && $order->business->vendor && $order->business->vendor->user) {
          $vendorUser = $order->business->vendor->user;
          $notify->createNotification([
            'user_id'         => $vendorUser->user_id, // Vendor (recipient)
            'actor_user_id'   => $user->user_id,      // Customer (actor)
            'event_type'      => 'PREORDER_CREATED',
            'reference_table' => 'orders, preorder',
            'reference_id'    => $order->order_id,
            'business_id'     => $businessId,
            'recipient_role'  => 'vendor',
            'payload' => [
              'order_id'       => $order->order_id,
              'title'          => "New Pre-Order #{$order->order_id} (COD)",
              'excerpt'        => "A customer placed a new pre-order (COD).",
              'status'         => $preorderStatus ?? 'Pending', // Use status from PreOrder logic
              'url'            => "/vendor/orders/preorder",
            ]
          ]);
        }

        // 2. Notify Customer
        $notify->createNotification([
          'user_id'         => $user->user_id,      // Customer (recipient)
          'actor_user_id'   => $user->user_id,      // Customer (actor)
          'event_type'      => 'PREORDER_CONFIRMED',
          'reference_table' => 'orders, preorder',
          'reference_id'    => $order->order_id,
          'recipient_role'  => 'customer',
          'payload' => [
            'order_id'       => $order->order_id,
            'title'          => "Your Pre-Order #{$order->order_id} is placed!",
            'excerpt'        => "Thank you for your pre-order. It is pending confirmation.",
            'status'         => $preorderStatus ?? 'Pending',
            'url'            => "/customer/orders",
          ]
        ]);
      } catch (\Throwable $e) {
        // IMPORTANT: Do NOT re-throw. Log and continue.
        Log::error('Failed to send pre-order (COD) notification', [
          'order_id' => $order->order_id,
          'error' => $e->getMessage()
        ]);
      }
      // [END] ADD NOTIFICATION

      Log::info('COD order created from draft', ['method' => __METHOD__, 'order_id' => $order->order_id, 'draft_id' => $draft->checkout_draft_id]);
    });
  }

  /**
   * Process an online draft (payment already completed).
   */
  public function processOnlineOrderFromDraft(CheckoutDraft $draft)
  {
    Log::info('processOnlineOrderFromDraft called', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id]);

    if ($draft->is_cod) {
      throw new Exception("Attempted to process COD draft #{$draft->checkout_draft_id} as an online order.");
    }

    if ($draft->processed_at) {
      Log::info('Skipping already processed online draft', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id]);
      return;
    }

    $orderType = $draft->delivery['order_type'] ?? 'delivery';

    DB::transaction(function () use ($draft, $orderType) {
      $businessId = collect($draft->cart)->pluck('business_id')->first();
      $deliveryTime = $draft->delivery['delivery_time'] ?? now()->format('H:i:s');

      $order = Order::create([
        'user_id'           => $draft->user_id,
        'business_id'       => $businessId,
        'total'             => $draft->total,
        'delivery_date'     => $draft->delivery['delivery_date'] ?? now()->toDateString(),
        'delivery_time'     => $deliveryTime,
        'payment_method_id' => $draft->payment_method_id,
      ]);

      $totalAdvanceRequired = 0;
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
          'order_item_note' => $draft->item_notes[$product->product_id] ?? null
        ]);

        $totalAdvanceRequired += ((float)$product->advance_amount * (int)$item['quantity']);
      }

      // normalize delivery payload and ensure user_id present
      $delivery = $draft->delivery ?? [];

      $deliveryPayload = [
        'order_id'        => $order->order_id,
        'user_id'         => $draft->user_id ?? null,
        'full_name'       => $delivery['full_name'] ?? null,
        'phone_number'    => $delivery['phone_number'] ?? null,
        // address fields (nullable by default)
        'region'          => $delivery['region'] ?? null,
        'province'        => $delivery['province'] ?? null,
        'city'            => $delivery['city'] ?? null,
        'barangay'        => $delivery['barangay'] ?? null,
        'street_name'     => $delivery['street_name'] ?? null,
        'postal_code'     => $delivery['postal_code'] ?? null,
        'latitude'        => $delivery['latitude'] ?? null,
        'longitude'       => $delivery['longitude'] ?? null,
        'payment_option'  => $delivery['payment_option'] ?? null,
        'requires_receipt' => $delivery['requires_receipt'] ?? null,
        'order_type'      => $delivery['order_type'] ?? null,
        'full_address'    => null,
      ];

      // If it's a delivery order compute full_address; otherwise explicitly null address fields
      if (($delivery['order_type'] ?? ($draft->delivery['order_type'] ?? null) ?? 'delivery') === 'delivery') {
        $deliveryPayload['full_address'] = collect($delivery)
          ->except('user_id', 'order_type', 'payment_option', 'requires_receipt')
          ->filter()
          ->reverse()
          ->join(', ');
      } else {
        // ensure address-specific columns are explicitly null (only phone + user kept)
        foreach (['region', 'province', 'city', 'barangay', 'street_name', 'postal_code', 'latitude', 'longitude', 'full_address'] as $k) {
          $deliveryPayload[$k] = null;
        }
      }

      // finally create
      DeliveryAddress::create($deliveryPayload);

      Log::debug('DeliveryAddress inserted', [
        'method' => __METHOD__,
        'order_id' => $order->order_id,
        'user_id' => $deliveryPayload['user_id'],
        'order_type' => $deliveryPayload['order_type'] ?? 'delivery',
      ]);


      $paymentOption = $draft->delivery['payment_option'] ?? $draft->payment_option ?? 'advance';

      if ($paymentOption === 'full') {
        $amountPaid = (float)$draft->total;
      } else {
        $amountPaid = 0.0;
        foreach ($draft->cart as $c) {
          $product = Product::find($c['product_id']);
          $amountPaid += ((float)($product->advance_amount ?? 0) * (int)$c['quantity']);
        }
        if ($amountPaid <= 0) $amountPaid = (float)$draft->total;
      }

      $amountDue = max($draft->total - $amountPaid, 0);

      PaymentDetail::create([
        'order_id' => $order->order_id,
        'payment_method_id' => $draft->payment_method_id,
        'transaction_id' => $draft->transaction_id,
        'amount_paid' => $amountPaid,
        'payment_status' => 'Paid',
        'paid_at' => now()
      ]);

      if ($paymentOption === 'full' && $amountPaid >= $draft->total) {
        PreOrder::create([
          'order_id' => $order->order_id,
          'total_advance_required' => $totalAdvanceRequired,
          'advance_paid_amount' => $amountPaid,
          'amount_due' => 0,
          'payment_transaction_id' => $draft->transaction_id,
          'payment_option' => 'full',
          'receipt_url' => null,
          'preorder_status' => 'Paid Full',
        ]);
      } else {
        PreOrder::create([
          'order_id' => $order->order_id,
          'total_advance_required' => $totalAdvanceRequired,
          'advance_paid_amount' => $amountPaid,
          'amount_due' => $amountDue,
          'payment_transaction_id' => $draft->transaction_id,
          'payment_option' => 'advance',
          'receipt_url' => null,
          'preorder_status' => ($amountPaid > 0 ? 'Paid Advance' : 'Pending'),
        ]);
      }

      $draft->update(['processed_at' => now()]);

      // [START] ADD NOTIFICATION
      try {
        // We need the customer User model, which is on the draft
        $customerUser = \App\Models\User::find($draft->user_id);
        $notify = app(\App\Services\NotificationService::class);
        $preorderStatus = $preorder->preorder_status ?? 'Pending';

        // 1. Notify Vendor
        $order->load('business.vendor.user');
        if ($order->business && $order->business->vendor && $order->business->vendor->user) {
          $vendorUser = $order->business->vendor->user;
          $notify->createNotification([
            'user_id'         => $vendorUser->user_id,         // Vendor (recipient)
            'actor_user_id'   => $customerUser->user_id,      // Customer (actor)
            'event_type'      => 'PREORDER_CREATED',
            'reference_table' => 'orders, preorder',
            'reference_id'    => $order->order_id,
            'business_id'     => $businessId,
            'recipient_role'  => 'vendor',
            'payload' => [
              'order_id'       => $order->order_id,
              'title'          => "New Pre-Order #{$order->order_id} (Paid)",
              'excerpt'        => "A customer placed and paid for a new pre-order.",
              'status'         => $preorderStatus,
              'url'            => "/vendor/orders/preorder",
            ]
          ]);
        }

        // 2. Notify Customer
        if ($customerUser) {
          $notify->createNotification([
            'user_id'         => $customerUser->user_id,      // Customer (recipient)
            'actor_user_id'   => $customerUser->user_id,      // Customer (actor)
            'event_type'      => 'PREORDER_CONFIRMED',
            'reference_table' => 'orders, preorder',
            'reference_id'    => $order->order_id,
            'recipient_role'  => 'customer',
            'payload' => [
              'order_id'       => $order->order_id,
              'title'          => "Your Pre-Order #{$order->order_id} is confirmed!",
              'excerpt'        => "Thank you for your payment. Your pre-order is confirmed.",
              'status'         => $preorderStatus,
              'url'            => "/customer/orders",
            ]
          ]);
        }
      } catch (\Throwable $e) {
        // IMPORTANT: Do NOT re-throw. Log and continue.
        Log::error('Failed to send pre-order (Online) notification', [
          'order_id' => $order->order_id,
          'error' => $e->getMessage()
        ]);
      }
      // [END] ADD NOTIFICATION

      Log::info('Online order created from draft', ['method' => __METHOD__, 'order_id' => $order->order_id, 'draft_id' => $draft->checkout_draft_id]);
    });
  }

  /**
   * Try to process any remaining COD drafts stored in the user's session flow.
   */
  public function processRemainingDrafts($userId)
  {
    Log::debug('processRemainingDrafts called', ['method' => __METHOD__, 'user_id' => $userId]);

    $draftIdsInFlow = session('checkout_flow_draft_ids', []);
    if (empty($draftIdsInFlow)) {
      Log::debug('No draft IDs in session flow', ['method' => __METHOD__]);
      return;
    }

    $unprocessedDrafts = CheckoutDraft::whereIn('checkout_draft_id', $draftIdsInFlow)
      ->where('user_id', $userId)->whereNull('processed_at')->get();

    foreach ($unprocessedDrafts as $draft) {
      try {
        if ($draft->is_cod) {
          $this->processCodOrderFromDraft($draft);
          $draft->update(['processed_at' => now()]);
        }
      } catch (Exception $e) {
        Log::error('Failed to process a remaining preorder COD draft', [
          'method' => __METHOD__,
          'draft_id' => $draft->checkout_draft_id,
          'error' => $e->getMessage()
        ]);
        continue;
      }
    }
  }

  /**
   * Return current checkout flow status for online drafts.
   */
  public function status()
  {
    Log::debug('status called', ['method' => __METHOD__]);

    $draftIdsInFlow = session('checkout_flow_draft_ids', []);
    if (empty($draftIdsInFlow)) {
      return response()->json(['status' => 'error', 'message' => 'No active preorder checkout flow found.'], 404);
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
      return ['draft_id' => $draft->checkout_draft_id, 'status' => $status];
    })->values();

    $flowStatus = $pendingCount === 0 ? 'complete' : 'pending';
    Log::debug('status response prepared', ['method' => __METHOD__, 'processed' => $processedCount, 'pending' => $pendingCount]);

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
   * Return unavailable (fully booked) dates for a business preorders.
   */
  public function getAvailability(BusinessDetail $business)
  {
    Log::debug('getAvailability called', ['method' => __METHOD__, 'business_id' => $business->business_id]);

    $fullyBookedDates = PreorderSchedule::where('business_id', $business->business_id)
      ->where('is_active', true)
      ->whereRaw('current_order_count >= max_orders')
      ->pluck('available_date')
      ->map(fn($date) => $date->format('Y-m-d'))
      ->toArray();

    return response()->json(['unavailable_dates' => $fullyBookedDates]);
  }

  /**
   * Render upload receipt page for a given order (if preorder awaiting receipt).
   */
  public function uploadReceipt($order_id)
  {
    Log::info('uploadReceipt called', ['method' => __METHOD__, 'user_id' => Auth::id(), 'order_id' => $order_id]);

    $pendingDbPreorder = PreOrder::whereHas('order', function ($q) use ($order_id) {
      $q->where('user_id', Auth::id())->where('order_id', $order_id);
    })
      ->whereNull('receipt_url')
      ->with('order.items.product')
      ->latest('created_at')
      ->first();

    if (! $pendingDbPreorder) {
      Log::warning('No pending preorder requiring receipt found', ['method' => __METHOD__, 'order_id' => $order_id]);
      return redirect()->route('customer.preorder')->with('error', 'No pending pre-order found that requires receipt upload.');
    }

    $order = $pendingDbPreorder->order;
    $total = 0;
    if ($order && $order->items) {
      foreach ($order->items as $item) {
        $price = null;
        if (isset($item->price) && $item->price !== null) {
          $price = (float)$item->price;
        } elseif (isset($item->product) && isset($item->product->price)) {
          $price = (float)$item->product->price;
        } else {
          $price = 0;
        }
        $qty = isset($item->quantity) ? (int)$item->quantity : 1;
        $total += $price * $qty;
      }
    }

    Log::debug('Rendering upload receipt view', ['method' => __METHOD__, 'order_id' => $order_id, 'total' => $total]);

    return view('content.customer.customer-upload-receipt', [
      'order'       => $order,
      'preorder'    => $pendingDbPreorder,
      'total'       => $total,
      'order_id'    => $order_id,
    ]);
  }

  /**
   * Confirm preorder by uploading a receipt image.
   */
  public function confirmPreorder(Request $request, $order)
  {
    Log::info('confirmPreorder called', ['method' => __METHOD__, 'user_id' => Auth::id(), 'order_id' => $order]);

    $order = Order::where('order_id', $order)->firstOrFail();
    $user = Auth::user();
    if ($order->user_id != $user->user_id) {
      Log::warning('Unauthorized confirmPreorder attempt', ['method' => __METHOD__, 'order_id' => $order->order_id, 'user_id' => $user->user_id]);
      abort(403, 'Unauthorized action.');
    }

    $preorder = PreOrder::where('order_id', $order->order_id)->firstOrFail();

    $request->validate(['receipt' => 'required|image|mimes:jpeg,png,jpg|max:2048']);

    if (! $request->hasFile('receipt')) {
      Log::warning('confirmPreorder missing receipt file', ['method' => __METHOD__, 'order_id' => $order->order_id]);
      return back()->withErrors(['receipt' => 'Please upload a valid receipt image.']);
    }

    $file = $request->file('receipt');
    $disk = Storage::disk('s3');
    $imagePath = $file->store('products', 's3');

    if (!$imagePath) {
      Log::error('Failed to store receipt on S3', ['method' => __METHOD__, 'order_id' => $order->order_id]);
      throw new Exception('Failed to store image on S3. Check AWS permissions or bucket policy.');
    }

    /**
     * @var \Illuminate\Filesystem\FilesystemAdapter $disk
     */
    $finalUrl = $disk->url($imagePath);

    $preorder->receipt_url = $finalUrl;
    $preorder->preorder_status = 'Receipt Uploaded';
    $preorder->save();

    $paymentDetail = PaymentDetail::where('order_id', $order->order_id)->first();
    if ($paymentDetail) {
      if ((float)($paymentDetail->amount_paid ?? 0) > 0) {
        $paymentDetail->payment_status = 'Paid';
        $paymentDetail->paid_at = $paymentDetail->paid_at ?? now();
        $paymentDetail->save();
      } else {
        if ((float)($preorder->advance_paid_amount ?? 0) > 0) {
          $paymentDetail->amount_paid = $preorder->advance_paid_amount;
          $paymentDetail->payment_status = 'Paid';
          $paymentDetail->paid_at = $paymentDetail->paid_at ?? now();
          $paymentDetail->save();
        }
      }
    }

    if (!empty($preorder->payment_transaction_id)) {
      $draft = CheckoutDraft::where('transaction_id', $preorder->payment_transaction_id)
        ->where('user_id', $user->user_id)
        ->first();

      if ($draft) {
        $draft->update(['processed_at' => now()]);
        $this->removeProcessedItemsFromPreorder(collect([$draft]));
        Log::info('Marked draft processed after receipt upload', ['method' => __METHOD__, 'draft_id' => $draft->checkout_draft_id]);
      }
    }

    // Remove session items by product ids from the order (fallback)
    $productIds = OrderItem::where('order_id', $order->order_id)->pluck('product_id')->all();
    if (!empty($productIds)) {
      $currentPreorders = collect(session('preorder', []));
      $finalPreorders = $currentPreorders->reject(function ($item) use ($productIds) {
        return in_array($item['product_id'] ?? null, $productIds);
      })->values()->all();
      session(['preorder' => $finalPreorders]);

      Log::info('Removed confirmed preorder items from session after receipt upload', [
        'method' => __METHOD__,
        'user_id' => $user->user_id,
        'order_id' => $order->order_id,
        'removed_product_ids' => $productIds
      ]);
    }

    // [START] ADD NOTIFICATION
    // Notify the vendor that a receipt has been uploaded for verification
    try {
      $notify = app(\App\Services\NotificationService::class);

      // We already have $order and $user (customer)
      $order->load('business.vendor.user');

      if ($order->business && $order->business->vendor && $order->business->vendor->user) {
        $vendorUser = $order->business->vendor->user;
        $notify->createNotification([
          'user_id'         => $vendorUser->user_id,     // Vendor (recipient)
          'actor_user_id'   => $user->user_id,           // Customer (actor)
          'event_type'      => 'RECEIPT_UPLOADED',
          'reference_table' => 'orders, preorder',
          'reference_id'    => $order->order_id,
          'business_id'     => $order->business_id,
          'recipient_role'  => 'vendor',
          'payload' => [
            'order_id'       => $order->order_id,
            'title'          => "Receipt Uploaded for Pre-Order #{$order->order_id}",
            'excerpt'        => "A customer has uploaded a receipt for verification.",
            'status'         => 'Receipt Uploaded',
            'url'            => "/vendor/orders/preorder",
          ]
        ]);
      }
    } catch (\Throwable $e) {
      // IMPORTANT: Do NOT re-throw.
      Log::error('Failed to send receipt upload notification', [
        'order_id' => $order->order_id,
        'error' => $e->getMessage()
      ]);
    }
    // [END] ADD NOTIFICATION

    return redirect()->route('customer.orders.index')
      ->with('success', 'Receipt uploaded! Your preorder is now pending vendor verification.')
      ->header('Cache-Control', 'no-cache, no-store, must-revalidate') // HTTP 1.1.
      ->header('Pragma', 'no-cache') // HTTP 1.0.
      ->header('Expires', '0');
  }

  /**
   * Resolve gateway type for PayMongo based on method name or configured gateway_type.
   */
  private function getPayMongoGatewayType($methodName, $paymentMethod)
  {
    $valid = ['card', 'gcash', 'paymaya'];
    if ($paymentMethod->gateway_type && in_array($paymentMethod->gateway_type, $valid)) {
      return $paymentMethod->gateway_type;
    }

    return match (strtolower($methodName)) {
      'gcash' => 'gcash',
      'maya', 'paymaya' => 'paymaya',
      default => 'card',
    };
  }

  /**
   * Remove processed draft items from session preorder list.
   * Public so PaymentController can call it.
   */
  public function removeProcessedItemsFromPreorder($processedDrafts, $force = false)
  {
    Log::debug('removeProcessedItemsFromPreorder called', ['method' => __METHOD__, 'force' => $force]);

    if (empty($processedDrafts)) return;

    $processedProductIds = [];

    foreach ($processedDrafts as $draft) {
      $requiresReceipt = data_get($draft, 'delivery.requires_receipt', false);
      $processedAt = data_get($draft, 'processed_at', null);

      // If draft is processed — remove its items even if requires_receipt was true.
      if (! $force && $requiresReceipt && empty($processedAt)) {
        Log::info('Skipping session removal for draft awaiting receipt upload', [
          'method' => __METHOD__,
          'draft_id' => $draft->checkout_draft_id ?? null,
          'requires_receipt' => $requiresReceipt,
          'transaction_id' => $draft->transaction_id ?? null,
        ]);
        continue;
      }

      $processedProductIds = array_merge(
        $processedProductIds,
        collect($draft->cart)->pluck('product_id')->all()
      );
    }


    if (empty($processedProductIds)) {
      Log::debug('No processed product ids to remove from session', ['method' => __METHOD__]);
      return;
    }

    $currentPreorders = collect(session('preorder', []));
    $finalPreorders = $currentPreorders
      ->reject(fn($item) => in_array($item['product_id'], $processedProductIds))
      ->values()
      ->all();

    session(['preorder' => $finalPreorders]);

    Log::info('Cleaned items from session preorder list', ['method' => __METHOD__, 'removed_product_ids' => $processedProductIds]);
  }
}
