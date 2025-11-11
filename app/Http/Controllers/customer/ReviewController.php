<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;           // For structured logging
use App\Services\NotificationService;         // For dispatching in-app notifications

class ReviewController extends Controller
{
  /**
   * Return all reviews for a business (newest first).
   */
  public function index($business_id)
  {
    $reviews = Review::with(['customer.user'])
      ->where('business_id', $business_id)
      ->latest()
      ->get();

    return response()->json($reviews);
  }

  /**
   * Store a new review for a business.
   * - Validates input and creates the review under the authenticated customer.
   * - Attempts to notify the vendor; notification failures are logged and do not block.
   */
  public function store(Request $request)
  {
    $validated = $request->validate([
      'business_id' => 'required|exists:business_details,business_id',
      'rating'      => 'required|integer|min:1|max:5',
      'comment'     => 'nullable|string|max:1000',
    ]);

    $customer = Customer::where('user_id', Auth::id())->firstOrFail();

    $review = Review::create([
      'customer_id' => $customer->customer_id,
      'business_id' => $validated['business_id'],
      'rating'      => $validated['rating'],
      'comment'     => $validated['comment'] ?? null,
    ]);

    // Try to notify the vendor of the new review; do not interrupt if it fails.
    try {
      $review->load('business.vendor.user');

      if (
        $review->business &&
        $review->business->vendor &&
        $review->business->vendor->user
      ) {
        $vendorUser   = $review->business->vendor->user;
        $customerUser = Auth::user();
        $notify       = app(NotificationService::class);

        $notify->createNotification([
          'user_id'         => $vendorUser->user_id,
          'actor_user_id'   => $customerUser->user_id,
          'event_type'      => 'NEW_REVIEW',
          'reference_table' => 'reviews',
          'reference_id'    => $review->review_id,
          'business_id'     => $review->business_id,
          'recipient_role'  => 'vendor',
          'payload'         => [
            'title'         => "You have a new {$review->rating}-star review!",
            'excerpt'       => "{$customerUser->fullname} left a review for your business.",
            'rating'        => $review->rating,
            'customer_name' => $customerUser->fullname,
            'url'           => '/vendor/feedback',
          ],
        ]);
      } else {
        Log::warning('[ReviewController] Vendor user not found for review notification', [
          'review_id'   => $review->review_id,
          'business_id' => $review->business_id,
        ]);
      }
    } catch (\Throwable $e) {
      Log::error('[ReviewController] Failed to send review notification', [
        'error'     => $e->getMessage(),
        'review_id' => $review->review_id ?? null,
      ]);
    }

    if ($request->expectsJson()) {
      return response()->json([
        'message' => 'Thanks for your feedback!',
        'review'  => $review,
      ], 201);
    }

    return redirect()->back()->with('success', 'Thanks for your feedback!');
  }
}
