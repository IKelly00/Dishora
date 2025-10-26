@extends('layouts/commonMaster')

@php
    /* Display elements */
    $contentNavbar = true;
    $containerNav = $containerNav ?? 'container-xxl';
    $isNavbar = $isNavbar ?? true;
    $isMenu = $isMenu ?? true;
    $isFlex = $isFlex ?? false;
    $isFooter = $isFooter ?? true;

    /* HTML Classes */
    $navbarDetached = 'navbar-detached';

    /* Content classes */
    $container = $container ?? 'container-xxl';

    /* Session & Flags */
    $manualTrigger = session('manualTrigger') === true;
    $vendorStatus = session('vendorStatus') ?? ($vendorStatus ?? null);
    $showRolePopup = session('showRolePopup') ?? ($showRolePopup ?? false);
    $roleSwitched = session('role_switched') === true;
    $showVendorStatusModal = $showVendorStatusModal ?? false;
    $showVendorRejectedModal = $showVendorRejectedModal ?? false;
    $hasShownVendorModal = session('vendor_status_modal_shown', false);

    if (session('active_role') === 'customer' || empty(session('active_role'))) {
        $businessStatus = null;
    }
@endphp

{{-- Error Alert --}}
@if (session('error'))
    <div class="alert alert-danger position-fixed start-50 translate-middle-x px-4 py-2 shadow-lg" role="alert"
        style="top: 80px; min-width: 300px; z-index: 9999;">
        {{ session('error') }}
    </div>

    <script>
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.classList.add('fade');
                setTimeout(() => alert.remove(), 500);
            }
        }, 3000);
    </script>
@endif

{{-- Modals --}}
@if ($showVendorStatusModal && $hasShownVendorModal === false)
    @include('_partials.vendor-status-modal')

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modalEl = document.getElementById('vendorStatusModal');
            if (modalEl && window.bootstrap) {
                let vendorModal = bootstrap.Modal.getInstance(modalEl);
                if (!vendorModal) {
                    vendorModal = new bootstrap.Modal(modalEl);
                }
                vendorModal.show();
            }
        });
    </script>
@endif

@if ($showVendorRejectedModal)
    @include('_partials.vendor-rejected-modal')
@endif

{{-- @if ($vendorStatus === 'Approved' && ($showRolePopup || $manualTrigger) && !$roleSwitched)
    @include('_partials.role-switch-modal')
@endif --}}

@if (session('active_role') === 'vendor' && $showVerificationModal && $vendorStatus === 'Approved' && !$manualTrigger)
    @include('_partials.business-verification-modal')
@endif

@section('layoutContent')
    <div class="layout-wrapper layout-content-navbar {{ $isMenu ? '' : 'layout-without-menu' }}">
        <div class="layout-container">

            {{-- Sidebar Menu --}}
            @if ($isMenu)
                @include('layouts/sections/menu/verticalMenu')
            @endif

            <!-- Layout Page -->
            <div class="layout-page">

                {{-- Navbar --}}
                @if ($isNavbar)
                    @include('layouts/sections/navbar/navbar')
                @endif

                <!-- Content Wrapper -->
                <div class="content-wrapper">

                    <!-- Content -->
                    <div
                        class="{{ $container }} {{ $isFlex ? 'd-flex align-items-stretch p-0' : 'flex-grow-1 container-p-y' }}">
                        @yield('content')
                    </div>
                    <!-- / Content -->

                    {{-- Footer --}}
                    @if ($isFooter)
                        @include('layouts/sections/footer/footer')
                    @endif

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- / Content Wrapper -->
            </div>
            <!-- / Layout Page -->
        </div>
    </div>

    {{-- Overlay for mobile --}}
    @if ($isMenu)
        <div class="layout-overlay layout-menu-toggle"></div>
    @endif

    <div class="drag-target"></div>

    @if (($showRolePopup || $manualTrigger) && $vendorStatus === 'Approved' && !$roleSwitched)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modalEl = document.getElementById('roleSwitchModal');
                if (modalEl && window.bootstrap) {
                    // Initialize or grab existing modal instance
                    let roleModal = bootstrap.Modal.getInstance(modalEl);
                    if (!roleModal) {
                        roleModal = new bootstrap.Modal(modalEl);
                    }

                    // Show it *after* a short delay, so Bootstrap binds controls
                    setTimeout(() => {
                        roleModal.show();
                    }, 100);

                    // Reset session variable once modal is closed
                    modalEl.addEventListener('hidden.bs.modal', () => {
                        fetch("{{ route('set.role.popup') }}", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": "{{ csrf_token() }}"
                            },
                            body: JSON.stringify({
                                reset: true
                            })
                        }) //.then(() => console.log('Role popup cleared'));
                    });
                }
            });
        </script>
    @endif
@endsection
