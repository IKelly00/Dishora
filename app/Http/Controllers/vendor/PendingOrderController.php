<?php

namespace App\Http\Controllers\vendor;

use App\Http\Controllers\Controller;
use App\Models\{Order, Vendor, OrderItem, PaymentDetail};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log, DB};
use Carbon\Carbon;

class PendingOrderController extends Controller
{
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

  /**
   * Show active orders (orders that are NOT pre-orders).
   */
  public function activeOrders(Request $request)
  {
    $statusFilter = $request->query('status', 'All');
    $dateFrom = $request->query('date_from');
    $dateTo = $request->query('date_to');

    $vendor = $this->getVendor();
    $activeBusinessId = $vendor ? session('active_business_id') : null;

    // Build query on orders (exclude orders that already exist in pre_orders)
    $preorderSubquery = DB::table('pre_orders')->select('order_id');

    // === FIX: Add 'paymentDetails' to the eager-load ===
    $query = Order::with(['user', 'paymentMethod', 'items.product', 'paymentDetails'])
      ->whereNotIn('order_id', $preorderSubquery)
      ->when($statusFilter && $statusFilter !== 'All', function ($q) use ($statusFilter) {
        // Filter by order item status (orders that have items with this status)
        $q->whereHas('items', fn($qi) => $qi->where('order_item_status', $statusFilter));
      });

    if ($dateFrom) {
      $query->whereDate('created_at', '>=', $dateFrom);
    }
    if ($dateTo) {
      $query->whereDate('created_at', '<=', $dateTo);
    }

    if ($activeBusinessId) {
      $query->where('business_id', $activeBusinessId);
    }

    $rows = $query->orderBy('created_at', 'desc')->get()->map(function ($order) {
      // Prefer items where is_pre_order = 1 (should usually be false for active orders),
      // otherwise use all items
      $itemsCollection = $order->items->where('is_pre_order', 1);
      if ($itemsCollection->isEmpty()) {
        $itemsCollection = $order->items;
      }

      $mappedItems = $itemsCollection->map(function ($it) {
        $price = $it->price_at_order_time ?? $it->price ?? 0;
        $qty = $it->quantity ?? 1;
        return [
          'order_item_id' => $it->order_item_id ?? null,
          'product_id'    => $it->product_id ?? null,
          'product_name'  => $it->product?->item_name ?? $it->product_name ?? 'N/A',
          'quantity'      => (int)$qty,
          'price'         => (float)$price,
          'subtotal'      => (float)($price * $qty),
          'note'          => $it->order_item_note ?? null,
          'status'        => $it->order_item_status ?? null,
          'image_url'     => $it->product?->image_url ?? null,
        ];
      });

      $itemsSubtotal = $mappedItems->sum('subtotal');
      $orderTotal = (float)($order->total ?? $itemsSubtotal);

      // === FIX: Get the actual amount paid from paymentDetails ===
      $amountPaid = (float)($order->paymentDetails->sum('amount_paid'));

      Log::debug('Processing order for modal', [
        'order_id' => $order->order_id,
        'order_total' => $orderTotal,
        'calculated_amount_paid' => $amountPaid,
        'payment_details_count' => $order->paymentDetails->count(), // How many payment records found?
        'payment_details_ids' => $order->paymentDetails->pluck('payment_detail_id')->toArray() // Which payment records?
      ]);

      // Representative item (for status / food_name / image)
      $firstItem = $mappedItems->first();
      $foodName = $firstItem['product_name'] ?? 'N/A';
      $foodImage = $firstItem['image_url'] ?? null;

      $orderDate = $order->created_at;
      if (!$orderDate instanceof Carbon) {
        $orderDate = Carbon::parse($orderDate);
      }

      // Representative status — prefer first item's status
      $repStatus = $mappedItems->first()['status'] ?? 'Pending';

      return (object) [
        'id' => $order->order_id ?? $order->id ?? null,
        'order_id' => $order->order_id ?? $order->id ?? null,
        'customer_name' => $order->user?->fullname ?: $order->user?->username ?: 'Unknown Customer',
        'order_date' => $orderDate,
        'payment_method' => $order->paymentMethod?->method_name ?? 'N/A',
        // no preorder fields here
        'preorder_status' => null,
        'status' => $repStatus,
        'items' => $mappedItems->values()->all(),
        'items_subtotal' => (float)$itemsSubtotal,
        'total' => (float)$orderTotal,

        // === FIX: Use the correct values ===
        // We use 'advance_paid' as the key because the modal JS already uses it
        'advance_paid' => $amountPaid,
        // Calculate the actual amount due
        'amount_due' => max(0, $orderTotal - $amountPaid),

        'receipt_url' => null,
        'notes' => $mappedItems->pluck('note')->filter()->join("\n") ?: null,
        'delivery_date' => $order->delivery_date ?? null,
        'delivery_time' => $order->delivery_time ?? null,

        'food_name' => $foodName,
        'food_image' => $foodImage,
      ];
    });

    $viewData = $this->buildViewData($vendor, [
      'activeOrders' => $rows,
      'statusFilter' => $statusFilter,
      'dateFrom' => $dateFrom,
      'dateTo' => $dateTo,
    ]);

    Log::debug('active-orders-sample', [
      'first_row_status' => optional($rows->first())->status ?? null,
      'rows_count' => $rows->count(),
    ]);

    return view('content.vendor.vendor-pending-order', $viewData);
  }

