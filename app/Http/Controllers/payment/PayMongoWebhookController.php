<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\{
  Order,
  OrderItem,
  PaymentDetail,
  PreOrder,
  CheckoutDraft,
  Product,
  DeliveryAddress
};
use App\Http\Controllers\customer\CheckoutPreorderController;
use Exception;

class PayMongoWebhookController extends Controller
{
  /**
   * Handles incoming webhooks from PayMongo for all payment types.
   */
  public function handle(Request $request)
  {
    Log::info('=== PAYMONGO WEBHOOK RECEIVED ===', $request->all());
    $eventType = $request->input('data.attributes.type');

    $allowedEvents = ['checkout_session.payment.paid', 'checkout_session.payment_paid', 'payment.paid'];
    if (!in_array($eventType, $allowedEvents)) {
      Log::info('Ignoring non-payment event type.', ['type' => $eventType]);
      return response()->json(['message' => 'event_ignored'], 200);
    }

    // --- ROUTING LOGIC ---
    $attributes = $request->input('data.attributes.data.attributes');
    $metadata = $attributes['metadata'] ?? [];

    if (isset($metadata['checkout_type']) && $metadata['checkout_type'] === 'preorder') {
      return $this->handlePreorderPayment($request->input('data.attributes.data'));
    } else {
      return $this->handleCartPayment($request);
    }
  }

