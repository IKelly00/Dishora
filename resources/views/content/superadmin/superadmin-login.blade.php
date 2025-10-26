@php
    $configData = [
        'mainLayoutType' => 'vertical',
        'theme' => 'light',
        'blankPage' => true,
        'bodyClass' => '',
        'lang' => 'en',
    ];
    $isNavbar = false;
    $height = $height ?? 25;
@endphp

@extends('layouts/commonMaster')

@section('title', 'Super Admin Login')

@section('page-style')
    {{-- REQUIRED FOR PASSWORD ICON: Add Remix Icon CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        /* BASE STYLES & CENTERING */
        .authentication-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #fef7ed;
            /* Light background */
        }

        .authentication-inner {
            align-items: center;
        }

        /* 👑 FORM EMPHASIS */
        .admin-login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            width: 100%;
        }

        .admin-login-card .app-brand {
            margin-bottom: 2rem !important;
        }

        /* INPUT STYLES (Copied from Non-Admin Login) */
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            background: white;
            border-color: #ced4da;
        }

        /* INPUT GROUP STYLES (For the password icon border/background) */
        .input-group-text {
            background: #fafbfc;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            color: #6b7280;
        }

        /* CUSTOM BUTTON STYLE */
        .btn-dishora {
            background-color: #e59a4f !important;
            border-color: #e59a4f !important;
            color: #ffffff;
        }

        .btn-dishora:hover {
            background-color: #d18a44 !important;
            border-color: #d18a44 !important;
        }
    </style>
@endsection

@section('layoutContent')
    <div class="authentication-wrapper authentication-cover">
        <div class="authentication-inner row m-0 justify-content-center w-100">

            <div class="d-flex col-12 col-sm-10 col-md-6 col-lg-4 align-items-center justify-content-center p-sm-5 p-4">

                <div class="admin-login-card mx-auto">

                    {{-- App Branding --}}
                    <div class="app-brand mb-5 d-flex justify-content-center">
                        <a href="{{ url('/') }}" class="app-brand-link gap-2 align-items-center">
                            <span class="app-brand-logo demo">
                                <svg width="25" viewBox="0 0 25 40" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                    <path fill="#696cff"
                                        d="M11.609 0H4.46c-.636 0-1.07.288-1.07.72V39.2c0 .432.434.72 1.07.72h7.149c.636 0 1.07-.288 1.07-.72V.72c0-.432-.434-.72-1.07-.72zM20.254 13.916c.433-.288.755-.83.755-1.584 0-.756-.322-1.296-.755-1.584-.322-.216-.756-.432-1.078-.432-1.397 0-2.484 1.152-2.484 2.808s1.087 2.808 2.484 2.808c.322 0 .756-.216 1.078-.432zM15.82 23.04c-.322-.216-.756-.432-1.078-.432-1.397 0-2.484 1.152-2.484 2.808s1.087 2.808 2.484 2.808c.322 0 .756-.216 1.078-.432.433-.288.755-.83.755-1.584 0-.756-.322-1.296-.755-1.584z" />
                                    <path fill="#696cff"
                                        d="M13.673 24.516c0-.432-.434-.72-1.07-.72H4.46c-.636 0-1.07.288-1.07.72V39.2c0 .432.434.72 1.07.72h7.149c.636 0 1.07-.288 1.07-.72V24.516z" />
                                </svg>
                            </span>
                            <span class="app-brand-text demo text-body fw-bolder">Dishora</span>
                        </a>
                    </div>

                    <h4 class="mb-2">Super Admin Panel 👑</h4>
                    <p class="mb-4">Please sign-in to your admin account</p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form id="formAuthentication" class="mb-3" action="{{ route('super-admin.login') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                placeholder="Enter your email" autofocus required>
                        </div>
                        <div class="mb-3 form-password-toggle">
                            <div class="d-flex justify-content-between">
                                <label class="form-label" for="password">Password</label>
                            </div>
                            <div class="input-group input-group-merge">
                                <input type="password" id="password" class="form-control" name="password"
                                    placeholder="············" aria-describedby="password" required />

                                <span class="input-group-text cursor-pointer" onclick="togglePassword()"
                                    style="cursor: pointer;">
                                    <i class="ri-eye-off-line" id="togglePasswordIcon"></i>
                                </span>
                            </div>
                        </div>

                        <button class="btn btn-dishora d-grid w-100 mt-4">
                            Sign in
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page-script')
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');

            // 1. Check if both elements exist before proceeding
            if (!passwordInput || !toggleIcon) {
                console.error("Password input or toggle icon not found.");
                return;
            }

            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';

            // Toggle the Remix Icon classes
            toggleIcon.classList.toggle('ri-eye-off-line');
            toggleIcon.classList.toggle('ri-eye-line');
        }
    </script>
@endpush
