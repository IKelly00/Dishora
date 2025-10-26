<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;
use Illuminate\Support\Facades\{DB, Log};

class SuperAdminVendorController extends Controller
{
  /**
   * Display a listing of the vendors.
   */
  public function index(Request $request)
  {
    // Eager load the 'businessDetails' relationship (which is a collection)
    $query = Vendor::with('businessDetails'); // Use the correct relationship name

    $q = $request->input('q');
    $status = $request->input('status', 'all');

    // Apply search filter
    if ($request->filled('q')) {
      $search = '%' . $q . '%';
      $query->where(function ($subQ) use ($search) {
        $subQ->where('fullname', 'like', $search)
          ->orWhere('phone_number', 'like', $search)
          // Search related business names (checks all associated businesses)
          ->orWhereHas('businessDetails', function ($bizQuery) use ($search) { // Use correct relationship name
            $bizQuery->where('business_name', 'like', $search);
          });
      });
    }

    // Apply status filter
    if ($status !== 'all') {
      $query->where('registration_status', $status);
    }

    $vendors = $query->latest('vendor_id')->paginate(15);

    return view('content.superadmin.vendors.index', compact('vendors', 'q', 'status'));
  }

  /**
   * Display the specified vendor's information.
   */
  public function show($id)
  {
    // Eager load the 'businessDetails' relationship
    $vendor = Vendor::with('businessDetails')->findOrFail($id);

    return view('content.superadmin.vendors.show', compact('vendor'));
  }

  /**
   * Approve vendor registration.
   */
  public function approveRegistration($id)
  {
    $vendor = Vendor::findOrFail($id);
    $vendor->registration_status = 'Approved';
    $vendor->save();

    // Return JSON response for AJAX
    return response()->json(['status' => 'Approved', 'message' => 'Vendor registration approved successfully.']);
  }

  /**
   * Reject vendor registration.
   */
  public function rejectRegistration(Request $request, $id)
  {
    $vendor = Vendor::findOrFail($id);
    $vendor->registration_status = 'Rejected';
    // Optionally save the reason if you add a 'remarks' column to vendors table
    // $vendor->remarks = $request->input('reason');
    $vendor->save();

    // Return JSON response for AJAX
    return response()->json(['status' => 'Rejected', 'message' => 'Vendor registration rejected.']);
  }

  /**
   * Remove the specified vendor from storage.
   */
  public function destroy(Request $request, $id) // Added Request $request
  {
    // Find the vendor, load business details relationship, or fail
    $vendor = Vendor::with('businessDetails')->findOrFail($id);

    // *** NEW VALIDATION CHECK ***
    // Check if any associated business has 'Approved' status
    $hasApprovedBusiness = $vendor->businessDetails->contains('verification_status', 'Approved');

    if ($hasApprovedBusiness) {
      $errorMessage = 'Cannot delete vendor: This vendor has one or more approved businesses. Please reject or delete them first.';

      // Check if the request wants JSON (likely from AJAX in the future)
      if ($request->wantsJson()) {
        // Return a 422 Unprocessable Entity error
        return response()->json(['message' => $errorMessage], 422);
      } else {
        // For standard form submission, redirect back with an error message
        // You might need to add session flash message display in your blade layout
        return redirect()->back()->with('error', $errorMessage);
      }
    }
    // *** END VALIDATION CHECK ***


    // Proceed with deletion if no approved businesses found
    try {
      DB::transaction(function () use ($vendor) {
        // Assuming you've implemented the 'booted' method with the deleting event in Vendor model:
        $vendor->delete();
        // If you haven't used the model event, uncomment the line below:
        // $vendor->businessDetails()->delete(); // Delete businesses manually first
        // $vendor->delete(); // Then delete vendor
      });

      $successMessage = 'Vendor and associated businesses deleted successfully.';

      if ($request->wantsJson()) {
        return response()->json(['message' => $successMessage]);
      } else {
        // Redirect to the vendor index page with a success message
        return redirect()->route('super-admin.vendors.index')->with('success', $successMessage);
      }
    } catch (\Exception $e) {
      Log::error("Error deleting vendor {$id}: " . $e->getMessage()); // Log the error

      $errorMessage = 'An error occurred while trying to delete the vendor.';

      if ($request->wantsJson()) {
        return response()->json(['message' => $errorMessage], 500); // Internal Server Error
      } else {
        return redirect()->back()->with('error', $errorMessage);
      }
    }
  }
}
