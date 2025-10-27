<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log, DB, Validator, Storage};
use App\Models\{Vendor, BusinessDetail, BusinessOpeningHour, BusinessPaymentMethod, PaymentMethod, BusinessPmDetail};

class StartSellingController extends Controller
{
  public function store(Request $request)
  {
    Log::info('StartSellingController store method triggered');
    Log::info('Request data', $request->all());

    // --- File Type Logging ---
    try {
      $fileDetails = [];
      foreach (array_keys($request->allFiles()) as $key) {
        if ($request->hasFile($key)) {
          $file = $request->file($key);
          $fileDetails[$key] = [
            'original_name' => $file->getClientOriginalName(),
            'extension' => $file->getClientOriginalExtension(),
            'size_kb' => round($file->getSize() / 1024, 2),
            'mime_type' => $file->getMimeType(),
          ];
        }
      }
      Log::info('File Upload Details', $fileDetails);
    } catch (\Exception $e) {
      Log::warning('Could not retrieve file details', ['error' => $e->getMessage()]);
    }

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
          ],
        ];
      })->toArray(),
    ]);

    $allPaymentMethods = PaymentMethod::all()->keyBy('payment_method_id');

    // --- Validation Rules ---
    $allowedMimes = 'pdf,jpg,jpeg,png,gif,bmp,webp';
    $maxSize = '10240'; // 10MB

    $rules = [
      'business_image' => "nullable|image|mimes:jpg,jpeg,png,gif,bmp,webp|max:$maxSize",
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
      'business_permit' => "required|file|mimes:$allowedMimes|max:$maxSize",
      'valid_id_type' => 'required|string|max:100',
      'valid_id' => "required|file|mimes:$allowedMimes|max:$maxSize",
      'mayors_permit' => "required|file|mimes:$allowedMimes|max:$maxSize",
      'business_duration' => 'nullable|string|max:255',
      'payment_methods' => 'required|array|min:1',
      'payment_methods.*' => 'exists:payment_methods,payment_method_id',
    ];

    if (!$request->filled('business_duration')) {
      $rules['bir_registration'] = "required|file|mimes:$allowedMimes|max:$maxSize";
    }

    $validator = Validator::make($request->all(), $rules);

    $validator->after(function ($validator) use ($request, $allPaymentMethods) {
      $selected = $request->input('payment_methods', []);

      foreach ($selected as $methodId) {
        $method = $allPaymentMethods->get($methodId);
        $methodName = $method ? strtolower($method->method_name) : '';
        $isCod = str_contains($methodName, 'cash on delivery') || str_contains($methodName, 'cod');

        if ($isCod) {
          continue;
        }

        $accNumber = $request->input("account_number.$methodId");
        $accName = $request->input("account_name.$methodId");

        if (!strlen(trim((string) $accNumber ?? ''))) {
          $validator->errors()->add("account_number.$methodId", "Account number is required for " . ($method->method_name ?? "method ID {$methodId}"));
        }
        if (!strlen(trim((string) $accName ?? ''))) {
          $validator->errors()->add("account_name.$methodId", "Account name is required for " . ($method->method_name ?? "method ID {$methodId}"));
        }
      }
    });

    if ($validator->fails()) {
      Log::warning('Validation failed', ['errors' => $validator->errors()]);
      return back()->withErrors($validator)->withInput();
    }

    $validated = $validator->validated();

    $validated['business_location'] = collect([
      $validated['street'],
      $validated['barangay'],
      $validated['city'],
      $validated['province'],
      $validated['postal_code'],
    ])->filter()->implode(', ');

    DB::beginTransaction();

    try {
      $disk = Storage::disk('s3');

      // Store files
      $birPath = $request->hasFile('bir_registration') ? $request->file('bir_registration')->store('', 's3') : null;
      $permitPath = $request->hasFile('business_permit') ? $request->file('business_permit')->store('', 's3') : null;
      $idPath = $request->hasFile('valid_id') ? $request->file('valid_id')->store('', 's3') : null;
      $mayorPath = $request->hasFile('mayors_permit') ? $request->file('mayors_permit')->store('', 's3') : null;
      $businessImagePath = $request->hasFile('business_image') ? $request->file('business_image')->store('', 's3') : null;

      $userId = Auth::id();

      $vendor = Vendor::firstOrCreate(
        ['user_id' => $userId],
        [
          'fullname' => $validated['fullname'],
          'phone_number' => $validated['phone_number'],
          'registration_status' => 'Pending',
          'created_at' => now(),
        ]
      );

      /**
       * @var \Illuminate\Filesystem\FilesystemAdapter $disk
       */

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
        'business_permit_file' => $permitPath ? $disk->url($permitPath) : null,
        'valid_id_type' => $validated['valid_id_type'],
        'valid_id_file' => $idPath ? $disk->url($idPath) : null,
        'mayor_permit_file' => $mayorPath ? $disk->url($mayorPath) : null,
        'business_duration' => $validated['business_duration'] ?? null,
        'created_at' => now(),
      ]);

      // Save payment methods
      foreach ($validated['payment_methods'] as $methodId) {
        $bp = BusinessPaymentMethod::create([
          'business_id' => $business->business_id,
          'payment_method_id' => $methodId,
        ]);

        $method = $allPaymentMethods->get($methodId);
        $methodName = $method ? strtolower($method->method_name) : '';
        $isCod = str_contains($methodName, 'cash on delivery') || str_contains($methodName, 'cod');

        if (!$isCod) {
          BusinessPmDetail::where('business_payment_method_id', $bp->business_payment_method_id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);

          BusinessPmDetail::create([
            'business_payment_method_id' => $bp->business_payment_method_id,
            'account_number' => $request->input("account_number.$methodId"),
            'account_name' => $request->input("account_name.$methodId"),
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
        'trace' => $e->getTraceAsString(),
      ]);
      return back()->withErrors(['error' => 'Something went wrong: ' . $e->getMessage()]);
    }
  }
}
