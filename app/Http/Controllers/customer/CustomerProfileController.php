<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Customer;
use Illuminate\Validation\Rule;

class CustomerProfileController extends Controller
{
  /**
   * Show edit form for customer's profile
   */
  public function edit()
  {
    $user = Auth::user();

    $customer = Customer::firstOrCreate(
      ['user_id' => $user->user_id],
      ['user_image' => null, 'user_address' => null, 'contact_number' => null]
    );

    // --- PARSE address into parts ---
    $street = null;
    $barangay = null;
    $city = null;
    $province = null;
    $postal = null;
    $region = null;

    if (!empty($customer->user_address)) {
      // Split by commas and trim spaces
      $parts = array_map('trim', explode(',', $customer->user_address));

      // Pattern: [Street, Barangay, City, Province PostalCode]
      if (count($parts) >= 4) {
        $street = $parts[0] ?? null;
        $barangay = $parts[1] ?? null;
        $city = $parts[2] ?? null;

        // The last part may contain province + postal
        $lastPart = $parts[3] ?? '';
        if (preg_match('/^(.*)\s+(\d{4,5})$/', $lastPart, $matches)) {
          $province = trim($matches[1]);
          $postal = trim($matches[2]);
        } else {
          $province = trim($lastPart);
        }
      }
    }

    // --- Pass parsed parts to the view ---
    return view('content.customer.customer-edit-profile', compact(
      'customer',
      'user',
      'street',
      'barangay',
      'city',
      'province',
      'postal',
      'region'
    ));
  }


  /**
   * Update the customer profile
   */
  public function update(Request $request)
  {
    $user = Auth::user();

    // Ensure customer exists
    $customer = Customer::firstOrCreate(
      ['user_id' => $user->user_id],
      ['user_image' => null, 'user_address' => null, 'contact_number' => null]
    );

    // Normalize bad '0' image marker
    if ($customer->user_image === '0' || $customer->user_image === 0) {
      $customer->user_image = null;
      DB::table('customers')->where('customer_id', $customer->customer_id)->update(['user_image' => null]);
    }

    // Validate — use "sometimes" so fields are optional
    $validated = $request->validate([
      'region'         => ['sometimes', 'nullable', 'string', 'max:255'],
      'province'       => ['sometimes', 'nullable', 'string', 'max:255'],
      'city'           => ['sometimes', 'nullable', 'string', 'max:255'],
      'barangay'       => ['sometimes', 'nullable', 'string', 'max:255'],
      'street_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
      'postal_code'    => ['sometimes', 'nullable', 'string', 'max:50'],

      'user_address'   => ['sometimes', 'nullable', 'string', 'max:2000'],
      'contact_number' => ['sometimes', 'nullable', 'string', 'max:20'],
      'latitude'       => ['sometimes', 'nullable', 'numeric'],
      'longitude'      => ['sometimes', 'nullable', 'numeric'],
      'user_image'     => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    ]);

    // sanitize contact if present
    if (array_key_exists('contact_number', $validated) && $validated['contact_number']) {
      $validated['contact_number'] = preg_replace('/\D+/', '', $validated['contact_number']);
    }

    // Normalize lat/lng values (allow empty string -> null)
    $latitude = $request->filled('latitude') ? (float) $request->input('latitude') : null;
    $longitude = $request->filled('longitude') ? (float) $request->input('longitude') : null;

    // === Handle user_image upload (S3) ===
    if ($request->hasFile('user_image')) {
      try {
        $file = $request->file('user_image');
        $disk = Storage::disk('s3');

        $path = $file->store('customers', 's3');
        if (!$path) {
          throw new \Exception('Failed to store image on S3.');
        }
        /**
         * @var \Illuminate\Filesystem\FilesystemAdapter $disk
         */
        $url = $disk->url($path);

        // delete old image (if it's a previously stored s3 url)
        if (!empty($customer->user_image) && $customer->user_image !== '0') {
          try {

            // Try to derive key from full URL — if you previously stored the full URL
            $base = $disk->url('');
            $oldKey = str_replace($base, '', $customer->user_image);
            if ($oldKey && $disk->exists($oldKey)) {
              $disk->delete($oldKey);
            }
          } catch (\Throwable $e) {
            Log::warning('Old S3 image delete failed', ['customer_id' => $customer->customer_id, 'err' => $e->getMessage()]);
          }
        }

        $customer->user_image = $url;
      } catch (\Throwable $e) {
        Log::error('Customer S3 upload failed', ['user_id' => $user->user_id, 'err' => $e->getMessage()]);
        return back()->withErrors(['user_image' => 'Failed to upload image. Please try again.']);
      }
    }

    // === Compose user_address from parts if any of the fields were submitted ===
    $addressPartsProvided = false;
    $parts = [];

    foreach (['street_name', 'barangay', 'city', 'province', 'region'] as $k) {
      if ($request->filled($k)) {
        $addressPartsProvided = true;
        $v = trim($request->input($k));
        if ($v !== '') $parts[] = $v;
      }
    }
    // Postal appended at the end if present
    $postal = $request->filled('postal_code') ? trim($request->input('postal_code')) : null;

    if ($addressPartsProvided) {
      $full = implode(', ', $parts);
      if ($postal) $full .= ' ' . $postal;
      // store composed address (overrides user_address if present)
      $customer->user_address = $full;
    } else {
      // If a raw user_address was provided explicitly (maybe JS composed it), accept it
      if (array_key_exists('user_address', $validated)) {
        $customer->user_address = $validated['user_address'];
      }
    }

    // === Update contact / coords if provided ===
    if (array_key_exists('contact_number', $validated)) {
      $customer->contact_number = $validated['contact_number'];
    }
    if ($request->filled('latitude')) {
      $customer->latitude = $latitude;
    }
    if ($request->filled('longitude')) {
      $customer->longitude = $longitude;
    }

    $customer->save();

    return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
  }
}
