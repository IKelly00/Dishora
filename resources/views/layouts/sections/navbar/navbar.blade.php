@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;

    $containerNav = $containerNav ?? 'container-fluid';
    $navbarDetached = $navbarDetached ?? '';
@endphp

<!-- Navbar -->
<nav id="layout-navbar"
    class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme
        {{ $navbarDetached }} {{ $navbarDetached === 'navbar-detached' ? $containerNav : '' }}">
    @if ($navbarDetached !== 'navbar-detached')
        <div class="{{ $containerNav }}">
    @endif

    {{-- Brand (only for full navbar) --}}
    @isset($navbarFull)
        <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-6">
            <a href="{{ url('/') }}" class="app-brand-link gap-2">
                <span class="app-brand-logo demo">
                    @include('_partials.macros', ['height' => 20])
                </span>
                <span class="app-brand-text demo menu-text fw-semibold ms-1">
                    {{ config('variables.templateName') }}
                </span>
            </a>
            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-xl-none">
                <i class="ri-close-fill align-middle"></i>
            </a>
        </div>
    @endisset

    {{-- Sidebar toggle (hidden for layout-without-menu) --}}
    @empty($navbarHideToggle)
        <div
            class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0
            {{ isset($menuHorizontal) ? 'd-xl-none' : '' }}
            {{ isset($contentNavbar) ? 'd-xl-none' : '' }}">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                <i class="ri-menu-fill ri-24px"></i>
            </a>
        </div>
    @endempty

    {{-- Navbar Right Side --}}
    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">

        {{-- GitHub Star Button --}}
        <ul class="navbar-nav flex-row align-items-center ms-auto">
            <li class="nav-item lh-1 me-4">
                {{-- Business Switcher in Navbar --}}
                @if (session('active_role') === 'vendor' && Auth::user()->vendor && Auth::user()->vendor->businessDetails->count() > 1)
                    <div class="dropdown me-3">
                        <button class="btn btn-light btn-sm dropdown-toggle business-pill" type="button"
                            id="businessDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ optional(Auth::user()->vendor->businessDetails->firstWhere('business_id', session('active_business_id')))->business_name ?? 'Select Business' }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="businessDropdown">
                            @foreach (Auth::user()->vendor->businessDetails as $business)
                                <li>
                                    <form action="{{ route('vendor.setBusiness') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="business_id" value="{{ $business->business_id }}">
                                        <button type="submit" class="dropdown-item">
                                            {{ $business->business_name }}
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </li>

            {{-- User Dropdown --}}
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                        @php
                            use App\Models\Customer;
                            use Illuminate\Support\Facades\Storage;
                            $user = auth()->user();
                            $avatarUrl = asset('assets/img/avatars/1.png');
                            if ($user) {
                                $uid = $user->user_id ?? $user->id;
                                $customer = Customer::where('user_id', $uid)->first();
                                if ($customer) {
                                    if ($customer->user_image && preg_match('#^https?://#i', $customer->user_image)) {
                                        $avatarUrl = $customer->user_image;
                                    } elseif ($customer->user_image) {
                                        try {
                                            $avatarUrl = Storage::disk('s3')->url($customer->user_image);
                                        } catch (\Throwable $e) {
                                            // keep default
                                        }
                                    }
                                }
                            }
                        @endphp

                        <img src="{{ $avatarUrl }}" alt="user-avatar" class="w-px-40 h-auto rounded-circle">

                    </div>
                </a>

                <ul class="dropdown-menu dropdown-menu-end mt-3 py-2">
                    {{-- Profile Header --}}
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 me-2">
                                    <div class="avatar avatar-online">
                                        <img src="{{ $avatarUrl }}" alt="user-avatar"
                                            class="w-px-40 h-auto rounded-circle">
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 small">{{ ucfirst(Auth::user()->fullname) }}</h6>
                                    <small class="text-muted">
                                        {{ ucfirst(session('active_role', 'Guest')) }}
                                    </small>
                                </div>
                            </div>
                        </a>
                    </li>

                    <li>
                        <div class="dropdown-divider"></div>
                    </li>

                    {{-- Profile --}}
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="ri-user-3-line ri-22px me-2"></i>
                            <span class="align-middle">My Profile</span>
                        </a>
                    </li>


                    {{-- Settings --}}
                    {{-- <li>
                        <a class="dropdown-item" href="javascript:void(0);">
                            <i class="ri-settings-4-line ri-22px me-2"></i>
                            <span class="align-middle">Settings</span>
                        </a>
                    </li> --}}

                    {{-- Billing --}}
                    {{-- <li>
                        <a class="dropdown-item" href="javascript:void(0);">
                            <span class="d-flex align-items-center align-middle">
                                <i class="flex-shrink-0 ri-file-text-line ri-22px me-3"></i>
                                <span class="flex-grow-1 align-middle">Billing</span>
                                <span
                                    class="flex-shrink-0 badge badge-center rounded-pill bg-danger h-px-20 d-flex align-items-center justify-content-center">
                                    4
                                </span>
                            </span>
                        </a>
                    </li> --}}

                    <li>
                        <div class="dropdown-divider"></div>
                    </li>

                    {{-- Logout --}}
                    <li>
                        <div class="d-grid px-4 pt-2 pb-1">
                            <a class="btn btn-danger d-flex" href="{{ route('logout') }}">
                                <small class="align-middle">Logout</small>
                                <i class="ri-logout-box-r-line ms-2 ri-16px"></i>
                            </a>
                        </div>
                    </li>
                </ul>
            </li>
            <!-- / User -->
        </ul>
    </div>

    @if ($navbarDetached !== 'navbar-detached')
        </div>
    @endif
