@extends('layouts/contentNavbarLayout')

@section('title', 'Food Hub')

@php
    use Illuminate\Support\Facades\Crypt;
@endphp

@section('content')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />

    <style>
        /* ================================
                                                                                                                                                                                                                              CONTAINER & LAYOUT BASE
                                                                                                                                                                                                                          ================================== */
        .container {
            padding: 1.5rem;
            max-width: 100%;
            margin: 0;
            background-color: transparent;
            min-height: 100vh;
        }

        .main-content-area {
            background: #ffffff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .section-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f3f4f6;
        }

        h4.fw-bold {
            color: #1f2937;
            font-size: 1.5rem;
            margin-bottom: 0;
            font-weight: 700;
        }


        /* ================================
                                                                                                                                                                                                                                       CARD STYLING (SHARED)
                                                                                                                                                                                                                                    ================================== */
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 100%;
            overflow: hidden;
            position: relative;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
            border-color: #fed7aa;
        }

        /* Subtle glow effect */
        .card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .card:hover::after {
            opacity: 1;
        }


        /* ================================
                                                                                                                                                                                                                                       CARD IMAGE (SHARED)
                                                                                                                                                                                                                                    ================================== */
        .card-img-wrapper {
            position: relative;
            padding-top: 60%;
            /* uniform aspect ratio */
            overflow: hidden;
            background: #f9fafb;
            border-bottom: 1px solid #f3f4f6;
        }

        .card-img-wrapper img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .card:hover .card-img-wrapper img {
            transform: scale(1.05);
        }


        /* ================================
                                                                                                                                                                                                                                       CARD BODY TEXT
                                                                                                                                                                                                                                    ================================== */
        .card-body {
            padding: 0.875rem;
        }

        h6.fw-bold {
            font-size: 0.9rem;
        }

        .text-muted {
            color: #6b7280 !important;
        }

        .product-description {
            min-height: 40px;
            line-height: 1.4;
            font-size: 0.8rem;
        }

        .store-name {
            font-size: 0.75rem;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 0.5rem;
            margin-bottom: 0.5rem !important;
        }

        .store-name i {
            font-size: 0.8rem;
            margin-right: 0.25rem;
        }


        /* ================================
                                                                                                                                                                                                                                       BUTTONS
                                                                                                                                                                                                                                    ================================== */
        .btn-primary {
            background: linear-gradient(135deg, #fbbf24 0%, #f97316 100%);
            border: none;
            border-radius: 10px;
            padding: 0.625rem 1.25rem;
            font-weight: 600;
            transition: all 0.25s ease;
            box-shadow: 0 3px 8px rgba(249, 115, 22, 0.3);
            color: #fff;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #faca15 0%, #ea580c 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(249, 115, 22, 0.35);
            color: #fff;
        }

        .btn-primary span {
            position: relative;
            z-index: 1;
        }

        /* Smaller buttons on small cards */
        .product-card .btn-primary,
        .vendor-card .btn-primary {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }


        /* ================================
                                                                                                                                                                                                                                       BADGES
                                                                                                                                                                                                                                    ================================== */
        .badge.bg-success {
            background-color: #bbf7d0 !important;
            color: #166534;
            font-weight: 600;
        }

        .badge.bg-secondary {
            background-color: #6b7280 !important;
            color: white;
        }

        .badge.bg-light {
            background-color: #fef3c7 !important;
            color: #92400e !important;
            border: 1px solid #fde68a;
        }


        /* ================================
                                                                                                                                                                                                                                       LOCATION MESSAGE
                                                                                                                                                                                                                                    ================================== */
        #location-source-message {
            background: #f9fafb;
            padding: 0.55rem 0.6rem;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            font-weight: 500;
            font-size: 0.775rem;
            display: inline-flex;
            align-items: center;
        }

        #location-source-message i {
            color: #f59e0b;
            margin-right: 0.5rem;
            font-size: 1rem;
        }


        /* ================================
                                                                                                                                                                                                                                       SCROLLABLE STRIPS (Products & Vendors)
                                                                                                                                                                                                                                    ================================== */
        .products-scroll-container,
        .vendors-scroll-container {
            position: relative;
            overflow-x: auto;
            overflow-y: hidden;
            margin: 0 -15px;
            padding: 0 15px;
        }

        .products-scroll-wrapper,
        .vendors-scroll-wrapper {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            min-height: 320px;
        }

        /* Vendor card body layout – balanced vertical spacing */
        /* Allow card height to expand naturally to its content */
        .vendor-card .card {
            height: auto !important;
            /* remove fixed stretch */
            display: flex;
            flex-direction: column;
            /* organize vertical flow */
            overflow: visible;
            /* ensures icons aren't clipped */
        }

        /* Let vendor body flex to push the button down */
        .vendor-card .card-body {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        /* Make space between description and button smooth and consistent */
        .vendor-card .product-description {
            margin-bottom: auto;
            /* pushes following content (button) to bottom */
        }

        /* Keep “View Vendor” button neatly parked at the bottom inside card */
        .vendor-card .btn-primary {
            margin-top: 0.75rem;
        }

        .product-card,
        .vendor-card {
            flex: 0 0 220px !important;
            max-width: 200px !important;
        }

        .product-card .card,
        .vendor-card .card {
            height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .product-card .card:hover,
        .vendor-card .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Scrollbar Styling */
        .products-scroll-container::-webkit-scrollbar,
        .vendors-scroll-container::-webkit-scrollbar {
            height: 8px;
        }

        .products-scroll-container::-webkit-scrollbar-track,
        .vendors-scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .products-scroll-container::-webkit-scrollbar-thumb,
        .vendors-scroll-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .products-scroll-container::-webkit-scrollbar-thumb:hover,
        .vendors-scroll-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }


        /* ================================
                                                                                                                                                                                                                                       LOADER OVERLAY
                                                                                                                                                                                                                                    ================================== */
        .vendors-wrapper {
            position: relative;
            min-height: 400px;
        }

        .vendors-spinner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        /* Green check beside store name */
        .verified-icon {
            vertical-align: middle;
            margin-left: 4px;
            width: 16px;
            height: 16px;
            display: inline-block;
            flex-shrink: 0;
        }

        /* ================================ */

        /* Product badge size */
        .dietary-badge {
            font-size: 0.65rem;
            /* Adjust this value as needed */
            padding: 0.2em 0.4em;
            /* Make the padding tighter */
            vertical-align: middle;
            /* Helps with alignment */
        }

        /* ================================ ANIMATED CHECKMARK ================================== */
        .checkmark {
            width: 50px;
            height: 50px;
            stroke-linecap: round;
            stroke-linejoin: round;
            display: block;
        }

        .checkmark__circle {
            stroke: #f59e0b;
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 4;
        }

        .checkmark__check {
            stroke: #f59e0b;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            stroke-width: 6;
        }

        .animate .checkmark__circle {
            animation: circle 0.6s ease-out forwards;
        }

        .animate .checkmark__check {
            animation: check 0.3s ease-out 0.6s forwards;
        }



        @keyframes circle {
            to {
                stroke-dashoffset: 0;
            }
        }

        @keyframes check {
            to {
                stroke-dashoffset: 0;
            }
        }


        /* ================================
                                                                                                                                                                                                                                       RESPONSIVE ADJUSTMENTS
                                                                                                                                                                                                                                    ================================== */
        @media (max-width: 768px) {

            .product-card,
            .vendor-card {
                flex: 0 0 180px;
                max-width: 180px;
            }

            .card-img-wrapper {
                padding-top: 55%;
            }

            .card-body {
                padding: 0.75rem;
            }

            h6.fw-bold {
                font-size: 0.85rem;
            }

            .product-description {
                font-size: 0.75rem;
                min-height: 35px;
            }

            .store-name {
                font-size: 0.7rem;
            }

            .products-scroll-wrapper,
            .vendors-scroll-wrapper {
                gap: 0.75rem;
            }
        }

        @media (max-width: 576px) {

            .product-card,
            .vendor-card {
                flex: 0 0 85%;
                max-width: 85%;
            }

            /* keep scroll gap small so cards don't look detached */
            .products-scroll-wrapper,
            .vendors-scroll-wrapper {
                gap: 0.5rem;
            }
        }
    </style>


    <div class="container py-4 py-lg-5">
        <!-- Vendors Nearby -->
        <div class="main-content-area vendors-wrapper position-relative">
            <div class="section-header">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <h4 class="fw-bold mb-3 mb-md-0">Vendors Nearby</h4>
                    <div id="location-source-message">
                        <i class="bi bi-geo-alt"></i> Detecting your location...
                    </div>
                </div>
            </div>

            <!-- Centered Spinner Overlay -->
            <div id="vendors-loading" class="vendors-spinner-overlay">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <!-- Vendors Grid -->
            <div class="vendors-scroll-container">
                <div class="vendors-scroll-wrapper" id="vendors-nearby">
                    <!-- JS replaces spinner with vendor cards -->
                </div>
            </div>
        </div>

        <!-- Order Again -->
        <div class="main-content-area">
            <div class="section-header">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <h4 class="fw-bold mb-0">Order Again</h4>

                    {{-- @if ($pastOrderProducts->count() > 0)
                        <select class="form-select form-select-sm" id="categoryFilter" style="width: 200px;">
                            <option value="all" selected>All Categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->product_category_id }}">
                                    {{ $category->category_name }}
                                </option>
                            @endforeach
                        </select>
                    @endif --}}

                </div>
            </div>

            <div class="products-scroll-container">
                <div class="products-scroll-wrapper" id="productsContainer">

                    @forelse ($pastOrderProducts as $product)
                        <div class="product-card" data-category="{{ $product->product_category_id }}">
                            <div class="card h-100">
                                <div class="card-img-wrapper">
                                    <img src="{{ $product->image_url ?? '/images/default-product.jpg' }}"
                                        class="card-img-top" alt="{{ $product->item_name }}">
                                    @if ($product->is_pre_order)
                                        <span class="badge bg-warning position-absolute top-0 end-0 m-2">Pre-order</span>
                                    @endif
                                </div>
                                <div class="card-body">
                                    <p class="store-name text-muted small mb-1">
                                        <i class="bi bi-shop"></i>
                                        {{ $product->business->business_name ?? 'Unknown Store' }}
                                    </p>

                                    <h6 class="fw-bold mb-2 text-truncate">{{ $product->item_name }}</h6>
                                    <p class="text-muted small mb-2 product-description">
                                        {{ Str::limit($product->description, 50) }}
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold text-primary">₱{{ number_format($product->price, 2) }}</span>
                                    </div>
                                    <button
                                        class="btn btn-sm btn-primary w-100 {{ $product->is_pre_order ? 'preorder' : 'addtocart' }}"
                                        data-id="{{ $product->product_id }}" data-qty="1" onclick="handleAdd(this)">
                                        {{ $product->is_pre_order ? 'Pre-order' : 'Add to Cart' }}
                                    </button>
                                    @if ($product->dietarySpecifications->count() > 0)
                                        <div class="mt-2">
                                            @foreach ($product->dietarySpecifications as $spec)
                                                <span class="badge bg-light text-secondary me-1 dietary-badge">
                                                    {{ $spec->dietary_spec_name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="alert alert-info text-center w-100" role="alert">
                                You haven't placed any orders yet. Once you do, your items will appear here!
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            <div id="noProductsMessage" class="alert alert-info text-center mt-3" style="display: none;">
                No products found in this category.
            </div>
        </div>
    </div>

    <!-- Add/Preorder Success Modal -->
    <div class="modal fade" id="productAddedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content rounded-3 shadow border-0">

                <!-- Modal Header -->
                <div class="modal-header border-0 bg-white">
                    <h6 class="modal-title fw-bold text-dark">Success</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body text-center p-4">
                    <!-- Animated Success Check -->
                    <div id="check-animation"
                        class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                        style="width:80px; height:80px; background:#fff8eb;">
                        <svg class="checkmark" viewBox="0 0 52 52">
                            <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
                            <path class="checkmark__check" fill="none" d="M14 27l7 7 16-16" />
                        </svg>
                    </div>

                    <!-- Dynamic success message -->
                    <div id="product-added-message" class="mb-3 fw-semibold text-dark small"></div>

                    <a href="{{ route('customer.cart') }}" id="goCartBtn" class="btn btn-sm w-100 mb-2 btn-primary d-none">
                        Go to Cart
                    </a>
                    <a href="{{ route('customer.preorder') }}" id="goPreorderBtn"
                        class="btn btn-sm w-100 mb-2 btn-primary d-none">
                        Go to Pre-orders
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const messageEl = document.getElementById("location-source-message");

            // Initial loading message
            messageEl.innerHTML = `<i class="bi bi-geo-alt"></i> Detecting your location...`;

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const apiKey = 'ff51cec2c6ee41d296032c492455155d'; // Geoapify API Key

                    // Fetch place name using Geoapify reverse geocoding
                    fetch(
                            `https://api.geoapify.com/v1/geocode/reverse?lat=${lat}&lon=${lng}&format=json&apiKey=${apiKey}`
                        )
                        .then(response => response.json())
                        .then(result => {
                            const place = result.results[0]?.formatted ?? 'Unknown location';

                            // Update message with place name
                            messageEl.innerHTML =
                                //`<i class="bi bi-geo-alt"></i> You are near: ${place}. Finding nearby vendors...`;
                                `<i class="bi bi-geo-alt"></i>Finding nearby vendors...`;

                            // Log the location to the backend
                            fetch("/log-live-location", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json",
                                    "X-CSRF-TOKEN": document.querySelector(
                                        'meta[name="csrf-token"]').getAttribute("content")
                                },
                                body: JSON.stringify({
                                    lat: lat,
                                    lng: lng,
                                    place_name: place
                                })
                            });

                            // Proceed to fetch nearby vendors
                            fetchNearbyVendors(lat, lng);
                        })
                        .catch(error => {
                            console.error("Geoapify reverse geocoding failed:", error);
                            messageEl.innerHTML =
                                `<i class="bi bi-geo-alt"></i> Location detected. Finding nearby vendors...`;
                            fetchNearbyVendors(lat, lng);
                        });
                }, function(error) {
                    if (error.code === error.PERMISSION_DENIED) {
                        messageEl.innerHTML =
                            `<i class="bi bi-geo-alt"></i> Location access denied. Showing vendors near your registered address.`;
                    } else {
                        messageEl.innerHTML =
                            `<i class="bi bi-geo-alt"></i> Unable to fetch your location. Showing vendors near your registered address.`;
                    }

                    // Fallback to registered location
                    fetchNearbyVendorsFromStored(true);
                });
            } else {
                // Fallback when geolocation is not available
                messageEl.innerHTML =
                    `<i class="bi bi-geo-alt"></i> Geolocation not supported. Showing vendors near your registered address.`;
                fetchNearbyVendorsFromStored(true);
            }

            // Function to fetch nearby vendors based on latitude and longitude
            function fetchNearbyVendors(lat, lng) {
                fetch(`/nearby-vendors?lat=${lat}&lng=${lng}`)
                    .then(response => response.json())
                    .then(data => {
                        //console.log("Live location vendors data:", data); // Debugging log to check response

                        if (data.fallback) {
                            // No vendors found, fallback to registered address
                            messageEl.innerHTML =
                                `<i class="bi bi-geo-alt"></i> No nearby vendors in your current area. Displaying nearby vendors from your registered address.`;
                            fetchNearbyVendorsFromStored(true); // Fallback to registered address vendors
                        } else {
                            // Vendors found, update message and render them
                            messageEl.innerHTML =
                                `<i class="bi bi-geo-alt"></i> Displaying vendors near your current area.`;
                            renderVendors(data.vendors);
                        }
                    })
                    .catch(error => {
                        console.error("Error fetching live location vendors:", error); // Log any errors
                        messageEl.innerHTML =
                            `<i class="bi bi-geo-alt"></i> Error fetching vendors. Showing vendors near your registered address.`;
                        fetchNearbyVendorsFromStored(true); // Fallback to stored location
                    });
            }

            // Function to fetch nearby vendors from stored location (e.g., user's registered address)
            function fetchNearbyVendorsFromStored(isFallback) {
                // If we're in fallback mode, show a different message
                if (isFallback) {
                    messageEl.innerHTML =
                        `<i class="bi bi-geo-alt"></i> No nearby vendors in your current area. Displaying nearby vendors from your registered address.`;
                }

                fetch(`/nearby-vendors/stored`)
                    .then(response => response.json())
                    .then(renderVendors)
                    .catch(error => {
                        console.error("Error fetching stored location vendors:", error);
                        messageEl.innerHTML =
                            `<i class="bi bi-geo-alt"></i> Unable to fetch vendors from your registered address.`;
                    });
            }

            // Function to render vendors in the HTML
            // Inside your renderVendors(data) in the JS:
            function renderVendors(data) {
                const container = document.getElementById('vendors-nearby');
                const spinner = document.getElementById('vendors-loading');
                container.innerHTML = ''; // Clear any grid first
                if (spinner) spinner.style.display = 'none'; // hide spinner

                if (!data || data.length === 0) {
                    container.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info text-center">
                    No vendors found in this area.
                </div>
            </div>`;
                    return;
                }

                data.forEach(vendor => {
                    if (vendor.verification_status && vendor.verification_status.toLowerCase() ===
                        'approved') {
                        container.innerHTML += `
                      <div class="vendor-card" data-vendor-id="${vendor.vendor_id}">
                          <div class="card h-100">
                              <div class="card-img-wrapper">
                                  <img src="${vendor.business_image || '/images/default-business.jpg'}"
                                      class="card-img-top" alt="${vendor.business_name}">
                              </div>
                              <div class="card-body">

                                  <!-- Store Name with Green Check -->
                                  <p class="store-name text-muted small mb-1 d-flex align-items-center justify-content-center">
                                      <i class="bi bi-shop me-1"></i>
                                      ${vendor.business_name}
                                      ${
                                        vendor.verification_status?.toLowerCase() === 'approved'
                                          ? `<svg class="verified-icon"
                                                                                                                     width="16" height="16"
                                                                                                                     viewBox="0 0 16 16"
                                                                                                                     xmlns="http://www.w3.org/2000/svg"
                                                                                                                     style="display:block;">
                                                                                                                  <circle cx="8" cy="8" r="8"
                                                                                                                          fill="#16a34a"
                                                                                                                          stroke="none" />

                                                                                                                  <path d="M4 8.5l2.5 2.5L12 5.5"
                                                                                                                        fill="none"
                                                                                                                        stroke="#ffffff"
                                                                                                                        stroke-width="2.2"
                                                                                                                        stroke-linecap="round"
                                                                                                                        stroke-linejoin="round"
                                                                                                                        shape-rendering="geometricPrecision"
                                                                                                                        vector-effect="non-scaling-stroke" />
                                                                                                                </svg>`
                                          : ''
                                      }
                                  </p>

                                  <p class="text-muted small mb-2 product-description text-center">
                                      ${vendor.distance ? `${parseFloat(vendor.distance).toFixed(1)} km away` : 'Nearby vendor'}
                                  </p>

                                  <a href="/customer/selected-business/${vendor.encrypted_business_id}/${vendor.encrypted_vendor_id}"
                                      class="btn btn-sm btn-primary w-100 mt-2">
                                      View Vendor
                                  </a>
                              </div>
                          </div>
                      </div>
                      `;
                    }
                });
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            //const categoryFilter = document.getElementById('categoryFilter');
            const productsContainer = document.getElementById('productsContainer');
            const noProductsMessage = document.getElementById('noProductsMessage');

            // Store all products HTML for filtering
            let allProductsHTML = productsContainer.innerHTML;

            // categoryFilter.addEventListener('change', function() {
            //     const selectedCategory = this.value;

            //     if (selectedCategory === 'all') {
            //         // Show all products
            //         productsContainer.innerHTML = allProductsHTML;
            //         noProductsMessage.style.display = 'none';
            //     } else {
            //         // Filter products using AJAX
            //         filterProducts(selectedCategory);
            //     }
            // });

            function filterProducts(categoryId) {
                // Show loading state
                productsContainer.innerHTML =
                    '<div class="text-center w-100"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

                // Fetch filtered products
                fetch(`{{ route('api.products.by-category') }}?category_id=${categoryId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.products.length > 0) {
                            displayProducts(data.products);
                            noProductsMessage.style.display = 'none';
                        } else {
                            productsContainer.innerHTML = '';
                            noProductsMessage.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        productsContainer.innerHTML =
                            '<div class="alert alert-danger">Error loading products</div>';
                    });
            }

            function displayProducts(products) {
                let html = '';

                products.forEach(product => {
                    const dietaryBadges = product.dietary_specifications ?
                        product.dietary_specifications.map(spec =>
                            `<span class="badge bg-light text-secondary me-1 small">${spec.name}</span>`
                        ).join('') : '';

                    // Get store name from business relationship
                    const storeName = product.business ? product.business.business_name : 'Unknown Store';

                    html += `
            <div class="product-card" data-category="${product.product_category_id}">
                <div class="card h-100">
                    <div class="card-img-wrapper">
                        <img src="${product.image_url || '/images/default-product.jpg'}"
                             class="card-img-top"
                             alt="${product.item_name}">
                        ${product.is_pre_order ? '<span class="badge bg-warning position-absolute top-0 end-0 m-2">Pre-order</span>' : ''}
                    </div>
                    <div class="card-body">
                        <!-- Store Name -->
                        <p class="store-name text-muted small mb-1">
                            <i class="bi bi-shop"></i> ${storeName}
                        </p>

                        <h6 class="fw-bold mb-2 text-truncate">${product.item_name}</h6>
                        <p class="text-muted small mb-2 product-description">
                            ${product.description ? product.description.substring(0, 50) + '...' : ''}
                        </p>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-primary">$${parseFloat(product.price).toFixed(2)}</span>
                        </div>
                        <button
                          class="btn btn-sm btn-primary w-100 ${product.is_pre_order ? 'preorder' : 'addtocart'}"
                          data-id="${product.product_id}"
                          data-qty="1"
                          onclick="handleAdd(this)">
                          ${product.is_pre_order ? 'Pre-order' : 'Add to Cart'}
                        </button>
                        ${dietaryBadges ? `<div class="mt-2">${dietaryBadges}</div>` : ''}
                    </div>
                </div>
            </div>
        `;
                });

                productsContainer.innerHTML = html;
            }
        });

        // Add to cart function (implement according to your cart system)
        function addToCart(productId) {
            console.log('Adding product to cart:', productId);
            // Implement your add to cart logic here
        }
    </script>

    <script>
        function handleAdd(button) {
            const productId = button.dataset.id;
            const qty = button.dataset.qty || 1;
            const isPreorder = button.classList.contains('preorder');
            const url = isPreorder ? "{{ route('preorder.add') }}" : "{{ route('cart.add') }}";

            fetch(url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: qty
                    })
                })
                .then(r => r.json())
                .then(data => {
                    // update success message text
                    const msgEl = document.getElementById('product-added-message');
                    msgEl.textContent =
                        `${data.product_name} (x${data.quantity}) added to ${isPreorder ? 'Pre-order' : 'Cart'}`;

                    // toggle relevant buttons
                    document.getElementById('goCartBtn').classList.toggle('d-none', isPreorder);
                    document.getElementById('goPreorderBtn').classList.toggle('d-none', !isPreorder);

                    // reset + re-run checkmark animation
                    const checkWrapper = document.getElementById('check-animation');
                    checkWrapper.classList.remove('animate');
                    void checkWrapper.offsetWidth; // force reflow
                    checkWrapper.classList.add('animate');

                    // show modal
                    const modal = new bootstrap.Modal(document.getElementById('productAddedModal'));
                    modal.show();
                })
                .catch(err => console.error(err));
        }
    </script>
@endsection