  /**
   * Update order item status.
   *
   * Accepts either:
   *  - an order id (updates all items for that order), or
   *  - an order_item id (updates only that item)
   *
   * Returns JSON when request expects JSON (AJAX), otherwise redirects back.
   */
  public function updateStatus(Request $request, $id)
  {
    $request->validate([
      'status' => 'required|in:Pending,Preparing,For Delivery,Completed,Cancelled'
    ]);

    $newStatus = $request->input('status');

    $vendor = $this->getVendor();
    $activeBusinessId = $vendor ? session('active_business_id') : null;

    // Determine if $id is an Order id or an OrderItem id
    $order = Order::find($id);

    try {
      DB::beginTransaction();

      if ($order) {
        // it's an order id — ensure vendor has access to this business
        if ($activeBusinessId && $order->business_id != $activeBusinessId) {
          DB::rollBack();
          if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to modify this order'], 403);
          }
          return redirect()->back()->with('error', 'Unauthorized to modify this order');
        }

        // update all items under this order
        $affected = OrderItem::where('order_id', $order->order_id)
          ->update(['order_item_status' => $newStatus]);

        Log::info('Updated order items status for order', [
          'order_id' => $order->order_id,
          'updated_count' => $affected,
          'new_status' => $newStatus,
          'vendor_id' => $vendor?->vendor_id ?? null,
        ]);

        // === START FIX 1 ===
        // If the order status has been set to Completed, update payment
        if (strtolower($newStatus) === 'completed') {

          PaymentDetail::updateOrCreate(
            ['order_id' => $order->order_id], // Find by order_id
            [
              'amount_paid'       => $order->total,    // Set amount_paid to the order's total
              'payment_status'    => 'Paid',           // Mark its status as 'Paid'
              'paid_at'           => now(),            // Set the payment time to now
              'payment_method_id' => $order->payment_method_id // Ensure this is set
            ]
          );

          Log::info('Updated payment_details for completed order', [
            'order_id' => $order->order_id,
          ]);
        }
        // === END FIX 1 ===

        DB::commit();

        if ($request->expectsJson()) {
          return response()->json([
            'success' => true,
            'message' => 'Order items updated',
            'updated' => $affected,
          ]);
        }

        return redirect()->back()->with('success', 'Order items updated successfully');
      } else {
        // Not an Order — treat as OrderItem id (backwards compatibility)
        $orderItem = OrderItem::findOrFail($id);
        $orderOfItem = Order::find($orderItem->order_id); // Load the parent order

        // check vendor access (if possible)
        if ($activeBusinessId && $orderOfItem && $orderOfItem->business_id != $activeBusinessId) {
          DB::rollBack();
          if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to modify this order item'], 403);
          }
          return redirect()->back()->with('error', 'Unauthorized to modify this order item');
        }

        $orderItem->order_item_status = $newStatus;
        $orderItem->save();

        Log::info('Updated single order item status', [
          'order_item_id' => $orderItem->order_item_id,
          'order_id' => $orderItem->order_id,
          'new_status' => $newStatus,
          'vendor_id' => $vendor?->vendor_id ?? null,
        ]);

        // If a single item was set to Completed, check if *all* order items are now Completed/Cancelled.
        // If yes, mark payment_details as Paid.
        if (strtolower($newStatus) === 'completed' || strtolower($newStatus) === 'cancelled') {
          $hasPending = OrderItem::where('order_id', $orderItem->order_id)
            ->whereNotIn('order_item_status', ['Completed', 'Cancelled'])
            ->exists();

          if (!$hasPending && $orderOfItem) { // Check if all items are done AND we have the parent order
            // === START FIX 2 ===
            PaymentDetail::updateOrCreate(
              ['order_id' => $orderOfItem->order_id], // Find by order_id
              [
                'amount_paid'       => $orderOfItem->total,    // Set amount_paid to the order's total
                'payment_status'    => 'Paid',           // Mark its status as 'Paid'
                'paid_at'           => now(),            // Set the payment time to now
                'payment_method_id' => $orderOfItem->payment_method_id // Ensure this is set
              ]
            );

            Log::info('Marked payment as Paid because all order items finished', [
              'order_id' => $orderOfItem->order_id,
            ]);
            // === END FIX 2 ===

          } else {
            Log::info('Not all order items are completed; skipping payment update', [
              'order_id' => $orderItem->order_id,
            ]);
          }
        }

        DB::commit();

        if ($request->expectsJson()) {
          return response()->json([
            'success' => true,
            'message' => 'Order item updated',
            'updated' => 1,
          ]);
        }

        return redirect()->back()->with('success', 'Order item status updated successfully');
      }
    } catch (\Throwable $e) {
      DB::rollBack();
      Log::error('Failed to update status', [
        'id' => $id,
        'status' => $newStatus,
        'error' => $e->getMessage(),
      ]);

      if ($request->expectsJson()) {
        return response()->json(['success' => false, 'message' => 'Failed to update status', 'error' => $e->getMessage()], 500);
      }

      return redirect()->back()->with('error', 'Failed to update status');
    }
  }

  public function show($id)
  {
    $order = Order::with([
      'user',
      'paymentMethod',
      'items.product',
      'paymentDetails',
      'deliveryAddress'
    ])->findOrFail($id);

    return view('orders.show', compact('order'));
  }

  public function getAllOrders()
  {
    $orders = Order::with(['user', 'paymentMethod', 'items'])
      ->orderBy('created_at', 'desc')
      ->paginate(15);

    return view('orders.index', compact('orders'));
  }

  public function getOrdersByStatus($status)
  {
    $validStatuses = ['Pending', 'Preparing', 'For Delivery', 'Completed', 'Cancelled'];

    if (!in_array($status, $validStatuses)) {
      abort(404);
    }

    $orders = Order::with(['user', 'paymentMethod', 'items'])
      ->whereHas('items', function ($query) use ($status) {
        $query->where('order_item_status', $status);
      })
      ->orderBy('created_at', 'desc')
      ->paginate(15);

    return view('orders.by-status', compact('orders', 'status'));
  }

  public function destroy($id)
  {
    $order = Order::findOrFail($id);

    // Only allow deletion of cancelled orders
    if ($order->order_status !== 'Cancelled') {
      return redirect()->back()->with('error', 'Only cancelled orders can be deleted');
    }

    $order->delete();

    return redirect()->back()->with('success', 'Order deleted successfully');
  }
}
