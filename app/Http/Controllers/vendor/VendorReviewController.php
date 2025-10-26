<?php

namespace App\Http\Controllers\vendor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Vendor;
use App\Models\Review;

class VendorReviewController extends Controller
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

    return [
      'activeBusinessId'        => $activeBusinessId,
      'businessStatus'          => $businessStatus,
      'showVerificationModal'   => $businessStatus === 'Pending',
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
   * Display reviews for the selected business.
   */
  public function index()
  {
    $vendor = $this->getVendor();

    if (!$vendor) {
      abort(403, 'Unauthorized');
    }

    $context = $this->resolveBusinessContext($vendor);
    $businessId = $context['activeBusinessId'];

    $reviews = Review::with(['customer.user'])
      ->where('business_id', $businessId)
      ->latest('created_at')
      ->paginate(10);

    return view('content.vendor.vendor-review', $this->buildViewData($vendor, [
      'reviews' => $reviews,
    ]));
  }
}
