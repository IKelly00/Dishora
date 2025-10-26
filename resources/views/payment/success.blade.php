<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payment Success</title>
</head>

<body>
    <p>{{ $successMessage }}</p>
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
