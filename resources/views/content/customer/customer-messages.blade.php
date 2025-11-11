@extends('layouts/contentNavbarLayout')

@section('title', 'My Inbox')

@section('page-style')
    <style>
        /* Style for the 'Conversations' header */
        .convo-header-style {
            background-color: #ffb54c;
            color: #fff;
            font-weight: 600;
        }

        /* Style for the 'Select a Conversation' header */
        .chat-header-style {
            background-color: #fff6e5 !important;
            color: #6c4a00 !important;
        }

        /* Conversation list item styling */
        .convo-item {
            transition: background-color 0.2s, transform 0.1s;
            cursor: pointer;
        }

        .convo-item:hover {
            background-color: #fff8e1;
            transform: translateX(2px);
        }

        .convo-item.active {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffb300;
            font-weight: 600;
        }

        /* Make chat box feel airy */
        #chat-box {
            background-color: #ffffff;
            flex-grow: 1;
            overflow-y: auto;
        }

        .message-wrapper {
            max-width: 85%;
            margin-bottom: 0.5rem;
        }

        .message-wrapper.text-start {
            text-align: left;
            margin-right: auto;
            margin-left: 0.5rem;
        }

        /* Message bubble for Vendor (Receiver) */
        .message-wrapper.text-start .message {
            background-color: #f9f9f9;
            border: 1px solid #f0f0f0;
            border-radius: 1rem 1rem 1rem 0.3rem;
        }

        .message-wrapper.text-end {
            text-align: right;
            margin-left: auto;
            margin-right: 0.5rem;
        }

        /* Message bubble for Customer (You) */
        .message-wrapper.text-end .message {
            background-color: #ffe9c9;
            border: 1px solid #ffdfb0;
            border-radius: 1rem 1rem 0.3rem 1rem;
        }

        .message {
            word-break: break-word;
            display: inline-block;
            max-width: 100%;
            text-align: left;
            padding: 0.5rem 0.75rem;
        }

        /* Input form */
        #sendMessageForm input.form-control:focus {
            box-shadow: none;
            border-color: #ffc107;
        }

        #chat-footer {
            border-top: 1px solid #ffecb3;
            background-color: #fffaf1 !important;
        }

        button.btn-warning {
            background-color: #ffb74d;
            border: none;
            transition: background-color 0.2s;
        }

        button.btn-warning:hover {
            background-color: #ffa726;
        }
    </style>
@endsection

