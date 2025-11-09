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

            {{-- Notifications Bell --}}
            <li class="nav-item dropdown me-3" id="nav-notifications">
                <a class="nav-link position-relative" href="javascript:void(0)" id="notificationsToggle"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ri-notification-3-line ri-24px"></i>
                    <span id="notif-badge"
                        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                        style="font-size:0.65rem; min-width:18px; padding:0.15rem 0.4rem;">
                        0
                    </span>
                </a>

                <script>
                    window.APP = window.APP || {};
                    window.APP.activeRole = "{{ session('active_role', 'customer') }}";
                    window.APP.activeBusinessId = "{{ session('active_business_id') ?? '' }}";
                </script>


                <div class="dropdown-menu dropdown-menu-end dropdown-notifications mt-3 p-0"
                    aria-labelledby="notificationsToggle" style="width:360px; max-height:420px; overflow:auto;">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <strong>Notifications</strong>
                        <button id="notif-mark-all" class="btn btn-sm btn-link">Mark all read</button>
                    </div>

                    <div id="notif-loading" class="p-3 text-center small text-muted">Loading…</div>

                    <div id="notif-list" style="display:none;">
                        <ul class="list-group list-group-flush" id="notif-items"></ul>
                        <div id="notif-empty" class="p-3 text-center text-muted small d-none">You're all caught up.
                        </div>
                        <div class="p-2 text-center">
                            <button id="notif-load-more" class="btn btn-sm btn-outline-secondary d-none">Load
                                more</button>
                        </div>
                    </div>

                    <div class="p-2 border-top small text-center">
                        <a href="{{ route('notifications.index') }}" class="text-decoration-none">View all</a>
                    </div>
                </div>
            </li>
            {{-- End Notifications Bell --}}

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

