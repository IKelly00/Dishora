@extends('layouts/contentNavbarLayout')

@section('title', 'Vendors Hub')

@section('content')

    text

    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>

    <div class="container py-4">
        <div class="main-content-area">
            <!-- Business Info -->
            <div class="card mb-4 p-5 shadow-sm border-0 rounded-4">
                <div class="row align-items-center">
                    <!-- Logo -->
                    <div class="col-md-3 mb-3 mb-md-0 d-flex justify-content-center">
                        <div class="business-logo-wrapper p-3 rounded-circle shadow-sm">
                            <img src="{{ $business->business_image }}" alt="Logo"
                                class="business-logo img-fluid rounded-circle">
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="col-md-9">
                        <h3 class="fw-bold mb-1 text-dark">{{ $business->business_name }}</h3>
                        <p class="text-muted mb-3">{{ $business->business_description }}</p>

                        <!-- Location -->
                        <div class="d-flex align-items-start mb-4">
                            <div class="me-2 mt-1">
                                <i class="bx bx-map text-warning fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">{{ $business->business_location }}</h6>
                            </div>
                        </div>

                        <!-- Opening Hours -->
                        <div class="p-3 rounded-4 border bg-light-subtle shadow-sm-sm">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bx bx-time-five text-warning fs-5 me-2"></i>
                                <h6 class="fw-bold text-dark mb-0">Opening Hours</h6>
                            </div>
                            <div class="row g-2">
                                @foreach ($business->openingHours as $hour)
                                    <div class="col-md-4 col-sm-6">
                                        <div
                                            class="d-flex justify-content-between align-items-center py-2 px-3 bg-white border rounded-3 shadow-sm-sm hover-lift">
                                            <span class="fw-semibold text-dark">{{ $hour->day_of_week }}</span>
                                            @if ($hour->is_closed)
                                                <span class="text-danger small fw-medium">Closed</span>
                                            @else
                                                <span class="text-muted small">
                                                    {{ \Carbon\Carbon::parse($hour->opens_at)->format('g:i A') }}
                                                    –
                                                    {{ \Carbon\Carbon::parse($hour->closes_at)->format('g:i A') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex gap-3 mt-4">
                            <button class="btn btn-warning px-4 rounded-pill fw-semibold" id="messageBtn"
                                data-bs-toggle="modal" data-bs-target="#messageModal"
                                data-business-id="{{ $business->business_id }}"
                                data-business-name="{{ $business->business_name }}"
                                data-receiver-id="{{ $business->vendor->user_id ?? '' }}">
                                Message
                            </button>
                            <button class="btn btn-outline-warning px-4 rounded-pill fw-semibold" id="toggleSectionBtn"
                                data-section="menu">
                                Feedback
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Menu Section -->
            <div class="toggle-section">
                <div id="menuSection">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold">🍴 Menu</h4>
                        <select class="form-select form-select-sm w-auto">
                            <option selected>All Categories</option>
                            <option>Meals</option>
                            <option>Noodles</option>
                            <option>Chicken</option>
                        </select>
                    </div>

                    <div class="row">
                        @foreach ($products as $item)
                            <div class="col-md-4 mb-4">
                                <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden">

                                    <!-- Item image with availability / preorder badges -->
                                    <div class="position-relative">
                                        <img src="{{ $item->image_url ? secure_asset($item->image_url) : secure_asset('images/no-image.jpg') }}"
                                            class="card-img-top" alt="{{ $item->item_name }}"
                                            style="height: 200px; object-fit: cover;">

                                        <!-- Availability badge -->
                                        <span
                                            class="position-absolute start-0 m-2 badge {{ $item->is_available ? 'bg-light text-dark' : 'bg-danger' }}"
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

                                    <!-- Card Body -->
                                    <div class="card-body text-center">
                                        <h5 class="fw-semibold">{{ $item->item_name }}</h5>
                                        <p class="text-danger fw-bold mb-3">₱{{ number_format($item->price, 2) }}</p>

                                        <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">

                                            <!-- Pre-order button -->
                                            <button class="btn-custom preorder" data-id="{{ $item->product_id }}"
                                                data-qty="1"
                                                @if (!$item->is_available) disabled style="opacity:0.6; cursor:not-allowed;" @endif>
                                                Pre-order
                                            </button>

                                            @if ($item->is_pre_order != 1)
                                                <!-- Add to cart (only if available & not preorder-only) -->
                                                <button class="btn-custom addtocart" data-id="{{ $item->product_id }}"
                                                    data-qty="1"
                                                    @if (!$item->is_available) disabled style="opacity:0.6; cursor:not-allowed;" @endif>
                                                    Add to cart
                                                </button>
                                            @endif

                                            <!-- Quantity selector -->
                                            <div
                                                class="d-flex align-items-center qty-box {{ !$item->is_available ? 'disabled-box' : '' }}">
                                                <button class="btn-qty"
                                                    @if (!$item->is_available) disabled @endif>-</button>
                                                <span class="px-2">1</span>
                                                <button class="btn-qty"
                                                    @if (!$item->is_available) disabled @endif>+</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Feedback Section (initially hidden) -->
                <div id="feedbackSection" class="mt-4" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0">
                            <i class="bx bx-chat text-warning"></i> Feedbacks
                        </h4>

                        <button class="btn btn-warning btn-sm rounded-pill fw-semibold shadow-sm" data-bs-toggle="modal"
                            data-bs-target="#feedbackModal">
                            <i class="bx bxs-plus-circle me-1"></i> Add Feedback
                        </button>
                    </div>

                    <div id="feedbackList" class="p-4 rounded-4 border bg-light"></div>
                </div>
            </div>
        </div>

        <!-- Feedback Modal -->
        <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header bg-warning text-white rounded-top-4 py-4">
                        <h5 class="modal-title fw-semibold" id="feedbackModalLabel">Send Feedback</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        <form id="feedbackForm">
                            @csrf
                            <input type="hidden" name="business_id" value="{{ $business->business_id }}">

                            <div class="mb-3">
                                <label for="rating" class="form-label fw-semibold">Rating</label>
                                <select id="rating" name="rating" class="form-select" required>
                                    <option value="5">⭐⭐⭐⭐⭐ - Excellent</option>
                                    <option value="4">⭐⭐⭐⭐ - Good</option>
                                    <option value="3">⭐⭐⭐ - Average</option>
                                    <option value="2">⭐⭐ - Poor</option>
                                    <option value="1">⭐ - Terrible</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="comment" class="form-label fw-semibold">Comments</label>
                                <textarea id="comment" name="comment" class="form-control" rows="4"
                                    placeholder="Write your feedback here..."></textarea>
                            </div>
                        </form>
                    </div>

                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill"
                            data-bs-dismiss="modal">Cancel</button>
                        <!-- Trigger confirmation modal instead of direct submit -->
                        <button type="button" class="btn btn-warning rounded-pill fw-semibold"
                            id="confirmSubmitBtn">Submit</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div class="modal fade" id="feedbackConfirmModal" tabindex="-1" aria-labelledby="feedbackConfirmLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-semibold text-dark" id="feedbackConfirmLabel">Confirm Submission</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body text-center pb-4">
                        <i class="bx bx-error-circle text-warning" style="font-size:2.5rem;"></i>
                        <p class="mt-3 mb-2 text-dark fw-semibold">Are you sure you want to submit your feedback?</p>
                        <p class="text-muted mb-0">Once submitted, it cannot be edited or deleted.</p>
                    </div>

                    <div class="modal-footer border-0 justify-content-center pb-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning rounded-pill fw-semibold px-4"
                            id="finalSubmitBtn">Yes, Submit</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header py-3">
                        <h5 class="modal-title" id="messageModalLabel">Chat with <span
                                id="chatBusinessName">Business</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body" id="chat-box"
                        style="height: 450px; overflow-y: auto; background-color: #f9f9f9;">
                        <div class="text-center text-muted">Loading messages...</div>
                    </div>

                    <div class="modal-footer p-3">
                        <form id="sendMessageForm" class="d-flex w-100">
                            <input type="hidden" id="chat_business_id" name="business_id">
                            <input type="hidden" id="chat_receiver_id" name="receiver_id"> <input type="text"
                                id="chat_message_text" class="form-control me-2" placeholder="Type your message..."
                                required autocomplete="off">
                            <button type="submit" class="btn btn-warning">Send</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Preorder Success Modal (NEW: used instead of alert) -->
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

                    <a href="{{ route('customer.cart') }}" id="goCartBtn"
                        class="btn btn-sm w-100 mb-2 btn-primary d-none">
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

    <!-- Custom Styles -->
    <style>
        .main-content-area {
            background: #ffffff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 6px 20px rgba(14, 30, 37, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
            margin-bottom: 2rem;
        }

        .toggle-section {
            border: 1px solid #f3f4f6;
            border-radius: 10px;
            padding: 1.5rem;
            background: #fcfcfc
        }

        .btn-custom {
            border: none;
            border-radius: 20px;
            padding: 6px 18px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }

        .btn-custom.preorder {
            background: #f6d99d;
            color: #000;
        }

        .btn-custom.preorder:hover {
            background: #f5c56f;
        }

        .btn-custom.addtocart {
            background: #e9eef5;
            color: #000;
        }

        .btn-custom.addtocart:hover {
            background: #d6dee9;
        }

        .qty-box {
            background: #f2f4f7;
            border-radius: 20px;
            padding: 4px 10px;
            font-size: 14px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .btn-qty {
            border: none;
            background: #e9eef5;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }

        .btn-qty:hover {
            background: #cfd6e0;
        }

        .btn-qty:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .disabled-box {
            opacity: 0.5;
            cursor: not-allowed !important;
        }

        .opening-hours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 6px 20px;
        }

        .opening-hours-grid div {
            font-size: 0.9rem;
        }

        #feedbackModal .form-control,
        #feedbackModal .form-select {
            border-radius: 10px;
            padding: 10px;
            font-size: 0.95rem;
        }

        .modal-header.bg-warning {
            background: linear-gradient(90deg, #f6d365, #fda085);
        }

        /* Feedback */
        .empty-feedback-state {
            background: linear-gradient(180deg, #fff, #fafafa);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }

        .empty-feedback-state:hover {
            transform: scale(1.01);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
        }

        .empty-feedback-state i {
            display: inline-block;
            padding: 0.7rem;
            background: rgba(255, 193, 7, 0.15);
            border-radius: 50%;
        }

        #feedbackSection h4 {
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        #feedbackSection button.btn-warning {
            background: #f6d365;
            border: none;
            transition: 0.2s ease-in-out;
        }

        #feedbackSection button.btn-warning:hover {
            background: #f5c56f;
        }

        /* Location and opening hrs */
        /* Larger, more elegant logo */
        .col-md-3 img {
            width: 170px !important;
            height: 170px !important;
            border: 5px solid #fff;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }

        /* soften small box shadow used in hours */
        .shadow-sm-sm {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.04);
        }

        /* gentle lift on hover */
        .hover-lift {
            transition: all 0.2s ease;
        }

        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
        }

        /* fine gradient background for subtle tone */
        .bg-light-subtle {
            background: linear-gradient(180deg, #fff, #fcfcfc);
        }

        /* Logo */
        .business-logo-wrapper {
            width: 200px;
            height: 200px;
            background: linear-gradient(145deg, #fff, #f8f9fa);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #fff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .business-logo-wrapper:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .business-logo {
            width: 150px;
            height: 150px;
            object-fit: contain;
        }

        @media (max-width: 768px) {
            .business-logo-wrapper {
                width: 150px;
                height: 150px;
            }

            .business-logo {
                width: 120px;
                height: 120px;
            }
        }

        /* Checkmark animation styles used by modal */
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
    </style>

    @push('page-script')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Handle quantity changes
                document.querySelectorAll('.qty-box').forEach(function(box) {
                    const minusBtn = box.querySelector('.btn-qty:first-child');
                    const plusBtn = box.querySelector('.btn-qty:last-child');
                    const qtySpan = box.querySelector('span');

                    // Skip logic if buttons are disabled
                    if (minusBtn.disabled || plusBtn.disabled) return;

                    const card = box.closest('.card');
                    const addBtn = card.querySelector('.addtocart');
                    const preorderBtn = card.querySelector('.preorder');

                    let qty = parseInt(qtySpan.textContent, 10);

                    minusBtn.addEventListener('click', function() {
                        if (qty > 1) {
                            qty--;
                            qtySpan.textContent = qty;
                            if (addBtn) addBtn.dataset.qty = qty;
                            if (preorderBtn) preorderBtn.dataset.qty = qty;
                        }
                    });

                    plusBtn.addEventListener('click', function() {
                        qty++;
                        qtySpan.textContent = qty;
                        if (addBtn) addBtn.dataset.qty = qty;
                        if (preorderBtn) preorderBtn.dataset.qty = qty;
                    });
                });

                // Handle Add to Cart / Pre-order clicks
                document.querySelectorAll('.addtocart, .preorder').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const productId = parseInt(this.dataset.id, 10);
                        const qty = parseInt(this.dataset.qty || 1, 10);

                        let url = '';
                        const isPreorder = this.classList.contains('preorder');
                        if (isPreorder) {
                            url = '{{ route('preorder.add') }}';
                        } else {
                            url = '{{ route('cart.add') }}';
                        }

                        fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector(
                                        'meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({
                                    product_id: productId,
                                    quantity: qty
                                })
                            })
                            .then(async res => {
                                const contentType = res.headers.get('content-type');
                                if (contentType && contentType.includes('application/json')) {
                                    const data = await res.json();

                                    // --- Instead of alert, show modal ---
                                    const msgEl = document.getElementById(
                                        'product-added-message');
                                    msgEl.textContent =
                                        `${data.product_name} (x${data.quantity}) added to ${isPreorder ? 'Pre-orders' : 'Cart'}`;

                                    // Show/hide the appropriate go buttons
                                    document.getElementById('goCartBtn').classList.toggle(
                                        'd-none', isPreorder);
                                    document.getElementById('goPreorderBtn').classList.toggle(
                                        'd-none', !isPreorder);

                                    // animate check
                                    const checkWrapper = document.getElementById(
                                        'check-animation');
                                    checkWrapper.classList.remove('animate');
                                    void checkWrapper.offsetWidth; // force reflow
                                    checkWrapper.classList.add('animate');

                                    // show modal
                                    const modal = new bootstrap.Modal(document.getElementById(
                                        'productAddedModal'));
                                    modal.show();
                                } else {
                                    const text = await res.text();
                                    console.error('Unexpected response:', text);
                                    // Fallback: show simple browser alert if response isn't json
                                    alert('Something went wrong. Please try again.');
                                }
                            })
                            .catch(err => {
                                console.error(err);
                                alert('Failed to add item. Please try again.');
                            });
                    });
                });


                // Toggle Menu and Feedback
                const toggleBtn = document.getElementById('toggleSectionBtn');
                const menuSection = document.getElementById('menuSection');
                const feedbackSection = document.getElementById('feedbackSection');
                const feedbackList = document.getElementById('feedbackList');
                const businessId = {{ $business->business_id }};

                toggleBtn.addEventListener('click', async function() {
                    const current = toggleBtn.dataset.section;

                    if (current === 'menu') {
                        // Switch to feedback view
                        menuSection.style.display = 'none';
                        feedbackSection.style.display = 'block';
                        toggleBtn.textContent = 'Menu';
                        toggleBtn.dataset.section = 'feedback';

                        // Fetch feedbacks
                        const res = await fetch(`{{ url('/feedback') }}/${businessId}`);
                        if (res.ok) {
                            const reviews = await res.json();
                            renderFeedbackList(reviews);
                        } else {
                            feedbackList.innerHTML = '<p class="text-danger">Failed to load feedbacks.</p>';
                        }
                    } else {
                        // Back to menu view
                        menuSection.style.display = 'block';
                        feedbackSection.style.display = 'none';
                        toggleBtn.textContent = 'Feedback';
                        toggleBtn.dataset.section = 'menu';
                    }
                });

                function renderFeedbackList(reviews) {
                    if (!reviews.length) {
                        feedbackList.innerHTML = `
                            <div class="empty-feedback-state text-center p-5 rounded-4 border bg-white shadow-sm">
                              <div class="mb-2">
                                <i class='bx bxs-message-rounded-dots text-warning' style="font-size: 2rem;"></i>
                              </div>
                              <h6 class="fw-semibold text-dark mb-1">No Feedback Yet</h6>
                              <p class="text-muted mb-0">Be the first to share your thoughts about this business!</p>
                            </div>
                          `;
                        return;
                    }

                    feedbackList.innerHTML = reviews.map(r => `
                    <div class="feedback-item p-3 mb-3 bg-white border rounded-3 shadow-sm">
                      <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong>${r.customer?.user?.fullname ?? 'Anonymous'}</strong>
                        <span class="text-warning small">${'⭐'.repeat(r.rating)}</span>
                      </div>
                      <p class="mb-1 text-secondary">${r.comment || ''}</p>
                      <small class="text-muted">${new Date(r.created_at).toLocaleString()}</small>
                    </div>
                  `).join('');
                }


                // Feedback modal
                const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
                const finalSubmitBtn = document.getElementById('finalSubmitBtn');
                const feedbackForm = document.getElementById('feedbackForm');
                let feedbackModalInstance, confirmModalInstance;

                confirmSubmitBtn.addEventListener('click', () => {
                    feedbackModalInstance = bootstrap.Modal.getInstance(document.getElementById(
                        'feedbackModal'));
                    confirmModalInstance = new bootstrap.Modal(document.getElementById('feedbackConfirmModal'));
                    feedbackModalInstance.hide();
                    confirmModalInstance.show();
                });

                finalSubmitBtn.addEventListener('click', async () => {
                    confirmModalInstance.hide();

                    const formData = new FormData(feedbackForm);
                    const response = await fetch('{{ route('feedback.store') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .content,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    if (response.ok) {
                        const data = await response.json();

                        // Reset and hide modal
                        feedbackForm.reset();
                        if (feedbackModalInstance) feedbackModalInstance.hide();

                        // Optionally reload feedback list if user is viewing feedbacks
                        if (document.getElementById('feedbackSection').style.display === 'block') {
                            const res = await fetch(
                                `{{ url('/feedback') }}/${formData.get('business_id')}`);
                            const reviews = await res.json();
                            renderFeedbackList(reviews);
                        }
                    } else {
                        const err = await response.json();
                        alert(err.message || 'Error submitting feedback');
                    }
                });

                // Get the currently logged-in customer's ID
                const currentUserId = {{ auth()->id() ?? 'null' }};
                let currentBusinessId = {{ $business->business_id ?? 'null' }}; // Get Business ID directly
                let currentReceiverId = {{ $business->vendor->user_id ?? 'null' }}; // Get Vendor User ID directly
                let pusher = null;
                let channel = null;

                // --- Helper function to append a single message (Defined ONCE) ---
                function appendMessage(message) {
                    if (!message || typeof message.sender_id === 'undefined') {
                        console.error("Invalid message object received:", message);
                        return;
                    }
                    const isSender = message.sender_id == currentUserId; // Use loose comparison (==)
                    const alignClass = isSender ? 'text-end' : 'text-start';
                    const messageBgColor = isSender ? '#fff0d9' : '#f1f1f1';
                    let senderName = 'Business'; // Default for customer view
                    if (isSender) {
                        senderName = 'You';
                    } else if (message.sender && message.sender.fullname) {
                        senderName = message.sender.fullname; // Use vendor's name if available
                    } else {
                        // Fallback if sender data is missing on received message
                        senderName = $('#chatBusinessName').text() || 'Business';
                    }

                    const messageHtml = `
            <div class="message-wrapper ${alignClass} mb-2" data-message-id="${message.message_id || ''}">
                <small class="text-muted d-block">${senderName}</small>
                <div class="message p-2 px-3 rounded d-inline-block"
                     style="background-color: ${messageBgColor}; max-width: 80%; text-align: left; word-wrap: break-word;">
                    ${message.message_text}
                </div>
            </div>
        `;
                    // Use jQuery to append since the rest of the script uses it
                    $('#chat-box').append(messageHtml);
                }

                // --- Helper function to scroll chat to bottom (Defined ONCE) ---
                function scrollToBottom() {
                    const chatBox = $('#chat-box');
                    if (chatBox.length > 0) {
                        chatBox.scrollTop(chatBox[0].scrollHeight);
                    }
                }

                // --- Initialize Pusher ---
                function initializePusher(businessId) {
                    if (channel) {
                        pusher.unsubscribe(channel.name);
                        console.log(`Unsubscribed from ${channel.name}`);
                    }
                    if (!pusher) {
                        try {
                            pusher = new Pusher("{{ config('broadcasting.connections.pusher.key') }}", {
                                cluster: "{{ config('broadcasting.connections.pusher.options.cluster') }}",
                                forceTLS: true
                            });
                            console.log("Customer Pusher initialized.");
                        } catch (e) {
                            console.error("Failed to initialize Pusher:", e);
                            return; // Don't proceed if Pusher fails
                        }
                    }

                    const channelName = `chat.business.${businessId}`;
                    channel = pusher.subscribe(channelName);
                    console.log(`Customer subscribing to ${channelName}`);

                    channel.bind('pusher:subscription_succeeded', () => {
                        console.log(`Customer successfully subscribed to ${channelName}`);
                    });
                    channel.bind('pusher:subscription_error', (status) => {
                        console.error(`Customer failed to subscribe to ${channelName}:`, status);
                    });

                    // Bind to your specific message event
                    channel.bind('App\\Events\\MessageSent', data => {
                        console.log("Customer Pusher received:", data);
                        const message = data.message;

                        // IMPORTANT: Only append if the message is relevant to this chat AND not sent by the current customer
                        if (message && message.sender_id != currentUserId && message.receiver_id ==
                            currentUserId) {
                            // Check if message already exists (prevent double append)
                            if ($(`#chat-box .message-wrapper[data-message-id="${message.message_id}"]`)
                                .length === 0) {
                                // Check if modal is currently open before appending
                                if ($('#messageModal').hasClass('show')) {
                                    appendMessage(message);
                                    scrollToBottom();
                                    // Optionally: Mark as read via AJAX
                                } else {
                                    console.log("Pusher: Modal closed, not appending message visually.");
                                    // You could show a notification badge elsewhere on the page here
                                }
                            } else {
                                console.log("Customer Pusher: Message already displayed, skipping append.");
                            }
                        } else {
                            console.log(
                                "Customer Pusher: Received message ignored (own message or wrong recipient)."
                            );
                        }
                    });
                }

                // --- Initialize Pusher on page load ---
                if (currentBusinessId) {
                    initializePusher(currentBusinessId);
                } else {
                    console.error("Cannot initialize Pusher: Business ID not found.");
                }


                // --- Event Handlers ---

                // 1. When the Message Modal is opened (Load Thread AJAX)
                $('#messageModal').on('show.bs.modal', function(event) {
                    var button = $(event
                        .relatedTarget); // Can reuse data from button if needed, but IDs are already known
                    currentBusinessId = button.data('business-id'); // Re-confirm IDs from button
                    currentReceiverId = button.data('receiver-id');
                    var businessName = button.data('business-name');

                    $('#chatBusinessName').text(businessName);
                    $('#chat-box').html(
                        '<div id="chat-placeholder" class="text-center text-muted">Loading messages...</div>'
                    );
                    $('#chat_business_id').val(currentBusinessId);
                    $('#chat_receiver_id').val(currentReceiverId);

                    if (!currentUserId) {
                        $('#chat-box').html(
                            '<div class="text-center text-danger">You must be logged in to send a message.</div>'
                        );
                        $('#sendMessageForm').hide();
                        return;
                    } else {
                        $('#sendMessageForm').show();
                    }

                    // AJAX Call to load messages
                    $.ajax({
                        url: `/messages/show/${currentBusinessId}/${currentReceiverId}`, // Ensure this route exists and works
                        type: 'GET',
                        success: function(messages) {
                            var chatBox = $('#chat-box');
                            chatBox.html(''); // Clear loading

                            if (messages.length === 0) {
                                chatBox.html(
                                    '<div id="chat-placeholder" class="text-center text-muted">Start the conversation!</div>'
                                );
                            } else {
                                messages.forEach(appendMessage); // Use the single defined function
                                scrollToBottom();
                            }
                        },
                        error: function(xhr) {
                            console.error("Customer: Error loading messages:", xhr.responseText);
                            $('#chat-box').html(
                                '<div class="text-center text-danger">Failed to load messages.</div>'
                            );
                        }
                    });
                });

                // 2. When the "Send" message form is submitted (Send Message AJAX)
                $('#sendMessageForm').on('submit', function(e) {
                    e.preventDefault();
                    var messageText = $('#chat_message_text').val();
                    if (messageText.trim() === '' || !currentReceiverId) return;

                    const $submitButton = $(this).find('button[type="submit"]');
                    $submitButton.prop('disabled', true).text('Sending...');

                    $.ajax({
                        url: '{{ route('customer.messages.send') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            receiver_id: currentReceiverId,
                            business_id: currentBusinessId,
                            message_text: messageText,
                        },
                        // CORRECT success function (doesn't clear chat box)
                        success: function(message) {
                            console.log("Customer: Message received after sending:", message);
                            $('#chat_message_text').val('');
                            $('#chat-placeholder').remove();

                            // Check if message already exists (if Pusher was faster)
                            if ($(
                                    `#chat-box .message-wrapper[data-message-id="${message.message_id}"]`
                                )
                                .length === 0) {
                                appendMessage(message); // Use the single defined function
                            } else {
                                console.log(
                                    "Customer AJAX Success: Message already displayed by Pusher, skipping append."
                                );
                            }
                            scrollToBottom();
                        },
                        error: function(xhr) {
                            console.error("Customer: Error sending message:", xhr.responseText);
                            alert('Failed to send message.');
                        },
                        complete: function() {
                            $submitButton.prop('disabled', false).text('Send');
                        }
                    });
                });
            });
        </script>
    @endpush
@endsection
