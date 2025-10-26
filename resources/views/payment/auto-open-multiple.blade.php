<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Complete Your Payments</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body {
            background-color: #f9fafb;
            color: #111827;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
            box-sizing: border-box;
        }

        .card {
            background-color: white;
            border: 1px solid #e5e7eb;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, .05), 0 2px 4px -2px rgba(0, 0, 0, .05);
            text-align: center;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-top: 0;
        }

        p {
            color: #4b5563;
            line-height: 1.6;
        }

        .payment-links {
            margin-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            padding-top: 1.5rem;
            text-align: left;
        }

        .payment-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .payment-link span {
            font-weight: 500;
        }

        .pay-button {
            background: linear-gradient(135deg, #fbbf24 0%, #f97316 100%);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .pay-button:hover {
            opacity: 0.9;
        }

        .pay-button.paid {
            background: #16a34a;
            cursor: default;
        }

        .pay-button.paid:hover {
            opacity: 1;
        }

        .pay-button.cancelled {
            background: #6b7280;
            text-decoration: line-through;
            cursor: default;
        }

        .pay-button.cancelled:hover {
            opacity: 1;
        }

        .status-area {
            margin-top: 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            background-color: #f3f4f6;
        }

        .status-text {
            font-weight: 600;
            color: #1f2937;
        }

        .actions {
            margin-top: 2rem;
        }

        .finish-button {
            background-color: #3b82f6;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 1rem;
            width: 100%;
        }

        .finish-button:hover {
            background-color: #2563eb;
        }

        .finish-button:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .spinner {
            border: 3px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>You're Almost Done!</h2>
        <p>
            Please pay for the orders you wish to proceed with. This page updates in real-time.
            <br><strong>When you are finished, click the "Finalize Checkout" button below.</strong>
        </p>

        <div class="payment-links">
            @foreach ($checkoutSessions as $session)
                <div class="payment-link">
                    <span>Order for {{ $session['business_name'] ?? 'Business' }}</span>
                    <a href="{{ $session['checkout_url'] }}" target="_blank" class="pay-button"
                        id="pay-button-{{ $session['draft_id'] }}" data-draft-id="{{ $session['draft_id'] }}">Pay Now</a>
                </div>
            @endforeach
        </div>

        <div class="status-area">
            <p id="status-text" class="status-text">Checking payment statuses...</p>
        </div>

        <div class="actions">
            <form id="finalizeForm" action="{{ route('checkout.finalize') }}" method="POST">
                @csrf
                <button id="finishButton" type="submit" class="finish-button">Finalize Checkout</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const statusText = document.getElementById('status-text');
            const finishButton = document.getElementById('finishButton');
            let intervalId = null; // Define intervalId here

            // Auto-open all links once on load
            document.querySelectorAll('.pay-button').forEach((button, i) => {
                setTimeout(() => {
                    if (!button.classList.contains('paid') && !button.classList.contains(
                            'cancelled')) {
                        window.open(button.href, `_blank_${button.dataset.draftId}`);
                    }
                }, i * 300);
            });

            // When the user submits the form, show a processing state and stop polling
            document.getElementById('finalizeForm').addEventListener('submit', () => {
                if (intervalId) {
                    clearInterval(intervalId);
                }
                finishButton.disabled = true;
                finishButton.innerHTML = `<div class="spinner"></div> Processing...`;
            });

            // This function is our single source of truth for UI updates
            async function updateStatus() {
                // Stop polling if the form has been submitted
                if (finishButton.disabled) {
                    return;
                }

                try {
                    const response = await fetch("{{ route('api.checkout.status') }}");

                    // If the session has expired or the flow is done, the backend will send a 404.
                    // In this case, we just stop polling.
                    if (response.status === 404) {
                        statusText.textContent = 'Checkout session has ended.';
                        clearInterval(intervalId);
                        return;
                    }

                    if (!response.ok) {
                        statusText.textContent = 'Error checking status.';
                        clearInterval(intervalId);
                        return;
                    }

                    const data = await response.json();

                    // Update the UI for each individual draft
                    data.details.forEach(detail => {
                        const button = document.getElementById(`pay-button-${detail.draft_id}`);
                        if (button) {
                            if (detail.status === 'paid') {
                                button.textContent = '✓ Paid';
                                button.classList.remove('cancelled');
                                button.classList.add('paid');
                                button.removeAttribute('href');
                            } else if (detail.status === 'cancelled') {
                                button.textContent = 'Cancelled';
                                button.classList.remove('paid');
                                button.classList.add('cancelled');
                                button.removeAttribute('href');
                            }
                        }
                    });

                    // Update the main summary text
                    statusText.textContent =
                        `Status: ${data.processed} paid, ${data.cancelled} cancelled, ${data.pending} pending.`;

                    // The ONLY job of this `complete` status is to update the button text for the user.
                    // It does NOT stop the poll.
                    if (data.status === 'complete') {
                        finishButton.textContent = 'All Done! View Your Orders';
                        finishButton.style.backgroundColor = '#16a34a'; // Green color for success
                    } else {
                        // Revert button to default state if a new payment is initiated somehow (edge case)
                        finishButton.textContent = 'Finalize Checkout';
                        finishButton.style.backgroundColor = '#3b82f6';
                    }
                } catch (error) {
                    console.error('Polling error:', error);
                    statusText.textContent = 'Connection error. Status check paused.';
                    clearInterval(intervalId);
                }
            }

            // Start the polling
            intervalId = setInterval(updateStatus, 3500);
            // Run an initial check immediately on page load
            updateStatus();
        });
    </script>
</body>

</html>
