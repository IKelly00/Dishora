@extends('layouts/commonMaster')
@section('title', 'Super Admin Dashboard')
@section('layoutContent')
    <meta name="csrf-token" content="{{ csrf_token() }}">


    {{-- Include the navbar partial INSIDE the new wrapper --}}
    @include('content.superadmin.partials.navbar')

    {{-- The content wrapper also goes INSIDE --}}
    <div class="tab-content-wrapper">
        {{-- Put your page content DIRECTLY inside the content wrapper --}}
        <div class="container-xxl flex-grow-1 container-p-y">

            {{-- Dashboard Header --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0">Super Admin Dashboard</h4>
                {{-- Optional: Keep Export button if it exports something other than businesses --}}
                {{-- <div><a href="#" class="btn btn-outline-primary">Export Data</a></div> --}}
            </div>

            {{-- Stats Row --}}
            <div class="row g-4 mb-4">
                @foreach (['Total Users' => 'total_users', 'Total Vendors' => 'total_vendors', 'Total Businesses' => 'total_businesses'] as $label => $key)
                    <div class="col-sm-6 col-xl-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="text-muted d-block mb-1">{{ $label }}</h5>
                                <h1 class="mt-2 mb-0 fw-bold">{{ number_format($stats[$key] ?? 0) }}</h1>
                                @if ($key === 'total_vendors')
                                    <small class="text-muted">{{ number_format($pendingVendors ?? 0) }} pending</small>
                                @elseif ($key === 'total_businesses')
                                    <small class="text-muted">{{ number_format($pendingBusinesses ?? 0) }}
                                        pending</small>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                {{-- Quick Actions Card --}}
                <div class="col-sm-6 col-xl-3">
                    <div class="card h-100 shadow-sm bg-primary text-white">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5 class="mb-2 text-white">Quick Actions</h5>
                            <p class="small mb-3">Jump to important management tools.</p>
                            <a href="{{ route('super-admin.businesses.index') }}" class="btn btn-light btn-sm w-100">Manage
                                Businesses</a>
                            <a href="{{ route('super-admin.vendors.index') }}"
                                class="btn btn-light btn-sm w-100 mt-2">Manage
                                Vendors</a>
                            {{-- <a href="#" class="btn btn-light btn-sm w-100 mt-2">Manage Vendors</a> --}}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Chart Row --}}
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header border-bottom mb-4">
                            <h6 class="mb-0 fw-bold">Platform Activity Overview</h6>
                        </div>
                        <div class="card-body">
                            <div style="min-height:220px">
                                <canvas id="activityChart" aria-label="Platform activity chart" role="img"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- You can add another chart or info panel in the remaining space --}}
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header border-bottom mb-4">
                            <h6 class="mb-0 fw-bold">System Status</h6>
                        </div>
                        <div class="card-body">
                            <p>System operational.</p>
                            {{-- Add other status indicators if needed --}}
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- End container --}}
    </div> {{-- End content-wrapper --}}

    {{-- Keep Toast container if needed for future dashboard actions --}}
    <div class="position-fixed top-0 end-0 p-3" style="z-index:1200">
        <div id="saToast" class="toast align-items-center text-white bg-primary border-0" role="alert"
            aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="saToastBody">
                    Notification
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
        </div>
    </div>
@endsection

@push('page-script')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Keep ONLY the chart initialization script
        (function() {
            function initDashboardChart() {
                const el = document.getElementById('activityChart');
                if (!el) return;
                const ctx = el.getContext('2d');
                const data = {
                    labels: ['Users', 'Vendors'], // Simplified labels
                    datasets: [{
                        data: [
                            {{ (int) ($stats['total_users'] ?? 0) }},
                            {{ (int) ($stats['total_vendors'] ?? 0) }}
                        ],
                        borderWidth: 1,
                        backgroundColor: [
                            '#696cff', // Primary for Users
                            '#03c3ec' // Info for Vendors
                        ],
                        hoverOffset: 4
                    }]
                };
                new Chart(ctx, {
                    type: 'doughnut',
                    data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            } // End initDashboardChart

            document.addEventListener('DOMContentLoaded', function() {
                initDashboardChart();
            });
        })(); // End IIFE
    </script>
@endpush
