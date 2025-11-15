<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VendorLocatorService;
use App\Models\{Notification, Vendor};
use Illuminate\Support\Facades\{DB, Log, Auth};

class NotificationController extends Controller
{

  public function __construct(protected VendorLocatorService $locator) {}

  private function getVendor(): ?Vendor
  {
    return Auth::user()?->vendor;
  }

  /**
   * Resolve the active business and related status flags for a vendor.
   */
  private function resolveBusinessContext(Vendor $vendor): array
  {
    $vendorStatus = $vendor->registration_status ?? null;

    $activeBusinessId = session('active_business_id');

    // Auto-select first business if none chosen yet
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

    // Reset "vendor status modal shown" when status first becomes Pending
    if ($vendorStatus === 'Pending' && session('last_vendor_status') !== 'Pending') {
      session(['vendor_status_modal_shown' => false]);
    }

    session(['last_vendor_status' => $vendorStatus]);

    if (!$business) {
      Log::warning('Business not found for vendor', compact('activeBusinessId'));
    } else {
      Log::info('Resolved businessStatus', compact('businessStatus'));
      Log::info('Show verification modal', compact('showVerificationModal'));
      Log::info('Vendor Status Session', ['vendor_status_modal_shown' => session('vendor_status_modal_shown')]);
    }

    return [
      'activeBusinessId'        => $activeBusinessId,
      'businessStatus'          => $businessStatus,
      'showVerificationModal'   => $showVerificationModal,
      'vendorStatus'            => $vendorStatus,
      'showVendorStatusModal'   => $vendorStatus === 'Pending',
      'showVendorRejectedModal' => $vendorStatus === 'Rejected',
    ];
  }

  /**
   * Build base view data depending on whether the user has a vendor profile.
   */
  private function buildViewData(?Vendor $vendor, array $extra = []): array
  {
    if (!$vendor) {
      return array_merge([
        'hasVendorAccess'         => false,
        'showRolePopup'           => true,
        'vendorStatus'            => session('vendorStatus', null),
        'showVendorStatusModal'   => session('vendorStatus') === 'Pending',
        'showVendorRejectedModal' => session('vendorStatus') === 'Rejected',
        'hasShownVendorModal'     => session('vendor_status_modal_shown', false),
      ], $extra);
    }

    return array_merge([
      'hasVendorAccess'     => $vendor->businessDetails()->exists(),
      'showRolePopup'       => false,
      'hasShownVendorModal' => session('vendor_status_modal_shown', false),
    ], $this->resolveBusinessContext($vendor), $extra);
  }