@section('content')
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>

    {{-- This is the HTML STRUCTURE from your VENDOR blade --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 pb-0 pt-4">
            <h3 class="mb-0 fw-semibold" style="color: #ffb54c;">
                <i class="bx bx-message-square-detail me-1"></i>
                My Inbox
            </h3>
        </div>
        <div class="card-body">

            {{-- This row (g-3) creates the GAP between the two cards --}}
            <div class="row g-3">

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-3" style="min-height: 70vh;">
                        <div class="card-header convo-header-style fw-semibold">
                            Conversations
                        </div>

                        <div class="list-group list-group-flush" id="conversation-list">
                            {{-- This is the LOOP from your CUSTOMER blade --}}
                            @forelse ($conversations as $convo)
                                <a href="#"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center convo-item p-3"
                                    {{-- These are the DATA ATTRIBUTES from your CUSTOMER javascript --}} data-business-id="{{ $convo->business_id }}" {{-- data-receiver-id="{{ $convo->vendor_user_id }}" --}}
                                    {{-- <-- We don't need this --}} data-business-name="{{ $convo->business_name }}"
                                    data-business-image="{{ $convo->business_image_url ?? secure_asset('images/no-image.jpg') }}">

                                    {{-- This is the CONTENT from your CUSTOMER blade (showing business info) --}}
                                    <img src="{{ $convo->business_image_url ?? secure_asset('images/no-image.jpg') }}"
                                        alt="Logo" class="rounded-circle me-3"
                                        style="width: 45px; height: 45px; object-fit: cover;">

                                    <div class="ms-2 me-auto" style="min-width: 0;">
                                        <div class="fw-semibold text-dark text-truncate">{{ $convo->business_name }}</div>
                                        <small class="text-muted text-truncate d-block">
                                            {{-- Use the last_message object's text --}}
                                            {{ $convo->last_message?->message_text ?? 'Click to open chat' }}
                                        </small>
                                    </div>

                                    @if (!empty($convo->unread_count) && $convo->unread_count > 0)
                                        <span class="badge bg-warning text-dark rounded-pill align-self-center ms-2">
                                            {{ $convo->unread_count }}
                                        </span>
                                    @endif
                                </a>
                            @empty
                                <div class="list-group-item text-center text-muted py-3">
                                    No conversations started.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    {{-- This card is a flex-column to make the footer stick to the bottom --}}
                    <div class="card border-0 shadow-sm rounded-3 d-flex flex-column" style="min-height: 70vh;">

                        <div class="card-header chat-header-style fw-semibold d-flex align-items-center"
                            id="chat-header-wrapper">
                            <img id="chat-header-image" src="" alt="Logo" class="rounded-circle me-3"
                                style="width: 40px; height: 40px; object-fit: cover; display: none;">
                            <span id="chat-header-name">Select a Conversation</span>
                        </div>

                        <div class="card-body bg-white p-3" id="chat-box" style="flex-grow: 1; overflow-y: auto;">
                            <div class="text-center text-muted mt-5 pt-5" id="chat-placeholder">
                                Select a conversation from the left to view messages.
                            </div>
                        </div>

                        <div class="card-footer border-top py-2" id="chat-footer"
                            style="display: none; background-color: #fffaf1 !important;">
                            <form id="sendMessageForm"> {{-- ID matches CUSTOMER JS --}}
                                @csrf
                                <input type="hidden" id="chat_business_id" name="business_id">
                                {{-- <input type="hidden" id="chat_receiver_id" name="receiver_id"> --}} {{-- <-- We don't need this --}}
                                <div class="input-group">
                                    <input type="text" id="chat_message_text" name="message_text"
                                        class="form-control border-0 py-3 px-3" placeholder="Type your reply..." required
                                        style="box-shadow: none; background-color: transparent;">
                                    <button type="submit" class="btn btn-warning fw-semibold text-dark px-4">
                                        Send
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection


@push('page-script')
    <script>
        $(document).ready(function() {

            // --- Global Vars ---
            const currentUserId = {{ auth()->id() ?? 'null' }};
            let currentBusinessId = null;
            // let currentReceiverId = null; // <-- We only need currentBusinessId
            let pusher = null;
            let channel = null;

            // jQuery cache
            const $chatBox = $('#chat-box');
            const $chatPlaceholder = $('#chat-placeholder');
            const $chatHeaderWrapper = $('#chat-header-wrapper');
            const $chatHeaderName = $('#chat-header-name');
            const $chatHeaderImage = $('#chat-header-image');
            const $chatFooter = $('#chat-footer');
            const $sendMessageForm = $('#sendMessageForm');


            // --- Helper function to append a single message ---
            function appendMessage(message) {
                if (!message || typeof message.sender_role === 'undefined') { // <-- Check for role
                    console.error("Invalid message object received:", message);
                    return;
                }

                // --- THIS IS THE FIX ---
                // We check the ROLE, not the sender_id.
                // If the sender's role is 'customer', it's "You".
                const isSender = message.sender_role === 'customer';
                // --- END FIX ---

                const alignClass = isSender ? 'text-end' : 'text-start';

                // --- FIX FOR SENDER NAME ---
                let senderName = '';
                if (isSender) {
                    senderName = 'You';
                } else {
                    // Use the sender's name from the object (e.g., "Joms Cuisine")
                    if (message.sender && (message.sender.business_name || message.sender.fullname)) {
                        senderName = message.sender.business_name || message.sender.fullname;
                    } else {
                        senderName = $chatHeaderName.text(); // Fallback to header
                    }
                }
                // --- END FIX ---

                const messageHtml = `
                    <div class="message-wrapper ${alignClass} mb-2" data-message-id="${message.message_id || ''}">
                        <small class="text-muted d-block mb-1">${senderName}</small>
                        <div class="message shadow-sm">
                            ${message.message_text}
                        </div>
                    </div>
                `;
                $chatBox.append(messageHtml);
            }

            // --- Helper function to scroll chat to bottom ---
            function scrollToBottom() {
                if ($chatBox.length > 0) {
                    $chatBox.scrollTop($chatBox[0].scrollHeight);
                }
            }

            // --- Main Function: Load a Conversation Thread ---
            function loadConversation(businessId, businessName, businessImage) {
                // 1. Set global vars
                currentBusinessId = businessId;
                // currentReceiverId = receiverId; // <-- Not needed

                // 2. Update UI
                $chatPlaceholder.hide();
                $chatFooter.show();

                $chatBox.html('<div class="text-center text-muted p-5">Loading messages...</div>');
                $chatHeaderName.text(businessName);
                $chatHeaderImage.attr('src', businessImage).show();

                $('#chat_business_id').val(businessId);
                // $('#chat_receiver_id').val(receiverId); // <-- Not needed

                // 3. Highlight active conversation in sidebar
                $('.convo-item').removeClass('active');
                $(`.convo-item[data-business-id="${businessId}"]`).addClass('active');
                $(`.convo-item[data-business-id="${businessId}"]`).find('.badge').remove();

                // 4. Initialize Pusher for this specific chat
                initializePusher(businessId);

                // 5. Fetch messages (using your AJAX logic)
                // --- AJAX URL FIX ---
                // We call the correct route, which only needs the business_id
                $.ajax({
                    url: `/customer/messages/thread/${businessId}`, // <-- Make sure this route exists
                    type: 'GET',
                    // --- END AJAX URL FIX ---
                    success: function(messages) {
                        $chatBox.html('');
                        if (messages.length === 0) {
                            $chatBox.html(
                                '<div class="text-center text-muted p-5">Start the conversation!</div>'
                            );
                        } else {
                            messages.forEach(appendMessage);
                            scrollToBottom();
                        }
                    },
                    error: function(xhr) {
                        console.error("Error loading messages:", xhr.responseText);
                        $chatBox.html(
                            '<div class="text-center text-danger p-5">Failed to load messages.</div>'
                        );
                    }
                });
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
                        return;
                    }
                }

                const channelName = `chat.business.${businessId}`;
                channel = pusher.subscribe(channelName);
                console.log(`Customer subscribing to ${channelName}`);

                channel.bind('pusher:subscription_succeeded', () => {
                    console.log(`Customer successfully subscribed to ${channelName}`);
                });

                // --- PUSHER FIX ---
                channel.bind('App\\Events\\MessageSent', data => {
                    console.log("Customer Pusher received:", data);
                    const message = data.message;

                    // 1. Is it a valid message?
                    if (!message || !message.sender_role) return;

                    // 2. Is this message FROM the business? (The only ones we care about)
                    if (message.sender_role === 'business') {
                        const messageBusinessId = message.sender_id;

                        // 3. Is it for the currently OPEN chat?
                        if (messageBusinessId == currentBusinessId) {
                            // Append to chat window
                            if ($(`#chat-box .message-wrapper[data-message-id="${message.message_id}"]`)
                                .length === 0) {
                                appendMessage(message);
                                scrollToBottom();
                            }
                        } else {
                            // 4. Not for the open chat, update sidebar
                            const $convoItem = $(`.convo-item[data-business-id="${messageBusinessId}"]`);
                            if ($convoItem.length) {
                                $convoItem.find('small.text-muted').text(message.message_text.substring(0,
                                    30) + '...');

                                let $badge = $convoItem.find('.badge');
                                if ($badge.length) {
                                    $badge.text(parseInt($badge.text() || 0) + 1);
                                } else {
                                    $convoItem.append(
                                        '<span class="badge bg-warning text-dark rounded-pill align-self-center ms-2">1</span>'
                                    );
                                }
                            }
                        }
                    }
                });
                // --- END PUSHER FIX ---
            }

            // --- Event Listener: Click on a Conversation ---
            $('#conversation-list').on('click', '.convo-item', function() {
                const $item = $(this);
                loadConversation(
                    $item.data('business-id'),
                    // $item.data('receiver-id'), // <-- Not needed
                    $item.data('business-name'),
                    $item.data('business-image')
                );
            });

            // --- Event Listener: Send Message Form ---
            $sendMessageForm.on('submit', function(e) {
                e.preventDefault();
                var messageText = $('#chat_message_text').val();
                // --- FIX: Check currentBusinessId ---
                if (messageText.trim() === '' || !currentBusinessId) return;

                const $submitButton = $(this).find('button[type="submit"]');
                const originalButtonHtml = $submitButton.html();
                $submitButton.prop('disabled', true).text('Sending...');

                $.ajax({
                    url: '{{ route('customer.messages.send') }}', // This route is correct
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        // receiver_id: currentReceiverId, // <-- Not needed
                        business_id: currentBusinessId, // <-- This is what the controller needs
                        message_text: messageText,
                    },
                    success: function(message) {
                        $('#chat_message_text').val('');
                        $chatBox.find('.text-muted.p-5')
                    .remove(); // Remove 'Start conversation' text

                        if ($(
                                `#chat-box .message-wrapper[data-message-id="${message.message_id}"]`)
                            .length === 0) {
                            appendMessage(message);
                        }
                        scrollToBottom();

                        // Update sidebar preview
                        $(`.convo-item.active small.text-muted`).text(message.message_text
                            .substring(0, 30) + '...');
                    },
                    error: function(xhr) {
                        console.error("Customer: Error sending message:", xhr.responseText);
                        alert('Failed to send message.');
                    },
                    complete: function() {
                        $submitButton.prop('disabled', false).html(originalButtonHtml);
                    }
                });
            });

            // --- Initial Check ---
            if (!currentUserId) {
                $chatPlaceholder.html(
                    '<div class="text-center text-danger p-5">You must be logged in to view messages.</div>'
                );
                $('#conversation-list').html(
                    '<li class="list-group-item text-center text-danger p-4">Please log in to view messages.</li>'
                );
            }
        });
    </script>
@endpush
