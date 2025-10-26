@extends('layouts/contentNavbarLayout')

@section('title', 'Dashboard')

@section('content')
    <style>
        .main-content-area {
            background: #ffffff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 6px 20px rgba(14, 30, 37, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
            margin-bottom: 2rem;
            min-height: 70vh;
        }
    </style>

    <div class="container-fluid px-4">

        <div class="main-content-area">
            <h1 class="mt-4">Dashboard: {{ $business->business_name }}</h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item active">Overview</li>
            </ol>

            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-primary text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class_name="fs-5 fw-bold">Total Revenue</div>
                                    <div class="fs-3">₱{{ number_format($totalRevenue, 2) }}</div>
                                </div>
                                <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-warning text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="fs-5 fw-bold">New Orders</div>
                                    <div class="fs-3">{{ $newOrdersCount }}</div>
                                </div>
                                <i class="fas fa-shopping-cart fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-success text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="fs-5 fw-bold">Active Products</div>
                                    <div class="fs-3">{{ $activeProductsCount }}</div>
                                </div>
                                <i class="fas fa-box fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-danger text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="fs-5 fw-bold">Avg. Rating</div>
                                    <div class="fs-3">{{ $averageRating }} / 5.0</div>
                                </div>
                                <i class="fas fa-star fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-star me-1"></i>
                    Recent Customer Reviews
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Rating</th>
                                    <th>Comment</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentReviews as $review)
                                    <tr>
                                        <td>{{ $review->customer->fullname ?? 'N/A' }}</td>
                                        <td>
                                            <span class="text-warning">
                                                {{-- Loop to show filled stars --}}
                                                @for ($i = 1; $i <= 5; $i++)
                                                    @if ($i <= $review->rating)
                                                        <i class="fas fa-star"></i>
                                                    @else
                                                        <i class="far fa-star"></i>
                                                    @endif
                                                @endfor
                                            </span>
                                        </td>
                                        <td>{{ Str::limit($review->comment, 60) ?? 'No comment' }}</td>
                                        <td>{{ $review->created_at->format('M d, Y') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No reviews found yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
