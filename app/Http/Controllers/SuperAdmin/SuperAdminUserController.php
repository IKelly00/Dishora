<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class SuperAdminUserController extends Controller
{
  /**
   * Display a listing of the users.
   */
  public function index(Request $request)
  {
    $query = User::query();
    $query->where('user_id', '!=', 1);

    $q = $request->input('q');
    $page = $request->input('page', 1);

    if ($request->filled('q')) {
      $search = '%' . $q . '%';
      $query->where(function ($subQ) use ($search) {
        $subQ->where('fullname', 'like', $search)
          ->orWhere('email', 'like', $search)
          ->orWhere('username', 'like', $search);
      });
    }

    // This logic ensures that a new search always starts on page 1
    $targetPage = $request->ajax() && $request->has('q') ? 1 : $page;
    $users = $query->latest('user_id')->paginate(15, ['*'], 'page', $targetPage);

    // 3. Handle AJAX vs. Full View
    if ($request->ajax()) {

      // We still need the pagination links
      $paginationLinks = $users->appends(['q' => $q])->links()->toHtml();

      // Format the data for safe and easy use in JavaScript
      $usersData = $users->map(function ($user) {
        return [
          'user_id' => $user->user_id,
          'username' => $user->username,
          'fullname' => $user->fullname,
          'email' => $user->email,
          // Pre-build the status HTML on the server
          'status_html' => (isset($user->is_active) && !$user->is_active)
            ? '<span class="badge bg-label-secondary me-1">Inactive</span>'
            : '<span class="badge bg-label-success me-1">Active</span>',
          'registered_date' => $user->created_at->format('M d, Y'),
          'edit_url' => route('super-admin.users.edit', $user->user_id),
        ];
      });

      return response()->json([
        'users_data' => $usersData, // <-- Send the raw data
        'pagination_links' => $paginationLinks,
        'total' => $users->total(),
        'from' => $users->firstItem(),
        'to' => $users->lastItem(),
      ]);
    }

    // 4. Return Full View for initial load
    return view('content.superadmin.users.index', compact('users'));
  }

  /**
   * Display the specified user's information.
   */
  public function show($id) // Changed parameter name from 'user' to 'id' for clarity
  {
    // Eager load the 'customer' relationship to avoid extra queries
    $user = User::with('customer')->findOrFail($id);

    // Pass the user data to the view
    return view('content.superadmin.users.show', compact('user'));
  }

  /**
   * Show the form for creating a new user.
   */
  public function create()
  {
    // Add form for creating a new user (optional, but good for CRUD)
    return view('content.superadmin.users.create');
  }

  /**
   * Store a newly created user in storage.
   */
  public function store(Request $request)
  {
    $data = $request->validate([
      'username' => 'required|string|max:100|unique:users,username',
      'email' => 'required|string|email|max:255|unique:users,email',
      'fullname' => 'required|string|max:255',
      'phone_number' => 'nullable|string|max:20',
      'password' => 'required|string|min:8|confirmed',
      // 'is_verified' is removed from validation
      'verification_status' => 'required|boolean', // Email Verification Status
    ]);

    DB::transaction(function () use ($data, $request) {
      // 1. Create the User
      $user = User::create([
        'username' => $data['username'],
        'email' => $data['email'],
        'fullname' => $data['fullname'],
        'password' => Hash::make($data['password']),

        // --- NEW LOGIC ---
        // Set both fields based on the one dropdown
        'is_verified' => (bool) $data['verification_status'],
        'email_verified_at' => $data['verification_status'] ? now() : null,
        // --- END NEW LOGIC ---
      ]);

      // 2. If a phone number was provided, create the related Customer record
      if ($request->filled('phone_number')) {
        $user->customer()->create([
          'contact_number' => $data['phone_number']
        ]);
      }
    });

    return redirect()->route('super-admin.users.index')
      ->with('success', 'User created successfully.');
  }


  /**
   * Show the form for editing the specified user.
   */
  public function edit($id)
  {
    // Eager load the customer relationship so we can display the phone number
    $user = User::with('customer')->findOrFail($id);
    return view('content.superadmin.users.edit', compact('user'));
  }

  /**
   * Update the specified user in storage.
   * (This is the fully corrected update method)
   */
  /**
   * Update the specified user in storage.
   */
  public function update(Request $request, $id)
  {
    $user = User::with('customer')->findOrFail($id);

    // 1. VALIDATE THE INPUT
    $data = $request->validate([
      'fullname' => 'required|string|max:255',
      'phone_number' => 'nullable|string|max:11',
      // 'is_verified' is removed from validation
      'verification_status' => 'required|boolean',
      'password' => 'nullable|string|min:8|confirmed',
    ]);

    // 2. CHECK FOR ACTUAL CHANGES
    $userUpdateData = [];
    $customerUpdateData = [];

    // --- User Table Changes ---
    if ($data['fullname'] !== $user->fullname) {
      $userUpdateData['fullname'] = $data['fullname'];
    }
    if ($request->filled('password')) {
      $userUpdateData['password'] = Hash::make($data['password']);
    }

    // --- NEW LOGIC for is_verified and email_verified_at ---
    $wantsVerified = (bool) $data['verification_status'];
    $isVerified_Account = (bool) $user->is_verified;
    $isVerified_Email = (bool) $user->email_verified_at;

    // Check if the account status needs to change
    if ($wantsVerified !== $isVerified_Account) {
      $userUpdateData['is_verified'] = $wantsVerified;
    }

    // Check if the email timestamp needs to change
    if ($wantsVerified && !$isVerified_Email) {
      // Admin wants to VERIFY
      $userUpdateData['email_verified_at'] = now();
    } elseif (!$wantsVerified && $isVerified_Email) {
      // Admin wants to UN-VERIFY
      $userUpdateData['email_verified_at'] = null;
    }
    // --- END NEW LOGIC ---

    // --- Customer Table Changes ---
    $currentPhoneNumber = $user->customer->contact_number ?? null;
    if ($data['phone_number'] !== $currentPhoneNumber) {
      $customerUpdateData['contact_number'] = $data['phone_number'];
    }

    // 3. PERFORM ACTION
    if (empty($userUpdateData) && empty($customerUpdateData)) {
      return redirect()->back()->with('info', 'No changes were detected.');
    }

    // 4. UPDATE IN DATABASE
    DB::transaction(function () use ($user, $userUpdateData, $customerUpdateData) {
      if (!empty($userUpdateData)) {
        $user->update($userUpdateData);
      }
      if (!empty($customerUpdateData)) {
        if ($user->customer) {
          $user->customer->update($customerUpdateData);
        } else {
          $user->customer()->create($customerUpdateData);
        }
      }
    });

    return redirect()->back()->with('success', 'User updated successfully.');
  }

  /**
   * Remove the specified user from storage.
   */
  public function destroy($id)
  {
    $user = User::findOrFail($id);

    DB::transaction(function () use ($user) {
      $user->delete();
    });

    return response()->json(['message' => 'User deleted successfully.']);
  }
}
