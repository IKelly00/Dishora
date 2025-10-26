@extends('layouts/commonMaster')
@section('title', 'Edit User')

@section('layoutContent')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="fw-bold mb-0">Edit System User</h4>
            <a href="{{ route('super-admin.users.index') }}" class="btn btn-outline-secondary">
                <i class="bx bx-arrow-back me-1"></i> Back to User List
            </a>
        </div>

        <script>
            // This toastr script is fine where it is
            document.addEventListener('DOMContentLoaded', function() {
                toastr.options = {
                    closeButton: true,
                    progressBar: true,
                    positionClass: 'toast-top-right',
                    timeOut: '7000',
                    extendedTimeOut: '2000'
                };
                @if (session('success'))
                    toastr.success("{{ session('success') }}");
                @endif
                @if (session('error'))
                    toastr.error("{{ session('error') }}");
                @endif
                @if (session('info'))
                    toastr.info("{{ session('info') }}");
                @endif
                @if (session('warning'))
                    toastr.warning("{{ session('warning') }}");
                @endif
            });
        </script>

        <div class="card mb-4 shadow-sm">
            <div class="card-header border-bottom">
                <h5 class="mb-0 fw-bold">User Details</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('super-admin.users.update', $user->user_id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        {{-- Unchanged Fields --}}
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="{{ $user->username }}" readonly
                                disabled>
                            <small class="text-muted">Usernames cannot be changed.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email"
                                value="{{ old('email', $user->email) }}" readonly disabled>
                            <small class="text-muted">Emails cannot be changed.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="fullname" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('fullname') is-invalid @enderror"
                                id="fullname" name="fullname" value="{{ old('fullname', $user->fullname) }}" required>
                            @error('fullname')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="text" class="form-control @error('phone_number') is-invalid @enderror"
                                id="phone_number" name="phone_number" maxlength="11" inputmode="numeric"
                                placeholder="e.g., 09123456789"
                                value="{{ old('phone_number', $user->customer->contact_number ?? null) }}">
                            @error('phone_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="verification_status" class="form-label">Email Verification Status</label>
                            <select id="verification_status" name="verification_status"
                                class="form-select @error('verification_status') is-invalid @enderror">
                                <option value="1"
                                    {{ old('verification_status', $user->email_verified_at ? 1 : 0) == 1 ? 'selected' : '' }}>
                                    Verified
                                </option>
                                <option value="0"
                                    {{ old('verification_status', $user->email_verified_at ? 1 : 0) == 0 ? 'selected' : '' }}>
                                    Not Verified
                                </option>
                            </select>
                            @error('verification_status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Manually verify or un-verify the user's email.</small>
                        </div>

                        {{-- ==== START: MODIFIED PASSWORD FIELD ==== --}}
                        <div class="col-md-6">
                            <label for="password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                    id="password" name="password"
                                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;">
                                <span class="input-group-text cursor-pointer" id="toggle-password">
                                    <i class="bx bx-hide"></i>
                                </span>
                            </div>
                            {{-- This error message is moved to display correctly with the input group --}}
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Leave blank to keep the current password.</small>
                        </div>
                        {{-- ==== END: MODIFIED PASSWORD FIELD ==== --}}

                        {{-- ==== START: MODIFIED CONFIRM PASSWORD FIELD ==== --}}
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password_confirmation"
                                    name="password_confirmation"
                                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;">
                                <span class="input-group-text cursor-pointer" id="toggle-password-confirmation">
                                    <i class="bx bx-hide"></i>
                                </span>
                            </div>
                            {{-- This new div will show the match/mismatch message --}}
                            <div id="password-match-message" class="form-text mt-1"></div>
                        </div>
                        {{-- ==== END: MODIFIED CONFIRM PASSWORD FIELD ==== --}}

                    </div>

                    <div class="pt-4">
                        <button type="submit" class="btn btn-primary me-2">Update User</button>
                        <a href="{{ route('super-admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

{{-- ==== START: MODIFIED SCRIPT SECTION ==== --}}
@push('page-script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // --- Your Existing Phone Number Script ---
            const phoneInput = document.getElementById('phone_number');
            if (phoneInput) {
                phoneInput.addEventListener('input', () => {
                    phoneInput.value = phoneInput.value.replace(/[^0-9]/g, '');
                });
            }

            // --- NEW: Password Match Validation ---
            const newPasswordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('password_confirmation');
            const messageDiv = document.getElementById('password-match-message');

            if (newPasswordInput && confirmPasswordInput && messageDiv) {
                // Function to check if passwords match
                const validateMatch = function() {
                    const pass = newPasswordInput.value;
                    const confirmPass = confirmPasswordInput.value;

                    // If both are empty, hide any messages
                    if (confirmPass.length === 0 && pass.length === 0) {
                        messageDiv.innerText = '';
                        confirmPasswordInput.classList.remove('is-invalid');
                        return;
                    }

                    // If the confirm password field is not empty, start comparing
                    if (confirmPass.length > 0) {
                        if (pass === confirmPass) {
                            messageDiv.innerText = 'Passwords match.';
                            messageDiv.classList.remove('text-danger');
                            messageDiv.classList.add('text-success');
                            confirmPasswordInput.classList.remove('is-invalid');
                        } else {
                            messageDiv.innerText = 'Passwords do not match.';
                            messageDiv.classList.add('text-danger');
                            messageDiv.classList.remove('text-success');
                            confirmPasswordInput.classList.add('is-invalid');
                        }
                    } else {
                        // If confirm is empty, just clear the message
                        messageDiv.innerText = '';
                        confirmPasswordInput.classList.remove('is-invalid');
                    }
                };

                // Add listeners to both inputs to check on every keystroke
                newPasswordInput.addEventListener('input', validateMatch);
                confirmPasswordInput.addEventListener('input', validateMatch);
            }

            // --- NEW: Reusable function for password toggle ---
            const setupPasswordToggle = (inputId, toggleId) => {
                const input = document.getElementById(inputId);
                const toggle = document.getElementById(toggleId);

                if (input && toggle) {
                    toggle.addEventListener('click', function() {
                        const icon = this.querySelector('i');
                        // Toggle the input type
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);

                        // Toggle the icon class
                        icon.classList.toggle('bx-show');
                        icon.classList.toggle('bx-hide');
                    });
                }
            };

            // --- NEW: Apply toggle to both password fields ---
            setupPasswordToggle('password', 'toggle-password');
            setupPasswordToggle('password_confirmation', 'toggle-password-confirmation');

        });
    </script>
@endpush
{{-- ==== END: MODIFIED SCRIPT SECTION ==== --}}
