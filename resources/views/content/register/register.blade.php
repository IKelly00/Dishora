@extends('layouts/commonMaster')

@section('title', 'Register')

@section('layoutContent')
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        .bg {
            background: linear-gradient(135deg, #fef7ed 0%, #fed7aa 50%, #fdba74 100%);
            min-height: 100vh;
        }

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

        .food-plate img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .register-card {
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

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .input-group-text {
            background: #fafbfc;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            color: #6b7280;
        }

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

        .invalid-feedback {
            display: none;
            font-size: 0.875rem;
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
    </style>

    <div class="min-vh-100 d-flex flex-column bg">
        <div class="flex-grow-1 d-flex align-items-center justify-content-center py-4">
            <div class="container-fluid">
                <div class="row align-items-center justify-content-center">

                    <!-- Left Side -->
                    <div class="col-12 col-xl-6 col-lg-6 order-2 order-lg-1">
                        <div class="welcome-section">
                            <div style="position: relative; z-index: 2;">
                                <h2 class="welcome-text">Welcome to Dishora</h2>
                                <p class="poppins-regular mb-3">
                                    At Dishora, we believe every great meal deserves a wider table. We're excited to connect
                                    vendors with customers, helping small businesses grow and communities savor every bite.
                                </p>
                                <p class="primary-color mb-4">
                                    <i class="ri-restaurant-line me-2"></i>Register today!
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

                    <!-- Register Form -->
                    <div class="col-12 col-xl-5 col-lg-6 order-1 order-lg-2">
                        <div class="card register-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">
                                    <i class="ri-user-add-line me-2 text-primary"></i>
                                    Create your account
                                </h5>
                                <p class="mb-0 text-muted small mt-1">Join Dishora today — it’s fast and easy!</p>
                            </div>

                            <div class="card-body p-4">
                                <form id="registerForm" method="POST" action="{{ route('register') }}">
                                    @csrf

                                    <div class="mb-3">
                                        <label for="fullname" class="form-label fw-medium">Full Name</label>
                                        <input type="text" class="form-control" id="fullname" name="fullname" required
                                            placeholder="Juan Dela Cruz">
                                    </div>

                                    <div class="mb-3">
                                        <label for="username" class="form-label fw-medium">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" required
                                            placeholder="juandelacruz">
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-medium">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" required
                                            placeholder="juan@example.com">
                                    </div>

                                    <div class="mb-3">
                                        <label for="password" class="form-label fw-medium">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-lock-2-line ri-18px"></i></span>
                                            <input type="password" class="form-control" id="password" name="password"
                                                placeholder="Enter your password" required>
                                            <span class="input-group-text" onclick="togglePassword('password','icon1')"
                                                style="cursor: pointer;">
                                                <i class="ri-eye-off-line ri-18px" id="icon1"></i>
                                            </span>
                                        </div>
                                        <div id="password-feedback" class="invalid-feedback text-danger mt-1">
                                            Password must be at least 8 characters long.
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="password_confirmation" class="form-label fw-medium">Confirm
                                            Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-lock-2-line ri-18px"></i></span>
                                            <input type="password" class="form-control" id="password_confirmation"
                                                name="password_confirmation" placeholder="Repeat your password" required>
                                            <span class="input-group-text"
                                                onclick="togglePassword('password_confirmation','icon2')"
                                                style="cursor: pointer;">
                                                <i class="ri-eye-off-line ri-18px" id="icon2"></i>
                                            </span>
                                        </div>
                                        <div id="confirm-feedback" class="invalid-feedback text-danger mt-1">
                                            Passwords do not match.
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 mt-3">
                                        <i class="ri-user-add-line me-2"></i>Register
                                    </button>

                                    <div class="divider"><span>or</span></div>

                                    <div class="text-center">
                                        <p class="mb-0 text-muted">
                                            Already have an account?
                                            <a href="{{ route('login') }}"
                                                class="text-primary text-decoration-none fw-medium">Sign in here</a>
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
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('password_confirmation');
            const passwordFeedback = document.getElementById('password-feedback');
            const confirmFeedback = document.getElementById('confirm-feedback');
            const form = document.getElementById('registerForm');

            function checkPasswordLength() {
                passwordFeedback.style.display = password.value.length < 8 ? 'block' : 'none';
            }

            function checkPasswordMatch() {
                confirmFeedback.style.display = (confirmPassword.value && password.value !== confirmPassword
                    .value) ? 'block' : 'none';
            }

            password.addEventListener('input', () => {
                checkPasswordLength();
                checkPasswordMatch();
            });

            confirmPassword.addEventListener('input', checkPasswordMatch);

            form.addEventListener('submit', function(e) {
                if (password.value.length < 8 || password.value !== confirmPassword.value) {
                    e.preventDefault();
                    checkPasswordLength();
                    checkPasswordMatch();
                }
            });

            window.togglePassword = function(inputId, iconId) {
                const input = document.getElementById(inputId);
                const icon = document.getElementById(iconId);
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                icon.classList.toggle('ri-eye-off-line');
                icon.classList.toggle('ri-eye-line');
            };
        });
    </script>
@endsection
