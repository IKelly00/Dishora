<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
  /**
   * Register services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap services.
   */

  public function boot(): void
  {
    View::composer('*', function ($view) {
      $role = 'guest';
      $sessionRole = null;

      try {
        if (Auth::check()) {
          $sessionRole = session('active_role');

          if ($sessionRole) {
            $role = strtolower($sessionRole);
          } else {
            // Optional fallback: check if user has customer record
            $isCustomer = DB::table('customers')->where('user_id', Auth::id())->exists();
            $role = $isCustomer ? 'customer' : 'guest';
            session(['active_role' => $role]);
          }
        }

        //Log::info('MenuServiceProvider detected role', ['active_role' => $role]);

        // Determine menu file path safely
        switch ($role) {
          case 'customer':
            $menuPath = base_path('resources/menu/verticalMenu-customer.json');
            break;
          case 'vendor':
            $menuPath = base_path('resources/menu/verticalMenu-vendor.json');
            break;
          case 'admin':
            $menuPath = base_path('resources/menu/verticalMenu-admin.json');
            break;
          default:
            $menuPath = base_path('resources/menu/verticalMenu.json');
            break;
        }

        // Safety net 1: check if file exists
        if (!file_exists($menuPath)) {
          Log::error("Menu file missing", ['path' => $menuPath]);
          $menuPath = base_path('resources/menu/verticalMenu.json'); // fallback to default
        }

        // Safety net 2: handle invalid JSON
        $verticalMenuJson = @file_get_contents($menuPath);
        $verticalMenuData = json_decode($verticalMenuJson);

        if (json_last_error() !== JSON_ERROR_NONE || !$verticalMenuData) {
          Log::error('Menu JSON invalid or empty', [
            'path' => $menuPath,
            'error' => json_last_error_msg(),
          ]);
          $verticalMenuData = [];
        }

        $view->with('menuData', [$verticalMenuData]);
      } catch (\Throwable $e) {
        // Safety net 3: catch any unexpected errors
        Log::error('MenuServiceProvider failed', [
          'message' => $e->getMessage(),
          'trace' => $e->getTraceAsString(),
        ]);

        // Load safe default menu
        $view->with('menuData', [[]]);
      }
    });
  }
}
