<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
  public function index($business_id)
  {
    $reviews = \App\Models\Review::with(['customer.user'])
      ->where('business_id', $business_id)
      ->latest()
      ->get();

    return response()->json($reviews);
  }

  /**
   * Store a new review (feedback) for a business.
   */
  public function store(Request $request)
  {
    $validated = $request->validate([
      'business_id' => 'required|exists:business_details,business_id',
      'rating'      => 'required|integer|min:1|max:5',
      'comment'     => 'nullable|string|max:1000',
    ]);

    // Get the authenticated user's customer record
    $customer = \App\Models\Customer::where('user_id', Auth::id())->firstOrFail();

    $review = Review::create([
      'customer_id' => $customer->customer_id,
      'business_id' => $validated['business_id'],
      'rating'      => $validated['rating'],
      'comment'     => $validated['comment'] ?? null,
    ]);
    // For AJAX form submissions, return JSON
    if ($request->expectsJson()) {
      return response()->json([
        'message' => 'Thanks for your feedback!',
        'review'  => $review,
      ], 201);
    }

    // Otherwise redirect back (for non‑AJAX calls)
    return redirect()->back()->with('success', 'Thanks for your feedback!');
  }
}