<script>
    (function() {
        // CONFIG
        const UNREAD_URL = '/notifications/unread-count';
        const LIST_URL = '/notifications';
        const MARK_READ_URL_TEMPLATE = '/notifications/{id}/read';
        const MARK_ALL_URL = '/notifications/read-all';
        const POLL_INTERVAL = 15000; // ms

        const ACTIVE_ROLE = (window.APP && window.APP.activeRole) ? window.APP.activeRole : 'customer';
        const BUSINESS_ID = (window.APP && window.APP.activeBusinessId) ? window.APP.activeBusinessId : '';
        const IS_VENDOR = ACTIVE_ROLE === 'vendor';

        function withBusiness(url) {
            if (!IS_VENDOR || !BUSINESS_ID) return url;
            return url + (url.indexOf('?') === -1 ? '?' : '&') + 'business_id=' + encodeURIComponent(BUSINESS_ID);
        }

        // STATE
        let currentPage = 1;
        let loading = false;

        // ELEMENTS
        const badge = document.getElementById('notif-badge');
        const toggle = document.getElementById('notificationsToggle');
        const notifLoading = document.getElementById('notif-loading');
        const notifListWrap = document.getElementById('notif-list');
        const notifItems = document.getElementById('notif-items');
        const notifEmpty = document.getElementById('notif-empty');
        const loadMoreBtn = document.getElementById('notif-load-more');
        const markAllBtn = document.getElementById('notif-mark-all');

        function updateBadge(count) {
            if (!badge) return;
            if (count && count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }

        // Fetch unread count (FIXED)
        async function fetchUnreadCount() {
            try {
                const res = await fetch(withBusiness(UNREAD_URL), {
                    credentials: 'same-origin'
                });
                if (!res.ok) return;
                const json = await res.json();
                updateBadge(json.unread ?? 0);
            } catch (err) {
                console.error('Unread count error', err);
            }
        }

        function renderNotificationItem(n) {
            const li = document.createElement('li');
            li.className = 'list-group-item list-group-item-action d-flex align-items-start';
            if (!n.is_read) li.classList.add('bg-light');

            const title = (n.payload && (n.payload.title || n.payload.order_id || n.event_type)) || n.event_type;
            const subtitle = (n.payload && n.payload.excerpt) ? n.payload.excerpt : (n.payload && n.payload.status ?
                'Status: ' + n.payload.status : '');
            const time = new Date(n.created_at).toLocaleString();

            const a = document.createElement('a');
            a.href = (n.payload && n.payload.url) ? n.payload.url : 'javascript:void(0);';
            a.className = 'flex-grow-1 text-decoration-none text-body';
            a.style.display = 'block';
            a.dataset.notificationId = n.notification_id;
            a.dataset.targetUrl = n.payload && n.payload.url ? n.payload.url : '';

            a.addEventListener('click', function(e) {
                if (!a.dataset.targetUrl) e.preventDefault();
                if (!n.is_read) {
                    markAsRead(n.notification_id).then(() => {
                        li.classList.remove('bg-light');
                        const curr = parseInt(badge.textContent) || 0;
                        updateBadge(Math.max(0, curr - 1));
                    }).catch(() => {});
                }
            });

            const h6 = document.createElement('div');
            h6.className = 'fw-medium';
            h6.textContent = title;
            const p = document.createElement('div');
            p.className = 'small text-muted';
            p.textContent = subtitle;
            const small = document.createElement('div');
            small.className = 'small text-muted mt-1';
            small.textContent = time;

            a.appendChild(h6);
            if (subtitle) a.appendChild(p);
            a.appendChild(small);
            li.appendChild(a);
            return li;
        }

        // Load notifications page (use withBusiness for vendor)
        async function loadNotifications(page = 1) {
            if (loading) return;
            loading = true;
            notifLoading.style.display = 'block';
            notifListWrap.style.display = 'none';
            try {
                const url = withBusiness(`${LIST_URL}?page=${page}`);
                const res = await fetch(url, {
                    credentials: 'same-origin'
                });
                if (!res.ok) throw new Error('Failed to fetch');
                const json = await res.json();
                const items = json.data || json;
                if (page === 1) notifItems.innerHTML = '';

                if (items && items.length) {
                    items.forEach(item => {
                        if (item.payload && typeof item.payload === 'string') {
                            try {
                                item.payload = JSON.parse(item.payload);
                            } catch (e) {}
                        }
                        const li = renderNotificationItem(item);
                        notifItems.appendChild(li);
                    });
                    notifEmpty.classList.add('d-none');
                } else {
                    if (page === 1) notifEmpty.classList.remove('d-none');
                }

                if (json.meta && json.meta.current_page && json.meta.last_page && json.meta.current_page < json
                    .meta.last_page) {
                    loadMoreBtn.classList.remove('d-none');
                    loadMoreBtn.dataset.nextPage = json.meta.current_page + 1;
                } else {
                    loadMoreBtn.classList.add('d-none');
                }

                notifListWrap.style.display = 'block';
            } catch (err) {
                console.error('Load notifications error', err);
            } finally {
                notifLoading.style.display = 'none';
                loading = false;
            }
        }

        async function markAsRead(notificationId) {
            try {
                const url = MARK_READ_URL_TEMPLATE.replace('{id}', notificationId);
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                            'content')
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('Mark read failed');
                return true;
            } catch (err) {
                console.error('markAsRead err', err);
                throw err;
            }
        }

        async function markAllRead() {
            try {
                const res = await fetch(MARK_ALL_URL, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                            'content')
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('Mark all failed');
                updateBadge(0);
                document.querySelectorAll('#notif-items .bg-light').forEach(el => el.classList.remove(
                    'bg-light'));
                return true;
            } catch (err) {
                console.error('markAllRead err', err);
                throw err;
            }
        }

        if (toggle) {
            toggle.addEventListener('click', function() {
                setTimeout(() => {
                    if (notifItems.children.length === 0) loadNotifications(1);
                }, 150);
            });
        }

        if (loadMoreBtn) loadMoreBtn.addEventListener('click', function() {
            const next = parseInt(loadMoreBtn.dataset.nextPage || '0');
            if (next) loadNotifications(next);
        });

        if (markAllBtn) markAllBtn.addEventListener('click', function() {
            markAllRead().catch(() => {});
        });

        // initial
        fetchUnreadCount();
        setInterval(fetchUnreadCount, POLL_INTERVAL);
    })();
</script>
