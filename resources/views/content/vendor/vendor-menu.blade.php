@extends('layouts/contentNavbarLayout')

@section('title', 'Menu')

@section('content')

    <style>
        .main-content-area {
            background: #ffffff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 6px 20px rgba(14, 30, 37, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
            margin-bottom: 2rem;
        }
    </style>

    <div class="container py-4">
        <div class="main-content-area">
            @if (session('active_business_id'))
                {{-- If a business is selected, show its menu --}}
                <div class="container py-4">

                    <!-- Page header -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold">Menu</h4>
                        <a href="{{ route('vendor.add.menu') }}" class="btn btn-warning">Add Item</a>
                    </div>

                    <!-- Menu items list -->
                    <div class="row g-4">
                        @forelse ($products as $item)
                            <div class="col-md-4 col-lg-3">
                                <div class="card shadow-sm border-0 h-100">

                                    <!-- Item image with availability / preorder badges -->
                                    <div class="position-relative">
                                        <img src="{{ $item->image_url ? secure_asset($item->image_url) : secure_asset('images/no-image.jpg') }}"
                                            class="card-img-top" alt="{{ $item->item_name }}"
                                            style="height: 200px; object-fit: cover;">

                                        <!-- Availability badge -->
                                        <span class="position-absolute start-0 m-2 badge bg-light text-dark"
                                            style="top: 0;">
                                            {{ $item->is_available ? 'Available' : 'Not Available' }}
                                        </span>

                                        <!-- Pre-order badge -->
                                        @if ($item->is_pre_order)
                                            <span class="position-absolute start-0 m-2 badge bg-light text-dark"
                                                style="top: 2rem;">
                                                Pre Order
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Item details -->
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="fw-bold">{{ $item->item_name }}</h6>
                                        <p class="mb-1 text-warning fw-bold">₱{{ number_format($item->price, 2) }}</p>

                                        {{-- Example fixed rating (static) --}}
                                        <div class="mb-2">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <i
                                                    class="bi {{ $i <= 4 ? 'bi-star-fill text-warning' : 'bi-star text-warning' }}"></i>
                                            @endfor
                                        </div>

                                        <!-- Edit button -->
                                        <a href="{{ route('vendor.edit.menu', $item->product_id) }}"
                                            class="btn btn-light mt-auto">Edit</a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <!-- If no menu items -->
                            <div class="col-12">
                                <p class="text-muted">No menu items found for this business.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @else
                {{-- If no business selected, show selection form --}}
                <div class="container py-4">
                    <h4 class="fw-bold mb-3">Select a Business to View Menu</h4>

                    <!-- Business selection form -->
                    <form action="{{ route('vendor.setBusiness') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="business_id" class="form-label">Choose Business</label>
                            <select name="business_id" id="business_id" class="form-select" required>
                                @foreach (Auth::user()->vendor->businessDetails as $business)
                                    <option value="{{ $business->business_id }}">{{ $business->business_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">View Menu</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
