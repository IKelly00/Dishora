@extends('layouts/commonMaster')

@section('title', 'No Account Found')

@section('layoutContent')
    <div class="container py-5 text-center">
        <div class="card mx-auto shadow-lg rounded-4" style="max-width: 500px;">
            <div class="card-body p-4">
                <h3 class="mb-3 text-danger">No Account Found</h3>
                <p class="text-muted mb-4">
                    Your email has been verified, but we couldn't find a linked account in our system.
                </p>
                <p class="mb-4">
                    Please try logging in manually to complete your setup.
                </p>
                <a href="{{ route('login') }}" class="btn btn-primary w-100">Go to Login Page</a>
            </div>
        </div>
    </div>
@endsection
