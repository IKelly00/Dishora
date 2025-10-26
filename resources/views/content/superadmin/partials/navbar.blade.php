<style>
    /* Define colors */
    :root {
        --tab-active-bg: #ffffff;
        /* White for active tab & content */
        --tab-inactive-bg: #f0f2f5;
        /* Lighter, subtle grey for inactive */
        --tab-border-color: #dee2e6;
        /* Bootstrap's default border color */
        --tab-inactive-text-color: #697a8d;
        /* Muted text for inactive */
        --tab-active-text-color: #566a7f;
        /* Standard text for active */
        /* Glow color */
        --tab-glow-color: #f3952f6b;
        /* Semi-transparent color for glow */
    }

    /* The UL container - main bottom border */
    .nav-tabs {
        border-bottom: 1px solid var(--tab-border-color);
        margin-left: -1.5rem;
        /* Offset padding from container */
        margin-right: -1.5rem;
        /* Offset padding from container */
        padding-left: 1.5rem;
        /* Re-add padding inside */
        padding-right: 1.5rem;
    }

    .nav-align-top {
        /* Div containing the tabs */
        flex-shrink: 0;
        /* Prevent navbar from shrinking */
        margin-bottom: 0 !important;
        /* Remove bottom margin if wrapper follows directly */
    }

    /* General styles for ALL tab links */
    .nav-tabs .nav-link {
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
        border-bottom: none;
        margin-bottom: 0;
        padding: 0.6rem 1rem;
        transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, color 0.15s ease-in-out;
    }

    /* Active Tab */
    .nav-tabs .nav-link.active,
    .nav-tabs .nav-item.show .nav-link {
        background-color: var(--tab-active-bg);
        color: var(--tab-active-text-color);
        font-weight: 500;
        /* Match borders, bottom matches background */
        border-color: var(--tab-border-color) var(--tab-border-color) var(--tab-active-bg);
        margin-bottom: -1px;
        /* Overlaps the UL's bottom border */
        position: relative;
        z-index: 2;
        /* Bring active tab to the front */
        /* Inner glow + Outer shadow */
        box-shadow: inset 0 3px 6px var(--tab-glow-color),
            0 0 8px rgba(0, 0, 0, 0.15);
    }

    /* Inactive Tabs */
    .nav-tabs .nav-link:not(.active) {
        background-color: var(--tab-inactive-bg);
        color: var(--tab-inactive-text-color);
        border-color: var(--tab-border-color);
        /* Ensure bottom border looks consistent with the UL border */
        border-bottom: 1px solid var(--tab-border-color);
        position: relative;
        /* Needed for z-index */
        z-index: 0;
        /* Keep inactive tabs behind */
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .nav-tabs .nav-link:not(.active):hover {
        background-color: #e4e8ec;
        /* Slightly darker hover */
        border-color: var(--tab-border-color);
        color: var(--tab-active-text-color);
        z-index: 1;
        /* Bring hovered tab slightly forward, but behind active */
        box-shadow: none;
        /* Remove inset shadow on hover */
    }

    /* Content Area Wrapper */
    .tab-content-wrapper {
        background-color: var(--tab-active-bg);
        /* White */
        border: 1px solid var(--tab-border-color);
        border-top: none;
        padding: 1.5rem;
        border-radius: 0 0 0.375rem 0.375rem;
        /* Round bottom corners */
        width: 100%;
        position: relative;
        z-index: 1;
        /* Sit below active tab */
        box-shadow: inset 0 3px 6px var(--tab-glow-color);
        min-height: 94.5vh;
    }

    #rejectModal .modal-dialog {
        position: absolute;
        /* Position relative to the viewport or nearest positioned ancestor */
        top: 50%;
        /* Move top edge to the vertical center */
        left: 50%;
        /* Move left edge to the horizontal center */
        transform: translate(-50%, -50%);
        /* Shift back by half its own width/height to truly center */
        margin: 0;
        /* Reset default margins */
        /* Or set specific positions: */
        /* top: 100px; */
        /* left: 200px; */
        /* transform: none; */
    }
</style>

<div class="nav-align-top">
    <ul class="nav nav-tabs" role="tablist">
        {{-- Dashboard Link --}}
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}"
                href="{{ route('super-admin.dashboard') }}">
                <i class="bx bx-tachometer me-1"></i> Dashboard
            </a>
        </li>

        {{-- == VENDORS LINK == --}}
        <li class="nav-item">
            {{-- Check if route name STARTS WITH 'super-admin.vendors.' or related vendor routes --}}
            <a class="nav-link {{ request()->routeIs('super-admin.vendors.index') /* || request()->routeIs('super-admin.vendor.*') */ ? 'active' : '' }}"
                href="{{ route('super-admin.vendors.index') }}">
                <i class='bx bxs-user-detail me-1'></i> Manage Vendors
            </a>
        </li>

        {{-- Businesses Link --}}
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('super-admin.businesses.index') || request()->routeIs('super-admin.business.*') ? 'active' : '' }}"
                href="{{ route('super-admin.businesses.index') }}">
                <i class='bx bx-store-alt me-1'></i> Manage Businesses
            </a>
        </li>

        {{-- Users Link --}}
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('super-admin.users.*') ? 'active' : '' }}"
                href="{{ route('super-admin.users.index') }}">
                <i class="bx bx-user me-1"></i> Manage Users
            </a>
        </li>
    </ul>
</div>
