<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payment Failed</title>
</head>

<body>
    <p>Payment failed or cancelled</p>
    <script>
        const pageType = @json($type);

        if (window.opener) {
            window.close();
        } else {
            if (pageType === 'preorder') {
                window.location.href = "{{ route('customer.preorder') }}";
            } else {
                window.location.href = "{{ route('customer.cart') }}";
            }
        }
    </script>
</body>

</html>
