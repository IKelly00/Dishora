@extends('layouts/commonMaster')

@section('title', 'Login')

@section('layoutContent')

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>


    <style>
        /* (Your CSS Styles remain here, no changes needed for this fix) */

        /* Base Styles */
        .bg {
            background: linear-gradient(135deg, #fef7ed 0%, #fed7aa 50%, #fdba74 100%);
            min-height: 100vh;
        }

        /* Welcome Section Styles (No Change) */
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

        /* Food Showcase Styles (No Change) */
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

        /* Login Card Styles (No Change) */
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

        /* ======================================================= */
        /* FORM CONTROL STYLES (Adjusted for Admin Input Height) */
        /* ======================================================= */
        .form-control {
            border: 1px solid #e9ecef;
            /* Reduced border width to 1px (like admin) */
            border-radius: 12px;
            padding: 0.5rem 1rem;
            /* Reduced padding for smaller height (closer to admin) */
            transition: all 0.3s ease;
            background: white;
            /* Changed from #fafbfc to white to better match admin form control */
        }

        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            background: white;
            border-color: #ced4da;
        }

        /* Input Group Text (For Icons) */
        .input-group-text {
            /* Adjusted to match new form-control padding for correct height */
            background: #fafbfc;
            border: 1px solid #e9ecef;
            /* Reduced border width */
            color: #6b7280;
            padding: 0.5rem 0.75rem;
            /* Adjusted padding */
            /* Standard border-radius logic for input groups */
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }

        /* General fix for input group borders and radius alignment */
        .input-group>.form-control:not(:first-child):not(.form-control-plaintext) {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            border-left: none;
            /* remove redundant border */
        }

        .input-group>.input-group-text:not(:last-child) {
            border-right: none;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        /* Specific styles for the right-side password toggle icon (input-group-merge structure) */
        .input-group-merge>.form-control:not(:last-child) {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .input-group-merge>.input-group-text:last-child {
            border-left: none;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }


        /* Button Styles (No Change) */
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

        /* Typography & Other Styles (No Change) */
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

        /* Responsive Styles (No Change) */
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

            .food-plate:first-child {
                transform: translateX(-80%);
            }

            .food-plate:last-child {
                transform: translateX(-20%);
            }

            .food-plate:hover {
                transform: translateX(-80%) scale(1.1);
            }

            .food-plate:last-child:hover {
                transform: translateX(-20%) scale(1.1);
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
        });
    </script>



    <div class="min-vh-100 d-flex flex-column bg">
        <div class="flex-grow-1 d-flex align-items-center justify-content-center py-4">
            <div class="container-fluid">
                <div class="row align-items-center justify-content-center">

                    <div class="col-12 col-xl-6 col-lg-6 order-2 order-lg-1">
                        <div class="welcome-section">
                            <div style="position: relative; z-index: 2;">
                                <h2 class="welcome-text">Welcome to Dishora</h2>
                                <p class="poppins-regular mb-3">
                                    At Dishora, we believe that every great meal
                                    deserves a wider table. We're excited to
                                    offer
                                    an easy-to-use platform that connects vendors with
                                    customers, helping small businesses
                                    grow
                                    and communities savor every bite.
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
                                            alt="Delicious Shrimps">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-5 col-lg-6 order-1 order-lg-2">
                        <div class="card login-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">
                                    <i class="ri-login-circle-line me-2 text-primary"></i>
                                    Sign in to your account
                                </h5>
                                <p class="mb-0 text-muted small mt-1">Welcome back! Please
                                    enter your details.</p>
                            </div>

                            <div class="card-body p-4">
                                <form method="POST" action="{{ route('login') }}">
                                    @csrf

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

                                    <div class="mb-3 form-password-toggle"> {{-- Added form-password-toggle --}}
                                        <label for="password" class="form-label fw-medium">Password</label>
                                        <div class="input-group input-group-merge">
                                            {{-- Changed to input-group-merge for border fix --}}
                                            <span class="input-group-text">
                                                <i class="ri-lock-2-line ri-18px"></i>
                                            </span>
                                            <input type="password" class="form-control" id="password" name="password"
                                                placeholder="Enter your password" required>
                                            <span class="input-group-text cursor-pointer" onclick="togglePassword(event)">
                                                <i class="ri-eye-off-line ri-18px" id="togglePasswordIcon"></i>
                                            </span>
                                        </div>
                                    </div>

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

                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="ri-login-circle-line me-2"></i>Sign In
                                        </button>
                                    </div>

                                    <div class="divider">
                                        <span>or</span>
                                    </div>

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


    <script>
        function togglePassword(event) {
            // Prevent the default click behavior which can cause the input to lose focus ("lag")
            if (event) event.preventDefault();

            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');

            const isPassword = passwordInput.type === 'password';

            passwordInput.type = isPassword ? 'text' : 'password';

            toggleIcon.classList.toggle('ri-eye-off-line');
            toggleIcon.classList.toggle('ri-eye-line');

            // Re-focus the input to immediately allow typing after the toggle
            passwordInput.focus();
        }

        // Auto-hide alert after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    // The 300ms matches a standard CSS transition time for a smooth fade-out
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            }
        });
    </script>

@endsection
