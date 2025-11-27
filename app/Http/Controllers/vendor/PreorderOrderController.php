<?php

namespace App\Http\Controllers\vendor;

use App\Http\Controllers\Controller;
use App\Models\{Order, Vendor, OrderItem, Preorder, PaymentDetail, BusinessDetail};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PreorderOrderController extends Controller
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

    $businessStatus        = $business?->verification_status ?? 'Unknown';
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
   * Show Pre-Orders (only items marked with is_pre_order = 1)
   */
  public function preorderOrders(Request $request)
  {
    $statusFilter = $request->query('status', 'All');
    $dateFrom = $request->query('date_from');
    $dateTo = $request->query('date_to');

    $vendor = $this->getVendor();
    $activeBusinessId = $vendor ? session('active_business_id') : null;

    // Eager load order, user, paymentMethod, items and each product
    $query = Preorder::with(['order.user', 'order.paymentMethod', 'order.items.product'])
      ->when($statusFilter && $statusFilter !== 'All', fn($q) => $q->where('preorder_status', $statusFilter));

    if ($dateFrom) {
      $query->whereDate('created_at', '>=', $dateFrom);
    }
    if ($dateTo) {
      $query->whereDate('created_at', '<=', $dateTo);
    }

    if ($activeBusinessId) {
      $query->whereHas('order', fn($q) => $q->where('business_id', $activeBusinessId));
    }

    $rows = $query->orderBy('created_at', 'desc')->get()->map(function ($pre) {
      $order = $pre->order;
      $itemsCollection = collect();

      if ($order) {
        // prefer items flagged is_pre_order, fallback to all items
        $itemsCollection = $order->items->where('is_pre_order', 1);
        if ($itemsCollection->isEmpty()) {
          $itemsCollection = $order->items;
        }
      }

      // Map items
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
      $orderTotal = (float)($order?->total ?? $itemsSubtotal);

      $advancePaid = (float)($pre->advance_paid_amount ?? $pre->amount_paid ?? 0);
      $amountDue = (float)($pre->amount_due ?? ($pre->total_advance_required ?? 0)) - $advancePaid;
      $receiptUrl = $pre->receipt_url ?? $pre->payment_receipt_url ?? null;

      // Choose a representative product image/name for the table (first item)
      $firstItem = $mappedItems->first();
      $foodName = $firstItem['product_name'] ?? 'N/A';
      $foodImage = $firstItem['image_url'] ?? null;

      $itemStatuses = $mappedItems->pluck('status')->unique();
      $firstItem = $mappedItems->first();

      if ($itemStatuses->contains('Pending')) {
        $currentStatus = 'Pending';
      } elseif ($itemStatuses->contains('Preparing')) {
        $currentStatus = 'Preparing';
      } elseif ($itemStatuses->contains('For Delivery')) {
        $currentStatus = 'For Delivery';
      } elseif ($itemStatuses->every(function ($s) {
        return $s === 'Completed';
      })) {
        // Only 'Completed' if ALL items are 'Completed'
        $currentStatus = 'Completed';
      } elseif ($itemStatuses->every(function ($s) {
        return $s === 'Cancelled';
      })) {
        // Only 'Cancelled' if ALL items are 'Cancelled'
        $currentStatus = 'Cancelled';
      } elseif ($firstItem) {
        // Fallback for mixed states (e.g., Completed + Cancelled)
        $currentStatus = $firstItem['status'] ?? 'Pending';
      } else {
        // No items found, safest default for the dropdown
        $currentStatus = 'Pending';
      }


      // Ensure order_date is Carbon
      $orderDate = $order?->created_at ?? $pre->created_at;
      if (! $orderDate instanceof Carbon) {
        $orderDate = Carbon::parse($orderDate);
      }

      $advancePaid = (float)($pre->advance_paid_amount ?? $pre->amount_paid ?? 0);
      $computedAmountDue = (float)$pre->amount_due;


      return (object) [
        'id' => $pre->pre_order_id ?? $pre->id ?? null,
        'order_id' => $order->order_id ?? $order->id ?? null,
        'customer_name' => $order->user?->fullname ?: $order->user?->username ?: 'Unknown Customer',
        'order_date' => $orderDate,
        'payment_method' => $order->paymentMethod?->method_name ?? 'N/A',
        'preorder_status' => $pre->preorder_status ?? 'N/A',
        'status' => $currentStatus,
        'items' => $mappedItems->values()->all(),
        'items_subtotal' => (float)$itemsSubtotal,
        'total' => (float)$orderTotal,
        'advance_paid' => $advancePaid,
        'amount_due' => $computedAmountDue,
        'receipt_url' => $receiptUrl,
        'notes' => $mappedItems->pluck('note')->filter()->join("\n") ?: $pre->note ?? null,
        'delivery_date' => $pre->delivery_date ?? $order->delivery_date ?? null,
        'delivery_time' => $pre->delivery_time ?? $order->delivery_time ?? null,
        'food_name' => $foodName,
        'food_image' => $foodImage,
        'proof_of_delivery' => $pre->proof_of_delivery, 
      ];
    });

    $viewData = $this->buildViewData($vendor, [
      'preorderOrders' => $rows,
      'statusFilter' => $statusFilter,
      'dateFrom' => $dateFrom,
      'dateTo' => $dateTo,
    ]);

    Log::debug('preorders-sample', [
      'first_row' => optional($rows->first())->preorder_status ?? null,
      'rows_count' => $rows->count(),
    ]);

    return view('content.vendor.vendor-preorder-order', $viewData);
  }

  public function updateStatus(Request $request, $id)
  {
    $request->validate([
      'status' => 'required|in:Pending,Preparing,For Delivery,Completed,Cancelled'
    ]);

    Log::debug('preorder.updateStatus called', [
      'incoming_id' => $id,
      'status' => $request->status,
      'all_input' => $request->all()
    ]);

    DB::beginTransaction();
    try {
      // If $id is a pre_order id, load PreOrder.
      $preorder = Preorder::find($id);

      if (!$preorder) {
        // Preorder not found, try fallback
        Log::warning('preorder.updateStatus - pre_order not found, trying order_items id fallback', ['id' => $id]);
        $orderItem = OrderItem::find($id);

        if ($orderItem) {
          $orderItem->order_item_status = $request->status;
          $orderItem->save();

          // [START] SAFE NOTIFICATION (FALLBACK)
          try {
            // Load the parent order to get customer ID
            $orderItem->load('order');
            if ($orderItem->order) {
              $parentOrder = $orderItem->order;
              $notify = app(\App\Services\NotificationService::class);
              $actorUserId = Auth::id();
              $newStatus = $request->status;
              
              $businessId = $parentOrder->business_id ?? null;
              $businessInfo = $businessId ? BusinessDetail::where('business_id', $businessId)->first() : null;
              $businessName = $businessInfo ? $businessInfo->business_name : '';

              $notify->createNotification([
                'user_id'         => $parentOrder->user_id,   // The Customer
                'actor_user_id'   => $actorUserId,          // The Vendor User
                'event_type'      => 'PREORDER_STATUS_CHANGED',
                'reference_table' => 'orders, preorder',
                'reference_id'    => $parentOrder->order_id,
                'business_id'     => $parentOrder->business_id,
                'recipient_role'  => 'customer',
                'payload' => [
                  'order_id'       => $parentOrder->order_id,
                  'title'          => "Your Pre-Order from {$businessName} status has changed",
                  'excerpt'        => "An item in your order is now: {$newStatus}.",
                  'status'         => $newStatus,
                  'url'            => "/customer/preorder"
                ]
              ]);
            }
          } catch (\Throwable $e) {
            // Do NOT re-throw.
            Log::error('Failed to send pre-order (fallback) status notification', [
              'order_item_id' => $orderItem->order_item_id,
              'error' => $e->getMessage()
            ]);
          }
          // [END] SAFE NOTIFICATION

          DB::commit();
          Log::info('preorder.updateStatus - updated single order_item fallback', ['order_item_id' => $id, 'status' => $request->status]);

          return $request->wantsJson()
            ? response()->json(['success' => true, 'message' => 'Order item updated (fallback)'])
            : redirect()->back()->with('success', 'Order item updated (fallback)');
        }

        // Neither were found
        DB::rollBack();
        Log::error('preorder.updateStatus - neither pre_order nor order_item found', ['id' => $id]);
        return $request->wBantsJson()
          ? response()->json(['success' => false, 'message' => 'Preorder not found'], 404)
          : redirect()->back()->with('error', 'Preorder not found');
      }

      // Load the related Order
      $order = Order::find($preorder->order_id);

      if (!$order) {
        // Related order is missing, which is a problem
        DB::rollBack();
        Log::error('preorder.updateStatus - Related Order not found!', ['order_id' => $preorder->order_id, 'pre_order_id' => $preorder->pre_order_id]);
        return $request->wantsJson()
          ? response()->json(['success' => false, 'message' => 'Related order record not found.'], 404)
          : redirect()->back()->with('error', 'Related order record not found.');
      }

      Log::debug('preorder.updateStatus - found pre_order and order', [
        'pre_order_id' => $preorder->pre_order_id ?? $preorder->id,
        'order_id' => $order->order_id,
        'order_total' => $order->total
      ]);

      // Update all order items associated with this order
      $updatedCount = OrderItem::where('order_id', $order->order_id)
        ->update(['order_item_status' => $request->status, 'updated_at' => now()]);

      // --- Main Logic Update ---

      // 1. Update the pre_orders.preorder_status
      $preorder->preorder_status = $request->status;

      // 2. If the new status is 'Completed', update payment fields
      if ($request->status === 'Completed') {
        // Set amount_due on the preorder to 0
        $preorder->amount_due = 0;

        // Update or Create the PaymentDetail record
        PaymentDetail::updateOrCreate(
          ['order_id' => $order->order_id], // Find the payment detail by the order_id
          [
            'amount_paid'       => $order->total,    // Set amount_paid to the order's total
            'payment_status'    => 'Paid',           // Mark its status as 'Paid'
            'paid_at'           => now(),            // Set the payment time to now
            'payment_method_id' => $order->payment_method_id // Ensure this is set, esp. if creating
          ]
        );
      }

      // --- End Logic Update ---

      $preorder->updated_at = now();
      $preorder->save();

      // [START] SAFE NOTIFICATION
      try {
    $notify = app(\App\Services\NotificationService::class);
    $actorUserId = Auth::id(); // The Vendor User
    $newStatus = $request->status;

    // --- THE FIX IS HERE ---
    // We look for the business using TWO methods:
    // 1. Does the order have a business_id? Use that.
    // 2. If not, find the business owned by the logged-in Vendor ($actorUserId).
    $businessInfo = BusinessDetail::where('business_id', $order->business_id)
                                  ->orWhere('vendor_id', $actorUserId)
                                  ->first();

    // Get name or fallback
    $businessName = $businessInfo ? $businessInfo->business_name : 'the store';
    // -----------------------

    $notify->createNotification([
        'user_id'         => $order->user_id,        // The Customer (recipient)
        'actor_user_id'   => $actorUserId,           // The Vendor User (actor)
        'event_type'      => 'PREORDER_STATUS_CHANGED',
        'reference_table' => 'orders, preorder',
        'reference_id'    => $order->order_id,
        'business_id'     => $order->business_id,
        'recipient_role'  => 'customer',
        'payload' => [
            'order_id'       => $order->order_id,
            
            // Now uses the fetched name
            'title'          => "Your Pre-Order from {$businessName} status has changed",
            
            'excerpt'        => "Your pre-order status is now: {$newStatus}.",
            'status'         => $newStatus,
            'url'            => "/customer/preorder"
        ]
    ]);

} catch (\Throwable $e) {
    // Log error to storage/logs/laravel.log
      Log::error('Failed to send pre-order status notification', [
        'order_id' => $order->order_id,
        'error' => $e->getMessage()
    ]);
}
      // [END] SAFE NOTIFICATION

      DB::commit();

      Log::info('preorder.updateStatus - success', [
        'pre_order_id' => $preorder->pre_order_id ?? $preorder->id,
        'order_id' => $order->order_id,
        'updated_items' => $updatedCount,
        'new_status' => $request->status
      ]);

      return $request->wantsJson()
        ? response()->json(['success' => true, 'updated' => $updatedCount])
        : redirect()->back()->with('success', 'Preorder status updated successfully');
    } catch (\Throwable $e) {
      DB::rollBack();
      Log::error('preorder.updateStatus - exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

      return $request->wantsJson()
        ? response()->json(['success' => false, 'error' => $e->getMessage()], 500)
        : redirect()->back()->with('error', 'Failed to update preorder status.');
    }
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

  public function uploadPreorderProof(\Illuminate\Http\Request $request, $id)
{
    $request->validate([
        'proof_of_delivery' => 'required|image|max:5120',
    ]);

    if ($request->hasFile('proof_of_delivery')) {
        $file = $request->file('proof_of_delivery');
        $filename = 'preorder_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('proofs'), $filename);

        // ➤ CRITICAL FIX: Use 'pre_order_id' to match your table screenshot
        DB::table('pre_orders')
            ->where('pre_order_id', $id) 
            ->update(['proof_of_delivery' => $filename]);

        return back()->with('success', 'Proof uploaded successfully.');
    }

    return back()->with('error', 'Upload failed.');
}
}
