<!DOCTYPE html>
<html class="light-style layout-menu-fixed" data-theme="theme-default" data-assets-path="{{ secure_asset('assets') }}/"
    data-base-url="{{ url('/') }}" data-framework="laravel" data-template="vertical-menu-laravel-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />

    <title>@yield('title') | Dishora</title>

    <meta name="description" content="{{ config('variables.templateDescription', '') }}" />
    <meta name="keywords" content="{{ config('variables.templateKeyword', '') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    {{-- Optional: Uncomment to force HTTPS for assets --}}
    {{-- <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests"> --}}

    <!-- Canonical SEO -->
    <link rel="canonical" href="{{ config('variables.productPage', '') }}" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon"
        href="{{ app()->environment('production')
            ? secure_asset('assets/img/favicon/favicon.ico')
            : asset('assets/img/favicon/favicon.ico') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Changa+One:ital@0;1&family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap"
        rel="stylesheet" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <!-- Styles -->
    @include('layouts/sections/styles')

    <!-- Scripts (Customizer, Helper, Analytics, Config) -->
    @include('layouts/sections/scriptsIncludes')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

</head>

<body>
    <!-- Layout Content -->
    @yield('layoutContent')
    <!-- / Layout Content -->

    <!-- Scripts -->
    @include('layouts/sections/scripts')
    @stack('page-script')
</body>

</html>
