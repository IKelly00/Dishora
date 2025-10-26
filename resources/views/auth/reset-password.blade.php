@extends('layouts.commonMaster')

@section('title', 'Reset Password')

@section('layoutContent')
    <div class="container py-5 d-flex justify-content-center align-items-center min-vh-100">
        <div class="card p-4 shadow-sm" style="max-width: 600px; width: 100%;">
            <h4 class="mb-3">Reset Your Password</h4>

            @if (session('status'))
                <div class="alert alert-success mb-3">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf

                <input type="hidden" name="token" value="{{ request()->route('token') }}">

                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                        name="email" value="{{ old('email') }}" required autofocus>
                    @error('email')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <!-- New Password -->
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text">
                            <i class="ri-lock-2-line ri-20px"></i>
                        </span>
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                            name="password" required>
                        <span class="input-group-text" onclick="togglePassword('password', 'togglePasswordIcon')"
                            style="cursor: pointer;">
                            <i class="ri-eye-off-line" id="togglePasswordIcon"></i>
                        </span>
                    </div>
                    <small id="password-feedback" class="text-danger d-none">
                        Password must be at least 8 characters long.
                    </small>
                    @error('password')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text">
                            <i class="ri-lock-2-line ri-20px"></i>
                        </span>
                        <input id="password_confirmation" type="password" class="form-control" name="password_confirmation"
                            required>
                        <span class="input-group-text"
                            onclick="togglePassword('password_confirmation', 'toggleConfirmIcon')" style="cursor: pointer;">
                            <i class="ri-eye-off-line" id="toggleConfirmIcon"></i>
                        </span>
                    </div>
                    <small id="confirm-feedback" class="text-danger d-none">
                        Passwords do not match.
                    </small>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('password_confirmation');
            const passwordFeedback = document.getElementById('password-feedback');
            const confirmFeedback = document.getElementById('confirm-feedback');

            function checkPasswordLength() {
                if (passwordInput.value.length < 8) {
                    passwordFeedback.classList.remove('d-none');
                } else {
                    passwordFeedback.classList.add('d-none');
                }
            }

            function checkPasswordMatch() {
                if (confirmInput.value && passwordInput.value !== confirmInput.value) {
                    confirmFeedback.classList.remove('d-none');
                } else {
                    confirmFeedback.classList.add('d-none');
                }
            }

            passwordInput.addEventListener('input', () => {
                checkPasswordLength();
                checkPasswordMatch();
            });

            confirmInput.addEventListener('input', checkPasswordMatch);

            window.togglePassword = function(inputId, iconId) {
                const input = document.getElementById(inputId);
                const icon = document.getElementById(iconId);

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('ri-eye-off-line');
                    icon.classList.add('ri-eye-line');
                } else {
                    input.type = 'password';
                    icon.classList.remove('ri-eye-line');
                    icon.classList.add('ri-eye-off-line');
                }
            }
        });
    </script>
@endsection