</nav>
<!-- / Navbar -->


<style>
    /* Navbar container */
    #layout-navbar {
        position: sticky;
        top: 0;
        background: rgba(255, 255, 255, 0.9) !important;
        /* subtle translucency */
        backdrop-filter: blur(8px);
        /* frosted-glass look */
        border-bottom: 1px solid #f3f4f6;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        z-index: 1030;
        min-height: 56px;
        /* Compact height */
        padding: 0 1rem;
        display: flex;
        align-items: center;
    }

    /* Brand text */
    #layout-navbar .app-brand-text {
        font-weight: 700;
        color: #1f2937;
        transition: color 0.2s ease;
    }

    /* Navigation icons (menu toggle, star, etc.) */
    #layout-navbar .navbar-nav .nav-link i {
        color: #6b7280;
        transition: color 0.2s ease;
        font-size: 1.25rem;
    }

    /* User Avatar Container */
    #layout-navbar .navbar-nav .avatar {
        width: 36px;
        height: 36px;
        min-width: 36px;
        border-radius: 50%;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 0 2px #86efac, 0 0 0 4px #fff;
        /* soft online ring */
    }

    /* Avatar image inside */
    #layout-navbar .navbar-nav .avatar img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover;
        border-radius: 50%;
    }

    /* Align dropdown toggle properly */
    #layout-navbar .navbar-nav .nav-item.dropdown-user .nav-link {
        display: flex;
        align-items: center;
        height: 100%;
        padding: 0 0.25rem;
    }

    /* Dropdown menu */
    #layout-navbar .dropdown-menu {
        border: none;
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        background-color: #ffffff;
        padding: 0.5rem 0;
        overflow: hidden;
        min-width: 220px;
    }

    /* Dropdown items */
    #layout-navbar .dropdown-menu .dropdown-item {
        padding: 0.625rem 1rem;
        font-size: 0.9rem;
        font-weight: 500;
        color: #374151;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
    }

    #layout-navbar .dropdown-menu .dropdown-item i {
        color: #9ca3af;
        transition: color 0.2s ease;
        margin-right: 0.5rem;
    }

    /* Divider */
    #layout-navbar .dropdown-menu .dropdown-divider {
        margin: 0.5rem 0 !important;
        border-color: #f3f4f6;
    }

    /* Profile header inside dropdown */
    #layout-navbar .dropdown-menu .dropdown-item h6 {
        margin-bottom: 2px;
        font-size: 0.95rem;
        color: #111827;
    }

    #layout-navbar .dropdown-menu .dropdown-item small {
        font-size: 0.8rem;
        color: #6b7280;
    }

    /* Logout Button */
    #layout-navbar .dropdown-menu .btn-danger {
        border-radius: 10px;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        transition: all 0.2s ease;
    }

    /* Pill-style navbar business switcher */
    #layout-navbar .business-pill {
        border-radius: 20px;
        font-weight: 500;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        color: #374151;
        padding: 0.575rem 0.85rem;
        transition: all 0.2s ease;
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>
