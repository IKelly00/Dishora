<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log, DB, Validator, Storage};
use App\Models\{Vendor, BusinessDetail, BusinessOpeningHour, BusinessPaymentMethod, PaymentMethod};
use App\Models\BusinessPmDetail;

class StartSellingController extends Controller
{
  public function store(Request $request)
  {
    Log::info('StartSellingController store method triggered');
    Log::info('Request data', $request->all());

    Log::info('File debug', [
      'hasFile_business_image' => $request->hasFile('business_image'),
      'allFiles_keys' => array_keys($request->allFiles()),
    ]);

    // Normalize opening hours
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $request->merge([
      'opening_hours' => collect($days)->mapWithKeys(function ($day) use ($request) {
        $isClosed = $request->input("status.$day") === 'closed';
        return [
          $day => [
            'opens_at' => $isClosed ? null : $request->input("open_time.$day"),
            'closes_at' => $isClosed ? null : $request->input("close_time.$day"),
            'is_closed' => $isClosed ? 1 : 0,
          ]
        ];
      })->toArray()
    ]);

    // --- CHANGED: Get all payment methods for the validator ---
    $allPaymentMethods = PaymentMethod::all()->keyBy('payment_method_id');

    // Validation rules
    $rules = [
      'business_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
      'fullname' => 'required|string|max:255',
      'business_name' => 'required|string|max:255|unique:business_details,business_name',
      'business_description' => 'required',
      'street' => 'required|string|max:255',
      'barangay' => 'required|string|max:255',
      'city' => 'required|string|max:255',
      'province' => 'required|string|max:255',
      'postal_code' => 'required|string|max:20',
      'latitude' => 'required|numeric',
      'longitude' => 'required|numeric',
      'opening_hours' => 'required|array',
      'opening_hours.*.opens_at' => 'nullable|date_format:H:i',
      'opening_hours.*.closes_at' => 'nullable|date_format:H:i',
      'opening_hours.*.is_closed' => 'nullable|boolean',
      'phone_number' => 'required|string|max:20',
      'business_type' => 'required|string|max:100',
      'business_permit' => 'required|file|mimes:pdf,jpg,jpeg,png',
      'valid_id_type' => 'required|string|max:100',
      'valid_id' => 'required|file|mimes:pdf,jpg,jpeg,png',
      'mayors_permit' => 'required|file|mimes:pdf,jpg,jpeg,png',
      'business_duration' => 'nullable|string|max:255',
      'payment_methods' => 'required|array|min:1',
      'payment_methods.*' => 'exists:payment_methods,payment_method_id',
    ];

    if (!$request->filled('business_duration')) {
      $rules['bir_registration'] = 'required|file|mimes:pdf,jpg,jpeg,png';
    }

    $validator = Validator::make($request->all(), $rules);

    // --- CHANGED: Pass $allPaymentMethods into the 'after' hook ---
    $validator->after(function ($validator) use ($request, $allPaymentMethods) {
      $selected = $request->input('payment_methods', []);

      foreach ($selected as $methodId) {

        // --- ADDED: Check if the method is COD ---
        $method = $allPaymentMethods->get($methodId);
        $methodName = $method ? strtolower($method->method_name) : '';
        $isCod = str_contains($methodName, 'cash on delivery') || str_contains($methodName, 'cod');

        // If it's COD, skip account number/name validation
        if ($isCod) {
          continue;
        }
        // --- END OF ADDED BLOCK ---

        $accNumber = $request->input("account_number.$methodId");
        $accName = $request->input("account_name.$methodId");
        if (!strlen(trim((string)$accNumber ?? ''))) {
          $validator->errors()->add("account_number.$methodId", "Account number is required for " . ($method->method_name ?? "method ID {$methodId}"));
        }
        if (!strlen(trim((string)$accName ?? ''))) {
          $validator->errors()->add("account_name.$methodId", "Account name is required for " . ($method->method_name ?? "method ID {$methodId}"));
        }
      }
    });

    if ($validator->fails()) {
      Log::warning('Validation failed', ['errors' => $validator->errors()]);
      return back()->withErrors($validator)->withInput();
    }

    $validated = $validator->validated();

    // (Rest of the controller is fine, but I'll update the payment detail loop to be safe)

    // Build business_location summary
    $validated['business_location'] = collect([
      $validated['street'],
      $validated['barangay'],
      $validated['city'],
      $validated['province'],
      $validated['postal_code']
    ])->filter()->implode(', ');

    DB::beginTransaction();
    try {
      /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */

      $disk = Storage::disk('s3');

      $birPath = $request->hasFile('bir_registration') ? $request->file('bir_registration')->store('', 's3') : null;
      $permitPath = $request->file('business_permit')->store('', 's3');
      $idPath = $request->file('valid_id')->store('', 's3');
      $mayorPath = $request->file('mayors_permit')->store('', 's3');
      $businessImagePath = $request->hasFile('business_image') ? $request->file('business_image')->store('', 's3') : null;

      $userId = Auth::id();

      $vendor = Vendor::where('user_id', $userId)->first();
      if (!$vendor) {
        $vendor = Vendor::create([
          'user_id' => $userId,
          'fullname' => $validated['fullname'],
          'phone_number' => $validated['phone_number'],
          'registration_status' => 'Pending',
          'created_at' => now(),
        ]);
      }

      $business = BusinessDetail::create([
        'business_image' => $businessImagePath ? $disk->url($businessImagePath) : null,
        'vendor_id' => $vendor->vendor_id,
        'business_name' => $validated['business_name'],
        'business_description' => $validated['business_description'],
        'business_type' => $validated['business_type'],
        'business_location' => $validated['business_location'],
        'latitude' => $validated['latitude'],
        'longitude' => $validated['longitude'],
        'bir_reg_file' => $birPath ? $disk->url($birPath) : null,
        'business_permit_file' => $disk->url($permitPath),
        'valid_id_type' => $validated['valid_id_type'],
        'valid_id_file' => $disk->url($idPath),
        'mayor_permit_file' => $disk->url($mayorPath),
        'business_duration' => $validated['business_duration'] ?? null,
        'created_at' => now()
      ]);

      // Save payment methods + details
      $selectedPaymentMethods = $validated['payment_methods'];
      foreach ($selectedPaymentMethods as $methodId) {
        // Create business_payment_methods pivot row
        $bp = BusinessPaymentMethod::create([
          'business_id' => $business->business_id,
          'payment_method_id' => $methodId,
        ]);

        // --- CHANGED: Check for COD before saving details ---
        $method = $allPaymentMethods->get($methodId);
        $methodName = $method ? strtolower($method->method_name) : '';
        $isCod = str_contains($methodName, 'cash on delivery') || str_contains($methodName, 'cod');

        // Only save details if it's NOT COD
        if (!$isCod) {
          // Deactivate previous active detail(s)
          BusinessPmDetail::where('business_payment_method_id', $bp->business_payment_method_id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);

          // Insert new detail row
          $accNumber = $request->input("account_number.$methodId");
          $accName = $request->input("account_name.$methodId");

          BusinessPmDetail::create([
            'business_payment_method_id' => $bp->business_payment_method_id,
            'account_number' => $accNumber,
            'account_name' => $accName,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
          ]);
        }
      }

      // Save opening hours
      foreach ($validated['opening_hours'] as $day => $hours) {
        BusinessOpeningHour::create([
          'business_id' => $business->business_id,
          'day_of_week' => $day,
          'opens_at' => $hours['opens_at'] ? date('H:i:s', strtotime($hours['opens_at'])) : null,
          'closes_at' => $hours['closes_at'] ? date('H:i:s', strtotime($hours['closes_at'])) : null,
          'is_closed' => $hours['is_closed'],
          'created_at' => now(),
          'updated_at' => now(),
        ]);
      }

      DB::commit();

      return redirect()
        ->route('customer.start.selling')
        ->with('success', 'Business registered successfully!');
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Error during business registration', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      return back()->withErrors(['error' => 'Something went wrong: ' . $e->getMessage()]);
    }
  }
}
