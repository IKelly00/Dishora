<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log};
use App\Models\{Vendor, Product};

class CartController extends Controller
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

    /* if (!$business) {
      Log::warning('Business not found for vendor', compact('activeBusinessId'));
    } else {
      Log::info('Resolved businessStatus', compact('businessStatus'));
      Log::info('Show verification modal', compact('showVerificationModal'));
    } */

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

  public function add(Request $request)
  {
    $request->validate([
      'product_id' => 'required|exists:products,product_id',
      'quantity'   => 'required|integer|min:1',
    ]);

    $cart = session()->get('cart', []);
    $found = false;

    foreach ($cart as &$item) {
      if ($item['product_id'] == $request->product_id) {
        $item['quantity'] += $request->quantity;
        $found = true;
        break;
      }
    }

    $product = Product::find($request->product_id);

    if (!$found) {
      $cart[] = [
        'product_id' => $request->product_id,
        'quantity'   => $request->quantity,
        'price'      => $product->price ?? 0,
      ];
    }

    session()->put('cart', $cart);

    Log::info('Cart contents updated', ['cart' => session('cart')]);

    $currentQty = collect($cart)
      ->where('product_id', $request->product_id)
      ->sum('quantity');

    return response()->json([
      'message'      => 'Item added to cart',
      'product_name' => $product->item_name ?? 'Unknown product',
      'quantity'     => $currentQty,
    ]);
  }

  public function showCart()
  {
    $data['cart'] = session()->get('cart', []);

    // Collect product IDs from the session cart
    $productIds = collect($data['cart'])->pluck('product_id')->toArray();
    $data['totalQuantity'] = collect($data['cart'])->sum('quantity');
    $data['totalPrice']    = collect($data['cart'])
      ->sum(fn($item) => ($item['price'] ?? 0) * $item['quantity']);

    // Fetch products
    $data['products'] = Product::whereIn('product_id', $productIds)->get()->keyBy('product_id');

    /* Log::info('=== showCart() Debug ===', [
      'cart_session'   => $data['cart'],                // raw cart from session
      'product_ids'    => $productIds,                  // IDs we’re about to query
      'products_found' => $data['products']->toArray(), // what the DB returned
      'total_quantity' => $data['totalQuantity'],
      'total_price'    => $data['totalPrice'],
    ]); */

    $vendor   = $this->getVendor();
    $viewData = $this->buildViewData($vendor, $data);

    return view('content.customer.customer-cart', $viewData);
  }

  public function update(Request $request)
  {
    $request->validate([
      'product_id' => 'required',
      'quantity'   => 'required|integer|min:1',
    ]);

    $cart = session()->get('cart', []);

    $product  = Product::find($request->product_id);

    foreach ($cart as &$item) {
      if ($item['product_id'] == $request->product_id) {
        $item['quantity'] = $request->quantity;
        $item['price']    = $product->price ?? $item['price'];
        break;
      }
    }

    session()->put('cart', $cart);

    Log::info('Cart contents', ['cart' => session('cart')]);

    return response()->json([
      'success' => true,
      'message' => 'Cart updated',
      'cart'    => $cart
    ]);
  }

  public function remove(Request $request)
  {
    $request->validate([
      'product_id' => 'required',
    ]);

    $cart = session()->get('cart', []);

    $cart = array_filter($cart, fn($i) => $i['product_id'] != $request->product_id);

    session()->put('cart', array_values($cart));

    return back()->with('success', 'Item removed from cart.');
  }
}
