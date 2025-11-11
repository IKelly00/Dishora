<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\{Order, OrderItem, PreOrder};
use Illuminate\Support\Facades\Http;
use Exception;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class OrdersController extends Controller
{
  public function index(Request $request)
  {
    $user = Auth::user();

    // Count only orders that still have at least one incomplete item
    $totalOrders = Order::where('user_id', $user->user_id)
      ->whereHas('items', fn($q) => $q->where('order_item_status', '!=', 'Completed'))
      ->count();

    // Fetch paginated orders (still have incomplete items)
    $orders = Order::with([
      'business',
      'items',
      'items.product',
      'paymentDetails.paymentMethod',
      'paymentMethod',
      'paymentDetails',
      'preorderDetail', // This relation is used for the receipt check
    ])
      ->where('user_id', $user->user_id)
      ->whereHas('items', fn($q) => $q->where('order_item_status', '!=', 'Completed'))
      ->orderBy('created_at', 'desc')
      ->paginate(10);

    $orders->getCollection()->transform(function ($order) use ($user) {
      // Determine item-level statuses
      $statuses = $order->items->pluck('order_item_status')
        ->map(fn($s) => ucfirst(strtolower(trim((string)$s))))
        ->unique()
        ->values();

      $hasForDelivery = $statuses->contains('For Delivery');
      $hasPreparing   = $statuses->contains('Preparing');
      $hasCompleted   = $statuses->contains('Completed');
      $hasPending     = $statuses->contains('Pending');
      $hasCancelled   = $statuses->contains('Cancelled');

      // Order-level status (priority)
      if ($hasForDelivery) {
        $order->order_status = 'For Delivery';
      } elseif (($hasPreparing || $hasCompleted) && $hasPending) {
        $order->order_status = 'Preparing';
      } elseif ($hasPreparing) {
        $order->order_status = 'Preparing';
      } elseif ($hasCompleted && !$hasPending) {
        $order->order_status = 'Completed';
      } elseif ($hasPending) {
        $order->order_status = 'Pending';
      } elseif ($hasCancelled) {
        $order->order_status = 'Cancelled';
      } else {
        $order->order_status = $statuses->first() ?? 'Pending';
      }

      // Determine order type
      $isPreorder = $order->items->contains(fn($it) => (int)($it->is_pre_order ?? 0) === 1)
        || (!empty($order->preorderDetail));

      $order->order_type = $isPreorder ? 'Preorder' : 'Order';

      // Combined label — e.g. "Pending Preorder"
      $order->order_status_label = "{$order->order_status} {$order->order_type}";

      // Are ALL items still Pending?
      $order->all_pending = $order->items->every(fn($it) => trim($it->order_item_status ?? '') === 'Pending');

      // Check Preorders (receipt logic applies only for Preorders)
      // Check Preorders (receipt logic applies only for Preorders)
      if ($isPreorder) {

        // Loop through all order_items: stop as soon as one needs advance
        $hasAdvanceRequired = $order->items->contains(function ($item) {
          $product = $item->product ?? null;
          return $product && (float)($product->advance_amount ?? 0) > 0;
        });

        // needs_receipt true if any item requires advance and receipt not yet uploaded
        $order->needs_receipt = $hasAdvanceRequired
          && !empty($order->preorderDetail)
          && empty($order->preorderDetail->receipt_url);
      } else {
        // Regular orders (non-preorder) never require receipts
        $order->needs_receipt = false;
      }

      return $order;
    });


    return view('content.customer.customer-orders', [
      'orders' => $orders,
      'totalOrders' => $totalOrders
    ]);
  }

  /**
   * Attempt to cancel an existing order for the authenticated user.
   * Important behaviors:
   *  - Verifies that all order items are still in 'Pending' status before canceling.
   *  - For online payments: finds the associated payment via PaymentDetail.transaction_id,
   *    looks up the 'pay_...' ID from the Payment Intent, and issues a refund via PayMongo.
   *  - For COD/offline: simply updates order items and payment detail statuses locally.
   *  - All external API calls and DB updates are logged for auditability.
   */
  public function cancel(Request $request, $order_id)
  {
    Log::info("Attempting to cancel order #{$order_id} for user " . Auth::id(), ['method' => __METHOD__]);

    DB::beginTransaction();
    try {
      $order = Order::with('paymentDetails.paymentMethod', 'items', 'preorderDetail')
        ->where('order_id', $order_id)
        ->where('user_id', Auth::id())
        ->firstOrFail();

      $canCancel = $order->items->every(fn($item) => $item->order_item_status === 'Pending');

      if (!$canCancel) {
        DB::rollBack();
        Log::warning('Cancel attempt for non-cancellable order', ['order_id' => $order->order_id, 'user_id' => Auth::id()]);
        return back()->with('error', 'This order can no longer be cancelled as it is already being processed.');
      }

      $paymentDetail = $order->paymentDetails->first();
      $paymentMethod = $paymentDetail?->paymentMethod;
      $isCod = false;

      if ($paymentMethod) {
        $methodName = strtolower(trim($paymentMethod->method_name));
        $isCod = in_array($methodName, ['cash', 'cod', 'card on delivery']);
      }

      if ($isCod || !$paymentDetail || !$paymentDetail->transaction_id || $paymentDetail->payment_status !== 'Paid') {
        Log::info("Cancelling COD/Offline order #{$order->order_id} locally.");

        $order->items()->update(['order_item_status' => 'Cancelled']);
        if ($paymentDetail) {
          $paymentDetail->update(['payment_status' => 'Cancelled']);
        }
      } else {
        Log::info("Processing PayMongo refund for order #{$order->order_id}");

        $paymentIntentId = $paymentDetail->transaction_id;
        $refundAmount = (int) round($order->total * 100);
        $paymongoSecret = config('services.paymongo.secret');

        Log::info("Retrieving Payment Intent {$paymentIntentId} to find payment_id");

        $retrieveResponse = Http::withHeaders([
          'accept' => 'application/json',
          'authorization' => 'Basic ' . base64_encode($paymongoSecret . ':')
        ])->get("https://api.paymongo.com/v1/payment_intents/{$paymentIntentId}");

        $intentData = $retrieveResponse->json();

        $paymentId = $intentData['data']['attributes']['payments'][0]['id'] ?? null;

        if (!$paymentId) {
          Log::error("Could not find a successful payment_id for Intent {$paymentIntentId}", ['response' => $intentData]);
          throw new Exception('Could not find the associated charge for this order.');
        }

        Log::info("Found payment_id {$paymentId} for refund.");

        $response = Http::withHeaders([
          'accept' => 'application/json',
          'content-type' => 'application/json',
          'authorization' => 'Basic ' . base64_encode($paymongoSecret . ':')
        ])->post('https://api.paymongo.com/v1/refunds', [
          'data' => [
            'attributes' => [
              'amount' => $refundAmount,
              'payment_id' => $paymentId,
              'reason' => 'requested_by_customer'
            ]
          ]
        ]);

        $responseData = $response->json();

        if ($response->successful() && isset($responseData['data']['attributes']['status'])) {

          Log::info("PayMongo refund successful for order #{$order->order_id}", ['response' => $responseData]);

          $order->items()->update(['order_item_status' => 'Cancelled']);

          $paymentDetail->update([
            'payment_status' => 'Refunded',
          ]);
        } else {
          Log::error("PayMongo refund FAILED for order #{$order->order_id}", ['response' => $responseData]);
          throw new Exception('The payment gateway rejected the refund request. Please contact support.');
        }
      }

      // [START] ADD PRE-ORDER CANCELLATION
      if ($order->preorderDetail) {
        Log::info("Updating preorder_detail status to 'cancelled' for order #{$order->order_id}");
        $order->preorderDetail->update([
          'preorder_status' => 'cancelled', // or 'Cancelled', whatever your status string is
          'updated_at' => now(),
        ]);
      }

      // Notify the vendor that the customer (actor) cancelled this order.
      try {
        $order->load('business.vendor.user'); // Make sure relations are loaded

        if ($order->business && $order->business->vendor && $order->business->vendor->user) {

          $vendorUser = $order->business->vendor->user;
          $customerUser = Auth::user(); // The customer who is cancelling
          $notify = app(NotificationService::class);

          // [START] DYNAMIC URL LOGIC
          $vendorUrl = $order->preorderDetail
            ? '/vendor/orders/preorder'
            : '/vendor/orders/cart';
          // [END] DYNAMIC URL LOGIC

          $notify->createNotification([
            'user_id'         => $vendorUser->user_id, // The Vendor (recipient)
            'actor_user_id'   => $customerUser->user_id, // The Customer (actor)
            'event_type'      => 'ORDER_CANCELLED_BY_CUSTOMER',
            'reference_table' => 'orders, preorder',
            'reference_id'    => $order->order_id,
            'business_id'     => $order->business_id,
            'recipient_role'  => 'vendor',
            'payload' => [
              'order_id'       => $order->order_id,
              'title'          => "Order with id #{$order->order_id} Cancelled",
              'excerpt'        => "The customer has cancelled order with id #{$order->order_id}.",
              'status'         => 'Cancelled',
              'url'            => $vendorUrl,
            ]
          ]);
        } else {
          Log::warning("Could not find vendor user to notify for order cancellation", [
            'order_id' => $order->order_id
          ]);
        }
      } catch (\Throwable $e) {
        // IMPORTANT: Do NOT re-throw.
        // We do not want a notification failure to roll back the cancellation.
        Log::error('Failed to send order cancellation notification', [
          'order_id' => $order->order_id,
          'error' => $e->getMessage()
        ]);
      }

      DB::commit();
      return redirect()->route('customer.orders.index')
        ->with('success', 'Order has been successfully cancelled.')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate') // HTTP 1.1.
        ->header('Pragma', 'no-cache') // HTTP 1.0.
        ->header('Expires', '0');
    } catch (Exception $e) {
      DB::rollBack();
      Log::error("Failed to cancel order #{$order_id}", ['method' => __METHOD__, 'error' => $e->getMessage()]);
      return back()->with('error', 'Failed to cancel order: ' . $e->getMessage());
    }
  }
}