  public function index(Request $request)
  {
    try {
      $user = $request->user();
      $role = session('active_role', 'customer');            // 'vendor' or 'customer'
      $businessId = $request->query('business_id') ?? session('active_business_id');

      Log::info('[notifications.index] start', [
        'user_id' => $user?->user_id,
        'role' => $role,
        'business_id' => $businessId,
        'query_params' => $request->all(),
      ]);

      // Start with rows targeted to the current user only
      $query = Notification::where('user_id', $user->user_id);

      // Role-specific filtering:
      if ($role === 'vendor') {
        $query->where(function ($q) use ($businessId) {
          $q->where(function ($q2) use ($businessId) {
            $q2->where('recipient_role', 'vendor');
            if ($businessId) {
              $q2->where('business_id', $businessId);
            }
          })
            ->orWhere(function ($q3) {
              $q3->where('is_global', true)
                ->where(function ($qq) {
                  $qq->whereNull('recipient_role')
                    ->orWhere('recipient_role', 'vendor');
                });
            });
        });
      } else {
        $query->where(function ($q) {
          $q->where('recipient_role', 'customer')
            ->orWhere(function ($q3) {
              $q3->where('is_global', true)
                ->where(function ($qq) {
                  $qq->whereNull('recipient_role')
                    ->orWhere('recipient_role', 'customer');
                });
            });
        });
      }

      // Log the built query SQL and bindings (for debugging)
      try {
        Log::debug('[notifications.index] built query', [
          'sql' => $query->toSql(),
          'bindings' => $query->getBindings(),
        ]);
      } catch (\Throwable $e) {
        Log::debug('[notifications.index] unable to get sql/bindings', ['err' => $e->getMessage()]);
      }

      $perPage = (int)$request->query('per_page', 10);
      $notifications = $query->orderByDesc('created_at')->paginate($perPage);

      // decode payload for client
      $notifications->getCollection()->transform(function ($n) {
        $n->payload = $n->payload ? json_decode($n->payload, true) : null;
        return $n;
      });

      // Log the pagination result
      Log::info('[notifications.index] result', [
        'user_id' => $user->user_id,
        'role' => $role,
        'business_id' => $businessId,
        'per_page' => $perPage,
        'returned_count' => $notifications->count(),
        'total' => $notifications->total ?? null,
        'current_page' => $notifications->currentPage ?? null,
        'last_page' => $notifications->lastPage ?? null,
      ]);

      return response()->json($notifications);
    } catch (\Throwable $e) {
      Log::error('[notifications.index] exception', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return response()->json(['error' => 'Failed to fetch notifications'], 500);
    }
  }

  public function viewAll(Request $request)
  {
    try {
      $user = $request->user();
      $role = session('active_role', 'customer');            // 'vendor' or 'customer'
      $businessId = $request->query('business_id') ?? session('active_business_id');

      Log::info('[notifications.index] start', [
        'user_id' => $user?->user_id,
        'role' => $role,
        'business_id' => $businessId,
        'query_params' => $request->all(),
      ]);

      // Start with rows targeted to the current user only
      $query = Notification::where('user_id', $user->user_id);

      // Role-specific filtering:
      if ($role === 'vendor') {
        $query->where(function ($q) use ($businessId) {
          $q->where(function ($q2) use ($businessId) {
            $q2->where('recipient_role', 'vendor');
            if ($businessId) {
              $q2->where('business_id', $businessId);
            }
          })
            ->orWhere(function ($q3) {
              $q3->where('is_global', true)
                ->where(function ($qq) {
                  $qq->whereNull('recipient_role')
                    ->orWhere('recipient_role', 'vendor');
                });
            });
        });
      } else {
        $query->where(function ($q) {
          $q->where('recipient_role', 'customer')
            ->orWhere(function ($q3) {
              $q3->where('is_global', true)
                ->where(function ($qq) {
                  $qq->whereNull('recipient_role')
                    ->orWhere('recipient_role', 'customer');
                });
            });
        });
      }

      // Log the built query SQL and bindings (for debugging)
      try {
        Log::debug('[notifications.index] built query', [
          'sql' => $query->toSql(),
          'bindings' => $query->getBindings(),
        ]);
      } catch (\Throwable $e) {
        Log::debug('[notifications.index] unable to get sql/bindings', ['err' => $e->getMessage()]);
      }

      $perPage = (int)$request->query('per_page', 10);
      $notifications = $query->orderByDesc('created_at')->paginate($perPage);

      // decode payload for client
      $notifications->getCollection()->transform(function ($n) {
        $n->payload = $n->payload ? json_decode($n->payload, true) : null;
        return $n;
      });

      // Log the pagination result
      Log::info('[notifications.index] result', [
        'user_id' => $user->user_id,
        'role' => $role,
        'business_id' => $businessId,
        'per_page' => $perPage,
        'returned_count' => $notifications->count(),
        'total' => $notifications->total ?? null,
        'current_page' => $notifications->currentPage ?? null,
        'last_page' => $notifications->lastPage ?? null,
      ]);

      // [START] === THIS IS THE FIX ===
      // Check if the request is an AJAX call (expects JSON)
      if ($request->expectsJson()) {
        // This is for your dropdown script
        return response()->json($notifications);
      }

      $vendor   = $this->getVendor();

      $viewData = $this->buildViewData($vendor, compact('notifications'));

      return view('layouts.sections.navbar.notifications-viewAll', $viewData);
    } catch (\Throwable $e) {
      Log::error('[notifications.index] exception', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      // Handle both types of requests on error
      if ($request->expectsJson()) {
        return response()->json(['error' => 'Failed to fetch notifications'], 500);
      }
      return back()->with('error', 'Failed to fetch notifications');
    }
  }

  public function unreadCount(Request $request)
  {
    try {
      $user = $request->user();
      $role = session('active_role', 'customer');
      $businessId = $request->query('business_id') ?? session('active_business_id');

      Log::info('[notifications.unreadCount] start', [
        'user_id' => $user?->user_id,
        'role' => $role,
        'business_id' => $businessId,
        'query_params' => $request->all(),
      ]);

      $query = Notification::where('user_id', $user->user_id)
        ->where('is_read', false);

      if ($role === 'vendor') {
        $query->where(function ($q) use ($businessId) {
          $q->where(function ($q2) use ($businessId) {
            $q2->where('recipient_role', 'vendor');
            if ($businessId) {
              $q2->where('business_id', $businessId);
            }
          })
            ->orWhere(function ($q3) {
              $q3->where('is_global', true)
                ->where(function ($qq) {
                  $qq->whereNull('recipient_role')
                    ->orWhere('recipient_role', 'vendor');
                });
            });
        });
      } else {
        $query->where(function ($q) {
          $q->where('recipient_role', 'customer')
            ->orWhere(function ($q3) {
              $q3->where('is_global', true)
                ->where(function ($qq) {
                  $qq->whereNull('recipient_role')
                    ->orWhere('recipient_role', 'customer');
                });
            });
        });
      }

      // Log SQL + bindings for the unread query
      try {
        Log::debug('[notifications.unreadCount] built query', [
          'sql' => $query->toSql(),
          'bindings' => $query->getBindings(),
        ]);
      } catch (\Throwable $e) {
        Log::debug('[notifications.unreadCount] unable to get sql/bindings', ['err' => $e->getMessage()]);
      }

      $count = $query->count();

      Log::info('[notifications.unreadCount] result', [
        'user_id' => $user->user_id,
        'role' => $role,
        'business_id' => $businessId,
        'unread' => $count,
      ]);

      return response()->json(['unread' => $count]);
    } catch (\Throwable $e) {
      Log::error('[notifications.unreadCount] exception', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return response()->json(['error' => 'Failed to fetch unread count'], 500);
    }
  }

  /**
   * Mark a single notification read — but only if it belongs to the user
   * AND is visible to their current role/business context.
   */
  public function markRead(Request $request, $id)
  {
    try {
      $user = $request->user();
      $role = session('active_role', 'customer');
      $businessId = $request->query('business_id') ?? session('active_business_id');

      Log::info('[notifications.markRead] start', [
        'user_id' => $user?->user_id,
        'role' => $role,
        'business_id' => $businessId,
        'notification_id' => $id,
      ]);

      // Base: the notification must belong to the user
      $query = Notification::where('notification_id', $id)
        ->where('user_id', $user->user_id);

      // Apply same visibility rules as index/unreadCount
      if ($role === 'vendor') {
        $query->where(function ($q) use ($businessId) {
          $q->where(function ($q2) use ($businessId) {
            $q2->where('recipient_role', 'vendor');
            if ($businessId) {
              $q2->where('business_id', $businessId);
            }
          })
            ->orWhere(function ($q3) {
              $q3->where('is_global', true)
                ->where(function ($qq) {
                  $qq->whereNull('recipient_role')
                    ->orWhere('recipient_role', 'vendor');
                });
            });
        });
      } else {
        $query->where(function ($q) {
          $q->where('recipient_role', 'customer')
            ->orWhere(function ($q3) {
              $q3->where('is_global', true)
                ->where(function ($qq) {
                  $qq->whereNull('recipient_role')
                    ->orWhere('recipient_role', 'customer');
                });
            });
        });
      }

      // Try to fetch the notification — if not found, it's either not there or not visible
      $n = $query->firstOrFail();

      if (!$n->is_read) {
        $n->is_read = true;
        $n->save();

        Log::info('[notifications.markRead] marked read', [
          'notification_id' => $n->notification_id,
          'user_id' => $user->user_id,
        ]);
      } else {
        Log::info('[notifications.markRead] already read', [
          'notification_id' => $n->notification_id,
          'user_id' => $user->user_id,
        ]);
      }

      return response()->json(['ok' => true]);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
      Log::warning('[notifications.markRead] not found or not visible', [
        'notification_id' => $id,
        'user_id' => $request->user()?->user_id,
        'role' => session('active_role'),
      ]);
      return response()->json(['error' => 'Notification not found'], 404);
    } catch (\Throwable $e) {
      Log::error('[notifications.markRead] exception', ['err' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
      return response()->json(['error' => 'Failed to mark notification read'], 500);
    }
  }

  /**
   * Mark all visible/unread notifications as read (role+business aware).
   * Returns { ok: true, updated: N }
   */
  public function markAllRead(Request $request)
  {
    try {
      $user = $request->user();
      $role = session('active_role', 'customer');
      $businessId = $request->query('business_id') ?? session('active_business_id');

      Log::info('[notifications.markAllRead] start', [
        'user_id' => $user?->user_id,
        'role' => $role,
        'business_id' => $businessId,
      ]);

      // Base unread rows for the user
      $query = Notification::where('user_id', $user->user_id)
        ->where('is_read', false);

      // visibility filter
      if ($role === 'vendor') {
        $query->where(function ($q) use ($businessId) {
          $q->where(function ($q2) use ($businessId) {
            $q2->where('recipient_role', 'vendor');
            if ($businessId) {
              $q2->where('business_id', $businessId);
            }
          })
            ->orWhere(function ($q3) {
              $q3->where('is_global', true)
                ->where(function ($qq) {
                  $qq->whereNull('recipient_role')
                    ->orWhere('recipient_role', 'vendor');
                });
            });
        });
      } else {
        $query->where(function ($q) {
          $q->where('recipient_role', 'customer')
            ->orWhere(function ($q3) {
              $q3->where('is_global', true)
                ->where(function ($qq) {
                  $qq->whereNull('recipient_role')
                    ->orWhere('recipient_role', 'customer');
                });
            });
        });
      }

      // Debug: log SQL & bindings for verification (optional)
      try {
        Log::debug('[notifications.markAllRead] built query', [
          'sql' => $query->toSql(),
          'bindings' => $query->getBindings(),
        ]);
      } catch (\Throwable $e) {
        // ignore if debug fails
      }

      // Perform update
      $updated = $query->update(['is_read' => true]);

      Log::info('[notifications.markAllRead] result', [
        'user_id' => $user->user_id,
        'role' => $role,
        'business_id' => $businessId,
        'updated' => $updated,
      ]);

      return response()->json(['ok' => true, 'updated' => $updated]);
    } catch (\Throwable $e) {
      Log::error('[notifications.markAllRead] exception', ['err' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
      return response()->json(['error' => 'Failed to mark all read'], 500);
    }
  }
}
