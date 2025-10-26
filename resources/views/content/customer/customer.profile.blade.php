@extends('layouts/contentNavbarLayout')
@section('title', 'My Profile')

@section('content')
    <div class="container mt-4">
        <h2 class="mb-4">👤 My Profile</h2>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form action="{{ route('customer.profile.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="fullname" class="form-label">Full Name</label>
                    <input type="text" name="fullname" id="fullname" class="form-control"
                        value="{{ old('fullname', $vendor->fullname) }}" required>
                </div>

                <div class="col-md-6">
                    <label for="phone_number" class="form-label">Phone Number</label>
                    <input type="text" name="phone_number" id="phone_number" class="form-control"
                        value="{{ old('phone_number', $vendor->phone_number) }}">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="text" class="form-control" value="{{ $vendor->user->email }}" disabled>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Registration Status</label>
                    <input type="text" class="form-control" value="{{ $vendor->registration_status }}" disabled>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Date Registered</label>
                <input type="text" class="form-control" value="{{ $vendor->created_at->format('F j, Y') }}" disabled>
            </div>

            <button type="submit" class="btn btn-primary">Update Profile</button>
        </form>
    </div>
@endsection
