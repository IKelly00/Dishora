<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log};
use App\Models\{Vendor, Product};

class PreorderController extends Controller
{
  /**
   * Get the current vendor of the logged in user
   */
  private function getVendor(): ?Vendor
  {
    return Auth::user()?->vendor;
  }

  /**
   * Resolve vendor’s active business context (unchanged from your CartController pattern)
   */
  private function resolveBusinessContext(Vendor $vendor): array
  {
    $vendorStatus     = $vendor->registration_status ?? null;
    $activeBusinessId = session('active_business_id');

    if (!$activeBusinessId && $vendor->businessDetails()->exists()) {
      $activeBusinessId = $vendor->businessDetails()
        ->orderBy('business_id')
        ->value('business_id');

      session(['active_business_id' => $activeBusinessId]);
      Log::info('Auto-selected first business', compact('activeBusinessId'));
    }

    $business              = $vendor->businessDetails()
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

  /**
   * Assemble vendor + page data for views
   */
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
   * Add item to preorder session
   */
  public function add(Request $request)
  {
    $request->validate([
      'product_id' => 'required|exists:products,product_id',
      'quantity'   => 'required|integer|min:1',
    ]);

    $preorder = session()->get('preorder', []);
    $found    = false;

    foreach ($preorder as &$item) {
      if ($item['product_id'] == $request->product_id) {
        $item['quantity'] += $request->quantity;
        $found = true;
        break;
      }
    }

    $product = Product::find($request->product_id);

    if (!$found) {
      $preorder[] = [
        'product_id' => $request->product_id,
        'quantity'   => $request->quantity,
        'price'      => $product->price ?? 0,
      ];
    }

    session()->put('preorder', $preorder);

    Log::info('Preorder contents updated', ['preorder' => session('preorder')]);

    $currentQty = collect(session('preorder', []))
      ->where('product_id', $request->product_id)
      ->sum('quantity');

    return response()->json([
      'message'      => 'Item added to preorder list',
      'product_name' => $product->item_name ?? 'Unknown product',
      'quantity'     => $currentQty,
    ]);
  }

  /**
   * Show the user’s preorder session & products
   */
  public function showPreorder()
  {
    // 1️⃣  Grab session preorders
    $sessionPreorders = collect(session('preorder', []));

    if ($sessionPreorders->isEmpty()) {
      return view('content.customer.customer-preorder', [
        'groupedProducts' => collect(),
      ]);
    }

    // 2️⃣  Fetch the matching Product models (with their Business)
    $productIds = $sessionPreorders->pluck('product_id');
    $products   = Product::with('business')
      ->whereIn('product_id', $productIds)
      ->get()
      ->keyBy('product_id');

    // 3️⃣  Combine session quantities with full product data
    $enriched = $sessionPreorders->map(function ($item) use ($products) {
      $p = $products[$item['product_id']] ?? null;
      return (object) [
        // session data
        'product_id'   => $item['product_id'],
        'quantity'     => $item['quantity'] ?? 1,
        // model data (copied for easy direct access)
        'item_name'    => $p?->item_name,
        'price'        => $p?->price,
        'advance_amount' => $p?->advance_amount,
        'is_available' => $p?->is_available,
        'image_url'    => $p?->image_url,
        'business'     => $p?->business,
        'product'      => $p, // keep full model just in case
      ];
    });

    // 4️⃣  Group by business so Blade can show vendor blocks
    $groupedProducts = $enriched->groupBy(fn($item) => $item->business?->business_id ?? 0);

    // 5️⃣  Pass to Blade
    return view('content.customer.customer-preorder', compact('groupedProducts'));
  }

  /**
   * Update preorder quantity
   */
  public function update(Request $request)
  {
    $request->validate([
      'product_id' => 'required',
      'quantity'   => 'required|integer|min:1',
    ]);

    $preorder = session()->get('preorder', []);
    $product  = Product::find($request->product_id);

    foreach ($preorder as &$item) {
      if ($item['product_id'] == $request->product_id) {
        $item['quantity'] = $request->quantity;
        $item['price']    = $product->price ?? $item['price'];
        break;
      }
    }

    session()->put('preorder', $preorder);

    Log::info('Preorder updated', ['preorder' => session('preorder')]);

    return response()->json([
      'success'  => true,
      'message'  => 'Preorder updated',
      'preorder' => $preorder
    ]);
  }

  /**
   * Remove a preorder item
   */
  public function remove(Request $request)
  {
    $request->validate([
      'product_id' => 'required',
    ]);

    $preorder = session()->get('preorder', []);
    $preorder = array_filter($preorder, fn($i) => $i['product_id'] != $request->product_id);

    session()->put('preorder', array_values($preorder));

    Log::info('Preorder item removed', ['preorder' => session('preorder')]);

    return back()->with('success', 'Item removed from preorder.');
  }
}
