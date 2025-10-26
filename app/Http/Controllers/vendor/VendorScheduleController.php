<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\{Vendor, BusinessOpeningHour, BusinessDetail, PreorderSchedule};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class VendorScheduleController extends Controller
{
  private function getVendor()
  {
    return Auth::user()?->vendor;
  }

  private function resolveBusinessContext($vendor): array
  {
    $vendorStatus = $vendor->registration_status ?? null;
    $activeBusinessId = session('active_business_id');

    if (!$activeBusinessId && $vendor->businessDetails()->exists()) {
      $activeBusinessId = $vendor->businessDetails()->orderBy('business_id')->value('business_id');
      session(['active_business_id' => $activeBusinessId]);
    }

    $business = $vendor->businessDetails()->where('business_id', $activeBusinessId)->first();
    $businessStatus = $business?->verification_status ?? 'Unknown';

    return [
      'activeBusinessId' => $activeBusinessId,
      'businessStatus' => $businessStatus,
      'showVerificationModal' => $businessStatus === 'Pending',
      'vendorStatus' => $vendorStatus,
      'showVendorStatusModal' => $vendorStatus === 'Pending',
      'showVendorRejectedModal' => $vendorStatus === 'Rejected',
    ];
  }

  private function buildViewData(?Vendor $vendor, array $extra = []): array
  {
    if (!$vendor) {
      return array_merge(['hasVendorAccess' => false, 'showRolePopup' => true], $extra);
    }

    return array_merge([
      'hasVendorAccess' => $vendor->businessDetails()->exists(),
      'showRolePopup' => false,
    ], $this->resolveBusinessContext($vendor), $extra);
  }

  public function index()
  {
    $vendor = $this->getVendor();
    $viewData = $this->buildViewData($vendor);

    $openingHours = [];
    if ($viewData['activeBusinessId']) {
      $openingHours = BusinessOpeningHour::where('business_id', $viewData['activeBusinessId'])
        ->get()
        ->keyBy('day_of_week');
    }
    $viewData['openingHours'] = $openingHours;

    // +++ CHANGE HERE: Pass the server's current time to the view +++
    // This ensures the JavaScript and PHP are using the same "now".
    $viewData['serverNow'] = now()->toIso8601String();

    return view('content.vendor.vendor-schedule', $viewData);
  }

  /**
   * NEW METHOD: Fetch schedule events as JSON for FullCalendar.
   */
  public function getScheduleEvents(Request $request)
  {
    $vendor = $this->getVendor();
    $viewData = $this->buildViewData($vendor);

    if (!$viewData['hasVendorAccess'] || !$viewData['activeBusinessId']) {
      return response()->json([]); // Return empty array if no business
    }

    $business = BusinessDetail::find($viewData['activeBusinessId']);
    if (!$business) {
      return response()->json([]);
    }

    $today = Carbon::today()->startOfDay();

    $scheduleEvents = $business->preorderSchedule()
      ->get()
      ->map(function ($schedule) {
        // compute fresh count for this specific schedule
        $count = \App\Models\Order::where('business_id', $schedule->business_id)
          ->whereDate('delivery_date', $schedule->available_date)
          ->whereHas('preorderDetail')  // only count orders with matching pre_orders row
          ->count();

        $isFull   = $count >= $schedule->max_orders;
        $isPast   = Carbon::parse($schedule->available_date)->isPast();
        $cssClass = $isPast
          ? 'event-past'
          : ($isFull ? 'event-full' : 'event-available');

        return [
          'id'   => $schedule->schedule_id,
          'title' => "{$count} / {$schedule->max_orders}",
          'start' => $schedule->available_date,
          'allDay' => true,
          'className' => $cssClass,
          'extendedProps' => [
            'current_order_count' => $count,
            'max_orders' => $schedule->max_orders,
          ],
        ];
      });

    return response()->json($scheduleEvents);
  }

  public function store(Request $request)
  {
    $vendor = $this->getVendor();
    $viewData = $this->buildViewData($vendor);

    if (!$vendor || !$viewData['activeBusinessId']) {
      return response()->json([
        'status' => 'error',
        'message' => 'Authentication error or no active business found.'
      ], 403);
    }

    $business = BusinessDetail::find($viewData['activeBusinessId']);
    if (!$business) {
      return response()->json([
        'status' => 'error',
        'message' => 'Active business not found.'
      ], 404);
    }

    $validated = $request->validate([
      'date' => 'required|date|after_or_equal:today',
      'max_orders' => 'required|integer|min:1',
    ]);

    $scheduleDate = Carbon::parse($validated['date']);
    $dayOfWeek = $scheduleDate->format('l');

    // 🚫 NEW CHECK — forbid if the business is closed that day
    $openingHours = $business->openingHours()
      ->where('day_of_week', $dayOfWeek)
      ->first();

    if (!$openingHours || $openingHours->is_closed) {
      return response()->json([
        'status' => 'error',
        'message' => "The business is closed on {$dayOfWeek}. You cannot set a pre‑order schedule."
      ], 422);
    }

    // Existing time‑based closing checks
    if ($scheduleDate->isToday()) {
      $closingTime = $openingHours->closes_at ? Carbon::parse($openingHours->closes_at) : null;
      $cutoffTime  = $closingTime ? (clone $closingTime)->subHours(2) : null;

      if ($closingTime && Carbon::now()->gt($closingTime)) {
        return response()->json([
          'status' => 'error',
          'message' => 'Changes cannot be made. You are past your closing time for today (' . $closingTime->format('g:i A') . ').'
        ], 422);
      }

      if ($cutoffTime && Carbon::now()->gt($cutoffTime)) {
        return response()->json([
          'status' => 'error',
          'message' => 'Order capacity can no longer be changed. The deadline was at ' . $cutoffTime->format('g:i A') . '.'
        ], 422);
      }
    }

    $schedule = $business->preorderSchedule()
      ->where('available_date', $validated['date'])
      ->first();

    if ($schedule && $validated['max_orders'] < $schedule->current_order_count) {
      return response()->json([
        'status' => 'error',
        'message' => 'Cannot set maximum orders below the current number of orders (' . $schedule->current_order_count . ').'
      ], 422);
    }

    $business->preorderSchedule()->updateOrCreate(
      ['available_date' => $validated['date']],
      ['max_orders' => $validated['max_orders'], 'is_active' => true]
    );

    return response()->json([
      'status' => 'success',
      'message' => 'Schedule updated successfully.'
    ]);
  }

  public function destroy($scheduleId)
  {
    $preorderSchedule = PreorderSchedule::findOrFail($scheduleId);

    $vendor = $this->getVendor();
    $viewData = $this->buildViewData($vendor);

    if (!$vendor || !$viewData['activeBusinessId'] || $preorderSchedule->business_id != $viewData['activeBusinessId']) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized action.'], 403);
    }

    // +++ START: ADDING CONSISTENT TIME VALIDATION TO DESTROY METHOD
    $scheduleDate = Carbon::parse($preorderSchedule->available_date);

    if ($scheduleDate->isToday()) {
      $business = BusinessDetail::find($viewData['activeBusinessId']);
      $dayOfWeek = $scheduleDate->format('l');
      $openingHours = $business->openingHours()->where('day_of_week', $dayOfWeek)->first();

      if ($openingHours && !$openingHours->is_closed && $openingHours->closes_at) {
        $closingTime = Carbon::parse($openingHours->closes_at);
        $cutoffTime = Carbon::parse($openingHours->closes_at)->subHours(2);

        if (Carbon::now()->gt($closingTime)) {
          return response()->json([
            'status' => 'error',
            'message' => 'Cannot remove schedule. You are past your closing time for today (' . $closingTime->format('g:i A') . ').'
          ], 422);
        }

        if (Carbon::now()->gt($cutoffTime)) {
          return response()->json([
            'status' => 'error',
            'message' => 'Cannot remove schedule. The deadline was at ' . $cutoffTime->format('g:i A') . '.'
          ], 422);
        }
      }
    }
    // +++ END: TIME VALIDATION

    if ($scheduleDate->isBefore(Carbon::today())) {
      return response()->json(['status' => 'error', 'message' => 'Cannot remove a schedule from the past.'], 422);
    }

    if ($preorderSchedule->current_order_count > 0) {
      return response()->json(['status' => 'error', 'message' => 'Cannot remove a schedule that has existing pre-orders.'], 422);
    }

    try {
      $preorderSchedule->delete();
      return response()->json(['status' => 'success', 'message' => 'The date has been closed for pre-orders.']);
    } catch (\Exception $e) {
      Log::error('Error deleting schedule: ' . $e->getMessage());
      return response()->json(['status' => 'error', 'message' => 'An unexpected error occurred while trying to remove the schedule.'], 500);
    }
  }
}
