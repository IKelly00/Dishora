@extends('layouts/contentNavbarLayout')

@section('title', 'Vendors Hub')

@section('content')

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
                                data-business-name="{{ $business->business_name }}">
                                {{-- REMOVED: data-receiver-id="{{ $business->vendor->user_id ?? '' }}" --}}
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
                            {{-- <input type="hidden" id="chat_receiver_id" name="receiver_id"> --}} {{-- <-- REMOVED --}}
                            <input type="text" id="chat_message_text" class="form-control me-2"
                                placeholder="Type your message..." required autocomplete="off">
                            <button type="submit" class="btn btn-warning">Send</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Preorder Success Modal (NEW: used instead of alert) -->
    <div class="modal fade" id="productAddedModal" tabindex="-1" aria-hidden="true">
        <!-- ... (modal content is unchanged) ... -->
    </div>

    <!-- Custom Styles -->
    <style>
        /* ... (all your existing styles are perfect) ... */
    </style>

    @push('page-script')
        <script>
            // This script is for quantity/cart/feedback (Vanilla JS)
            document.addEventListener('DOMContentLoaded', function() {
                // ... (all your existing DOMContentLoaded JS is correct and unchanged) ...
            });

            // This script is for the Message Modal (jQuery)
            $(document).ready(function() {
                // Get the currently logged-in customer's ID
                const currentUserId = {{ auth()->id() ?? 'null' }};
                let currentBusinessId = {{ $business->business_id ?? 'null' }}; // Get Business ID directly
                // let currentReceiverId = {{ $business->vendor->user_id ?? 'null' }}; // <-- No longer needed
                let pusher = null;
                let channel = null;

                // --- Helper function to append a single message (Defined ONCE) ---
                function appendMessage(message) {
                    if (!message || typeof message.sender_role === 'undefined') { // <-- CHECK ROLE
                        console.error("Invalid message object received:", message);
                        return;
                    }

                    // --- THIS IS THE FIX ---
                    // Check the ROLE, not the ID.
                    const isSender = message.sender_role == 'customer';
                    // --- END FIX ---

                    const alignClass = isSender ? 'text-end' : 'text-start';
                    const messageBgColor = isSender ? '#fff0d9' : '#f1f1f1';

                    // --- SENDER NAME FIX ---
                    let senderName = 'Business'; // Default for customer view
                    if (isSender) {
                        senderName = 'You';
                    } else if (message.sender && (message.sender.business_name || message.sender.fullname)) {
                        // Use the name from the sender object
                        senderName = message.sender.business_name || message.sender.fullname;
                    } else {
                        // Fallback if sender data is missing
                        senderName = $('#chatBusinessName').text() || 'Business';
                    }
                    // --- END SENDER NAME FIX ---

                    const messageHtml = `
                        <div class="message-wrapper ${alignClass} mb-2" data-message-id="${message.message_id || ''}">
                            <small class="text-muted d-block">${senderName}</small>
                            <div class="message p-2 px-3 rounded d-inline-block"
                                style="background-color: ${messageBgColor}; max-width: 80%; text-align: left; word-wrap: break-word;">
                                ${message.message_text}
                            </div>
                        </div>
                    `;
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

                    // --- PUSHER LOGIC FIX ---
                    channel.bind('App\\Events\\MessageSent', data => {
                        console.log("Customer Pusher received:", data);
                        const message = data.message;

                        // Only append if it's from the 'business'
                        if (message && message.sender_role === 'business') {
                            // Check if message already exists (prevent double append)
                            if ($(`#chat-box .message-wrapper[data-message-id="${message.message_id}"]`)
                                .length === 0) {
                                // Check if modal is currently open before appending
                                if ($('#messageModal').hasClass('show')) {
                                    appendMessage(message);
                                    scrollToBottom();
                                }
                            }
                        }
                    });
                    // --- END PUSHER LOGIC FIX ---
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
                    var button = $(event.relatedTarget);
                    currentBusinessId = button.data('business-id'); // Re-confirm ID from button
                    var businessName = button.data('business-name');

                    $('#chatBusinessName').text(businessName);
                    $('#chat-box').html(
                        '<div id="chat-placeholder" class="text-center text-muted">Loading messages...</div>'
                    );
                    $('#chat_business_id').val(currentBusinessId);
                    // $('#chat_receiver_id').val(currentReceiverId); // <-- REMOVED

                    if (!currentUserId) {
                        $('#chat-box').html(
                            '<div class="text-center text-danger">You must be logged in to send a message.</div>'
                        );
                        $('#sendMessageForm').hide();
                        return;
                    } else {
                        $('#sendMessageForm').show();
                    }

                    // --- AJAX CALL FIX ---
                    // Use the named route that only requires business_id
                    $.ajax({
                        url: `{{ route('customer.messages.thread', ['business_id' => ':business_id']) }}`
                            .replace(':business_id', currentBusinessId),
                        type: 'GET',
                        // --- END AJAX CALL FIX ---
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
                    // --- FIX: Check for business_id ---
                    if (messageText.trim() === '' || !currentBusinessId) return;

                    const $submitButton = $(this).find('button[type="submit"]');
                    $submitButton.prop('disabled', true).text('Sending...');

                    $.ajax({
                        url: '{{ route('customer.messages.send') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            // receiver_id: currentReceiverId, // <-- REMOVED
                            business_id: currentBusinessId, // <-- CORRECT
                            message_text: messageText,
                        },
                        success: function(message) {
                            console.log("Customer: Message received after sending:", message);
                            $('#chat_message_text').val('');
                            $('#chat-placeholder').remove();

                            if ($(
                                    `#chat-box .message-wrapper[data-message-id="${message.message_id}"]`)
                                .length === 0) {
                                appendMessage(message); // Use the single defined function
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
