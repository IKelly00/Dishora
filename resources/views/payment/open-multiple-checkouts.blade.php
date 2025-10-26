<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Complete Your Payments</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"> {{-- For the cancel button --}}
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
            transition: opacity 0.2s;
        }

        .pay-button:hover {
            opacity: 0.9;
        }

        .status-area {
            margin-top: 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            background-color: #f3f4f6;
            text-align: center;
        }

        .status-area .spinner {
            border: 4px solid #e5e7eb;
            border-top-color: #f97316;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 0 auto .5rem auto;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .status-text {
            font-weight: 600;
            color: #1f2937;
        }

        .actions {
            margin-top: 2rem;
            text-align: center;
        }

        .cancel-button {
            background-color: #d1d5db;
            color: #1f2937;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .cancel-button:hover {
            background-color: #9ca3af;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>Complete Your Payments</h2>
        <p>Please complete the payment for each business in the new tabs that have opened. This page will automatically
            update once all payments are confirmed.</p>

        <div class="payment-links">
            @foreach ($checkoutSessions as $index => $session)
                <div class="payment-link">
                    <span>Order for {{ $session['business_name'] ?? 'Business' }}</span>
                    <a href="{{ $session['checkout_url'] }}" target="_blank" class="pay-button">Pay Now</a>
                </div>
            @endforeach
        </div>

        <div class="status-area">
            <div class="spinner"></div>
            <p id="status-text" class="status-text">Waiting for payments... (0 of {{ count($checkoutSessions) }}
                complete)</p>
        </div>

        <div class="actions">
            <form action="{{ route('checkout.cancelFlow') }}" method="POST"
                onsubmit="return confirm('Are you sure you want to cancel all pending online payments and return to your cart?');">
                @csrf
                <button type="submit" class="cancel-button">Cancel All & Return to Cart</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const statusText = document.getElementById('status-text');
            const totalPayments = {{ count($checkoutSessions) }};

            // Auto-open all links once on load
            const urls = @json(collect($checkoutSessions)->pluck('checkout_url'));
            urls.forEach((url, i) => {
                setTimeout(() => window.open(url, `_blank_${i}`), i * 250);
            });

            // Start polling for status
            const intervalId = setInterval(checkStatus, 3000); // Check every 3 seconds

            async function checkStatus() {
                try {
                    const response = await fetch("{{ route('checkout.status') }}");
                    if (!response.ok) {
                        statusText.textContent = 'Error checking status. Please refresh.';
                        clearInterval(intervalId);
                        return;
                    }

                    const data = await response.json();

                    statusText.textContent =
                        `Waiting for payments... (${data.processed} of ${data.total_online} complete)`;

                    if (data.status === 'complete') {
                        clearInterval(intervalId);
                        statusText.textContent = 'All payments complete! Redirecting...';
                        // Redirect to the final success page or orders index
                        window.location.href = "{{ route('customer.orders.index') }}?status=success";
                    }
                } catch (error) {
                    console.error('Polling error:', error);
                    statusText.textContent = 'Connection error. Status check paused.';
                    clearInterval(intervalId);
                }
            }
        });
    </script>
</body>

</html>
