<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessDetail;
use App\Models\Order; // <-- Make sure to import your Order model
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // <-- Import the DB facade

class CustomerScheduleController extends Controller
{
  /**
   * Fetch schedule events for a specific business, formatted for a customer-facing calendar.
   */
  public function getAvailability($business_id)
  {
    try {
      $business = BusinessDetail::findOrFail($business_id);

      // 1. Get all the defined schedule slots from the vendor
      $schedules = $business->preorderSchedule()
        ->where('is_active', true)
        ->where('available_date', '>=', Carbon::today())
        ->get();

      // --- THIS IS THE CORRECTED QUERY ---

      // Get all the dates from the schedules we just fetched
      $scheduleDates = $schedules->pluck('available_date');

      // Get all order counts for this business on those specific dates in ONE query
      // This query is now matched to your database schema
      $orderCounts = Order::query()
        // Join 1: Ensure it's a pre-order by checking the pre_orders table
        ->join('pre_orders', 'orders.order_id', '=', 'pre_orders.order_id')
        // Join 2: Get the status from the order_items table
        ->join('order_items', 'orders.order_id', '=', 'order_items.order_id')
        // Filter by the specific business
        ->where('orders.business_id', $business_id)
        // Only look at dates the vendor has marked as available
        ->whereIn('orders.delivery_date', $scheduleDates)
        // IMPORTANT: Only count orders that are not finished or cancelled
        ->whereNotIn('order_items.order_item_status', ['Completed', 'Cancelled'])
        // Select the date and the *unique* count of orders
        ->select(
          'orders.delivery_date as order_date',
          DB::raw('COUNT(DISTINCT orders.order_id) as count')
        )
        ->groupBy('orders.delivery_date')
        ->get()
        ->keyBy('order_date'); // Key by the 'Y-m-d' date string

      // --- END OF CORRECTED QUERY ---


      $events = $schedules->map(function ($schedule) use ($orderCounts) {
        $date = Carbon::parse($schedule->available_date);
        $dateString = $date->format('Y-m-d');
        $max = max((int) $schedule->max_orders, 1);

        // --- THIS IS THE FIX ---
        // Look up the *real* count from the query we just ran.
        // If no entry exists for this date, default to 0.
        $current = $orderCounts->get($dateString)?->count ?? 0;
        // --- END OF FIX ---

        // (This was the old, non-working line that read from stale data)
        // $current = (int) $schedule->current_order_count;

        $fill = ($max > 0) ? ($current / $max) : 1;
        $fillPercent = round($fill * 100);

        // --- Determine slot state ---
        if ($fill >= 1) {
          $state          = 'full';
          $title          = 'Fully Booked';
          $class          = 'event-full';
          $indicatorIcon  = '⛔';
          $indicatorColor = '#dc3545';
        } elseif ($fill >= 0.5) {
          $state          = 'limited';
          $title          = 'Limited Slots';
          $class          = 'event-limited';
          $indicatorIcon  = '⚠️';
          $indicatorColor = '#ffc107';
        } else {
          $state          = 'available';
          $title          = 'Available';
          $class          = 'event-available';
          $indicatorIcon  = '✅';
          $indicatorColor = '#198754';
        }

        // --- The complete event for FullCalendar ---
        // We wrap the data in 'extendedProps' so your Blade JavaScript can read it
        return [
          'title'            => "{$indicatorIcon} {$title}", // Fallback title
          'start'            => $dateString,
          'allDay'           => true,
          'display'          => 'block',
          'className'        => $class,

          // This wrapper is required by your Blade's eventContent function
          'extendedProps' => [
            'state'          => $state,
            'indicatorIcon'  => $indicatorIcon,
            'indicatorColor' => $indicatorColor,
            'currentOrders'  => $current, // The REAL count
            'maxOrders'      => $max,
            'fillPercent'    => $fillPercent,
          ]
        ];
      });

      return response()->json($events);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
      Log::warning("getAvailability API called with invalid Business ID: {$business_id}");
      return response()->json(['message' => 'Business not found.'], 404);
    } catch (\Exception $e) {
      Log::error("Error in getAvailability API for Business ID {$business_id}: " . $e->getMessage());
      Log::error($e->getTraceAsString()); // Add trace for more debug info
      return response()->json(['message' => 'An error occurred on the server.'], 500);
    }
  }
}
