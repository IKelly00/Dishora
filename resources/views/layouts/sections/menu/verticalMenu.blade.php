<style>
    /* Remove horizontal sidebar when screen size changed  */
    .menu-vertical .menu-inner {
        overflow: hidden !important;
    }

    /* Sidebar container */
    .layout-menu {
        background: #ffffff !important;
        /* clean background */
        border-right: 1px solid #e5e7eb;
        box-shadow: 2px 0 6px rgba(0, 0, 0, 0.05);
    }

    /* App brand / logo area */
    .layout-menu .app-brand {
        padding: 1.25rem 1.5rem !important;
        border-bottom: 1px solid #f3f4f6;
    }

    .layout-menu .app-brand-link {
        font-weight: 700;
        color: #1f2937;
        font-size: 1.1rem;
        transition: color 0.2s ease;
    }

    /* Section header text */
    .layout-menu .menu-header-text {
        font-size: 0.75rem;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.05em;
        color: #9ca3af !important;
        /* muted gray */
        margin: 1.5rem 1rem 0.5rem;
    }

    /* Menu items (default) */
    .layout-menu .menu-item .menu-link {
        border-radius: 10px;
        padding: 0.6rem 1rem;
        color: #374151 !important;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .layout-menu .menu-item .menu-link i {
        font-size: 1.2rem;
        margin-right: 0.75rem;
        color: #9ca3af !important;
        /* muted icon */
        transition: color 0.2s ease;
    }

    /* Active state */
    .layout-menu .menu-item.active>.menu-link {
        background: linear-gradient(180deg, #f7b955 0%, #f0a93a 100%);
        color: #fff;
        padding: 8px 14px;
        border-radius: 10px;
        border: none;
        font-weight: 700;
        box-shadow: 0 6px 14px rgba(240, 169, 58, 0.12);
    }

    .layout-menu .menu-item.active>.menu-link i {
        color: #fff !important;
    }

    /* Disabled state */
    .layout-menu .menu-item.disabled>.menu-link {
        opacity: 0.5;
        cursor: not-allowed !important;
    }

    /* Submenu styling */
    .layout-menu .menu-sub .menu-item .menu-link {
        padding: 0.5rem 1.25rem;
        font-size: 0.9rem;
        border-radius: 8px;
    }

    /* Smooth submenu dropdown */
    .layout-menu .menu-item.open>.menu-sub {
        animation: fadeInDown 0.25s ease both;
    }

    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Role switcher section improvement */
    #roleSwitchWrapper {
        border-top: 1px solid #f3f4f6;
        margin-top: 1.5rem;
        padding-top: 1rem;
    }

    #roleSwitchWrapper button {
        border-radius: 12px !important;
        font-weight: 600 !important;
        padding: 0.65rem;
        transition: all 0.2s ease;
    }

    #roleSwitchWrapper button:hover {
        background: #fef3c7;
        border-color: #f59e0b;
        color: #b45309;
    }
</style>

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <!-- App brand -->
    <div class="app-brand demo">
        <a href="{{ url('/') }}" class="app-brand-link">
            {{-- <span class="app-brand-logo demo me-1">
                @include('_partials.macros', ['height' => 20])
            </span> --}}
            <span class="app-brand-text demo menu-text fw-semibold ms-2">
                {{ config('variables.templateName') }}
            </span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="menu-toggle-icon d-xl-block align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <!-- Menu list -->
    <ul class="menu-inner py-1">
        @foreach ($menuData[0]->menu as $menu)
            @php
                $slug = $menu->slug ?? null;
                $currentRouteName = Route::currentRouteName();
                $isDisabled = $businessStatus === 'Pending' && $slug !== 'vendor.dashboard';
                $activeClass = '';

                if ($slug && $currentRouteName === $slug) {
                    $activeClass = 'active';
                } elseif (isset($menu->submenu)) {
                    $slugs = is_array($slug) ? $slug : [$slug];
                    foreach ($slugs as $s) {
                        if (is_string($s) && str_starts_with($currentRouteName, $s)) {
                            $activeClass = 'active open';
                            break;
                        }
                    }
                }
            @endphp

            @if (isset($menu->menuHeader))
                <li class="menu-header mt-7">
                    <span class="menu-header-text">{{ __($menu->menuHeader) }}</span>
                </li>
            @else
                <li class="menu-item {{ $activeClass }} {{ $isDisabled ? 'disabled' : '' }}">
                    <a href="{{ $isDisabled ? 'javascript:void(0);' : url($menu->url ?? '#') }}"
                        class="menu-link {{ isset($menu->submenu) ? 'menu-toggle' : '' }}"
                        @if (!empty($menu->target)) target="_blank" @endif
                        style="{{ $isDisabled ? 'pointer-events:none;opacity:0.5;' : '' }}"
                        title="{{ $isDisabled ? 'Pending verification' : '' }}">
                        @isset($menu->icon)
                            <i class="{{ $menu->icon }}"></i>
                        @endisset
                        <div>{{ __($menu->name ?? '') }}</div>
                        @isset($menu->badge)
                            <div class="badge bg-{{ $menu->badge[0] }} rounded-pill ms-auto">
                                {{ $menu->badge[1] }}
                            </div>
                        @endisset
                    </a>

                    @isset($menu->submenu)
                        @include('layouts.sections.menu.submenu', ['menu' => $menu->submenu])
                    @endisset
                </li>
            @endif
        @endforeach
    </ul>

    <!-- Role Switcher -->
    @php
        $currentRole = session('active_role') ?? 'customer';
        $targetRole = $currentRole === 'customer' ? 'vendor' : 'customer';
        // session('vendorStatus') is set on login (Approved|Pending|Rejected|None|null)
        $initialVendorStatus = session('vendorStatus') ?? 'None';
    @endphp

    <div class="mt-auto px-3 pb-4" id="roleSwitchWrapper" data-initial-status="{{ $initialVendorStatus }}">
        <!-- Intentionally empty. Button will be rendered by JS only when appropriate. -->
    </div>

