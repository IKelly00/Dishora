<?php

namespace App\Http\Controllers\vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\{Auth, DB, Storage, Validator, Log, Session};
use App\Models\BusinessDetail;
use App\Models\BusinessOpeningHour;
use App\Models\BusinessPaymentMethod;
use App\Models\BusinessPmDetail;
use App\Models\PaymentMethod;
use App\Models\Vendor;
use Illuminate\Support\Str;

class VendorBusinessDetail extends Controller
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
    $showVerificationModal = $businessStatus === 'Pending';

    return [
      'activeBusinessId'        => $activeBusinessId,
      'businessStatus'          => $businessStatus,
      'showVerificationModal'   => $showVerificationModal,
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
   * Determine the business id to operate on.
   * Priority: session('active_business_id') -> $routeBusinessId
   *
   * @param  int|null  $routeBusinessId
   * @return int|null
   */
  protected function resolveBusinessId($routeBusinessId = null)
  {
    $sessionId = session('active_business_id');
    return $sessionId ?: $routeBusinessId;
  }

  /**
   * Ensure the authenticated user is the vendor that owns the given business.
   * Redirects back with Toastr error if not authorized.
   *
   * @param  \App\Models\BusinessDetail  $business
   * @return \Illuminate\Http\RedirectResponse|\App\Models\Vendor
   */
  protected function authorizeVendorOwnership(BusinessDetail $business)
  {
    $user = Auth::user();

    // Not logged in
    if (!$user) {
      return redirect()->back()->with('error', 'You must be logged in to access this page.');
    }

    // Find vendor record for current user
    $vendor = Vendor::where('user_id', $user->user_id ?? $user->id ?? Auth::id())->first();

    if (!$vendor) {
      return redirect()->back()->with('error', 'You are not registered as a vendor.');
    }

    // Check ownership
    if ((int)$business->vendor_id !== (int)$vendor->vendor_id) {
      return redirect()->back()->with('error', 'You are not authorized to manage this business.');
    }

    return $vendor;
  }

  /**
   * Show the edit form for a business.
   *
   * Uses session('active_business_id') if present.
   */
  public function edit($business_id = null)
  {
    $businessId = $this->resolveBusinessId($business_id);

    if (!$businessId) {
      return redirect()->back()->with('error', 'No active business selected.');
    }

    // --- MODIFIED: Eager-load all required relationships to match schema ---
    // This assumes you have the following relationships set up in your models:
    // BusinessDetail -> hasMany(BusinessPaymentMethod::class, 'business_id')
    // BusinessPaymentMethod -> hasOne(BusinessPmDetail::class, 'business_payment_method_id')
    // BusinessPaymentMethod -> belongsTo(PaymentMethod::class, 'payment_method_id')
    $business = BusinessDetail::with([
      'openingHours',
      'businessPaymentMethods.details',
      'businessPaymentMethods.paymentMethod' // Gets the name
    ])->find($businessId);


    if (!$business) {
      return redirect()->back()->with('error', 'Business not found.');
    }

    // Vendor authorization (redirects if unauthorized)
    $authCheck = $this->authorizeVendorOwnership($business);
    if ($authCheck instanceof \Illuminate\Http\RedirectResponse) {
      return $authCheck;
    }

    // At this point, $authCheck is a Vendor instance
    $vendor = $authCheck;

    // --- NEW: Parse the Business Location string ---
    $address = [
      'street'      => '',
      'barangay'    => '',
      'city'        => '',
      'province'    => '',
      'postal_code' => '',
      'region'      => ''
    ];

    if ($business->business_location && !$business->street) {
      // User's format: Street, Barangay, City, Province, Region, Postal Code
      $parts = array_map('trim', explode(',', $business->business_location));

      if (count($parts) === 6) {
        // Manually set the properties on the $business object
        // This will make them available in the Blade file.
        $business->street      = $parts[0];
        $business->barangay    = $parts[1];
        $business->city        = $parts[2];
        $business->province    = $parts[3];
        $business->region      = $parts[4];
        $business->postal_code = $parts[5];
      }
    }
    // --- END NEW ---

    // Get all available payment methods for the dropdown
    $paymentMethods = PaymentMethod::orderBy('method_name')->get();

    // Prepare opening hours keyed by day for easy view consumption
    $openingHoursByDay = $business->openingHours->keyBy('day_of_week');

    // --- MODIFIED: Get selected payment method data from the new relationship ---
    $selectedMethods = $business->businessPaymentMethods->pluck('payment_method_id')->all();

    // --- NEW: Get the details (account name/number) keyed by the payment_method_id
    $selectedMethodDetails = $business->businessPaymentMethods->mapWithKeys(function ($bpm) {
      // $bpm is a BusinessPaymentMethod model
      if ($bpm->details) {
        // Key by the main payment_method_id, value is the BusinessPmDetail model
        return [$bpm->payment_method_id => $bpm->details];
      }
      return [$bpm->payment_method_id => null]; // No details found for this method
    });
    // --- END MODIFIED ---

    // Build extra view data
    $extra = [
      'vendor'                => $vendor,       // --- NEW ---
      'business'              => $business,
      'address'               => $address,      // --- NEW ---
      'paymentMethods'        => $paymentMethods, // All methods for the dropdown
      'selectedMethods'       => $selectedMethods, // Just the IDs
      'selectedMethodDetails' => $selectedMethodDetails, // --- NEW ---
      'openingHoursByDay'     => $openingHoursByDay,
    ];

    $viewData = $this->buildViewData($vendor, $extra);

    // Your view path
    // Make sure this view name is correct
    return view('content.vendor.vendor-edit-business-details', $viewData);
  }

  /**
   * Update the given business.
   *
   * Uses session('active_business_id') if present.
   */
  public function update(Request $request, $business_id = null)
  {
    $businessId = $this->resolveBusinessId($business_id);

    if (!$businessId) {
      return redirect()->back()->with('error', 'No active business selected.');
    }

    $business = BusinessDetail::find($businessId);

    if (!$business) {
      return redirect()->back()->with('error', 'Business not found.');
    }

    // Vendor authorization (redirects if unauthorized)
    $authCheck = $this->authorizeVendorOwnership($business);
    if ($authCheck instanceof \Illuminate\Http\RedirectResponse) {
      return $authCheck;
    }

    // --- NEW --- Get vendor data
    $vendor = $authCheck;

    // Validation rules
    $rules = [
      // --- NEW --- Add rules for vendor fields
      'fullname' => 'required|string|max:150',
      'phone_number' => 'required|string|max:20|regex:/^09\d{9}$/', // Example: 09123456789

      // Business fields
      'business_name'        => 'required|string|max:150',
      'business_description' => 'required|string',
      'business_type'        => 'required|string|max:150',

      // --- NEW --- Address fields from your blade
      'region'      => 'required|string',
      'province'    => 'required|string',
      'city'        => 'required|string',
      'barangay'    => 'required|string',
      'street'      => 'required|string|max:255',
      'postal_code' => 'required|string|max:10',
      // --- END NEW ---

      'latitude'  => 'nullable|numeric',
      'longitude' => 'nullable|numeric',
      'preorder_lead_time_hours' => 'nullable|integer|min:0',
      'business_duration' => 'nullable|string|max:255',
      'remarks' => 'nullable|string|max:255',

      // IDs and permit numbers
      'valid_id_type'      => 'nullable|string|max:50',
      'valid_id_no'        => 'nullable|string|max:50',
      'business_permit_no' => 'nullable|string|max:50',
      'bir_reg_no'         => 'nullable|string|max:50',

      // files: allow images and pdfs
      'business_image'       => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120',
      'valid_id_file'        => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
      'business_permit_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
      'bir_reg_file'         => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
      'mayor_permit_file'    => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',

      // opening hours structure
      // --- MODIFIED --- Use request data structure
      'open_time' => 'nullable|array',
      'close_time' => 'nullable|array',
      'status' => 'nullable|array',
      // --- END MODIFIED ---

      // payment methods
      'payment_methods'   => 'nullable|array',
      'payment_methods.*' => 'integer|exists:payment_methods,payment_method_id',

      // Account details
      'account_number'      => 'nullable|array',
      'account_number.*'    => 'required_with:account_name.*|string|max:100',
      'account_name'        => 'nullable|array',
      'account_name.*'      => 'required_with:account_number.*|string|max:150',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()
        ->withErrors($validator)
        ->withInput()
        ->with('error', 'Please correct the highlighted errors.');
    }

    DB::beginTransaction();
    try {
      $disk = Storage::disk('s3');

      // Map form 'name' to the database_column
      $fileFields = [
        'business_image'    => 'business_image',
        'valid_id'          => 'valid_id_file',
        'business_permit'   => 'business_permit_file',
        'bir_registration'  => 'bir_reg_file',
        'mayors_permit'     => 'mayor_permit_file'
      ];

      foreach ($fileFields as $formName => $dbColumn) {
        if ($request->hasFile($formName)) {

          // 1. Delete the old file from S3 (Simplified)
          $oldFileUrl = $business->{$dbColumn};
          if ($oldFileUrl) {
            // Get the last part of the URL (the filename)
            $oldFileName = Str::afterLast($oldFileUrl, '/');
            if ($oldFileName) {
              $disk->delete($oldFileName);
            }
          }

          // 2. Store the new file and get its path (hash name)
          $path = $request->file($formName)->store('', 's3');

          /**
           * @var \Illuminate\Filesystem\FilesystemAdapter $disk
           */
          // 3. Save the new *full URL* to the database
          $business->{$dbColumn} = $disk->url($path);
        }
      }

      // --- NEW: Combine address fields into one string ---
      $fullAddress = implode(', ', [
        $request->input('street'),
        $request->input('barangay'),
        $request->input('city'),
        $request->input('province'),
        $request->input('region'),
        $request->input('postal_code')
      ]);
      // --- END NEW ---

      // Update basic info
      $business->fill($request->only([
        'business_name',
        'business_description',
        'business_type',
        'latitude',
        'longitude',
        'preorder_lead_time_hours',
        'business_duration',
        'remarks',
        'valid_id_type',
        'valid_id_no',
        'business_permit_no',
        'bir_reg_no'
      ]));

      // --- NEW --- Save the combined address
      $business->business_location = $fullAddress;
      $business->save();

      // --- NEW --- Update Vendor table
      $vendor->fullname = $request->input('fullname');
      $vendor->phone_number = $request->input('phone_number');
      $vendor->save();
      // --- END NEW ---

      // --- MODIFIED: Recreate opening hours from form structure ---
      BusinessOpeningHour::where('business_id', $business->business_id)->delete();
      $openTimes = $request->input('open_time', []);
      $closeTimes = $request->input('close_time', []);
      $statuses = $request->input('status', []);

      foreach ($statuses as $day => $status) {
        $isClosed = ($status === 'closed');
        BusinessOpeningHour::create([
          'business_id' => $business->business_id,
          'day_of_week' => $day, // $day is "Monday", "Tuesday", etc. from the form
          'opens_at'    => $isClosed ? null : ($openTimes[$day] ?? null),
          'closes_at'   => $isClosed ? null : ($closeTimes[$day] ?? null),
          'is_closed'   => $isClosed,
        ]);
      }
      // --- END MODIFIED ---

      // --- MODIFIED: Sync payment methods AND their details (SCHEMA-COMPLIANT) ---

      // 1. Get posted data
      $postedMethodIds = array_filter((array)$request->input('payment_methods', []), 'is_numeric');
      $postedAccountNumbers = (array)$request->input('account_number', []);
      $postedAccountNames = (array)$request->input('account_name', []);

      // 2. Get pivot records to delete
      $pivotsToDelete = BusinessPaymentMethod::where('business_id', $business->business_id)
        ->whereNotIn('payment_method_id', $postedMethodIds ?: [0])
        ->get();

      // 3. Delete details first, then the pivots
      if ($pivotsToDelete->isNotEmpty()) {
        $pivotIdsToDelete = $pivotsToDelete->pluck('business_payment_method_id');
        BusinessPmDetail::whereIn('business_payment_method_id', $pivotIdsToDelete)->delete();
        BusinessPaymentMethod::whereIn('business_payment_method_id', $pivotIdsToDelete)->delete();
      }

      // 4. Get all payment method definitions (for checking if they are COD)
      $allPaymentMethods = PaymentMethod::whereIn('payment_method_id', $postedMethodIds)
        ->pluck('method_name', 'payment_method_id');

      // 5. Loop and update or create
      foreach ($postedMethodIds as $pmId) {
        $methodName = $allPaymentMethods->get($pmId, '');
        $isCod = str_contains(strtolower($methodName), 'cash') || str_contains(strtolower($methodName), 'cod');

        // Step 5a: Find or create the PIVOT record
        $businessPaymentMethod = BusinessPaymentMethod::firstOrCreate(
          [
            'business_id' => $business->business_id,
            'payment_method_id' => $pmId,
          ]
        );

        // Step 5b: Update or create the DETAILS record, unless it's COD
        if (!$isCod) {
          $accNum = $postedAccountNumbers[$pmId] ?? null;
          $accName = $postedAccountNames[$pmId] ?? null;

          // Use the pivot model's ID to link the details
          BusinessPmDetail::updateOrCreate(
            [
              // Attributes to find
              'business_payment_method_id' => $businessPaymentMethod->business_payment_method_id,
            ],
            [
              // Attributes to update or create
              'account_number' => $accNum,
              'account_name'   => $accName,
              'is_active'      => 1, // Assuming it's active if provided
            ]
          );
        } else {
          // If it IS COD, delete any existing details just in case
          BusinessPmDetail::where('business_payment_method_id', $businessPaymentMethod->business_payment_method_id)->delete();
        }
      }
      // --- End Modified Sync ---

      DB::commit();

      $redirectId = session('active_business_id') ?: $business->business_id;

      // Make sure this route name is correct in your web.php
      // It might be 'customer.start.selling.edit'
      // I am using the one from your previous 'update' method: 'vendor.business.edit'
      // In your update() method in the controller

      return redirect()->route('vendor.business.edit', $redirectId)
        ->with('success', 'Business details updated successfully.')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate') // HTTP 1.1.
        ->header('Pragma', 'no-cache') // HTTP 1.0.
        ->header('Expires', '0'); // Proxies.
    } catch (\Throwable $e) {
      DB::rollBack();
      Log::error('Business update failed: ' . ($e->getMessage() ?? 'Unknown error'), [
        'business_id' => $business->business_id,
        'exception' => $e
      ]);
      return redirect()->back()
        ->with('error', 'Failed to update business: ' . $e->getMessage())
        ->withInput();
    }
  }
}
