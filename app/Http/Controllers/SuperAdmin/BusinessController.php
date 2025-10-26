<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BusinessController extends Controller
{
  /**
   * Display a listing of the businesses.
   */
  public function index(Request $request)
  {
    $query = BusinessDetail::with('vendor'); // Eager load vendor info

    $q = $request->input('q');
    $status = $request->input('status', 'all'); // Default to 'all'

    // Apply search filter
    if ($request->filled('q')) {
      $search = '%' . $q . '%';
      $query->where(function ($subQ) use ($search) {
        $subQ->where('business_name', 'like', $search)
          ->orWhere('business_type', 'like', $search)
          ->orWhereHas('vendor', function ($vendorQuery) use ($search) {
            $vendorQuery->where('fullname', 'like', 'search');
          });
      });
    }

    // Apply status filter
    if ($status !== 'all') {
      $query->where('verification_status', $status);
    }

    // Paginate the results
    $businesses = $query->latest('business_id')->paginate(15); // Adjust pagination count if needed

    // Return the view with data
    return view('content.superadmin.business.index', compact('businesses', 'q', 'status'));
  }

  public function show($id)
  {
    $business = BusinessDetail::with('vendor', 'openingHours', 'paymentMethods')->findOrFail($id);

    // files/columns we want to present and label for UI
    $fileColumns = [
      'business_image' => 'Business Image',
      'business_permit_file' => 'Business Permit',
      'valid_id_file' => 'Valid ID',
      'bir_reg_file' => 'BIR Registration',
      'mayor_permit_file' => 'Mayor Permit',
    ];

    $documents = [];

    foreach ($fileColumns as $col => $label) {
      $val = $business->{$col} ?? null;
      if (!$val) {
        $documents[$col] = [
          'label' => $label,
          'url' => null,
          'type' => null,
          'raw' => null,
        ];
        continue;
      }

      $url = null;
      $type = null;

      try {
        // If value looks like a full URL, use it directly
        if (filter_var($val, FILTER_VALIDATE_URL)) {
          $url = $val;
        } else {
          // If it's a path on disk, try to create a temporary URL
          // Adjust disk if you store on specific disk (default 'public' or 's3')
          // First check if file exists on default disk

          if (Storage::exists($val)) {
            // temporary URL for 30 minutes
            $url = Storage::temporaryUrl($val, Carbon::now()->addMinutes(30));
          } elseif (Storage::disk('public')->exists($val)) {
            // public disk fallback

            /** @var \Illuminate\Filesystem\FilesystemAdapter $publicDisk */
            $publicDisk = Storage::disk('public');
            $url = $publicDisk->url($val);
          } else {
            // fallback: maybe the path is relative to public path
            $publicPath = public_path($val);
            if (file_exists($publicPath)) {
              $url = asset($val);
            } else {
              // nothing found — use raw DB value (may still work)
              $url = $val;
            }
          }
        }

        // determine filetype by extension (simple)
        $ext = strtolower(pathinfo(parse_url($val, PHP_URL_PATH) ?: $val, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'])) {
          $type = 'image';
        } elseif ($ext === 'pdf') {
          $type = 'pdf';
        } else {
          $type = 'other';
        }
      } catch (\Throwable $e) {
        Log::warning("Could not prepare document url for business {$id} col {$col}: " . $e->getMessage());
        $url = $val; // fallback
        $type = null;
      }

      $documents[$col] = [
        'label' => $label,
        'url' => $url,
        'type' => $type,
        'raw' => $val,
      ];
    }

    return view('content.superadmin.business.show', compact('business', 'documents'));
  }

  public function edit($id)
  {
    $business = BusinessDetail::with('vendor')->findOrFail($id);
    return view('content.superadmin.business.edit', compact('business'));
  }

  public function update(Request $request, $id)
  {
    $business = BusinessDetail::findOrFail($id);

    // Validate the incoming data, including the newly added fields.
    $data = $request->validate([
      'business_name' => 'required|string|max:150',
      'business_description' => 'required|string',
      'business_type' => 'required|string|max:150',
      'business_location' => 'nullable|string', // New field added for location
      'verification_status' => ['required', Rule::in(['Pending', 'Approved', 'Rejected'])],
      'remarks' => 'nullable|string|max:255', // New field added for admin remarks
      'latitude' => 'nullable|numeric|between:-90,90', // New field added for latitude
      'longitude' => 'nullable|numeric|between:-180,180', // New field added for longitude
    ]);

    DB::transaction(function () use ($business, $data) {
      // Update the business record with the validated data
      $business->update($data);
    });

    return redirect()->route('super-admin.business.view', $business->business_id)
      ->with('success', 'Business updated successfully.');
  }

  // Approve
  public function approve(Request $request, $id)
  {
    $business = BusinessDetail::with('vendor')->findOrFail($id);

    // Validation Check
    if (!$business->vendor || $business->vendor->registration_status !== 'Approved') {
      $errorMessage = 'Cannot approve business: The vendor account is not yet approved.';

      if ($request->wantsJson()) {
        return response()->json(['message' => $errorMessage], 422);
      }
      return back()->with('error', $errorMessage);
    }

    $business->verification_status = 'Approved';
    $business->remarks = $request->input('remarks', $business->remarks);
    $business->save();

    if ($request->wantsJson()) {
      return response()->json(['message' => 'Business approved.', 'status' => 'Approved']);
    }

    return back()->with('success', 'Business approved.');
  }

  // Reject
  public function reject(Request $request, $id)
  {
    // *** UPDATED: Eager load vendor ***
    $business = BusinessDetail::with('vendor')->findOrFail($id);

    // *** UPDATED: Added Validation Check ***
    if (!$business->vendor || $business->vendor->registration_status !== 'Approved') {
      $errorMessage = 'Cannot reject business: The vendor account is not yet approved.';

      if ($request->wantsJson()) {
        // Return a 422 Unprocessable Entity error for the AJAX call
        return response()->json(['message' => $errorMessage], 422);
      }
      // Fallback for non-JSON requests
      return back()->with('error', $errorMessage);
    }
    // *** END: Validation Check ***

    $request->validate([
      'reason' => 'nullable|string|max:255',
    ]);

    $business->verification_status = 'Rejected';
    $business->remarks = $request->input('reason', $business->remarks);
    $business->save();

    if ($request->wantsJson()) {
      return response()->json(['message' => 'Business rejected.', 'status' => 'Rejected']);
    }

    return back()->with('success', 'Business rejected.');
  }

  /**
   * Remove the specified business from storage.
   */
  public function destroy(Request $request, $id)
  {
    $business = BusinessDetail::findOrFail($id);

    DB::transaction(function () use ($business) {
      $business->delete();
    });

    if ($request->wantsJson()) {
      return response()->json(['message' => 'Business deleted.']);
    }

    return redirect()->route('superadmin.dashboard')->with('success', 'Business deleted.');
  }
}
