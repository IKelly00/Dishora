<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

use App\Services\VendorLocatorService;

class CustomerLocationController extends Controller
{
  public function __construct(protected VendorLocatorService $locator) {}

  // Use live location for fetching vendors
  public function getNearbyLive(Request $request)
  {
    $user = Auth::user();
    $customer = Customer::where('user_id', $user->user_id)->first();

    // ADD THIS CHECK FIRST
    if (!$customer) {
      Log::warning("Customer not found for user_id: {$user->user_id}");
      return response()->json(['error' => 'Customer not found'], 404);
    }

    // NOW safe to log customer's stored location
    Log::info('Customer Stored Location: ', [
      'lat' => $customer->latitude,
      'lng' => $customer->longitude
    ]);

    $lat = $request->query('lat');
    $lng = $request->query('lng');

    if (!$lat || !$lng) {
      return response()->json([], 400);
    }

    Log::info('Live location', ['lat' => $lat, 'lng' => $lng]);

    // Use the live location to fetch vendors
    $nearbyVendors = $this->locator->getNearbyVendors($customer->customer_id, $lat, $lng);

    // Filter vendors to only include those with products
    $vendors = $this->filterVendorsWithProducts($nearbyVendors);

    Log::info('Live location vendors with products:', ['count' => $vendors->count()]);

    // Check if no vendors were found in the live location, send fallback flag
    if ($vendors->isEmpty()) {
      // Log the customer's stored location (already safe since we checked $customer above)
      Log::info('Fallback location lat: ' . $customer->latitude . ', lng: ' . $customer->longitude);

      return response()->json([
        'vendors' => $vendors,
        'fallback' => true,
      ]);
    }

    return response()->json([
      'vendors' => $vendors,
      'fallback' => false,
    ]);
  }

  public function getNearbyFromStored()
  {
    try {
      $user = Auth::user();
      $customer = Customer::where('user_id', $user->user_id)->first();

      // Ensure the customer and their location are valid
      if (!$customer || !$customer->latitude || !$customer->longitude) {
        return response()->json([]);
      }

      // Use customer_id and stored latitude/longitude to fetch nearby vendors
      $nearbyVendors = $this->locator->getNearbyVendors($customer->customer_id, $customer->latitude, $customer->longitude);

      // Filter vendors to only include those with products
      $vendors = $this->filterVendorsWithProducts($nearbyVendors);

      // Log vendor info for debugging
      foreach ($vendors as $vendor) {
        Log::info("Vendor: {$vendor->fullname}, Distance: {$vendor->distance}, Products: {$vendor->products_count}");
      }

      return response()->json($vendors);
    } catch (\Exception $e) {
      Log::error('Error fetching vendors from stored location: ' . $e->getMessage());
      return response()->json(['error' => 'Internal server error'], 500);
    }
  }

  /**
   * Filter vendors to only include those with available products
   */
  private function filterVendorsWithProducts($nearbyVendors)
  {
    // Gather all business IDs from nearby vendors
    $businessIds = $nearbyVendors->pluck('business_id');

    // Fetch those businesses with product counts/products
    $businesses = \App\Models\BusinessDetail::with(['products' => function ($q) {
      $q->where('is_available', true);
    }])
      ->withCount(['products' => function ($q) {
        $q->where('is_available', true);
      }])
      ->whereIn('business_id', $businessIds)
      ->get()
      ->filter(fn($biz) => $biz->products_count > 0) // ✅ keep only businesses with products
      ->map(function ($business) use ($nearbyVendors) {
        // Merge in vendor info + distance from the locator row
        $vendorRow = $nearbyVendors->firstWhere('business_id', $business->business_id);

        if ($vendorRow) {
          // Safely copy vendor properties
          $business->distance = $vendorRow->distance ?? null;
          $business->vendor_id = $vendorRow->vendor_id;
          $business->fullname = $vendorRow->fullname;
          $business->phone_number = $vendorRow->phone_number;
          $business->verification_status = $vendorRow->verification_status ?? null; // ✅ Add this

          // Add any other vendor fields you need here

          $business->encrypted_business_id = Crypt::encryptString($business->business_id);
          $business->encrypted_vendor_id   = Crypt::encryptString($business->vendor_id);
        }
        return $business;
      })
      ->values();

    return $businesses;
  }

  public function logLiveLocation(Request $request)
  {
    $user = Auth::user();
    $customer = Customer::where('user_id', $user->user_id)->first();

    $lat = $request->input('lat');
    $lng = $request->input('lng');
    $place = $request->input('place_name');

    Log::info("Live location received: lat={$lat}, lng={$lng}, place={$place}");

    if (!$customer) {
      Log::warning("Customer not found for user_id: {$user->user_id}");
      return response()->json(['error' => 'Customer not found'], 404);
    }

    if (!$lat || !$lng) {
      Log::error("Invalid coordinates received", ['lat' => $lat, 'lng' => $lng]);
      return response()->json(['error' => 'Invalid coordinates'], 400);
    }

    return response()->json(['message' => 'Location updated']);
  }
}
