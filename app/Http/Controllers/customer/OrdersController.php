<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\{Order, OrderItem, PreOrder};
use Exception;
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
}
