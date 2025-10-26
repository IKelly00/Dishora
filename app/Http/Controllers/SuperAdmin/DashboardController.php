<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\BusinessDetail;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
  public function index(Request $request)
  {
    $start = microtime(true);
    Log::info('[SuperAdmin Dashboard] index() start', [
      'url' => $request->fullUrl(),
      'ip' => $request->ip(),
      'query' => $request->all(),
    ]);

    try {
      // Basic platform stats
      $stats = [
        'total_users' => User::where('username', '!=', 'admin')->where('user_id', '!=', 1)->count(),
        'total_vendors' => Vendor::count(),
        'pending_businesses' => BusinessDetail::where('verification_status', 'Pending')->count(),
        'total_orders' => Order::count(),
      ];

      Log::info('[SuperAdmin Dashboard] stats computed', ['stats' => $stats]);

      // Additional counts used in blade
      $totalBusinesses = BusinessDetail::count();
      $pendingBusinesses = BusinessDetail::where('verification_status', 'Pending')->count();
      Log::info('[SuperAdmin Dashboard] business counts', [
        'totalBusinesses' => $totalBusinesses,
        'pendingBusinesses' => $pendingBusinesses,
      ]);

      // Pending vendors count (adjust column if different)
      $pendingVendors = Vendor::where('registration_status', 'Pending')->count();
      Log::info('[SuperAdmin Dashboard] pending vendors', ['pendingVendors' => $pendingVendors]);

      // Build businesses listing (with vendor relationship)
      $perPage = 15;
      $businessesQuery = BusinessDetail::with('vendor')
        ->when($request->filled('q'), function ($qBuilder) use ($request) {
          $term = '%' . $request->input('q') . '%';
          $qBuilder->where(function ($q) use ($term) {
            $q->where('business_name', 'like', $term)
              ->orWhere('business_description', 'like', $term)
              ->orWhere('business_type', 'like', $term);
          });
        })
        ->when($request->filled('status') && $request->input('status') !== 'all', function ($qBuilder) use ($request) {
          $qBuilder->where('verification_status', $request->input('status'));
        })
        ->orderBy('created_at', 'desc');

      // Log SQL / bindings for debugging (single line)
      try {
        $sql = $businessesQuery->toSql();
        $bindings = $businessesQuery->getQuery()->getBindings();
        Log::debug('[SuperAdmin Dashboard] businessesQuery', ['sql' => $sql, 'bindings' => $bindings]);
      } catch (\Throwable $exSql) {
        Log::warning('[SuperAdmin Dashboard] failed to get query SQL', ['exception' => $exSql->getMessage()]);
      }

      $businesses = $businessesQuery->paginate($perPage)->withQueryString();
      Log::info('[SuperAdmin Dashboard] businesses paginated', [
        'perPage' => $perPage,
        'currentPage' => $businesses->currentPage(),
        'total' => $businesses->total(),
      ]);

      // Recent orders (latest 6)
      $recentOrders = Order::orderBy('created_at', 'desc')->limit(6)->get();
      Log::info('[SuperAdmin Dashboard] recentOrders count', ['count' => $recentOrders->count()]);

      $duration = round((microtime(true) - $start) * 1000, 2);
      Log::info('[SuperAdmin Dashboard] index() end', ['duration_ms' => $duration]);

      // Pass everything the view expects
      return view('content.superadmin.superadmin-dashboard', [
        'stats' => $stats,
        'totalBusinesses' => $totalBusinesses,
        'pendingBusinesses' => $pendingBusinesses,
        'pendingVendors' => $pendingVendors,
        'businesses' => $businesses,
      ]);
    } catch (\Throwable $e) {
      // Log full error and re-throw so APP_DEBUG can show the exception page
      Log::error('[SuperAdmin Dashboard] Exception in index()', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
      ]);

      // optionally return a JSON debug response while developing:
      // return response()->json(['error' => $e->getMessage()], 500);

      throw $e;
    }
  }
}
