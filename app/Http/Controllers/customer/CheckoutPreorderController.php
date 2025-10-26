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

use App\Models\PreOrder;
use App\Models\PreorderSchedule;
use Illuminate\Support\Facades\Storage;

class CheckoutPreorderController extends Controller
{
  /**
   * Entry point for preorder checkout
   */
  public function proceed($business_id)
  {
    // --- Initial Setup (Get session items and product details) ---
    $allPreorders = collect(session('preorder', []));
    $productIds = $allPreorders->pluck('product_id');
    $products = Product::with('business')->whereIn('product_id', $productIds)->get()->keyBy('product_id');

    // Filter session items for the specific business we are checking out
    $itemsForBusiness = $allPreorders->filter(function ($item) use ($business_id, $products) {
      return isset($products[$item['product_id']]) && $products[$item['product_id']]->business_id == $business_id;
    });

    // --- STATE A: NORMAL CHECKOUT (No pending DB order found) ---

    // Check if there are items for this business in the session
    if ($itemsForBusiness->isEmpty()) {
      return redirect()->route('customer.preorder')->with('error', 'No items found for this business.');
    }

    Log::debug('Proceeding to normal checkout with session items.', [
      'user_id' => Auth::id(),
      'business_id' => $business_id
    ]);

    // Use the filtered session items for checkout calculations
    $checkoutPreorder = $itemsForBusiness;

    // --- Compute totals ONLY for the items being checked out now ---
    $total = 0;
    $totalAdvanceRequired = 0;
    $advance_breakdown = [];
    $requires_advance = false;

    foreach ($checkoutPreorder as $i) {
      if (!isset($products[$i['product_id']])) continue;

      $p = $products[$i['product_id']];
      $price  = (float) $p->price;
      $advAmt = (float) ($p->advance_amount ?? 0);
      $qty    = (int)   $i['quantity'];

      $total += $price * $qty;

      if ($advAmt > 0) {
        $currentAdvance = $advAmt * $qty;
        $totalAdvanceRequired += $currentAdvance;
        $requires_advance = true;
        $advance_breakdown[$p->item_name] = ['quantity' => $qty, 'advance_total' => $currentAdvance];
      }
    }

    // Filter out unavailable items after calculation
    $checkoutPreorder = $checkoutPreorder->filter(function ($i) use ($products) {
      return isset($products[$i['product_id']]) && $products[$i['product_id']]->is_available;
    })->values();

    if ($checkoutPreorder->isEmpty()) {
      return redirect()->route('customer.preorder')->with('error', 'All items for this business are currently unavailable.');
    }

    // Fetch user, customer, vendors, opening hours (unchanged)
    $user = Auth::user();
    $customer = Customer::where('user_id', $user->user_id)->first();
    $vendors = BusinessDetail::with(['paymentMethods' => fn($q) => $q->where('status', 'active')])
      ->where('business_id', $business_id)->get()->keyBy('business_id');

    $opening = \App\Models\BusinessOpeningHour::where('business_id', $business_id)->get();
    $openingHours = [];
    foreach ($opening as $row) {
      $key = strtolower($row->day_of_week);
      $openingHours[$key] = [
        'opens_at'  => $row->opens_at ? substr($row->opens_at, 0, 8) : null,
        'closes_at' => $row->closes_at ? substr($row->closes_at, 0, 8) : null,
        'is_closed' => (bool) $row->is_closed,
      ];
    }

    // --- Return the normal checkout view ---
    return view('content.customer.customer-preorder-checkout', [
      'upload_mode'            => false,
      'business_id'            => $business_id,
      'preorders'              => $checkoutPreorder,
      'products'               => $products,
      'user'                   => $user,
      'fullName'               => $user?->fullname,
      'contactNumber'          => $customer?->contact_number,
      'vendors'                => $vendors,
      'total'                  => $total,
      'total_advance_required' => $totalAdvanceRequired,
      'advance_breakdown'      => $advance_breakdown,
      'requires_advance'       => $requires_advance,
      'openingHours'           => $openingHours,
      'order'                  => null, // Required by Blade
      'preorder'               => null, // Required by Blade
    ]);
  }

