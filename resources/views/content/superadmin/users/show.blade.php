@extends('layouts/commonMaster')
@section('title', 'View User Details')

@section('layoutContent')
    {{-- Include the navbar partial --}}
    @include('content.superadmin.partials.navbar')

    {{-- Main content wrapper --}}
    <div class="tab-content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y" style="padding: 0 !important;">

            {{-- Page Header --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0">User Details: {{ $user->username }}</h4>
                <div>
                    <a href="{{ route('super-admin.users.edit', $user->user_id) }}" class="btn btn-primary me-2">
                        <i class="bx bx-edit-alt me-1"></i> Edit User
                    </a>
                    <a href="{{ route('super-admin.users.index') }}" class="btn btn-outline-secondary">
                        <i class="bx bx-arrow-back me-1"></i> Back to User List
                    </a>
                </div>
            </div>

            {{-- User Information Card --}}
            <div class="card mb-4 shadow-sm">
                <div class="card-header border-bottom mb-5">
                    <h5 class="mb-0 fw-bold">Account Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Full Name</dt>
                        <dd class="col-sm-9">{{ $user->fullname ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">Username</dt>
                        <dd class="col-sm-9">{{ $user->username }}</dd>

                        <dt class="col-sm-3">Email</dt>
                        <dd class="col-sm-9">{{ $user->email }}</dd>

                        <dt class="col-sm-3">Account Status</dt>
                        <dd class="col-sm-9">
                            @if ($user->is_verified)
                                <span class="badge bg-label-success">Active</span>
                            @else
                                <span class="badge bg-label-secondary">Inactive</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Email Verified</dt>
                        <dd class="col-sm-9">
                            @if ($user->email_verified_at)
                                <span class="badge bg-label-success">Verified</span> on
                                {{ $user->email_verified_at->format('M d, Y H:i A') }}
                            @else
                                <span class="badge bg-label-warning">Not Verified</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Registered On</dt>
                        <dd class="col-sm-9">{{ $user->created_at ? $user->created_at->format('M d, Y H:i A') : 'N/A' }}
                        </dd>

                        <dt class="col-sm-3">Last Updated</dt>
                        <dd class="col-sm-9">{{ $user->updated_at ? $user->updated_at->format('M d, Y H:i A') : 'N/A' }}
                        </dd>
                    </dl>
                </div>
            </div>

            {{-- Customer Information Card (Only show if customer data exists) --}}
            @if ($user->customer)
                <div class="card mb-4 shadow-sm">
                    <div class="card-header border-bottom mb-5">
                        <h5 class="mb-0 fw-bold">Customer Information</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-3">Contact Number</dt>
                            <dd class="col-sm-9">{{ $user->customer->contact_number ?? 'N/A' }}</dd>

                            <dt class="col-sm-3">Address</dt>
                            <dd class="col-sm-9">{{ $user->customer->user_address ?? 'N/A' }}</dd>

                            <dt class="col-sm-3">Location Coordinates</dt>
                            <dd class="col-sm-9">
                                @if ($user->customer->latitude && $user->customer->longitude)
                                    Latitude: {{ $user->customer->latitude }}, Longitude: {{ $user->customer->longitude }}
                                    {{-- You could add a link to a map here --}}
                                    {{-- <a href="https://www.google.com/maps?q={{ $user->customer->latitude }},{{ $user->customer->longitude }}" target="_blank">(View on Map)</a> --}}
                                @else
                                    N/A
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>
            @else
                <div class="alert alert-secondary" role="alert">
                    No additional customer information found for this user.
                </div>
            @endif

        </div> {{-- End container --}}
    </div> {{-- End tab-content-wrapper --}}

@endsection
