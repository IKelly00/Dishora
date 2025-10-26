@extends('layouts.commonMaster')

@section('title', 'Verify Email')

@section('layoutContent')
    <div class="min-vh-100 d-flex align-items-center justify-content-center bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card shadow-sm p-4">
                        <div class="card-body">
                            <h4 class="mb-4 text-center">Verify Your Email Address</h4>

                            @if (session('status') === 'verification-link-sent')
                                <div class="alert alert-success" role="alert">
                                    A new verification link has been sent to your email address.
                                </div>
                            @endif

                            <p class="mb-3 text-center">
                                Before continuing, please check your email and click the verification link we just sent you.
                            </p>

                            <p class="text-center mb-4">
                                Didn’t receive the email? Click below to resend:
                            </p>

                            <form method="POST" action="{{ route('verification.send') }}" class="text-center">
                                @csrf
                                <button type="submit" class="btn btn-primary rounded-pill px-4">
                                    Resend Verification Email
                                </button>
                            </form>
                        </div>
                    </div>

                    <p class="text-center mt-3 text-muted" style="font-size: 0.9rem;">
                        If you entered the wrong email, please <a href="{{ route('register') }}">register again</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