  /**
   * Robust: Handles preorder payments with webhook-first logic but supports cart-style pending order path.
   */
  private function handlePreorderPayment(array $paymentData)
  {
    try {
      // Resolve attributes
      $attrs = $paymentData['attributes'] ?? $paymentData;

      // Try various locations for payment_intent id
      $paymentIntentId = data_get($attrs, 'payment_intent.id')
        ?? data_get($attrs, 'payment_intent_id')
        ?? data_get($paymentData, 'id')
        ?? data_get($attrs, 'payments.0.payment_intent.id')
        ?? null;

      Log::debug('Preorder Webhook: resolved paymentIntentId', [
        'candidates' => [
          'payment_intent.id' => data_get($attrs, 'payment_intent.id'),
          'payment_intent_id' => data_get($attrs, 'payment_intent_id'),
          'top_level_id' => data_get($paymentData, 'id'),
          'payments_0_pi' => data_get($attrs, 'payments.0.payment_intent.id'),
        ],
        'chosen' => $paymentIntentId
      ]);

      if (!$paymentIntentId) {
        Log::warning('Preorder Webhook: Could not find payment_intent id in payload', ['payload' => $paymentData]);
        return response()->json(['message' => 'no_payment_intent_found'], 200);
      }

      // Try to find draft and existing pending payment detail (created by createPendingOrderFromDraft)
      $draft = CheckoutDraft::where('transaction_id', $paymentIntentId)->first();
      $existingPaymentDetail = PaymentDetail::where('transaction_id', $paymentIntentId)->first();

      // Resolve paid amount (try multiple shapes)
      $paidAmountCents = data_get($attrs, 'amount')
        ?? data_get($attrs, 'payments.0.attributes.amount')
        ?? data_get($paymentData, 'attributes.payments.0.attributes.amount')
        ?? null;
      $paidAmount = $paidAmountCents !== null ? ($paidAmountCents / 100) : 0;

      // If we already created an Order + PaymentDetail at checkout (cart-style), update that record
      if ($existingPaymentDetail && $draft) {
        DB::transaction(function () use ($draft, $existingPaymentDetail, $paidAmount) {
          $order = $existingPaymentDetail->order;
          if (!$order) {
            throw new Exception('Existing PaymentDetail has no Order to update.');
          }

          // Update PaymentDetail
          $existingPaymentDetail->update([
            'amount_paid' => $paidAmount,
            'payment_status' => 'Paid',
            'paid_at' => now(),
          ]);

          // Compute total advance required for preorder record
          $totalAdvanceRequired = 0;
          foreach ($draft->cart as $item) {
            $product = Product::find($item['product_id']);
            $totalAdvanceRequired += ((float)$product->advance_amount * (int)$item['quantity']);
          }

          $paymentOption = $draft->delivery['payment_option'] ?? $draft->payment_option ?? 'advance';
          $amountDue = max($draft->total - $paidAmount, 0);

          // Create or update PreOrder
          $existingPre = PreOrder::where('order_id', $order->order_id)->first();
          if (!$existingPre) {
            PreOrder::create([
              'order_id' => $order->order_id,
              'total_advance_required' => $totalAdvanceRequired,
              'advance_paid_amount' => $paidAmount,
              'amount_due' => $amountDue,
              'payment_transaction_id' => $draft->transaction_id,
              'payment_option' => $paymentOption,
              'receipt_url' => null,
              'preorder_status' => 'confirmed',
            ]);
          } else {
            $existingPre->update([
              'advance_paid_amount' => $paidAmount,
              'amount_due' => $amountDue,
              'preorder_status' => 'confirmed',
            ]);
          }

          // Mark draft processed and cleanup session/preorder list
          $draft->update(['processed_at' => now()]);
          app(CheckoutPreorderController::class)->removeProcessedItemsFromPreorder(collect([$draft]));

          Log::info("Preorder Webhook (update path): Updated PaymentDetail and created/updated PreOrder for Order #{$order->order_id}");
        });

        return response()->json(['message' => 'ok'], 200);
      }

      // FALLBACK: No existing PaymentDetail — original behavior (webhook creates order & preorder)
      if (!$draft) {
        Log::warning('Preorder Webhook: No matching checkout_draft found.', ['paymentIntentId' => $paymentIntentId]);
        return response()->json(['message' => 'no_record_found'], 200);
      }

      if ($draft->processed_at) {
        Log::info('Preorder Webhook: Draft already processed.', ['draft_id' => $draft->checkout_draft_id]);
        return response()->json(['message' => 'duplicate_ignored'], 200);
      }

      DB::transaction(function () use ($draft, $paidAmount) {
        $businessId = collect($draft->cart)->pluck('business_id')->first();
        Log::debug('Preorder Webhook: Creating order from draft', ['draft_id' => $draft->checkout_draft_id]);

        // 1. Create the Order
        $order = Order::create([
          'user_id'           => $draft->user_id,
          'business_id'       => $businessId,
          'total'             => $draft->total,
          'delivery_date'     => $draft->delivery['delivery_date'] ?? now()->toDateString(),
          'delivery_time'     => $draft->delivery['delivery_time'] ?? now()->format('H:i:s'),
          'payment_method_id' => $draft->payment_method_id,
        ]);

        // 2. Create Order Items & total advance
        $totalAdvanceRequired = 0;
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

          $totalAdvanceRequired += ((float)$product->advance_amount * (int)$item['quantity']);
        }

        // 3. Delivery Address
        DeliveryAddress::create(array_merge($draft->delivery, [
          'order_id'     => $order->order_id,
          'full_address' => collect($draft->delivery)->except('user_id')->filter()->reverse()->join(', '),
        ]));

        // 4. Payment Detail
        PaymentDetail::create([
          'order_id'           => $order->order_id,
          'payment_method_id'  => $draft->payment_method_id,
          'transaction_id'     => $draft->transaction_id,
          'amount_paid'        => $paidAmount,
          'payment_status'     => 'Paid',
          'paid_at'            => now(),
        ]);

        // 5. PreOrder
        $paymentOption = $draft->delivery['payment_option'] ?? $draft->payment_option ?? 'advance';
        $amountDue     = max($draft->total - $paidAmount, 0);
        PreOrder::create([
          'order_id'               => $order->order_id,
          'total_advance_required' => $totalAdvanceRequired,
          'advance_paid_amount'    => $paidAmount,
          'amount_due'             => $amountDue,
          'payment_transaction_id' => $draft->transaction_id,
          'payment_option'         => $paymentOption,
          'receipt_url'            => null,
          'preorder_status'        => 'confirmed',
        ]);

        // 6. Mark draft processed & cleanup session
        $draft->update(['processed_at' => now()]);
        app(CheckoutPreorderController::class)->removeProcessedItemsFromPreorder(collect([$draft]));

        Log::info("Preorder Webhook (create path): Successfully created Order #{$order->order_id} from Draft #{$draft->checkout_draft_id}.");
      });
    } catch (Exception $e) {
      Log::error('Preorder Webhook: CRITICAL ERROR.', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'payload_debug' => $paymentData
      ]);
      return response()->json(['message' => 'processing_error'], 500);
    }

    return response()->json(['message' => 'ok'], 200);
  }

  /**
   * UNCHANGED: This is the original logic for cart payments.
   */
  private function handleCartPayment(Request $request)
  {
    try {
      $transactionId = $request->input('data.attributes.data.id');
      $attributes = $request->input('data.attributes.data.attributes');
      $paymentDetail = PaymentDetail::where('transaction_id', $transactionId)->first();

      if (!$paymentDetail) {
        Log::warning('Cart Webhook: No matching payment_details record found.', ['transactionId' => $transactionId]);
        return response()->json(['message' => 'no_record_found'], 200);
      }
      if ($paymentDetail->payment_status === 'Paid') {
        Log::info('Cart Webhook: Payment already processed. Ignoring duplicate.', ['order_id' => $paymentDetail->order_id]);
        return response()->json(['message' => 'duplicate_ignored'], 200);
      }

      DB::transaction(function () use ($paymentDetail, $attributes) {
        $orderId = $paymentDetail->order_id;
        $paid_amount = $attributes['payments'][0]['attributes']['amount'] / 100;

        $paymentDetail->update([
          'amount_paid' => $paid_amount,
          'payment_status' => 'Paid',
          'paid_at' => now(),
        ]);

        $preOrder = PreOrder::where('order_id', $orderId)->first();
        if ($preOrder) {
          $preOrder->update([
            'advance_paid_amount' => $paid_amount,
            'amount_due' => DB::raw(" (SELECT total FROM orders WHERE order_id = {$orderId}) - {$paid_amount} "),
            'preorder_status' => 'confirmed',
          ]);
          Log::info("Cart Webhook (via PreOrder update): Pre-Order #{$orderId} status updated to confirmed.");
        }

        $orderItems = OrderItem::where('order_id', $orderId)->get();
        $isAnyItemPreOrder = $orderItems->contains('is_pre_order', true);
        $sessionKey = $isAnyItemPreOrder ? 'preorder' : 'cart';
        $productIdsToRemove = $orderItems->pluck('product_id')->all();

        if (session()->has($sessionKey)) {
          $currentItems = collect(session($sessionKey, []));
          $finalItems = $currentItems->reject(fn($item) => in_array($item['product_id'], $productIdsToRemove))->values()->all();
          session([$sessionKey => $finalItems]);
          Log::info("Cart Webhook: Cleaned {$sessionKey} session for user.");
        }
        Log::info("Cart Webhook: Successfully processed payment for Order #{$orderId}.");
      });
    } catch (Exception $e) {
      Log::error('Cart Webhook: CRITICAL ERROR.', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return response()->json(['message' => 'processing_error'], 500);
    }
    return response()->json(['message' => 'ok'], 200);
  }
}
