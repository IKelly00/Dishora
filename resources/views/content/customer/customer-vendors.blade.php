@extends('layouts/contentNavbarLayout')

@section('title', 'Food Hub')

@php
    use Illuminate\Support\Facades\Crypt;
@endphp

@section('content')
    <style>
        .main-content-area {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 20px rgba(0, 0, 0, .08)
        }
    </style>

    {{-- Only if not already included in layout --}}
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.2.0/fonts/remixicon.css" rel="stylesheet">

    <div class="container py-5">
        <div class="main-content-area">
            <h4 class="mb-3 fw-bold">Vendors</h4>
            <div class="row row-cols-2 row-cols-md-5 g-3">
                @foreach ($vendors as $vendor)
                    <div class="col">
                        <div class="card text-center shadow-sm h-100">
                            <div class="card-body d-flex flex-column">
                                <img src="{{ $vendor->business_image }}" class="img-fluid mb-2"
                                    alt="{{ $vendor->business_name }}" style="height: 100px; object-fit: contain;">

                                <h6 class="fw-bold mb-1">
                                    {{ $vendor->business_name }} <br>
                                    <span class="badge text-muted ms-1">
                                        {{ $vendor->verification_status === 'Approved' ? 'Verified' : $vendor->verification_status }}
                                    </span>
                                </h6>

                                {{-- Rating --}}
                                @php
                                    $avgRating = $vendor->reviews_avg_rating;
                                    $reviewsCount = $vendor->reviews_count;
                                @endphp

                                @if ($reviewsCount > 0)
                                    <div class="mb-3 small">
                                        @for ($i = 1; $i <= 5; $i++)
                                            @if ($avgRating >= $i)
                                                <i class="ri-star-fill text-warning"></i>
                                            @elseif ($avgRating >= $i - 0.5)
                                                <i class="ri-star-half-line text-warning"></i>
                                            @else
                                                <i class="ri-star-line text-warning"></i>
                                            @endif
                                        @endfor
                                        <span class="text-muted">
                                            {{ number_format($avgRating, 1) }} ({{ $reviewsCount }})
                                        </span>
                                    </div>
                                @else
                                    <div class="mb-3 small text-muted">No ratings yet</div>
                                @endif

                                <div class="mt-auto">
                                    <a href="{{ url('/customer/selected-business/' . Crypt::encryptString($vendor->business_id) . '/' . Crypt::encryptString($vendor->vendor_id)) }}"
                                        class="btn btn-primary w-100">
                                        Order Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
