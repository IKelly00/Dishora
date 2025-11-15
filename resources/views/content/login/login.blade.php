@extends('layouts/commonMaster')

@section('title', 'Login')

@section('layoutContent')

    <!-- Fonts & Toastr -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <style>
        /* Background gradient */
        .bg {
            background: linear-gradient(135deg, #fef7ed 0%, #fed7aa 50%, #fdba74 100%);
            min-height: 100vh;
        }

        /* Welcome section container */
        .welcome-section {
            border-radius: 20px;
            padding: 3rem;
            margin: 1rem;
            box-shadow: 0 20px 40px rgba(243, 149, 47, 0.25);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: shimmer 6s ease-in-out infinite alternate;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-50%) translateY(-50%) rotate(0deg);
            }

            100% {
                transform: translateX(-30%) translateY(-30%) rotate(10deg);
            }
        }

        /* Food showcase section */
        .food-showcase {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            height: 250px;
        }

        .food-plate {
            width: 220px;
            height: 220px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            overflow: hidden;
            transition: all 0.3s ease;
            background: transparent;
        }

        .food-plate:first-child {
            left: 48%;
            transform: translateX(-85%);
            z-index: 1;
        }

        .food-plate:last-child {
            left: 52%;
            transform: translateX(-15%) translateY(-30px);
            z-index: 2;
        }

        .food-plate:hover {
            z-index: 3;
            transform: translateX(-85%) scale(1.1);
        }

        .food-plate:last-child:hover {
            transform: translateX(-15%) scale(1.1);
        }

        .food-plate img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Login card container */
        .login-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            margin: 1rem;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-bottom: 1px solid #e9ecef;
            padding: 1.5rem;
        }

        /* Input styling */
        .form-control {
            border: none;
            border-radius: 0;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            box-shadow: none;
            background: white;
            border-color: transparent;
        }

        /* Icon input group */
        .input-group-text {
            background: #fafbfc;
            border: none;
            color: #6b7280;
            padding: 0.5rem 0.75rem;
            border-radius: 0;
        }

        /* Primary button */
        .btn-primary {
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
        }

        /* Typography */
        .welcome-text {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
        }

        .primary-color {
            color: #f3952f !important;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .poppins-regular {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: rgba(0, 0, 0, 0.9);
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }

        /* Divider line */
        .divider {
            position: relative;
            text-align: center;
            margin: 2rem 0;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
        }

        .divider span {
            background: white;
            padding: 0 1rem;
            color: #6b7280;
            font-size: 0.9rem;
            position: relative;
        }

        /* Password toggle button spacing */
        .input-group-text.cursor-pointer {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .input-group-text.cursor-pointer i {
            font-size: 1.25rem;
            pointer-events: none;
        }

        /* Input-group border and focus state */
        .input-group {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .input-group:focus-within {
            border-color: #fdba74;
            box-shadow: 0 0 0 4px rgba(243, 149, 47, 0.25);
        }

        /* Responsive adjustments */
        @media (max-width: 1399px) {
            .food-plate {
                width: 200px;
                height: 200px;
            }
        }

        @media (max-width: 1199px) {
            .welcome-section {
                margin-bottom: 2rem;
                text-align: center;
                padding: 2.5rem;
            }

            .welcome-text {
                font-size: 2rem;
            }

            .food-plate {
                width: 180px;
                height: 180px;
            }
        }

        @media (max-width: 991px) {
            .welcome-section {
                padding: 2rem;
            }

            .food-showcase {
                height: 220px;
            }

            .food-plate {
                width: 170px;
                height: 170px;
            }
        }

        @media (max-width: 768px) {

            .welcome-section,
            .login-card {
                margin: 0.5rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-text {
                font-size: 1.8rem;
            }

            .food-showcase {
                height: 200px;
            }

            .food-plate {
                width: 150px;
                height: 150px;
            }

            .card-body {
                padding: 1.5rem !important;
            }
        }

        @media (max-width: 576px) {
            .welcome-section {
                padding: 1rem;
                margin: 0.25rem;
            }

            .login-card {
                margin: 0.25rem;
            }

            .welcome-text {
                font-size: 1.5rem;
            }

            .food-showcase {
                height: 180px;
            }

            .food-plate {
                width: 130px;
                height: 130px;
            }

            .card-body {
                padding: 1rem !important;
            }

            .btn-primary {
                padding: 0.875rem 1.5rem;
            }
        }
    </style>

    <script>
        $(document).ready(function() {

            // Show toast notifications
            @if (session('success'))
                toastr.success("{{ session('success') }}");
            @endif

            @if (session('error'))
                toastr.error("{{ session('error') }}");
            @endif

            @if ($errors->any())
                @foreach ($errors->all() as $error)
                    toastr.error("{{ $error }}");
                @endforeach
            @endif

            // Toggle password visibility
            $('.form-password-toggle .input-group-text.cursor-pointer').on('click', function(e) {
                e.preventDefault();

                const $container = $(this).closest('.input-group');
                const $input = $container.find('input');
                const $icon = $(this).find('i');

                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $icon.removeClass('ri-eye-off-line').addClass('ri-eye-line');
                } else {
                    $input.attr('type', 'password');
                    $icon.removeClass('ri-eye-line').addClass('ri-eye-off-line');
                }

                $input.focus();
            });

            // Auto-hide alerts
            const $alert = $('.alert');
            if ($alert.length) {
                setTimeout(() => {
                    $alert.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        });
    </script>

    <div class="min-vh-100 d-flex flex-column bg">
        <div class="flex-grow-1 d-flex align-items-center justify-content-center py-4">
            <div class="container-fluid">
                <div class="row align-items-center justify-content-center">

                    <!-- Left: Welcome Section -->
                    <div class="col-12 col-xl-6 col-lg-6 order-2 order-lg-1">
                        <div class="welcome-section">
                            <div style="position: relative; z-index: 2;">
                                <h2 class="welcome-text">Welcome to Dishora</h2>
                                <p class="poppins-regular mb-3">
                                    At Dishora, we believe that every great meal deserves a wider table.
                                    We're excited to offer an easy-to-use platform that connects vendors
                                    with customers, helping small businesses grow and communities savor every bite.
                                </p>
                                <p class="primary-color mb-4">
                                    <i class="ri-restaurant-line me-2"></i>Order Now!
                                </p>

                                <div class="food-showcase">
                                    <div class="food-plate">
                                        <img src="{{ asset('assets/img/welcome_page/Burger.png') }}" alt="Delicious burger">
                                    </div>
                                    <div class="food-plate">
                                        <img src="{{ asset('assets/img/welcome_page/Shrimps.png') }}"
                                            alt="Delicious shrimps">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Login Form -->
                    <div class="col-12 col-xl-5 col-lg-6 order-1 order-lg-2">
                        <div class="card login-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">
                                    <i class="ri-login-circle-line me-2 text-primary"></i>
                                    Sign in to your account
                                </h5>
                                <p class="mb-0 text-muted small mt-1">Welcome back! Please enter your details.</p>
                            </div>

                            <div class="card-body p-4">
                                <form method="POST" action="{{ route('login') }}">
                                    @csrf

                                    <!-- Username / Email -->
                                    <div class="mb-3">
                                        <label for="login" class="form-label fw-medium">Username or Email</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="ri-user-line ri-18px"></i>
                                            </span>
                                            <input type="text" class="form-control" id="login" name="login"
                                                required placeholder="Enter your username or email"
                                                value="{{ old('login') }}">
                                        </div>
                                    </div>

                                    <!-- Password Field -->
                                    <div class="mb-3 form-password-toggle">
                                        <label for="password" class="form-label fw-medium">Password</label>
                                        <div class="input-group input-group-merge">
                                            <span class="input-group-text">
                                                <i class="ri-lock-2-line ri-18px"></i>
                                            </span>
                                            <input type="password" class="form-control" id="password" name="password"
                                                placeholder="Enter your password" required>

                                            <span class="input-group-text cursor-pointer">
                                                <i class="ri-eye-off-line ri-18px"></i>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Remember Me + Forgot Password -->
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="rememberMe" name="remember">
                                            <label class="form-check-label small" for="rememberMe">
                                                Remember me
                                            </label>
                                        </div>

                                        <a href="{{ route('password.request') }}"
                                            class="text-decoration-none small text-primary">
                                            Forgot password?
                                        </a>
                                    </div>

                                    <!-- Submit -->
                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="ri-login-circle-line me-2"></i>Sign In
                                        </button>
                                    </div>

                                    <!-- Divider -->
                                    <div class="divider"><span>or</span></div>

                                    <!-- Register Link -->
                                    <div class="text-center">
                                        <p class="mb-0 text-muted">
                                            Don't have an account?
                                            <a href="{{ route('registerForm') }}"
                                                class="text-primary text-decoration-none fw-medium">
                                                Create an account
                                            </a>
                                        </p>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        @include('layouts/sections/footer/footer')
    </div>

@endsection
