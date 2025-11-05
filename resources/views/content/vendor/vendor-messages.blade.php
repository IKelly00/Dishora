@extends('layouts/contentNavbarLayout')

@section('title', 'Messages')

@section('content')
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>

    <div class="container-fluid">
        <div class="main-content-area shadow-sm p-4 bg-light rounded-3">
            <h3 class="mb-4 fw-semibold text-primary">📨 Messages for {{ $businessName ?? 'Your Business' }}</h3>

            <div class="row g-3">
                <!-- Conversation List -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-primary text-white fw-semibold">
                            Conversations
                        </div>
                        <div class="list-group list-group-flush" id="conversation-list">
                            @forelse($conversations as $convo)
                                <a href="#"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-start convo-item"
                                    data-customer-id="{{ $convo->user_id }}" data-customer-name="{{ $convo->fullname }}">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-semibold text-dark">{{ $convo->fullname }}</div>
                                        <small class="text-muted">
                                            {{ Str::limit($convo->last_message?->message_text, 30) }}
                                        </small>
                                    </div>
                                    @if ($convo->unread_count > 0)
                                        <span class="badge bg-warning text-dark rounded-pill align-self-center">
                                            {{ $convo->unread_count }}
                                        </span>
                                    @endif
                                </a>
                            @empty
                                <div class="list-group-item text-center text-muted py-3">
                                    No messages found.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Chat Panel -->
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-light fw-semibold" id="chat-header">
                            Select a Conversation
                        </div>
                        <div class="card-body bg-white" id="chat-box" style="height: 500px; overflow-y: auto;">
                            <div class="text-center text-muted mt-5" id="chat-placeholder">
                                Select a conversation from the left to view messages.
                            </div>
                        </div>
                        <div class="card-footer bg-light py-2" id="chat-footer" style="display: none;">
                            <form id="vendorReplyForm">
                                @csrf
                                <input type="hidden" id="reply_customer_id" name="customer_id">
                                <div class="input-group">
                                    <input type="text" id="reply_message_text" name="message_text"
                                        class="form-control border-0" placeholder="Type your reply..." required>
                                    <button type="submit" class="btn btn-warning fw-semibold text-dark">
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

    <style>
        /* ... (all your existing styles are perfect) ... */
        .main-content-area {
            background: #fdfdfd !important;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #f2f2f2;
        }

        body {
            background-color: #fdfdfd !important;
        }

        .convo-item {
            transition: background-color 0.2s, transform 0.1s;
        }

        .convo-item:hover {
            background-color: #fff8e1;
            transform: translateX(2px);
        }

        .convo-item.active {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffb300;
        }

        #chat-box {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 1rem;
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

        .message-wrapper.text-end .message {
            background-color: #ffe9c9;
            border: 1px solid #ffdfb0;
            border-radius: 1rem 1rem 0.3rem 1rem;
        }

        .message {
            word-break: break-word;
            display: inline-block;
        }

        .card-header {
            background-color: #ffb54c;
            color: #fff;
            font-weight: 600;
        }

        #chat-header {
            background-color: #fff6e5 !important;
            color: #6c4a00 !important;
        }

        #vendorReplyForm input.form-control:focus {
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

    <script>
        $(document).ready(function() {
            // This is the ID of the logged-in VENDOR'S USER
            const currentVendorUserId = {{ auth()->user()->user_id }};
            // This is the ID of the BUSINESS they are acting as
            const activeBusinessId = {{ $activeBusinessId ?? 'null' }};

            let activeCustomerId = null;
            let pusher = null;
            let channel = null;

            function appendMessage(message) {
                if (!message || typeof message.sender_role === 'undefined') { // <-- Check for role
                    console.error("Invalid message object:", message);
                    return;
                }

                // --- THIS IS THE FIX ---
                // We check the ROLE, not the sender_id.
                // If the sender's role is 'business', it's "You".
                const isSender = message.sender_role === 'business';
                // --- END FIX ---

                const alignClass = isSender ? 'text-end' : 'text-start';

                // --- FIX FOR SENDER NAME ---
                let senderName = '';
                if (isSender) {
                    senderName = 'You';
                } else {
                    // Use the sender's name from the object (e.g., "Jomar Nicholas Veras")
                    if (message.sender && message.sender.fullname) {
                        senderName = message.sender.fullname;
                    } else {
                        senderName = 'Customer'; // Fallback
                    }
                }
                // --- END FIX ---

                const messageHtml = `
                    <div class="message-wrapper ${alignClass} mb-2" data-message-id="${message.message_id || ''}">
                        <small class="text-muted d-block mb-1">${senderName}</small>
                        <div class="message px-3 py-2 shadow-sm">
                            ${message.message_text}
                        </div>
                    </div>
                `;
                $('#chat-box').append(messageHtml);
            }

            function scrollToBottom() {
                const chatBox = $('#chat-box');
                chatBox.scrollTop(chatBox[0].scrollHeight);
            }

            function initializePusher(businessId) {
                if (channel) pusher.unsubscribe(channel.name);
                if (!pusher) {
                    pusher = new Pusher("{{ config('broadcasting.connections.pusher.key') }}", {
                        cluster: "{{ config('broadcasting.connections.pusher.options.cluster') }}",
                        forceTLS: true
                    });
                }

                const channelName = `chat.business.${businessId}`;
                channel = pusher.subscribe(channelName);

                channel.bind('App\\Events\\MessageSent', data => {
                    const message = data.message;

                    // --- THIS IS THE PUSHER FIX ---
                    // Only append if the message is from a 'customer'
                    // AND it belongs to the currently active chat window.
                    if (message && message.sender_role === 'customer' && message.sender_id ==
                        activeCustomerId) {
                        // --- END FIX ---
                        if (!$(`#chat-box [data-message-id="${message.message_id}"]`).length) {
                            appendMessage(message);
                            scrollToBottom();
                        }
                    }
                });
            }

            if (activeBusinessId) initializePusher(activeBusinessId);

            $('#conversation-list').on('click', 'a', function(e) {
                e.preventDefault();
                activeCustomerId = $(this).data('customer-id');
                const customerName = $(this).data('customer-name');
                $('#chat-header').text(`Chat with ${customerName}`);
                $('#chat-box').html(
                    '<div class="text-center text-muted mt-5" id="chat-placeholder">Loading...</div>');
                $('#chat-footer').show();
                $('#reply_customer_id').val(activeCustomerId);
                $('#conversation-list a').removeClass('active');
                $(this).addClass('active');
                $(this).find('.badge').remove();

                $.ajax({
                    url: `{{ route('vendor.messages.thread') }}`,
                    type: 'GET',
                    data: {
                        customer_id: activeCustomerId
                    },
                    success: function(messages) {
                        $('#chat-box').empty();
                        if (!messages.length) {
                            $('#chat-box').html(
                                '<div class="text-center text-muted mt-5" id="chat-placeholder">No messages yet.</div>'
                            );
                        } else {
                            messages.forEach(appendMessage);
                            scrollToBottom();
                        }
                    }
                });
            });

            $('#vendorReplyForm').on('submit', function(e) {
                e.preventDefault();
                const messageText = $('#reply_message_text').val();
                if (!messageText.trim() || !activeCustomerId) return;
                const $btn = $(this).find('button');
                $btn.prop('disabled', true).text('Sending...');

                $.ajax({
                    url: `{{ route('vendor.messages.send') }}`,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        customer_id: activeCustomerId,
                        message_text: messageText
                    },
                    success: function(message) {
                        $('#reply_message_text').val('');
                        if (!$(`#chat-box [data-message-id="${message.message_id}"]`).length) {
                            appendMessage(message);
                        }
                        scrollToBottom();
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Send');
                    }
                });
            });
        });
    </script>
@endsection
