<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Customer;
use Illuminate\Support\Facades\Crypt;

class VendorLocatorService
{
  // Display vendors within a configurable radius (default 50km)
  public function getNearbyVendors($customerId, $liveLat = null, $liveLng = null, $radiusKm = 5)
  {
    $customer = Customer::findOrFail($customerId);
    $baseLat = $liveLat ?? $customer->latitude;
    $baseLng = $liveLng ?? $customer->longitude;

    Log::info($liveLat && $liveLng
      ? "Using live location for customer ID {$customerId}."
      : "Fallback triggered for customer ID {$customerId} using stored location.");

    Log::info("🔍 Searching vendors within {$radiusKm}km of [{$baseLat}, {$baseLng}]");

    $vendors = DB::table(DB::raw('(
    SELECT
        business_details.business_id,
        business_details.business_image,
        business_details.business_name,
        business_details.business_type,
        business_details.business_location,
        business_details.valid_id_type,
        business_details.valid_id_no,
        business_details.business_permit_no,
        business_details.bir_reg_no,
        business_details.business_permit_file,
        business_details.valid_id_file,
        business_details.bir_reg_file,
        business_details.mayor_permit_file,
        business_details.business_duration,
        business_details.latitude,
        business_details.longitude,
        business_details.verification_status,
        business_details.remarks,
        vendors.vendor_id,
        vendors.user_id,
        vendors.fullname,
        vendors.phone_number,
        vendors.registration_status,
        vendors.created_at,
        (6371 * acos(
    -- This CASE statement "clamps" the value between -1.0 and 1.0
    CASE
        WHEN (
            cos(radians(13.6211001)) *
            cos(radians(business_details.latitude)) *
            cos(radians(business_details.longitude) - radians(123.20525360000001)) +
            sin(radians(13.6211001)) *
            sin(radians(business_details.latitude))
        ) > 1.0 THEN 1.0
        WHEN (
            cos(radians(13.6211001)) *
            cos(radians(business_details.latitude)) *
            cos(radians(business_details.longitude) - radians(123.20525360000001)) +
            sin(radians(13.6211001)) *
            sin(radians(business_details.latitude))
        ) < -1.0 THEN -1.0
        ELSE (
            cos(radians(13.6211001)) *
            cos(radians(business_details.latitude)) *
            cos(radians(business_details.longitude) - radians(123.20525360000001)) +
            sin(radians(13.6211001)) *
            sin(radians(business_details.latitude))
        )
    END
)) AS distance
    FROM business_details
    JOIN vendors ON vendors.vendor_id = CAST(business_details.vendor_id AS BIGINT)
    WHERE vendors.registration_status = \'Approved\'
    AND business_details.verification_status = \'Approved\'
) AS sub'))
      ->select('*')
      ->addBinding([$baseLat, $baseLng, $baseLat], 'select')
      ->where('distance', '<=', $radiusKm)
      ->orderBy('distance')
      ->get()
      ->map(function ($vendor) {
        $vendor->encrypted_business_id = Crypt::encryptString($vendor->business_id);
        $vendor->encrypted_vendor_id   = Crypt::encryptString($vendor->vendor_id);
        return $vendor;
      });


    Log::info("Found " . $vendors->count() . " nearby vendors.");

    if ($vendors->isEmpty()) {
      Log::warning("No vendors found. Query bindings: [" . implode(', ', [$baseLat, $baseLng, $baseLat]) . "]");
    }

    return $vendors;
  }
}
