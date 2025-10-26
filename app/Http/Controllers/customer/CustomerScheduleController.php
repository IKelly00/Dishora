<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


class CustomerScheduleController extends Controller
{
  /**
   * Fetch schedule events for a specific business, formatted for a customer-facing calendar.
   */
  public function getAvailability($business_id)
  {
    try {
      $business = BusinessDetail::findOrFail($business_id);

      $schedules = $business->preorderSchedule()
        ->where('is_active', true)
        ->where('available_date', '>=', Carbon::today())
        ->get();

      $events = $schedules->map(function ($schedule) {
        $date = Carbon::parse($schedule->available_date)->format('Y-m-d');
        $current = (int) $schedule->current_order_count;
        $max = max((int) $schedule->max_orders, 1);

        $fill = $current / $max;
        $fillPercent = round($fill * 100);

        // --- Determine slot state ---
        if ($fill >= 1) {
          $state          = 'full';
          $title          = 'Fully Booked';
          $class          = 'event-full';
          $indicatorIcon  = '⛔';
          $indicatorColor = '#dc3545'; // Bootstrap red
        } elseif ($fill >= 0.5) {
          $state          = 'limited';
          $title          = 'Limited Slots';
          $class          = 'event-limited';
          $indicatorIcon  = '⚠️';
          $indicatorColor = '#ffc107'; // Bootstrap yellow
        } else {
          $state          = 'available';
          $title          = 'Available';
          $class          = 'event-available';
          $indicatorIcon  = '✅';
          $indicatorColor = '#198754'; // Bootstrap green
        }

        // --- The complete event for FullCalendar ---
        return [
          'title'            => "{$indicatorIcon} {$title}",
          'start'            => $date,
          'allDay'           => true,
          'display'          => 'block',
          'className'        => $class,
          'state'            => $state,
          'indicatorIcon'    => $indicatorIcon,
          'indicatorColor'   => $indicatorColor,
          'currentOrders'    => $current,
          'maxOrders'        => $max,
          'fillPercent'      => $fillPercent,
        ];
      });

      return response()->json($events);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
      Log::warning("getAvailability API called with invalid Business ID: {$business_id}");
      return response()->json(['message' => 'Business not found.'], 404);
    } catch (\Exception $e) {
      Log::error("Error in getAvailability API for Business ID {$business_id}: " . $e->getMessage());
      return response()->json(['message' => 'An error occurred on the server.'], 500);
    }
  }
}