  // You still need the helper method for clearing transactions if called elsewhere,
  // but the proceed() method no longer calls it directly in the main flow.
  private function clearTransactionFromSession($transactionId)
  {
    if (!$transactionId) return;

    $currentPreorders = collect(session('preorder', []));
    $cleanedPreorders = $currentPreorders->map(function ($item) use ($transactionId) {
      if (isset($item['transaction_id']) && $item['transaction_id'] == $transactionId) {
        unset($item['transaction_id']);
        Log::debug('Cleared transaction_id from session item', ['product_id' => $item['product_id'] ?? null]);
      }
      return $item;
    })->all();
    session(['preorder' => $cleanedPreorders]);
    Log::info('Attempted to clear transaction from session', ['transaction_id' => $transactionId]);
  }

  /**
   * Store preorder checkout (COD + online)
   */
  public function store(Request $request)
  {
    $request->validate([
      'business_id'    => 'required|integer|exists:business_details,business_id',
      'delivery_date'  => 'required|date|after_or_equal:tomorrow',
      'delivery_time'  => 'required|string',
      'full_name'      => 'required|string|max:255',
      'phone_number'   => ['required', 'regex:/^09\d{9}$/'],
      'region'         => 'required|string|max:100',
      'province'       => 'required|string|max:100',
      'city'           => 'required|string|max:100',
      'barangay'       => 'required|string|max:100',
      'postal_code'    => 'required|string|max:20',
      'street_name'    => 'required|string|max:255',
      'payment_option' => 'nullable|in:advance,full',
      'payment_method' => 'required|integer|exists:payment_methods,payment_method_id',
    ]);

    $user = Auth::user();
    $preorders = collect(session('preorder', []));
    if ($preorders->isEmpty()) {
      return back()->withErrors(['error' => 'Your preorder list is empty.']);
    }

    $businessId = $request->business_id;
    $products = Product::whereIn('product_id', $preorders->pluck('product_id'))->get()->keyBy('product_id');

    $items = $preorders
      ->filter(
        fn($i) =>
        isset($products[$i['product_id']]) &&
          $products[$i['product_id']]->business_id == $businessId &&
          $products[$i['product_id']]->is_available
      )
      ->map(function ($i) use ($businessId, $products) {
        $p = $products[$i['product_id']];
        $i['business_id'] = $businessId;
        // Ensure the item carries the price at time of checkout
        $i['price'] = (float) ($p->price ?? 0);
        return $i;
      })
      ->values();


    if ($items->isEmpty()) {
      return back()->withErrors(['error' => 'No available preorder products for this vendor.']);
    }

    $total = $items->sum(fn($i) => $products[$i['product_id']]->price * $i['quantity']);
    $totalAdvanceRequired = $items->sum(
      fn($i) =>
      $products[$i['product_id']]->advance_amount * $i['quantity']
    );

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
    //$deliveryData['requires_receipt'] = ($totalAdvanceRequired > 0);
    $deliveryData['requires_receipt'] = true;


    $pmId = $request->payment_method;
    $pm = PaymentMethod::findOrFail($pmId);
    $methodName = strtolower(trim($pm->method_name));
    $isCod = in_array($methodName, ['cash on delivery', 'cod', 'card on delivery']);
    $itemNotes = $request->input('item_notes', []);

    // If user selected a COD method but an advance is required, force GCash as the payment method.
    if ($isCod && $totalAdvanceRequired > 0) {
      Log::info('COD selected but advance required — forcing GCash as default payment method', [
        'user_id' => $user->user_id,
        'original_payment_method_id' => $pmId,
        'totalAdvanceRequired' => $totalAdvanceRequired,
      ]);

      // Try to find a payment method named "gcash" (case-insensitive).
      $gcash = PaymentMethod::whereRaw('LOWER(method_name) = ?', ['gcash'])->first();

      if ($gcash) {
        $pmId = $gcash->payment_method_id;
        $pm = $gcash;
        $methodName = 'gcash';
        Log::info('Overrode payment method to GCash', ['gcash_id' => $pmId]);
      } else {
        // fallback: keep original and log a warning so you can configure GCash in DB
        Log::warning('GCash payment method not found — cannot override COD to GCash', [
          'user_id' => $user->user_id,
          'original_payment_method_id' => $request->payment_method,
        ]);
      }
    }

    DB::beginTransaction();
    try {
      Log::debug('Creating CheckoutDraft', ['user_id' => $user->user_id, 'business_id' => $businessId]);

      // Decide payment option and compute EXACT amount to charge now (without persisting)
      $paymentOption = $request->payment_option ?? ($totalAdvanceRequired <= 0 ? 'full' : 'advance');

      // Ensure the delivery payload saved into the draft reflects the chosen payment option
      $deliveryData['payment_option'] = $paymentOption;

      if ($paymentOption === 'full') {
        $amountToCharge = $total;
      } else {
        // sum only advances for items that actually have advance_amount > 0
        $amountToCharge = $items->reduce(function ($carry, $i) use ($products) {
          $p = $products[$i['product_id']];
          $adv = (float)($p->advance_amount ?? 0);
          $qty = (int)$i['quantity'];
          return $carry + ($adv > 0 ? $adv * $qty : 0.0);
        }, 0.0);
      }

      // Create the draft (we store payment_option inside delivery already)
      $draft = CheckoutDraft::create([
        'user_id'           => $user->user_id,
        'payment_method_id' => $pmId,
        'total'             => $total,
        'cart'              => $items->toArray(),
        'delivery'          => $deliveryData,
        'item_notes'        => $itemNotes[$businessId] ?? [],
        'is_cod'            => $isCod,
      ]);

      // If it's COD and no advance is required, process immediately —
      // but only when we do NOT require a receipt. If requires_receipt is true
      // we must wait for receipt upload before processing/removing items.
      if ($isCod && $totalAdvanceRequired <= 0 && empty($deliveryData['requires_receipt'])) {

        Log::info('Processing COD preorder immediately', ['draft_id' => $draft->checkout_draft_id]);
        $this->processCodOrderFromDraft($draft);
        $draft->update(['processed_at' => now()]);
        DB::commit();
        $this->removeProcessedItemsFromPreorder(collect([$draft]));
        return redirect()->route('customer.orders.index')
          ->with('success', 'Your preorder has been placed successfully (Cash on Delivery).');
      }

      // Build PayMongo line items.
      // IMPORTANT:
      //  - when paymentOption === 'advance' we only include products that have advance_amount > 0
      //  - each line is aggregated (amount = unit * qty) and quantity = 1 so PayMongo shows a single amount per product
      // Build PayMongo line items properly
      $lineItems = $items->map(function ($i) use ($paymentOption, $products) {
        $product = $products[$i['product_id']];
        if (!$product) return null;

        $qty = (int)$i['quantity'];

        if ($paymentOption === 'full') {
          $unitAmount = (float)$product->price;
        } else {
          // advance selected → charge advance only if defined; skip if 0
          $unitAmount = (float)($product->advance_amount ?? 0);
          if ($unitAmount <= 0) return null;
        }

        // Build friendly label
        if ($paymentOption === 'advance' && $product->advance_amount > 0) {
          $label = sprintf(
            "%s — Advance (₱%s)",
            $product->item_name,
            number_format($product->advance_amount, 2),
            number_format($product->price, 2)
          );
        } else {
          $label = sprintf(
            "%s — Full (₱%s)",
            $product->item_name,
            number_format($product->price, 2)
          );
        }

        return [
          'name'     => $label,
          'amount'   => intval(round($unitAmount * 100, 0)), // PayMongo expects per-unit amount in centavos
          'currency' => 'PHP',
          'quantity' => $qty,
        ];
      })->filter()->values()->all();

      // If amountToCharge is effectively zero (e.g. user chose 'advance' but no advances exist),
      // treat as paying full price
      if ($amountToCharge <= 0) {
        $amountToCharge = $total;
        // rebuild line items to charge full amount for all items (safe fallback)
        $lineItems = $items->map(function ($i) use ($products) {
          $product = $products[$i['product_id']];
          $qty = (int)$i['quantity'];
          $label = sprintf("%s — Full (₱%s)", $product->item_name, number_format($product->price, 2));
          return [
            'name' => $label,
            'amount' => intval(round($product->price * $qty * 100, 0)),
            'currency' => 'PHP',
            'quantity' => 1,
          ];
        })->values()->all();
      }

      $business   = BusinessDetail::find($businessId);
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
          'description'          => 'Pre-Order payment for ' . $business?->business_name,
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
        Log::error('PayMongo response error', ['response' => $data]);
        throw new Exception("PayMongo error: " . ($data['errors'][0]['detail'] ?? 'Unknown'));
      }

      $checkoutUrl     = $data['data']['attributes']['checkout_url'];
      $paymentIntentId = $data['data']['attributes']['payment_intent']['id'];

      // Save transaction id on draft so webhook can find it later
      $draft->update(['transaction_id' => $paymentIntentId]);

      // mark related session items as pending using transaction id
      $productIdsInTransaction = $items->pluck('product_id')->all();
      $currentPreorders = collect(session('preorder', []));
      $updatedPreorders = $currentPreorders->map(function ($sessionItem) use ($productIdsInTransaction, $paymentIntentId) {
        if (in_array($sessionItem['product_id'], $productIdsInTransaction)) {
          $sessionItem['transaction_id'] = $paymentIntentId;
        }
        return $sessionItem;
      })->all();
      session(['preorder' => $updatedPreorders]);

      // Store flow info
      session([
        'checkout_flow_draft_ids' => [$draft->checkout_draft_id],
        'checkout_flow_type'      => 'preorder',
      ]);

      DB::commit();
      Log::info('Redirecting to PayMongo', ['draft_id' => $draft->checkout_draft_id, 'amount_charged' => $amountToCharge]);
      return redirect()->away($checkoutUrl);
    } catch (Exception $e) {
      DB::rollBack();
      Log::error('Preorder checkout failed', [
        'message' => $e->getMessage(),
        'trace'   => $e->getTraceAsString(),
      ]);
      return back()->withErrors(['error' => 'Preorder checkout failed. Please try again.']);
    }
  }

  private function createPendingOrderFromDraft(CheckoutDraft $draft)
  {
    return DB::transaction(function () use ($draft) {
      $businessId = collect($draft->cart)->pluck('business_id')->first();
      Log::info('Creating PENDING order from draft before payment', ['draft_id' => $draft->checkout_draft_id]);

      $order = Order::create([
        'user_id'           => $draft->user_id,
        'business_id'       => $businessId,
        'total'             => $draft->total,
        'delivery_date'     => $draft->delivery['delivery_date'],
        'delivery_time'     => $draft->delivery['delivery_time'] ?? now()->format('H:i:s'),
        'payment_method_id' => $draft->payment_method_id,
        'status'            => 'pending_payment', // Set an initial status
      ]);

      foreach ($draft->cart as $item) {
        $product = Product::find($item['product_id']);
        if (!$product) throw new Exception("Product {$item['product_id']} not found");

        OrderItem::create([
          'order_id'            => $order->order_id,
          'product_id'          => $product->product_id,
          'product_name'        => $product->item_name,
          'product_description' => $product->description,
          'quantity'            => (int)$item['quantity'],
          'price_at_order_time' => $item['price'],
          'order_item_status'   => 'Pending',
          'order_item_note'     => $draft->item_notes[$product->product_id] ?? null,
          'is_pre_order'        => true,
        ]);
      }

      DeliveryAddress::create(array_merge($draft->delivery, [
        'order_id'     => $order->order_id,
        'full_address' => collect($draft->delivery)->except('user_id')->filter()->reverse()->join(', '),
      ]));

      // Create the PaymentDetail record with a 'Pending' status.
      // The webhook will find and update this record.
      PaymentDetail::create([
        'order_id'           => $order->order_id,
        'payment_method_id'  => $draft->payment_method_id,
        'transaction_id'     => $draft->transaction_id, // This holds the payment_intent_id
        'amount_paid'        => 0,
        'payment_status'     => 'Pending', // CRUCIAL
        'paid_at'            => null,
      ]);

      return $order;
    });
  }

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

      DeliveryAddress::create(array_merge($draft->delivery, [
        'order_id' => $order->order_id,
        'full_address' => collect($draft->delivery)->except('user_id')->filter()->reverse()->join(', ')
      ]));

      // Create initial PaymentDetail
      $paymentDetail = PaymentDetail::create([
        'order_id' => $order->order_id,
        'payment_method_id' => $draft->payment_method_id,
        'amount_paid' => 0,
        'payment_status' => 'Pending'
      ]);

      // Determine payment option from draft data (stored in delivery)
      $paymentOption = $draft->delivery['payment_option'] ?? 'advance';

      // If the draft has a transaction_id, user completed an online payment (redirect-from-COD-to-online)
      $hadOnlinePayment = !empty($draft->transaction_id);

      if ($hadOnlinePayment) {
        // Compute how much was actually charged: if 'full' => full total; else sum advances only
        if ($paymentOption === 'full') {
          $amountPaid = (float)$draft->total;
        } else {
          // sum only the advances for items that have advance_amount
          $amountPaid = 0.0;
          foreach ($draft->cart as $c) {
            $prod = Product::find($c['product_id']);
            $amountPaid += ((float)($prod->advance_amount ?? 0) * (int)$c['quantity']);
          }
          // if for any reason amountPaid is zero but API transaction exists, fallback to draft total:
          if ($amountPaid <= 0) {
            $amountPaid = (float)$draft->total;
          }
        }

        $amountDue = max((float)$draft->total - $amountPaid, 0.00);

        // Update PaymentDetail
        $paymentDetail->transaction_id = $draft->transaction_id;
        $paymentDetail->amount_paid = $amountPaid;
        $paymentDetail->payment_status = 'Paid';
        $paymentDetail->paid_at = now();
        $paymentDetail->save();

        // PreOrder status
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
        // True offline COD: nothing paid yet
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

      Log::info('COD order created successfully from draft', ['order_id' => $order->order_id, 'draft_id' => $draft->checkout_draft_id]);
    });
  }


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

      DeliveryAddress::create(array_merge($draft->delivery, [
        'order_id' => $order->order_id,
        'full_address' => collect($draft->delivery)->except('user_id')->filter()->reverse()->join(', ')
      ]));

      // Determine payment option from draft and compute what was actually paid
      $paymentOption = $draft->delivery['payment_option'] ?? $draft->payment_option ?? 'advance';

      if ($paymentOption === 'full') {
        $amountPaid = (float)$draft->total;
      } else {
        // sum only advances present in the cart
        $amountPaid = 0.0;
        foreach ($draft->cart as $c) {
          $product = Product::find($c['product_id']);
          $amountPaid += ((float)($product->advance_amount ?? 0) * (int)$c['quantity']);
        }
        // fallback: if amountPaid is zero (no advances) treat as full paid (safe fallback)
        if ($amountPaid <= 0) {
          $amountPaid = (float)$draft->total;
        }
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

      Log::info("Order #{$order->order_id} created successfully from online draft #{$draft->checkout_draft_id}");
    });
  }

  public function processRemainingDrafts($userId)
  {
    $draftIdsInFlow = session('checkout_flow_draft_ids', []);
    if (empty($draftIdsInFlow)) {
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
        Log::error('Failed to process a remaining preorder COD draft', ['draft_id' => $draft->checkout_draft_id, 'error' => $e->getMessage()]);
        continue;
      }
    }
  }

  public function status()
  {
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
      } else if (in_array($draft->checkout_draft_id, $cancelledDraftIds)) {
        $status = 'cancelled';
        $cancelledCount++;
      } else {
        $pendingCount++;
      }
      return ['draft_id' => $draft->checkout_draft_id, 'status' => $status];
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

  // Public so it can be called from PaymentController
  public function removeProcessedItemsFromPreorder($processedDrafts, $force = false)
  {
    if (empty($processedDrafts)) return;

    $processedProductIds = [];

    foreach ($processedDrafts as $draft) {
      // If not forcing removal AND the draft requires a receipt, skip it.
      $requiresReceipt = data_get($draft, 'delivery.requires_receipt', false);

      if (!$force && $requiresReceipt) {
        Log::info('Skipping session removal for draft awaiting receipt upload', [
          'draft_id' => $draft->checkout_draft_id,
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

    if (empty($processedProductIds)) return;

    $currentPreorders = collect(session('preorder', []));
    $finalPreorders = $currentPreorders
      ->reject(fn($item) => in_array($item['product_id'], $processedProductIds))
      ->values()
      ->all();

    session(['preorder' => $finalPreorders]);

    Log::info('Cleaned items from session preorder list.', ['removed_product_ids' => $processedProductIds]);
  }


  public function getAvailability(BusinessDetail $business)
  {
    $fullyBookedDates = PreorderSchedule::where('business_id', $business->business_id)
      ->where('is_active', true)
      ->whereRaw('current_order_count >= max_orders')
      ->pluck('available_date')
      ->map(fn($date) => $date->format('Y-m-d')) // Format for Flatpickr
      ->toArray();

    return response()->json([
      'unavailable_dates' => $fullyBookedDates
    ]);
  }

  public function uploadReceipt($order_id)
  {
    // Find latest PreOrder for this user/business that still needs a receipt
    $pendingDbPreorder = PreOrder::whereHas('order', function ($q) use ($order_id) {
      $q->where('user_id', Auth::id())
        ->where('order_id', $order_id);
    })
      ->whereNull('receipt_url')
      ->with('order.items.product') // eager load items and product (if relation exists)
      ->latest('created_at')
      ->first();

    if (! $pendingDbPreorder) {
      // No pending preorder that requires receipt upload
      return redirect()->route('customer.preorder')
        ->with('error', 'No pending pre-order found that requires receipt upload.');
    }

    $order = $pendingDbPreorder->order;

    // Compute order total defensively (use stored item price if available, else product price)
    $total = 0;
    if ($order && $order->items) {
      foreach ($order->items as $item) {
        // Try to read price from the order item first (if you store it there),
        // otherwise fall back to the related product price.
        $price = null;
        if (isset($item->price) && $item->price !== null) {
          $price = (float) $item->price;
        } elseif (isset($item->product) && isset($item->product->price)) {
          $price = (float) $item->product->price;
        } else {
          $price = 0;
        }

        $qty = isset($item->quantity) ? (int) $item->quantity : 1;
        $total += $price * $qty;
      }
    }

    // Pass variables the blade expects:
    return view('content.customer.customer-upload-receipt', [
      'order'       => $order,
      'preorder'    => $pendingDbPreorder,
      'total'       => $total,
      'order_id' => $order_id,
    ]);
  }

  /**
   * Upload receipt to confirm a preorder (after payment).
   */
  public function confirmPreorder(Request $request, $order)
  {
    // Find order and authorize
    $order = Order::where('order_id', $order)->firstOrFail();
    $user = Auth::user();
    if ($order->user_id != $user->user_id) {
      abort(403, 'Unauthorized action.');
    }

    // Find preorder record
    $preorder = PreOrder::where('order_id', $order->order_id)->firstOrFail();

    // Validate receipt
    $request->validate([
      'receipt' => 'required|image|mimes:jpeg,png,jpg|max:2048', // 2MB
    ]);

    if (! $request->hasFile('receipt')) {
      return back()->withErrors(['receipt' => 'Please upload a valid receipt image.']);
    }

    // Store receipt on S3 (keeps same behaviour as earlier code)
    $file = $request->file('receipt');
    $disk = Storage::disk('s3');
    $imagePath = $file->store('products', 's3');

    if (!$imagePath) {
      throw new \Exception('Failed to store image on S3. Check AWS permissions or bucket policy.');
    }
    /**
     * @var \Illuminate\Filesystem\FilesystemAdapter $disk
     */

    $finalUrl = $disk->url($imagePath);

    // Save full URL to DB
    $preorder->receipt_url = $finalUrl;
    $preorder->preorder_status = 'Receipt Uploaded';
    $preorder->save();

    // Update PaymentDetail if present: mark Paid/paid_at when appropriate
    $paymentDetail = PaymentDetail::where('order_id', $order->order_id)->first();
    if ($paymentDetail) {
      // If already has amount_paid > 0, ensure status is Paid and paid_at set
      if ((float)($paymentDetail->amount_paid ?? 0) > 0) {
        $paymentDetail->payment_status = 'Paid';
        $paymentDetail->paid_at = $paymentDetail->paid_at ?? now();
        $paymentDetail->save();
      } else {
        // If payment detail has 0 but preorder records an advance_paid_amount (user provided proof),
        // update amount_paid from preorder and mark Paid.
        if ((float)($preorder->advance_paid_amount ?? 0) > 0) {
          $paymentDetail->amount_paid = $preorder->advance_paid_amount;
          $paymentDetail->payment_status = 'Paid';
          $paymentDetail->paid_at = $paymentDetail->paid_at ?? now();
          $paymentDetail->save();
        }
      }
    }

    // If there's a CheckoutDraft associated with the payment transaction, mark it processed and remove session items
    if (!empty($preorder->payment_transaction_id)) {
      $draft = CheckoutDraft::where('transaction_id', $preorder->payment_transaction_id)
        ->where('user_id', $user->user_id)
        ->first();

      if ($draft) {
        // mark as processed and remove items from session using your helper
        $draft->update(['processed_at' => now()]);
        $this->removeProcessedItemsFromPreorder(collect([$draft]));
      } else {
        // No draft found: fall back to removing based on order items below
      }
    }

    // If we didn't remove items above via the draft, remove session items by product IDs from the order
    // (covers edge cases where transaction_id/draft is not available)
    $productIds = OrderItem::where('order_id', $order->order_id)->pluck('product_id')->all();
    if (!empty($productIds)) {
      $currentPreorders = collect(session('preorder', []));
      $finalPreorders = $currentPreorders
        ->reject(function ($item) use ($productIds) {
          return in_array($item['product_id'] ?? null, $productIds);
        })
        ->values()
        ->all();
      session(['preorder' => $finalPreorders]);

      Log::info('Removed confirmed preorder items from session after receipt upload', [
        'user_id' => $user->user_id,
        'order_id' => $order->order_id,
        'removed_product_ids' => $productIds,
      ]);
    }

    return redirect()->route('customer.orders.index')->with('success', 'Receipt uploaded! Your preorder is now pending vendor verification.');
  }
}
