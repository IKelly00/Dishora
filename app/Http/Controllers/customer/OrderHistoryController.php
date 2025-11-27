<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Review;

class OrderHistoryController extends Controller
{
  public function index()
  {
    $user = Auth::user();

    // Count only orders where ALL order items are 'Completed'
    $totalOrders = Order::where('user_id', $user->user_id)
      ->whereDoesntHave('items', function ($q) {
        $q->where('order_item_status', '!=', 'Completed');
      })->count();

    // Get paginated orders (only orders whose ALL items are 'Completed')
    $orders = Order::with([
      'business',
      'items.product',
      'paymentDetails',
      'preOrder',
    ])
      ->where('user_id', $user->user_id)
      ->whereDoesntHave('items', function ($q) {
        $q->where('order_item_status', '!=', 'Completed');
      })
      ->orderBy('created_at', 'desc')
      ->paginate(10);

    return view('content.customer.customer-order-history', [
      'orders' => $orders,
      'totalOrders' => $totalOrders
    ]);
  }

  /**
   * Handle review submission directly here
   */
  public function storeReview(Request $request)
  {
    // Validate (no order_id anymore)
    $request->validate([
      'business_id' => 'required|exists:business_details,business_id',
      'rating'      => 'required|integer|min:1|max:5',
      'comment'     => 'nullable|string|max:255',
    ]);

    $user = Auth::user();

    // Ensure the user has actually ordered from this business at least once
    $hasOrdered = Order::where('user_id', $user->user_id)
      ->where('business_id', $request->business_id)
      ->exists();

    if (!$hasOrdered) {
      return redirect()->back()->withErrors('You can only review businesses you have purchased from.');
    }

    // Prevent duplicate reviews per business
    $alreadyReviewed = Review::where('customer_id', $user->user_id)
      ->where('business_id', $request->business_id)
      ->exists();

    if ($alreadyReviewed) {
      return redirect()->back()->withErrors('You have already reviewed this business.');
    }

    // Create review (no order_id)
    $review = Review::create([
      'business_id' => $request->business_id,
      'customer_id' => $user->user_id,
      'rating'      => $request->rating,
      'comment'     => $request->comment,
    ]);

    Log::info('Review saved successfully', $review->toArray());

    return redirect()->back()->with('success', 'Thanks for leaving your feedback!');
  }
}
