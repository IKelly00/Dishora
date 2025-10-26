@extends('layouts.commonMaster')

@section('title', 'Verification link expired')

@section('layoutContent')
    <div class="container py-5">
        <div class="card p-4 shadow-sm">
            <h4>Verification link invalid or expired</h4>
            <p>The verification link appears to be invalid or expired. If you still have access to your account you can
                resend the verification email.</p>

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button class="btn btn-primary">Resend verification email</button>
            </form>
        </div>
    </div>
@endsection
