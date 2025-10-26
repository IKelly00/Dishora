@extends('layouts.commonMaster')

@section('title', 'Forgot Password')

@section('layoutContent')
    <div class="container py-5 d-flex justify-content-center align-items-center min-vh-100">
        <div class="card p-4 shadow-sm w-100" style="max-width: 500px;">
            <h4 class="mb-3">Forgot Your Password?</h4>

            <p class="text-muted mb-4">
                Enter your registered email address and we'll send you a link to reset your password.
            </p>

            @if (session('status'))
                <div class="alert alert-success mb-3">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <!-- Email Address -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                        name="email" value="{{ old('email') }}" required autofocus>
                    @error('email')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <!-- Submit Button -->
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        Send Reset Link
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
