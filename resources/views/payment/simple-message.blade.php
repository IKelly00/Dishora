<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Payment Status' }}</title>
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
            text-align: center;
        }

        .card {
            background-color: white;
            border: 1px solid #e5e7eb;
            padding: 2rem;
            border-radius: 12px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, .05), 0 2px 4px -2px rgba(0, 0, 0, .05);
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-top: 0;
        }

        p {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 0;
        }

        .countdown {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>{{ $title ?? 'Status' }}</h1>
        <p>{{ $message ?? 'Please return to the main window.' }}</p>
        <p id="countdown-container" class="countdown" style="display: none;">This window will close automatically in <span
                id="countdown-timer">3</span> seconds...</p>
        <p id="redirect-container" class="countdown" style="display: none;">You will be redirected automatically in <span
                id="redirect-timer">3</span> seconds...</p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Check if this window was opened by another window's script (i.e., it's a popup)
            const isPopup = window.opener != null;

            const countdownElement = document.getElementById('countdown-timer');
            const redirectElement = document.getElementById('redirect-timer');
            let seconds = 3;

            if (isPopup) {
                // --- THIS LOGIC RUNS FOR MULTIPLE ONLINE PAYMENTS ---
                document.getElementById('countdown-container').style.display = 'block';

                const updateTimer = () => {
                    countdownElement.textContent = seconds;
                    if (seconds > 0) {
                        seconds--;
                    }
                };
                const countdownInterval = setInterval(updateTimer, 1000);

                setTimeout(() => {
                    clearInterval(countdownInterval);
                    // This will successfully close windows opened via window.open()
                    window.close();
                }, seconds * 1000 + 500);

            } else {
                // --- THIS LOGIC RUNS FOR A SINGLE ONLINE PAYMENT ---
                document.getElementById('redirect-container').style.display = 'block';

                const successRedirectUrl = "{{ route('customer.orders.index') }}?status=success";
                const failureRedirectUrl = "{{ route('customer.cart') }}";

                // Determine if this is a success or failure page by the title
                const isSuccess = "{{ strtolower($title ?? '') }}".includes('received');
                const redirectUrl = isSuccess ? successRedirectUrl : failureRedirectUrl;

                const updateTimer = () => {
                    redirectElement.textContent = seconds;
                    if (seconds > 0) {
                        seconds--;
                    }
                };
                const countdownInterval = setInterval(updateTimer, 1000);

                setTimeout(() => {
                    clearInterval(countdownInterval);
                    // Redirect the main window to a safe and useful page
                    window.location.href = redirectUrl;
                }, seconds * 1000 + 500);
            }
        });
    </script>
</body>

</html>