</aside>

<!-- Single Role Switch Modal -->
<div class="modal fade" id="roleSwitchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Switch Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Do you want to switch to <strong>{{ ucfirst($targetRole) }}</strong> role?</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="{{ route('role.switch') }}">
                    @csrf
                    <input type="hidden" name="target_role" value="{{ $targetRole }}">
                    <button type="submit" class="btn btn-primary">Yes, Switch</button>
                </form>
                <!-- Cancel button with id -->
                <button type="button" class="btn btn-secondary" id="cancelRoleSwitchBtn" data-bs-dismiss="modal">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const wrapper = document.getElementById('roleSwitchWrapper');
        const modalEl = document.getElementById('roleSwitchModal');
        const cancelBtn = document.getElementById('cancelRoleSwitchBtn');

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                document.querySelectorAll('.menu-item.disabled').forEach(item => {
                    item.classList.remove('disabled');
                    const link = item.querySelector('a');
                    if (link) {
                        link.style.pointerEvents = '';
                        link.style.opacity = '';
                        link.title = '';
                    }
                });
            });
        }

        function updateButton() {
            fetch("{{ route('vendor.status.check') }}", {
                    method: "GET",
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status !== 'ok') return;

                    const currentRole = data.currentRole || '{{ $currentRole }}';
                    const targetRole = currentRole === 'customer' ? 'vendor' : 'customer';

                    // If user has no vendor application, clear the wrapper (don't keep server button).
                    if (!data.vendorStatus || data.vendorStatus === 'None' || data.vendorStatus ===
                        'Guest') {
                        wrapper.innerHTML = ''; // <-- important: remove server-rendered button
                        wrapper.classList.add('d-none'); // optionally hide the wrapper
                        return;
                    } else {
                        // ensure wrapper is visible when there is any vendor status
                        wrapper.classList.remove('d-none');
                    }

                    let html = '';

                    switch (data.vendorStatus) {
                        case 'Approved':
                            html = `
                    <button id="switchRoleBtn" class="btn btn-outline-primary w-100" data-target-role="${targetRole}">
                      <i class="bx bx-refresh me-2"></i> Switch to ${capitalize(targetRole)}
                    </button>`;
                            break;
                        case 'Pending':
                            html = `
                    <button class="btn btn-outline-secondary w-100" disabled>
                      <i class="bx bx-lock me-2"></i> Switch Role (Pending)
                    </button>`;
                            break;
                        case 'Rejected':
                            html = `
                    <button class="btn btn-outline-danger w-100" disabled>
                      <i class="bx bx-x-circle me-2"></i> Vendor Rejected
                    </button>`;
                            break;
                        default:
                            // unexpected — clear UI
                            wrapper.innerHTML = '';
                            wrapper.classList.add('d-none');
                            return;
                    }

                    if (html) {
                        wrapper.innerHTML = html;
                        attachSwitchHandler();
                    }
                })
                .catch(err => {
                    console.error('Vendor status check failed', err);
                });
        }

        function attachSwitchHandler() {
            const switchBtn = document.getElementById('switchRoleBtn');
            if (!switchBtn || !modalEl || !window.bootstrap) return;

            // Remove any previous click listeners by cloning (cheap and safe)
            const newBtn = switchBtn.cloneNode(true);
            switchBtn.parentNode.replaceChild(newBtn, switchBtn);

            const roleModal = bootstrap.Modal.getOrCreateInstance(modalEl);

            newBtn.addEventListener('click', () => {
                const targetRole = newBtn.dataset.targetRole;
                modalEl.querySelector('.modal-body strong').textContent = capitalize(targetRole);
                const input = modalEl.querySelector('input[name="target_role"]');
                if (input) input.value = targetRole;
                roleModal.show();
            });
        }

        function capitalize(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        // initial call and periodic refresh
        updateButton();
        setInterval(updateButton, 10000);
    });
</script>
