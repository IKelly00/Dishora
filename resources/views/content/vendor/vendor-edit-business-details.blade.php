@extends('layouts/contentNavbarLayout')

@section('title', 'Start Selling')

@section('content')
    {{-- CSS Includes --}}
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.2.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/@geoapify/geocoder-autocomplete@^1/styles/minimal.css">

    {{-- JS Includes (Loaded early for availability) --}}
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <style>
        /* General Styles */
        .main-content-area {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 20px rgba(0, 0, 0, .08);
        }

        .card {
            padding: 1.5rem;
            padding-top: 2rem;
            border-radius: 1rem;
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.05);
        }

        .verification-note {
            border-radius: 10px;
            background: linear-gradient(180deg, #fff8e6 0%, #fff3d6 100%);
            border: 1px solid rgba(224, 164, 32, 0.08);
            color: #7a5a11;
            margin: 0.8rem 0;
        }

        /* Step Indicator */
        .step-indicator {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            color: white;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .step-active {
            background-color: #f4a621;
        }

        .step-inactive {
            background-color: #6c757d;
        }

        /* Form Inputs & Validation */
        .error-message {
            font-size: 0.85rem;
            color: red;
            margin-top: 4px;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .input-group.is-invalid .form-control,
        .input-group.is-invalid .form-select,
        .input-group.is-invalid .input-group-text {
            border: none !important;
            box-shadow: none !important;
        }

        .input-group.is-invalid {
            border: 1px solid #dc3545 !important;
            border-radius: 0.375rem;
            box-shadow: none !important;
        }

        .choices.is-invalid .choices__inner {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, .25) !important;
        }

        .choices+.error-message {
            font-size: 0.85rem;
            color: red;
            position: relative;
            top: -20px;
        }

        /* Choices JS Specific */
        .choices__input.choices__input--cloned {
            min-width: 150px !important;
            width: auto !important;
        }

        .choices__inner {
            border-radius: 0.375rem !important;
            background-color: #ffffff !important;
        }

        .choices__input {
            background-color: #ffffff !important;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(180deg, #f7b955 0%, #f0a93a 100%);
            color: #fff;
            padding: 8px 14px;
            border-radius: 10px;
            border: none;
            font-weight: 700;
            box-shadow: 0 6px 14px rgba(240, 169, 58, 0.12);
        }

        .btn-secondary {
            border-radius: 10px;
        }

        .btn-primary:hover,
        .btn-secondary:hover {
            transform: translateY(-2px);
        }

        /* Payment Details */
        .payment-detail-row {
            border: 1px solid #f0f0f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            background: #fafafa;
        }

        /* Drag and Drop Styling */
        .drop-zone {
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }

        .drop-zone.dragover {
            border-color: #f4a621 !important;
            /* Orange border */
            background-color: #fff8e6 !important;
            /* Light orange background */
        }

        .drop-zone.is-invalid {
            border-color: #dc3545 !important;
            /* Red border for validation error */
            border-width: 2px !important;
        }

        /* Modals */
        .modal-content {
            margin-top: 20vh;
            max-height: 80vh;
            overflow-y: auto;
        }

        /* Other Utilities */
        #fileTypeAlert {
            /* Toastr Alert */
            transition: opacity 0.5s ease;
            opacity: 1;
            visibility: visible;
        }

        #fileTypeAlert.fade-out {
            opacity: 0 !important;
            visibility: hidden !important;
        }

        .drop-zone .file-preview-img {
            max-height: 220px;
            /* increased height for clearer preview */
            width: auto;
            max-width: 100%;
            display: block;
            margin: 0 auto;
            object-fit: contain;
        }

        .drop-zone .file-preview-container {
            width: 100%;
            text-align: center;
        }

        #business_image_preview img {
            width: 320px;
            /* wider preview */
            height: 200px;
            /* taller preview */
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }
    </style>

    <div class="container mb-4">

        {{-- Toastr Setup Script --}}
        <script>
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

        <div class="main-content-area">

            {{-- Step Progress Indicator --}}
            <div class="position-relative mx-auto mb-5" style="max-width: 600px; height: 50px;">
                <div class="position-absolute start-0 w-100 bg-light"
                    style="height: 2px; transform: translateY(-50%); z-index: 1; top: 15px"></div>
                <div id="progress-bar-active" class="position-absolute start-0 bg-warning"
                    style="height: 2px; width: 0%; transform: translateY(-50%); z-index: 2; top: 15px"></div>
                <div class="d-flex justify-content-between align-items-center h-100 position-relative" style="z-index: 3;">
                    <div class="d-flex flex-column align-items-center" style="width: 33%;">
                        <div class="step-indicator step-active" id="step1-indicator">1</div>
                        <div class="small text-muted" style="margin-top: 10px;">Business Information</div>
                    </div>
                    <div class="d-flex flex-column align-items-center" style="width: 33%;">
                        <div class="step-indicator step-inactive" id="step2-indicator">2</div>
                        <div class="small text-muted" style="margin-top: 10px;">Accepted Payment Methods</div>
                    </div>
                    <div class="d-flex flex-column align-items-center" style="width: 33%;">
                        <div class="step-indicator step-inactive" id="step3-indicator">3</div>
                        <div class="small text-muted" style="margin-top: 10px;">Requirements</div>
                    </div>
                </div>
            </div>

            {{-- Verification Note --}}
            <div class="verification-note d-flex gap-3 align-items-start p-3">
                <i class="ri-error-warning-line fs-4 text-warning me-1" style="line-height:1"></i>
                <div>
                    <strong class="d-block mb-1">For a Smooth Verification Process</strong>
                    <div class="small text-muted">To prevent delays, please double-check that the information you provide
                        here aligns exactly with the details on your official uploaded documents, as we will be verifying
                        them.</div>
                </div>
            </div>

            {{-- Main Form --}}
            <form method="POST" action="{{ route('vendor.business.update') }}" enctype="multipart/form-data"
                id="start-selling-form">
                @csrf

                {{-- Toastr File Type Alert (No longer used for validation, but kept for potential future use) --}}
                <div id="fileTypeAlert"
                    class="alert alert-danger position-fixed top-0 start-50 translate-middle-x mt-3 d-none" role="alert"
                    style="min-width: 300px; z-index: 9999;">
                    Invalid file type.
                </div>

                {{-- Server-side Validation Errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Hidden Input for Current Step --}}
                <input type="hidden" name="current_step" id="current_step" value="{{ old('current_step', 'step1') }}">

                {{-- STEP 1: BUSINESS INFORMATION --}}
                <div id="step1" class="card"
                    style="{{ old('current_step', 'step1') === 'step1' ? '' : 'display: none;' }}">
                    <h5 class="mb-4">Business Information</h5>

                    {{-- Owner/Business Basic Info --}}
                    <div class="row px-6 mb-4">
                        <div class="col-md-6 mb-4">
                            <label for="fullname" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="ri-user-line ri-20px"></i></span>
                                <input type="text" class="form-control @error('fullname') is-invalid @enderror"
                                    id="fullname" name="fullname" placeholder="Enter your full name"
                                    value="{{ old('fullname', $vendor->fullname ?? '') }}" required>
                            </div>
                            @error('fullname')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="phone_number" class="form-label">Phone Number <span
                                    class="text-danger">*</span></label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="ri-phone-line ri-20px"></i></span>
                                <input type="text" class="form-control @error('phone_number') is-invalid @enderror"
                                    id="phone_number" name="phone_number" maxlength="11" inputmode="numeric"
                                    placeholder="e.g., 09123456789"
                                    value="{{ old('phone_number', $vendor->phone_number ?? '') }}" required>
                            </div>
                            @error('phone_number')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Business Logo + Info Side by Side --}}
                    <div class="row px-6 align-items-center mb-4">
                        {{-- Logo Upload --}}
                        <div class="col-md-4 mb-4 d-flex flex-column justify-content-center">
                            <div>
                                <label for="business_image" class="form-label">Business Logo / Image (Optional)</label>
                                <div class="border border-dashed rounded mb-2 bg-light d-flex flex-column justify-content-center align-items-center drop-zone @error('business_image') is-invalid @enderror"
                                    style="cursor:pointer; height: 200px;" data-input-id="business_image_input"
                                    data-display-id="business_image_file_name">
                                    <input type="file" name="business_image" class="d-none" id="business_image_input">

                                    {{-- Add style here to hide placeholder if image exists --}}
                                    <div id="business_upload_placeholder" class="text-center"
                                        style="{{ $business->business_image ? 'display:none;' : '' }}">
                                        <i class="ri-image-line" style="font-size: 1.8rem;"></i><br>
                                        <span class="text-muted">Drag & Drop or <span class="text-primary">Choose
                                                image</span> to upload</span><br>
                                        <small class="text-muted">Any file type</small>
                                    </div>

                                    {{-- This div already correctly shows only if image exists --}}
                                    <div id="business_image_preview"
                                        style="{{ $business->business_image ? 'position: relative; z-index: 1;' : 'display:none;' }}">
                                        {{-- Note: Removed the extra semicolon here too --}}
                                        <img src="{{ $business->business_image ?? '#' }}" alt="Business Image Preview"
                                            class="rounded" {{-- Add display: block --}}
                                            style="display: block; width:250px; height:150px; object-fit:cover;">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">File Uploaded</label>
                                    <input type="text" class="form-control form-control-sm"
                                        id="business_image_file_name"
                                        value="{{ $business->business_image ? basename($business->business_image) : 'None' }}"
                                        readonly>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-sm btn-secondary cancel-btn me-1"
                                        data-input="business_image_input"
                                        data-display="business_image_file_name">Remove</button>
                                    <button type="button" class="btn btn-sm btn-primary import-btn"
                                        data-input="business_image_input">Upload</button>
                                </div>
                                @error('business_image')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>



                        {{-- Business Name, Type, Description --}}
                        <div class="col-md-8 mb-4 d-flex flex-column justify-content-center">
                            <div class="row mb-4">
                                <label for="business_name" class="form-label">Business Name <span
                                        class="text-danger">*</span></label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="ri-store-line ri-20px"></i></span>
                                    <input type="text"
                                        class="form-control @error('business_name') is-invalid @enderror"
                                        id="business_name" name="business_name" placeholder="Enter your business name"
                                        value="{{ $business->business_name ? basename($business->business_name) : 'None' }}"
                                        required>
                                </div>
                                @error('business_name')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="row mb-4">
                                <label for="business_type" class="form-label">Business Type <span
                                        class="text-danger">*</span></label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="ri-briefcase-line ri-20px"></i></span>
                                    <input type="text"
                                        class="form-control @error('business_type') is-invalid @enderror"
                                        id="business_type" name="business_type"
                                        placeholder="e.g. Restaurant, Grocery, etc."
                                        value="{{ $business->business_type ? basename($business->business_type) : 'None' }}"
                                        required>
                                </div>
                                @error('business_type')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="row">
                                <label for="business_description" class="form-label">Business Description <span
                                        class="text-danger">*</span></label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="ri-information-line ri-20px"></i></span>
                                    <textarea class="form-control @error('business_description') is-invalid @enderror" id="business_description"
                                        name="business_description" placeholder="Enter your business description" rows="3" required>{{ $business->business_description ? basename($business->business_description) : 'None' }}</textarea>
                                </div>
                                @error('business_description')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Business Location --}}
                    <div class="mt-4 px-6">
                        <h6 class="mb-3">Business Location <span class="text-danger">*</span></h6>
                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Region</label>
                                {{-- MODIFIED: name="region_display", added disabled, added hidden input --}}
                                <select class="form-select @error('region') is-invalid @enderror" name="region_display"
                                    id="region_select" required disabled></select>
                                <input type="hidden" name="region" id="region_hidden">
                                @error('region')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Province</label>
                                {{-- MODIFIED: name="province_display", added disabled, added hidden input --}}
                                <select class="form-select @error('province') is-invalid @enderror"
                                    name="province_display" id="province_select" required disabled></select>
                                <input type="hidden" name="province" id="province_hidden">
                                @error('province')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City / Municipality</label>
                                {{-- MODIFIED: name="city_display", added disabled, added hidden input --}}
                                <select class="form-select @error('city') is-invalid @enderror" name="city_display"
                                    id="city_select" required disabled></select>
                                <input type="hidden" name="city" id="city_hidden">
                                @error('city')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Barangay</label>
                                <select class="form-select @error('barangay') is-invalid @enderror" name="barangay"
                                    id="barangay_select" required></select>
                                @error('barangay')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-9 mb-3">
                                <label class="form-label">Street Name, Building, House No.</label>
                                <input type="text" class="form-control @error('street') is-invalid @enderror"
                                    name="street" id="street_name" value="{{ old('street', $business->street ?? '') }}"
                                    required> @error('street')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control @error('postal_code') is-invalid @enderror"
                                    name="postal_code" id="postal_code" readonly placeholder="Auto-populated"
                                    value="{{ old('postal_code', $business->postal_code ?? '') }}" required>
                                @error('postal_code')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        {{-- Hidden Lat/Lng Inputs --}}
                        <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}">
                        <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}">
                        @error('latitude')
                            <div class="error-message">Location could not be determined. Please check the address.</div>
                        @enderror
                    </div>

                    {{-- Opening Hours --}}
                    <div class="mt-4 px-6">
                        <h6 class="mb-3">Opening Hours <span class="text-danger">*</span></h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle text-center mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Day</th>
                                        <th>Open</th>
                                        <th>Close</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'] as $short => $day)
                                        @php
                                            // Get the data for the current day from the collection passed by the controller
                                            $hour = $openingHoursByDay->get($day); // Use the correct variable name

                                            // Set defaults based on the database data or null if not found
                                            $db_status = 'open'; // Default to open if no record exists for the day
                                            $db_open_time = '';
                                            $db_close_time = '';

                                            if ($hour) {
                                                // If a record exists for this day
                                                $db_status = $hour->is_closed ? 'closed' : 'open';
                                                // Format time as H:i (e.g., 09:00) only if it's not closed and time exists
    if (!$hour->is_closed) {
        $db_open_time = $hour->opens_at
            ? \Carbon\Carbon::parse($hour->opens_at)->format('H:i')
            : '';
        $db_close_time = $hour->closes_at
            ? \Carbon\Carbon::parse($hour->closes_at)->format('H:i')
            : '';
                                                }
                                            }
                                        @endphp
                                        <tr>
                                            <td>{{ $short }}</td>
                                            <td>
                                                {{-- Use old() first, then fall back to the DB value ($db_open_time) --}}
                                                <input type="time"
                                                    class="form-control form-control-sm @error("open_time.$day") is-invalid @enderror"
                                                    name="open_time[{{ $day }}]"
                                                    value="{{ old("open_time.$day", $db_open_time) }}">
                                                @error("open_time.$day")
                                                    <div class="error-message">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td>
                                                {{-- Use old() first, then fall back to the DB value ($db_close_time) --}}
                                                <input type="time"
                                                    class="form-control form-control-sm @error("close_time.$day") is-invalid @enderror"
                                                    name="close_time[{{ $day }}]"
                                                    value="{{ old("close_time.$day", $db_close_time) }}">
                                                @error("close_time.$day")
                                                    <div class="error-message">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td>
                                                <select
                                                    class="form-select form-select-sm @error("status.$day") is-invalid @enderror"
                                                    name="status[{{ $day }}]">
                                                    {{-- Use old() first, then fall back to the DB value ($db_status) --}}
                                                    <option value="open"
                                                        {{ old("status.$day", $db_status) === 'open' ? 'selected' : '' }}>
                                                        Open
                                                    </option>
                                                    <option value="closed"
                                                        {{ old("status.$day", $db_status) === 'closed' ? 'selected' : '' }}>
                                                        Closed
                                                    </option>
                                                </select>
                                                @error("status.$day")
                                                    <div class="error-message">{{ $message }}</div>
                                                @enderror
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @error('opening_hours')
                                <div class="error-message mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Next Button --}}
                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-primary rounded-pill px-4"
                            onclick="goToStep2()">Next</button>
                    </div>
                </div>

                {{-- STEP 2: ACCEPTED PAYMENT METHODS --}}
                <div id="step2" class="card"
                    style="{{ old('current_step') === 'step2' ? '' : 'display: none;' }}">
                    <h5 class="mb-4">Accepted Payment Methods</h5>
                    <div class="form-group mb-4 px-6">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="payment_methods" class="form-label mb-0">Accepted Payment Methods <span
                                    class="text-danger">*</span></label>
                            <div class="d-flex align-items-center gap-2 text-muted" style="font-size: 0.85rem;">
                                <i class="ri-information-line"></i><span>Online payment is required for pre-orders.</span>
                            </div>
                        </div>
                        <select id="payment_methods" name="payment_methods[]"
                            class="form-select @error('payment_methods') is-invalid @enderror" multiple>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->payment_method_id }}"
                                    {{ collect(old('payment_methods', $selectedMethods ?? []))->contains($method->payment_method_id) ? 'selected' : '' }}>
                                    {{ $method->method_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('payment_methods')
                            <div class="error-message">{{ $message }}</div>
                        @enderror

                        <div id="payment_details_container" class="mt-3">
                            {{-- Render existing details if validation failed --}}
                            @if (old('payment_methods'))
                                @foreach (old('payment_methods') as $pmid)
                                    @php
                                        // ... (PHP logic to get method, accNum, accName from old data) ...
                                        $method = $paymentMethods->firstWhere('payment_method_id', $pmid);
                                        $accNum = old('account_number.' . $pmid, '');
                                        $accName = old('account_name.' . $pmid, '');
                                        $isCod =
                                            $method &&
                                            (str_contains(strtolower($method->method_name), 'cash') ||
                                                str_contains(strtolower($method->method_name), 'cod'));
                                    @endphp
                                    @if ($method && !$isCod)
                                        <div class="payment-detail-row" data-method-id="{{ $pmid }}">
                                            {{-- ... (HTML for displaying fields based on OLD data) ... --}}
                                            <input type="text"
                                                class="form-control @error("account_number.$pmid") is-invalid @enderror"
                                                name="account_number[{{ $pmid }}]" value="{{ $accNum }}"
                                                required>
                                            {{-- ... --}}
                                            <input type="text"
                                                class="form-control @error("account_name.$pmid") is-invalid @enderror"
                                                name="account_name[{{ $pmid }}]" value="{{ $accName }}"
                                                required>
                                            {{-- ... --}}
                                        </div>
                                    @endif
                                @endforeach

                                {{-- **** THIS IS THE IMPORTANT PART FOR INITIAL LOAD **** --}}
                                {{-- Display details from the database if there's no old input --}}
                            @elseif (isset($selectedMethodDetails))
                                @foreach ($selectedMethodDetails as $pmid => $details)
                                    @php
                                        // Find the corresponding payment method name
                                        $method = $paymentMethods->firstWhere('payment_method_id', $pmid);
                                        // Check if method exists and has details (details will be null if it's COD or not found)
                                    @endphp

                                    {{-- Only show rows for methods that have details (i.e., not COD and were found) --}}
                                    @if ($method && $details)
                                        <div class="payment-detail-row" data-method-id="{{ $pmid }}">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong>{{ $method->method_name }}</strong>
                                                <button type="button" class="btn btn-sm btn-danger remove-payment-detail"
                                                    onclick="safeDeselectPayment('{{ $pmid }}')">Remove</button>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label class="form-label">Account Number <span
                                                            class="text-danger">*</span></label>
                                                    {{-- Make sure value points to the correct property from the $details object --}}
                                                    <input type="text"
                                                        class="form-control @error("account_number.$pmid") is-invalid @enderror"
                                                        name="account_number[{{ $pmid }}]"
                                                        value="{{ $details->account_number ?? '' }}" required>
                                                    {{-- Access property --}}
                                                    @error("account_number.$pmid")
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Account Name <span
                                                            class="text-danger">*</span></label>
                                                    {{-- Make sure value points to the correct property from the $details object --}}
                                                    <input type="text"
                                                        class="form-control @error("account_name.$pmid") is-invalid @enderror"
                                                        name="account_name[{{ $pmid }}]"
                                                        value="{{ $details->account_name ?? '' }}" required>
                                                    {{-- Access property --}}
                                                    @error("account_name.$pmid")
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    @endif {{-- End if $method && $details --}}
                                @endforeach {{-- End foreach $selectedMethodDetails --}}
                            @endif {{-- End if old('payment_methods') / elseif isset($selectedMethodDetails) --}}
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary rounded-pill px-4"
                            onclick="goToStep1()">Back</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4"
                            onclick="goToStep3()">Next</button>
                    </div>
                </div>

                {{-- STEP 3: REQUIREMENTS --}}
                <div id="step3" class="card"
                    style="{{ old('current_step') === 'step3' ? '' : 'display: none;' }}">
                    <h5 class="mb-4">Document Requirements</h5>
                    <div class="row">
                        {{-- BIR Registration --}}
                        <div class="col-md-6 mb-4">
                            <div class="border rounded p-3 h-100">
                                <input type="hidden" name="business_duration" id="business_duration"
                                    value="{{ old('business_duration') }}">
                                <label class="form-label fw-bold">BIR Registration Upload <span
                                        class="text-danger">*</span></label>
                                <div class="border border-dashed p-4 text-center mb-2 bg-light drop-zone @error('bir_registration') is-invalid @enderror @error('business_duration') is-invalid @enderror"
                                    style="cursor:pointer;" data-input-id="bir_registration_input"
                                    data-display-id="bir_file_name">
                                    <input type="file" name="bir_registration" class="d-none"
                                        id="bir_registration_input"
                                        value="{{ $business->bir_reg_file ? basename($business->bir_reg_file) : 'None' }}">

                                    {{-- Always render placeholder, hide with style if file exists --}}
                                    <div class="upload-placeholder"
                                        style="{{ $business->bir_reg_file ? 'display: none;' : '' }}">
                                        <i class="ri-upload-cloud-2-line" style="font-size: 1.8rem;"></i><br>
                                        <span class="text-muted">Drag & Drop or
                                            <span class="text-primary">Choose file</span> to upload</span><br>
                                        <small class="text-muted">Any file type</small>
                                    </div>
                                    {{-- Preview container style remains the same --}}
                                    <div class="file-preview-container"
                                        style="{{ $business->bir_reg_file ? '' : 'display: none;' }}">
                                        <img src="{{ $business->bir_reg_file ?? '#' }}" alt="BIR Document Preview"
                                            class="img-fluid rounded mb-2 file-preview-img"
                                            style="max-height: 100px; display: block;">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">File Uploaded</label>
                                    <input type="text" class="form-control form-control-sm" id="bir_file_name"
                                        value="{{ $business->bir_reg_file ? basename($business->bir_reg_file) : 'None' }}"
                                        readonly>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <div><small class="text-muted"><i class="ri-information-line"></i> <a href="#"
                                                onclick="showBusinessDurationModal(event)">Doesn’t have BIR?</a></small>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-secondary cancel-btn"
                                            data-input="bir_registration_input"
                                            data-display="bir_file_name">Cancel</button>
                                        <button type="button" class="btn btn-sm btn-primary import-btn"
                                            data-input="bir_registration_input">Upload</button>
                                    </div>
                                </div>
                                @error('bir_registration')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                                @error('business_duration')
                                    <div class="error-message">BIR file or Business Duration is required.</div>
                                @enderror {{-- Combined error message --}}
                            </div>
                        </div>

                        {{-- Valid ID --}}
                        <div class="col-md-6 mb-4">
                            <div class="border rounded p-3 h-100">
                                <label class="form-label fw-bold">Valid ID Upload <span
                                        class="text-danger">*</span></label>
                                <div class="border border-dashed p-4 text-center mb-2 bg-light drop-zone @error('valid_id') is-invalid @enderror @error('valid_id_type') is-invalid @enderror"
                                    style="cursor:pointer;" data-input-id="valid_id_input"
                                    data-display-id="valid_id_file_name" id="valid_id_upload_area">
                                    <input type="file" name="valid_id" class="d-none" id="valid_id_input"
                                        value="{{ $business->valid_id_file ? basename($business->valid_id_file) : 'None' }}">
                                    <input type="hidden" name="valid_id_type" id="valid_id_type_hidden"
                                        value="{{ old('valid_id_type', $business->valid_id_type ?? '') }}">

                                    {{-- Always render placeholder, hide with style if file exists --}}
                                    <div class="upload-placeholder"
                                        style="{{ $business->valid_id_file ? 'display: none;' : '' }}">
                                        <i class="ri-upload-cloud-2-line" style="font-size: 1.8rem;"></i><br>
                                        <span class="text-muted">Drag & Drop or
                                            <span class="text-primary">Choose file</span> to upload</span><br>
                                        <small class="text-muted">Any file type</small>
                                    </div>
                                    {{-- Preview container style remains the same --}}
                                    <div class="file-preview-container"
                                        style="{{ $business->valid_id_file ? '' : 'display: none;' }}">
                                        <img src="{{ $business->valid_id_file ?? '#' }}" alt="Valid ID Preview"
                                            class="img-fluid rounded mb-2 file-preview-img"
                                            style="max-height: 100px; display: block;">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">File Uploaded</label>
                                    <input type="text" class="form-control form-control-sm" id="valid_id_file_name"
                                        value="{{ $business->valid_id_file ? basename($business->valid_id_file) : 'None' }}"
                                        readonly>
                                    <label class="form-label small mt-1">Selected ID Type</label>
                                    <input type="text" class="form-control form-control-sm" id="valid_id_type_display"
                                        value="{{ old('valid_id_type', $business->valid_id_type ?? '') }}" readonly
                                        placeholder="None">
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-sm btn-secondary cancel-btn me-1"
                                        data-input="valid_id_input" data-display="valid_id_file_name">Cancel</button>
                                    <button type="button" class="btn btn-sm btn-primary import-btn"
                                        data-input="valid_id_input" id="valid_id_import_btn">Upload</button>
                                </div>
                                @error('valid_id')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                                @error('valid_id_type')
                                    <div class="error-message">Valid ID Type is required. Click 'Upload' to select type.</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Business Permit --}}
                        <div class="col-md-6 mb-4">
                            <div class="border rounded p-3 h-100">
                                <label class="form-label fw-bold">Business Permit <span
                                        class="text-danger">*</span></label>
                                <div class="border border-dashed p-4 text-center mb-2 bg-light drop-zone @error('business_permit') is-invalid @enderror"
                                    style="cursor:pointer;" data-input-id="business_permit_input"
                                    data-display-id="business_permit_file_name">
                                    <input type="file" name="business_permit" class="d-none"
                                        id="business_permit_input"
                                        value="{{ $business->business_permit_file ? basename($business->business_permit_file) : 'None' }}">

                                    {{-- Always render placeholder, hide with style if file exists --}}
                                    <div class="upload-placeholder"
                                        style="{{ $business->business_permit_file ? 'display: none;' : '' }}">
                                        <i class="ri-upload-cloud-2-line" style="font-size: 1.8rem;"></i><br>
                                        <span class="text-muted">Drag & Drop or
                                            <span class="text-primary">Choose file</span> to upload</span><br>
                                        <small class="text-muted">Any file type</small>
                                    </div>
                                    {{-- Preview container style remains the same --}}
                                    <div class="file-preview-container"
                                        style="{{ $business->business_permit_file ? '' : 'display: none;' }}">
                                        <img src="{{ $business->business_permit_file ?? '#' }}"
                                            alt="Business Permit Preview" class="img-fluid rounded mb-2 file-preview-img"
                                            style="max-height: 100px; display: block;">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">File Uploaded</label>
                                    <input type="text" class="form-control form-control-sm"
                                        id="business_permit_file_name"
                                        value="{{ $business->business_permit_file ? basename($business->business_permit_file) : 'None' }}"
                                        readonly>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-sm btn-secondary cancel-btn me-1"
                                        data-input="business_permit_input"
                                        data-display="business_permit_file_name">Cancel</button>
                                    <button type="button" class="btn btn-sm btn-primary import-btn"
                                        data-input="business_permit_input">Upload</button>
                                </div>
                                @error('business_permit')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Mayor's Permit --}}
                        <div class="col-md-6 mb-4">
                            <div class="border rounded p-3 h-100">
                                <label class="form-label fw-bold">Mayor's Permit <span
                                        class="text-danger">*</span></label>
                                <div class="border border-dashed p-4 text-center mb-2 bg-light drop-zone @error('mayors_permit') is-invalid @enderror"
                                    style="cursor:pointer;" data-input-id="mayors_permit_input"
                                    data-display-id="mayors_permit_file_name">
                                    <input type="file" name="mayors_permit" class="d-none" id="mayors_permit_input"
                                        value="{{ $business->mayor_permit_file ? basename($business->mayor_permit_file) : 'None' }}">

                                    {{-- Always render placeholder, hide with style if file exists --}}
                                    <div class="upload-placeholder"
                                        style="{{ $business->mayor_permit_file ? 'display: none;' : '' }}">
                                        <i class="ri-upload-cloud-2-line" style="font-size: 1.8rem;"></i><br>
                                        <span class="text-muted">Drag & Drop or
                                            <span class="text-primary">Choose file</span> to upload</span><br>
                                        <small class="text-muted">Any file type</small>
                                    </div>
                                    {{-- Preview container style remains the same --}}
                                    <div class="file-preview-container"
                                        style="{{ $business->mayor_permit_file ? '' : 'display: none;' }}">
                                        <img src="{{ $business->mayor_permit_file ?? '#' }}" alt="Mayor's Permit Preview"
                                            class="img-fluid rounded mb-2 file-preview-img"
                                            style="max-height: 100px; display: block;">
                                    </div>

                                </div>
                                <div class="mb-2">
                                    <label class="form-label">File Uploaded</label>
                                    <input type="text" class="form-control form-control-sm"
                                        id="mayors_permit_file_name"
                                        value="{{ $business->mayor_permit_file ? basename($business->mayor_permit_file) : 'None' }}"
                                        readonly>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-sm btn-secondary cancel-btn me-1"
                                        data-input="mayors_permit_input"
                                        data-display="mayors_permit_file_name">Cancel</button>
                                    <button type="button" class="btn btn-sm btn-primary import-btn"
                                        data-input="mayors_permit_input">Upload</button>
                                </div>
                                @error('mayors_permit')
                                    <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Back/Submit Buttons --}}
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary rounded-pill px-4"
                            onclick="goToStep2()">Back</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4"
                            onclick="prepareSubmit(event)">Submit</button>
                    </div>
                </div>
            </form>

            {{-- Valid ID Type Modal --}}
            <div class="modal fade" id="validIdModal" tabindex="-1" aria-labelledby="validIdModalLabel"
                aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="validIdModalLabel">Select Valid ID Type</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <select class="form-select" id="valid_id_type_select" required> {{-- Changed ID slightly to avoid conflict --}}
                                <option value="" selected disabled>Select ID type</option>
                                <option value="Unified Multi-Purpose Identification (UMID) Card">UMID</option>
                                <option value="Social Security System (SSS) Card">SSS Card</option>
                                <option value="Government Service Insurance System (GSIS) Card">GSIS Card</option>
                                <option value="Driver’s License">Driver’s License</option>
                                <option value="Passport">Passport</option>
                                <option value="PhilHealth ID">PhilHealth ID</option>
                                <option value="Professional Regulation Commission (PRC) ID">PRC ID</option>
                                <option value="National ID">National ID</option>
                            </select>
                            <small class="text-danger mt-2 d-none" id="valid_id_type_error">Please select an ID
                                type.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmValidIdType">Continue</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Business Duration Modal --}}
            <div class="modal fade" id="businessDurationModal" tabindex="-1"
                aria-labelledby="businessDurationModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="businessDurationModalLabel">Please answer to proceed!</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label for="business_duration_modal_select" class="form-label">How long has your business been
                                operating?</label> {{-- Changed ID slightly --}}
                            <select class="form-select" id="business_duration_modal_select"
                                name="business_duration_modal">
                                <option value="" selected disabled>Select duration</option>
                                <option value="Less than 6 months">Less than 6 months</option>
                                <option value="6 months to 1 year">6 months to 1 year</option>
                                <option value="1 to 3 years">1 to 3 years</option>
                                <option value="More than 3 years">More than 3 years</option>
                            </select>
                            <small class="text-danger mt-2 d-none" id="duration_select_error">Please select a
                                duration.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmBusinessDuration">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- End .main-content-area --}}
    </div> {{-- End .container --}}

    @vite(['resources/js/philippine-addresses.js'])

    <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {

                const loadedAddress = {!! json_encode([
                    'street' => $business->street,
                    'barangay' => $business->barangay,
                    'city' => $business->city,
                    'province' => $business->province,
                    'region' => $business->region,
                    'postal_code' => $business->postal_code,
                ]) !!};

                const safeQuery = (id) => document.getElementById(id);
                const qs = (sel) => document.querySelector(sel);

                /* ---------- Utilities ---------- */
                function debounce(fn, ms) {
                    let t;
                    return function(...args) {
                        clearTimeout(t);
                        t = setTimeout(() => fn.apply(this, args), ms);
                    };
                }

                function isValidFileType(file, allowedTypes) {
                    return true; // Always allow
                }

                function removeErrors(selector) {
                    // Remove previous error messages
                    document.querySelectorAll(`${selector} .error-message`).forEach(el => el.remove());
                    // Remove is-invalid class from inputs/selects/drop-zones within the step
                    document.querySelectorAll(`${selector} .is-invalid`).forEach(el => el.classList.remove(
                        'is-invalid'));
                    // Specifically remove from Choices JS wrappers if present
                    document.querySelectorAll(`${selector} .choices.is-invalid`).forEach(el => el.classList
                        .remove('is-invalid'));
                }

                /* ---------- Google Maps (Ensure API Key is loaded correctly) ---------- */
                window.initMap = function() {
                    try {
                        if (window.google && window.google.maps) {
                            window.geocoder = new google.maps.Geocoder();
                            console.info('Google Maps geocoder ready');
                            // Add map initialization logic here if needed, or trigger it after geocoder is ready
                        }
                    } catch (e) {
                        console.warn('initMap error', e);
                    }
                };
                (function loadGoogleMaps() {
                    const existing = Array.from(document.scripts).find(s => (s.src || '').includes(
                        'maps.googleapis.com'));
                    if (existing) {
                        // If script exists but geocoder isn't ready, try calling initMap again
                        if (!window.geocoder && typeof window.initMap === 'function') {
                            window.initMap();
                        }
                        return;
                    }
                    const s = document.createElement('script');
                    // Replace YOUR_API_KEY if needed, or ensure it's loaded securely (e.g., from server config)
                    s.src =
                        'https://maps.googleapis.com/maps/api/js?key=AIzaSyCvpcdeUJTkj9qPV9tZDSIQB184oR8Mwrc&libraries=marker,geocoding&callback=initMap'; // Replace with your key
                    s.async = true;
                    s.defer = true;
                    s.onerror = (err) => console.warn('Failed to load Google Maps script', err);
                    document.head.appendChild(s);
                })();

                async function geocodeAddress() {
                    if (!window.geocoder) {
                        console.warn('Geocoder not ready.');
                        return; // Don't run if geocoder isn't loaded
                    }
                    const addressParts = [
                        safeQuery('street_name')?.value,
                        safeQuery('barangay_select')?.value,
                        safeQuery('city_select')?.value,
                        safeQuery('province_select')?.value,
                        safeQuery('region_select')?.value,
                        'Philippines' // Add country for better accuracy
                    ].filter(Boolean).join(', '); // Filter out empty parts

                    // Only proceed if there's enough address info (e.g., at least region)
                    if (!safeQuery('region_select')?.value) {
                        // Clear coordinates if address is incomplete
                        safeQuery('latitude').value = '';
                        safeQuery('longitude').value = '';
                        return;
                    }

                    const latEl = safeQuery('latitude');
                    const lngEl = safeQuery('longitude');

                    try {
                        const {
                            results
                        } = await window.geocoder.geocode({
                            address: addressParts,
                            componentRestrictions: {
                                country: 'PH'
                            } // Restrict to Philippines
                        });

                        if (results && results[0]) {
                            const location = results[0].geometry.location;
                            if (latEl) latEl.value = location.lat();
                            if (lngEl) lngEl.value = location.lng();
                            console.log('Geocode successful:', location.lat(), location.lng());
                        } else {
                            if (latEl) latEl.value = '';
                            if (lngEl) lngEl.value = '';
                            console.warn('Geocode: No results found for address:', addressParts);
                            // Optionally show a user-friendly message via toastr
                            // toastr.warning('Could not pinpoint the exact location on the map for the address provided.');
                        }
                    } catch (err) {
                        if (latEl) latEl.value = '';
                        if (lngEl) lngEl.value = '';
                        console.error('Geocode failed:', err);
                        // Optionally show a user-friendly error message via toastr
                        // toastr.error('An error occurred while trying to find the location.');
                    }
                }
                const geocodeDebounced = debounce(geocodeAddress, 800); // Increased debounce slightly


                /* ---------- Philippine Addresses Logic ---------- */
                const regionSelect = safeQuery('region_select');
                const regionHidden = safeQuery('region_hidden');
                const provinceSelect = safeQuery('province_select');
                const provinceHidden = safeQuery('province_hidden');
                const citySelect = safeQuery('city_select');
                const cityHidden = safeQuery('city_hidden');
                const barangaySelect = safeQuery('barangay_select');
                const streetInput = safeQuery('street_name');
                const postalCodeInput = safeQuery('postal_code');

                const resetSelect = (el, ph) => {
                    if (!el) return;
                    el.innerHTML = `<option value="">${ph}</option>`;
                    el.disabled = true;
                };
                const populateSelect = (el, data, ph, selectedValue = null) => {
                    if (!el) return;
                    const currentValue = el.value; // Store current value before clearing
                    el.innerHTML = `<option value="">${ph}</option>`;
                    data.forEach(item => {
                        const option = new Option(item, item);
                        // Use provided selectedValue first, then fallback to currentValue if re-populating
                        if (selectedValue === item || (!selectedValue && currentValue === item)) {
                            option.selected = true;
                        }
                        el.add(option);
                    });
                    el.disabled = data.length === 0; // Disable if no options
                    // Restore selected value if it was passed or previously existed
                    if (selectedValue && data.includes(selectedValue)) el.value = selectedValue;
                    else if (currentValue && data.includes(currentValue)) el.value = currentValue;

                };
                try {
                    if (regionSelect && typeof philippineAddresses !== 'undefined') {
                        const regions = Object.keys(philippineAddresses).sort();

                        // --- START: MODIFIED FOR DEFAULTS ---
                        const oldRegion = "{{ old('region') }}";
                        // Default to 'V' if oldRegion is empty
                        const defaultRegion = oldRegion ? oldRegion : 'V';
                        populateSelect(regionSelect, regions, 'Select Region', defaultRegion);
                        if (regionSelect) regionSelect.disabled = true; // Force disable
                        if (regionHidden) regionHidden.value = regionSelect.value; // Set hidden input

                        const oldProvince = "{{ old('province') }}";
                        // Default to 'CAMARINES SUR' if oldProvince is empty
                        const defaultProvince = oldProvince ? oldProvince : 'CAMARINES SUR';

                        const oldCity = "{{ old('city') }}";
                        // Default to 'NAGA CITY' if oldCity is empty
                        const defaultCity = oldCity ? oldCity : 'NAGA CITY';

                        const oldBarangay = "{{ old('barangay') }}";
                        // --- END: MODIFIED FOR DEFAULTS ---


                        function updateProvinces(selectedRegion) {
                            resetSelect(provinceSelect, 'Select Province');
                            resetSelect(citySelect, 'Select City / Municipality');
                            resetSelect(barangaySelect, 'Select Barangay');
                            if (postalCodeInput) postalCodeInput.value = '';
                            if (selectedRegion && philippineAddresses[selectedRegion]) {
                                const provinces = Object.keys(philippineAddresses[selectedRegion].province_list)
                                    .sort();

                                // --- START: MODIFIED FOR DEFAULTS ---
                                // Use oldProvince if it matches the region, OR use defaultProvince if no old data and region matches default ('V')
                                const provinceToSelect = (oldRegion === selectedRegion) ?
                                    oldProvince :
                                    (!oldRegion && selectedRegion === 'V' ? defaultProvince : null);
                                // --- END: MODIFIED FOR DEFAULTS ---

                                populateSelect(provinceSelect, provinces, 'Select Province',
                                    provinceToSelect);
                                if (provinceSelect) provinceSelect.disabled = true; // Force disable
                                if (provinceHidden) provinceHidden.value = provinceSelect
                                    .value; // Set hidden input

                                if (provinceSelect.value) updateCities(selectedRegion, provinceSelect.value);
                            }
                        }

                        function updateCities(selectedRegion, selectedProvince) {
                            resetSelect(citySelect, 'Select City / Municipality');
                            resetSelect(barangaySelect, 'Select Barangay');
                            if (postalCodeInput) postalCodeInput.value = '';
                            if (selectedRegion && selectedProvince && philippineAddresses[selectedRegion]
                                ?.province_list[selectedProvince]) {
                                const cities = Object.keys(philippineAddresses[selectedRegion].province_list[
                                    selectedProvince].municipality_list).sort();

                                // --- START: MODIFIED FOR DEFAULTS ---
                                // Use oldCity if path matches, OR use defaultCity if no old data and path matches default
                                const cityToSelect = (oldRegion === selectedRegion && oldProvince ===
                                        selectedProvince) ?
                                    oldCity :
                                    (!oldRegion && !oldProvince && selectedRegion === 'V' &&
                                        selectedProvince === 'CAMARINES SUR' ? defaultCity : null);
                                // --- END: MODIFIED FOR DEFAULTS ---

                                populateSelect(citySelect, cities, 'Select City / Municipality',
                                    cityToSelect);
                                if (citySelect) citySelect.disabled = true; // Force disable
                                if (cityHidden) cityHidden.value = citySelect.value; // Set hidden input

                                if (citySelect.value) updateBarangaysAndPostal(selectedRegion, selectedProvince,
                                    citySelect.value);
                            }
                        }

                        function updateBarangaysAndPostal(selectedRegion, selectedProvince, selectedCity) {
                            resetSelect(barangaySelect, 'Select Barangay');
                            if (postalCodeInput) postalCodeInput.value = '';
                            if (selectedRegion && selectedProvince && selectedCity && philippineAddresses[
                                    selectedRegion]?.province_list[selectedProvince]?.municipality_list[
                                    selectedCity]) {
                                const cityData = philippineAddresses[selectedRegion].province_list[
                                    selectedProvince].municipality_list[selectedCity];
                                if (cityData.barangay_list) {

                                    // --- START MODIFICATION ---
                                    // Get the old value if it exists, otherwise use the value loaded from the DB
                                    const oldBarangayValue = "{{ old('barangay') }}";
                                    const barangayToSelect = oldBarangayValue ? oldBarangayValue : (
                                        loadedAddress.barangay || null);
                                    // --- END MODIFICATION ---

                                    // Pass barangayToSelect to populateSelect
                                    populateSelect(barangaySelect, cityData.barangay_list.sort(),
                                        'Select Barangay', barangayToSelect
                                    ); // <-- Use the determined value here

                                    // --- NEW --- Make sure barangaySelect is enabled after populating
                                    if (barangaySelect) {
                                        barangaySelect.disabled = false;
                                    }
                                    // --- END NEW ---

                                } else {
                                    // --- NEW --- Disable if no barangays found
                                    if (barangaySelect) {
                                        barangaySelect.disabled = true;
                                    }
                                    // --- END NEW ---
                                }
                                if (postalCodeInput) postalCodeInput.value = cityData.postal_code || '';
                            } else {
                                // --- NEW --- Disable if city data is missing
                                if (barangaySelect) {
                                    barangaySelect.disabled = true;
                                }
                                // --- END NEW ---
                            }
                        }
                        // Initial population based on old input (or defaults)
                        if (regionSelect.value) {
                            updateProvinces(regionSelect.value);
                        }

                        // Event listeners
                        // MODIFIED: Region/Province/City listeners are no longer needed as they are disabled
                        // regionSelect.addEventListener('change', () => { ... });
                        // provinceSelect?.addEventListener('change', () => { ... });
                        // citySelect?.addEventListener('change', () => { ... });

                        // Barangay and Street listeners are still needed
                        barangaySelect?.addEventListener('change', geocodeDebounced);
                        streetInput?.addEventListener('blur', geocodeDebounced);

                    }
                } catch (e) {
                    console.warn('Address wiring error', e);
                }


                /* ---------- Multistep Form Navigation & State ---------- */
                function setInitialStepState() {
                    const currentStep = safeQuery('current_step')?.value || 'step1';
                    safeQuery('step1').style.display = 'none';
                    safeQuery('step2').style.display = 'none';
                    safeQuery('step3').style.display = 'none';
                    qs('#step1-indicator')?.classList.remove('step-active', 'step-inactive');
                    qs('#step2-indicator')?.classList.remove('step-active', 'step-inactive');
                    qs('#step3-indicator')?.classList.remove('step-active', 'step-inactive');

                    if (currentStep === 'step2') {
                        safeQuery('step2').style.display = '';
                        qs('#step1-indicator')?.classList.add('step-active');
                        qs('#step2-indicator')?.classList.add('step-active');
                        qs('#step3-indicator')?.classList.add('step-inactive');
                        qs('#progress-bar-active').style.width = '50%';
                    } else if (currentStep === 'step3') {
                        safeQuery('step3').style.display = '';
                        qs('#step1-indicator')?.classList.add('step-active');
                        qs('#step2-indicator')?.classList.add('step-active');
                        qs('#step3-indicator')?.classList.add('step-active');
                        qs('#progress-bar-active').style.width = '100%';
                    } else { // Default to step 1
                        safeQuery('step1').style.display = '';
                        qs('#step1-indicator')?.classList.add('step-active');
                        qs('#step2-indicator')?.classList.add('step-inactive');
                        qs('#step3-indicator')?.classList.add('step-inactive');
                        qs('#progress-bar-active').style.width = '0%';
                    }
                }
                setInitialStepState(); // Run on load

                window.goToStep1 = function() {
                    setStep('step1');
                };
                window.goToStep2 = async function() {
                    if (validateStep1()) setStep('step2');
                };
                window.goToStep3 = function() {
                    if (validateStep2()) setStep('step3');
                };

                function setStep(stepId) {
                    safeQuery('current_step').value = stepId;
                    setInitialStepState();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }


                /* ---------- Validation Functions ---------- */
                function validateStep1() {
                    removeErrors('#step1');
                    let ok = true;
                    // Check required fields in step 1
                    ['fullname', 'phone_number', 'business_name', 'business_type', 'business_description',
                        'region_hidden', 'province_hidden', 'city_hidden', 'barangay_select', 'street_name',
                        'postal_code'
                    ].forEach(id => {
                        const el = safeQuery(id);
                        if (!el || !String(el.value || '').trim()) {
                            ok = false;
                            // Find the *visible* element to apply the class to
                            let visibleEl = el;
                            if (id.includes('_hidden')) {
                                visibleEl = safeQuery(id.replace('_hidden', '_select'));
                            }
                            visibleEl?.classList.add('is-invalid');
                            visibleEl?.closest('.input-group')?.classList.add('is-invalid');
                        }
                    });
                    // Check opening hours consistency
                    let hasOpenDay = false;
                    let openDayValid = true;
                    ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].forEach(
                        d => {
                            const status = qs(`select[name="status[${d}]"]`);
                            if (status && status.value === 'open') {
                                hasOpenDay = true;
                                const open = qs(`input[name="open_time[${d}]"]`);
                                const close = qs(`input[name="close_time[${d}]"]`);
                                if (!open?.value || !close?.value) {
                                    openDayValid = false;
                                    open?.classList.add('is-invalid');
                                    close?.classList.add('is-invalid');
                                }
                            }
                        });
                    if (hasOpenDay && !openDayValid) {
                        ok = false;
                        toastr.error('Provide open/close times for days marked Open.');
                    }
                    if (!ok && !toastr.active) toastr.error(
                        'Please fill required fields in Business Information.');
                    return ok;
                }

                // --- Account Number Format Validation ---
                function validateAccountNumberByMethod(methodName, value) {
                    const num = value.replace(/\s+/g, ''); // remove spaces
                    const lower = methodName.toLowerCase();

                    if (lower.includes('gcash')) {
                        // Must be 11 digits starting with 09
                        return /^09\d{9}$/.test(num);
                    } else if (lower.includes('maya')) {
                        // 10 to 13 digits
                        return /^\d{10,13}$/.test(num);
                    } else if (lower.includes('card') || lower.includes('debit')) {
                        // 12 to 19 digits (typical PAN length)
                        return /^\d{12,19}$/.test(num);
                    }
                    // Default: pass validation if not one of these
                    return true;
                }

                function validateStep2() {
                    removeErrors('#step2');
                    let valid = true;
                    let selected = [];
                    if (window._PAYMENT_CHOICES_INSTANCE) {
                        selected = window._PAYMENT_CHOICES_INSTANCE.getValue(true) || [];
                    } else {
                        const native = safeQuery('payment_methods');
                        selected = native ? Array.from(native.selectedOptions).map(o => o.value) : [];
                    }
                    const paySelectWrapper = safeQuery('payment_methods').parentNode.querySelector(
                        '.choices') || safeQuery('payment_methods');
                    if (!selected.length) {
                        valid = false;
                        paySelectWrapper.classList.add('is-invalid');
                        if (!paySelectWrapper.parentNode.querySelector('.error-message')) {
                            const error = document.createElement('div');
                            error.className = 'error-message';
                            error.textContent = 'Select at least one payment method.';
                            paySelectWrapper.parentNode.appendChild(error);
                        }
                    } else {
                        paySelectWrapper.classList.remove('is-invalid');
                        paySelectWrapper.parentNode.querySelector('.error-message')?.remove();
                    }
                    // Validate account details for non-COD methods
                    selected.forEach(mid => {
                        const methodName = (paymentMethodMap[mid] || '').toLowerCase();
                        const isCod = methodName.includes('cash') || methodName.includes(
                            'cod');
                        if (isCod) return;
                        const accNumInput = qs(`input[name="account_number[${mid}]"]`);
                        const accNameInput = qs(`input[name="account_name[${mid}]"]`);

                        // Validate Account Number
                        if (!accNumInput || !String(accNumInput.value || '').trim()) {
                            valid = false;
                            accNumInput?.classList.add('is-invalid');
                        } else {
                            const cleanValue = accNumInput.value.trim();
                            if (!validateAccountNumberByMethod(methodName, cleanValue)) {
                                valid = false;
                                accNumInput.classList.add('is-invalid');
                                if (!accNumInput.parentNode.querySelector('.error-message')) {
                                    const err = document.createElement('div');
                                    err.className = 'error-message';
                                    err.textContent =
                                        `Invalid format for ${paymentMethodMap[mid]} account number.`;
                                    accNumInput.parentNode.appendChild(err);
                                }
                            } else {
                                accNumInput?.classList.remove('is-invalid');
                                accNumInput.parentNode.querySelector('.error-message')?.remove();
                            }
                        }
                        // Validate Account Name
                        if (!accNameInput || !String(accNameInput.value || '').trim()) {
                            valid = false;
                            accNameInput?.classList.add('is-invalid');
                        } else {
                            accNameInput?.classList.remove('is-invalid');
                        }

                    });
                    if (!valid && !toastr.active) toastr.error(
                        'Fill required and valid account details for selected methods.');
                    return valid;
                }

                function validateStep3() {
                    removeErrors('#step3');
                    let ok = true;
                    let errorMessages = []; // Collect error messages

                    const birInput = safeQuery('bir_registration_input');
                    const durationInput = safeQuery('business_duration');
                    const validIdInput = safeQuery('valid_id_input');
                    const idTypeInput = safeQuery('valid_id_type_hidden');
                    const permitInput = safeQuery('business_permit_input');
                    const mayorInput = safeQuery('mayors_permit_input');

                    // Check BIR or Duration
                    const birFileNameInput = safeQuery('bir_file_name');
                    if (!birInput?.files.length && !durationInput?.value && birFileNameInput?.value ===
                        'None') {
                        ok = false;
                        birInput?.closest('.drop-zone').classList.add('is-invalid');
                        errorMessages.push('Upload BIR Registration or specify Business Duration.');
                    } else {
                        birInput?.closest('.drop-zone').classList.remove('is-invalid');
                    }

                    // Check Valid ID file
                    const validIdFileNameInput = safeQuery('valid_id_file_name');
                    if (!validIdInput?.files.length && validIdFileNameInput?.value === 'None') {
                        ok = false;
                        validIdInput?.closest('.drop-zone').classList.add('is-invalid');
                        errorMessages.push('Upload a Valid ID.');
                    } else { // This else corresponds to the NEW condition above
                        validIdInput?.closest('.drop-zone').classList.remove('is-invalid');
                        // Check ID Type only if file exists (new OR pre-loaded)
                        if ((validIdInput?.files.length || validIdFileNameInput?.value !== 'None') && !
                            idTypeInput?.value) {
                            ok = false;
                            validIdInput?.closest('.drop-zone').classList.add(
                                'is-invalid'); // Add invalid class back if type missing
                            errorMessages.push('Select the Valid ID Type via the Upload button.');
                        }
                    }

                    // Check Business Permit
                    const permitFileNameInput = safeQuery('business_permit_file_name');
                    if (!permitInput?.files.length && permitFileNameInput?.value === 'None') {
                        ok = false;
                        permitInput?.closest('.drop-zone').classList.add('is-invalid');
                        errorMessages.push('Upload the Business Permit.');
                    } else {
                        permitInput?.closest('.drop-zone').classList.remove('is-invalid');
                    }

                    // Check Mayor's Permit
                    const mayorFileNameInput = safeQuery('mayors_permit_file_name');
                    if (!mayorInput?.files.length && mayorFileNameInput?.value === 'None') {
                        ok = false;
                        mayorInput?.closest('.drop-zone').classList.add('is-invalid');
                        errorMessages.push("Upload the Mayor's Permit.");
                    } else {
                        mayorInput?.closest('.drop-zone').classList.remove('is-invalid');
                    }

                    // Show errors via Toastr if any
                    if (!ok) {
                        // Show only the first error for brevity, or combine them
                        toastr.error(errorMessages[0] || 'Please upload all required documents.');
                        // Optionally show all: toastr.error(errorMessages.join('<br>'));
                    }

                    return ok;
                }

                window.prepareSubmit = function(e) {
                    e.preventDefault();

                    // Run validation for the current step (Step 3)
                    if (!validateStep3()) {
                        // Validation failed. Toastr messages are shown inside validateStep3.
                        return; // Stop function execution
                    }

                    // --- If validation PASSED ---
                    const btn = e.target;
                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML =
                            '<span class="spinner-border spinner-border-sm"></span> Submitting...';
                    }

                    // Now, explicitly submit the form using JavaScript
                    console.log("Validation passed, attempting form submit..."); // Add log for debugging
                    safeQuery('start-selling-form')?.submit();
                };


                /* ---------- Payment Methods Logic (Choices JS) ---------- */
                const paySelectId = 'payment_methods';
                const paymentMethodMap = {};
                @foreach ($paymentMethods as $m)
                    paymentMethodMap['{{ $m->payment_method_id }}'] =
                        {!! json_encode($m->method_name) !!};
                @endforeach

                function createPaymentDetailRow(id, name, acc = '', aname = '') {
                    const container = safeQuery('payment_details_container');
                    if (!container) return;
                    if (container.querySelector(`[data-method-id="${id}"]`)) return;
                    const lower = name.toLowerCase();
                    if (lower.includes('cash') || lower.includes('cod')) return;
                    const div = document.createElement('div');
                    div.className = 'payment-detail-row';
                    div.setAttribute('data-method-id', id);
                    div.innerHTML =
                        `<div class="d-flex justify-content-between align-items-center mb-2"><strong>${name}</strong><button type="button" class="btn btn-sm btn-danger remove-payment-detail">Remove</button></div><div class="row g-2"><div class="col-md-6"><label class="form-label">Account Number <span class="text-danger">*</span></label><input type="text" class="form-control" name="account_number[${id}]" value="${acc}" required></div><div class="col-md-6"><label class="form-label">Account Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="account_name[${id}]" value="${aname}" required></div></div>`;
                    container.appendChild(div);

                    // --- Live account number input restriction & validation ---
                    const accountNumberInput = div.querySelector(`input[name="account_number[${id}]"]`);
                    if (accountNumberInput) {
                        const methodLower = name.toLowerCase();

                        accountNumberInput.addEventListener("input", function(e) {
                            // 1️⃣ Strip any non-digit characters immediately (Allow spaces for potential formatting)
                            // const numeric = this.value.replace(/[^\d\s]/g, ""); // Allow digits and space
                            const numeric = this.value.replace(/\D/g, ""); // Only allow digits
                            if (this.value !== numeric) this.value = numeric;

                            // 2️⃣ Validate format according to method
                            const parent = this.parentNode;
                            parent.querySelector(".error-message")?.remove(); // Clear previous error

                            let isValid = validateAccountNumberByMethod(methodLower, this.value);
                            if (!isValid && this.value.length >
                                0) { // Only show error if input is not empty
                                this.classList.add("is-invalid");
                                const err = document.createElement("div");
                                err.classList.add("error-message");
                                err.textContent = `Invalid ${name} account number format.`;
                                parent.appendChild(err);
                            } else {
                                this.classList.remove("is-invalid");
                            }
                        });

                        // 3️⃣ Prevent non-numeric keypresses
                        accountNumberInput.addEventListener("keypress", function(e) {
                            if (!/[0-9]/.test(e.key)) { // Only allow digits 0-9
                                e.preventDefault();
                            }
                        });
                    }

                    const btn = div.querySelector('.remove-payment-detail');
                    if (btn) {
                        btn.addEventListener('click', () => {
                            safeDeselectPayment(id);
                            div.remove();
                        });
                    }
                }

                function safeDeselectPayment(methodId) {
                    const native = safeQuery(paySelectId);
                    if (!native) return;
                    if (window._PAYMENT_CHOICES_INSTANCE?.removeActiveItemsByValue) {
                        try {
                            window._PAYMENT_CHOICES_INSTANCE.removeActiveItemsByValue(methodId);
                            return;
                        } catch (e) {
                            console.warn('Choices remove failed', e);
                        }
                    }
                    const option = Array.from(native.options || []).find(o => String(o.value) === String(
                        methodId));
                    if (option) {
                        option.selected = false;
                        native.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                    }
                }
                (function waitForPaymentSelectAndInit() {
                    const nativeSelect = safeQuery(paySelectId);
                    if (nativeSelect) {
                        initPaymentChoices(nativeSelect);
                        return;
                    }
                    const observer = new MutationObserver((mutations, obs) => {
                        const el = safeQuery(paySelectId);
                        if (el) {
                            obs.disconnect();
                            initPaymentChoices(el);
                        }
                    });
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                    setTimeout(() => observer.disconnect(), 5000); // Failsafe timeout
                })();

                function initPaymentChoices(nativeSelect) {
                    if (!nativeSelect) return;
                    try {
                        const choices = new Choices(nativeSelect, {
                            removeItemButton: true,
                            searchEnabled: true,
                            placeholder: true,
                            placeholderValue: 'Select Payment Methods',
                            searchPlaceholderValue: 'Search...'
                        });
                        window._PAYMENT_CHOICES_INSTANCE = choices;
                        console.info('Choices initialized.');
                    } catch (e) {
                        console.warn('Choices init failed.', e);
                        window._PAYMENT_CHOICES_INSTANCE = null; // Fallback if Choices fails
                    }

                    const changeTarget = window._PAYMENT_CHOICES_INSTANCE?.passedElement?.element ||
                        nativeSelect; // Use native select if Choices failed

                    changeTarget.addEventListener('change', () => {
                        try {
                            const selected = window._PAYMENT_CHOICES_INSTANCE ? window
                                ._PAYMENT_CHOICES_INSTANCE.getValue(true) : Array.from(nativeSelect
                                    .selectedOptions || []).map(o => o.value);
                            // Add new rows
                            selected.forEach(id => {
                                const methodName = paymentMethodMap[id] || 'Unknown Method';
                                // Preserve existing input values if the row is re-added
                                const existingAccNum = qs(`input[name="account_number[${id}]"]`)
                                    ?.value || '';
                                const existingAccName = qs(`input[name="account_name[${id}]"]`)
                                    ?.value || '';
                                createPaymentDetailRow(id, methodName, existingAccNum,
                                    existingAccName);
                            });
                            // Remove deselected rows
                            document.querySelectorAll('#payment_details_container .payment-detail-row')
                                .forEach(row => {
                                    const mid = row.getAttribute('data-method-id');
                                    if (!selected.includes(mid)) row.remove();
                                });

                            // Clear validation on change for the main select
                            const paySelectWrapper = qs('.choices[data-type="select-multiple"]') ||
                                nativeSelect;
                            paySelectWrapper.classList.remove('is-invalid');
                            paySelectWrapper.parentNode.querySelector('.error-message')?.remove();

                        } catch (err) {
                            console.warn('payment change handler error', err);
                        }
                    });

                    // Initial rendering of details for pre-selected options (e.g., from old input or db)
                    const initiallySelected = window._PAYMENT_CHOICES_INSTANCE ? window
                        ._PAYMENT_CHOICES_INSTANCE.getValue(true) : Array.from(nativeSelect.selectedOptions ||
                        []).map(o => o.value);
                    initiallySelected.forEach(id => {
                        // Get values potentially already rendered by Blade's old() helper
                        const initialAccNum = qs(`input[name="account_number[${id}]"]`)?.value || '';
                        const initialAccName = qs(`input[name="account_name[${id}]"]`)?.value || '';
                        const methodName = paymentMethodMap[id] || 'Unknown Method';
                        createPaymentDetailRow(id, methodName, initialAccNum, initialAccName);
                    });

                    // Ensure remove buttons added by Blade's old() helper work
                    document.querySelectorAll('#payment_details_container .remove-payment-detail').forEach(
                        btn => {
                            if (!btn.listenerAttached) { // Avoid attaching multiple listeners
                                btn.addEventListener('click', () => {
                                    const row = btn.closest('.payment-detail-row');
                                    const mid = row?.getAttribute('data-method-id');
                                    if (mid) safeDeselectPayment(mid);
                                    row?.remove();
                                });
                                btn.listenerAttached = true; // Mark as attached
                            }
                        });
                }


                /* ---------- File Input & Drag-and-Drop Logic ---------- */
                document.querySelectorAll('.drop-zone').forEach(zone => {
                    const inputId = zone.dataset.inputId;
                    const displayId = zone.dataset.displayId;
                    const fileInput = safeQuery(inputId);
                    const displayInput = safeQuery(displayId); // The text input below the drop zone
                    //const allowedTypes = (fileInput?.accept || '').split(',').map(t => t.trim().toLowerCase()).filter(t => t); // No longer needed
                    if (!fileInput || !displayInput) return;

                    zone.addEventListener('click', (e) => {
                        // Prevent click if clicking on the preview image itself (optional)
                        // if (e.target.tagName === 'IMG') return;
                        if (inputId === 'valid_id_input') window.showValidIdModal(e);
                        else fileInput.click();
                    });

                    fileInput.addEventListener('change', () => {
                        const file = fileInput.files[0];
                        displayInput.value = file ? file.name :
                            'None'; // Keep updating the text input
                        zone.classList.remove('is-invalid');

                        // Get preview elements within the specific drop zone
                        const placeholder = zone.querySelector('.upload-placeholder');
                        const previewContainer = zone.querySelector('.file-preview-container');
                        const previewImg = zone.querySelector('.file-preview-img');
                        // const previewName = zone.querySelector('.file-preview-name'); // Removed

                        // Special handling for the original business_image input (uses different IDs)
                        if (inputId === 'business_image_input') {
                            const legacyPreview = safeQuery('business_image_preview');
                            const legacyPreviewImg = legacyPreview?.querySelector('img');
                            const legacyPlaceholder = safeQuery('business_upload_placeholder');

                            if (file && legacyPreviewImg) {
                                if (file.type.startsWith('image/')) {
                                    const reader = new FileReader();
                                    reader.onload = e => {
                                        legacyPreviewImg.src = e.target.result;
                                        if (legacyPreview) legacyPreview.style.display =
                                            'block';
                                        if (legacyPlaceholder) legacyPlaceholder.style
                                            .display = 'none';
                                    };
                                    reader.readAsDataURL(file);
                                } else {
                                    // If not an image, just show placeholder
                                    if (legacyPreviewImg) legacyPreviewImg.src = '#';
                                    if (legacyPreview) legacyPreview.style.display = 'none';
                                    if (legacyPlaceholder) legacyPlaceholder.style.display =
                                        'block';
                                    // Optionally show a generic file icon or the filename here if needed
                                    // toastr.info("Non-image file selected for logo. Only images will be previewed.");
                                }
                            } else { // File removed or input cleared
                                if (legacyPreviewImg) legacyPreviewImg.src = '#';
                                if (legacyPreview) legacyPreview.style.display = 'none';
                                if (legacyPlaceholder) legacyPlaceholder.style.display =
                                    'block';
                            }
                        }
                        // Handle previews for other file inputs (BIR, ID, Permits)
                        else if (placeholder && previewContainer &&
                            previewImg /* && previewName */ ) { // Removed previewName check
                            if (file) {
                                placeholder.style.display = 'none';
                                previewContainer.style.display = 'block';
                                // previewName.textContent = file.name; // Don't show filename inside preview

                                if (file.type.startsWith('image/')) {
                                    previewImg.style.display = 'block'; // Show img element
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        previewImg.src = e.target.result;
                                    }
                                    reader.readAsDataURL(file);
                                } else {
                                    previewImg.style.display =
                                        'none'; // Hide img element if not image
                                    previewImg.src = '#'; // Clear src
                                    // You could optionally show a generic file icon here instead of just nothing
                                    // previewContainer.innerHTML = '<i class="ri-file-line ri-3x"></i>'; // Example
                                }
                            } else { // No file selected (e.g., cancelled or removed)
                                placeholder.style.display = 'block';
                                previewContainer.style.display = 'none';
                                previewImg.src = '#';
                                // previewName.textContent = ''; // Removed
                            }
                        }
                    });


                    zone.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        zone.classList.add('dragover');
                    });
                    zone.addEventListener('dragleave', (e) => {
                        zone.classList.remove('dragover');
                    });
                    zone.addEventListener('drop', (e) => {
                        e.preventDefault();
                        zone.classList.remove('dragover');
                        if (e.dataTransfer.files.length) {
                            fileInput.files = e.dataTransfer
                                .files; // Assign dropped files to input
                            const changeEvent = new Event('change', {
                                bubbles: true
                            });
                            fileInput.dispatchEvent(
                                changeEvent); // Trigger change event to update preview etc.
                        }
                    });
                });

                document.querySelectorAll('.import-btn').forEach(b => b.addEventListener('click', () => {
                    const inputId = b.dataset.input;
                    if (inputId === 'valid_id_input') window.showValidIdModal();
                    else safeQuery(inputId)?.click();
                }));

                // PASTE THIS NEW COMBINED LISTENER
                document.querySelectorAll('.cancel-btn').forEach(b => b.addEventListener('click', () => {
                    const inputId = b.dataset.input;
                    const i = safeQuery(inputId); // 'i' is the file input

                    if (!i) return; // safety check

                    // 1. Clear the file input's value
                    i.value = '';

                    // 2. Dispatch the 'change' event so the preview handler fires
                    const changeEvent = new Event('change', {
                        bubbles: true
                    });
                    i.dispatchEvent(changeEvent);

                    // 3. Remove validation error class
                    i.closest('.drop-zone')?.classList.remove('is-invalid');

                    // 4. Reset the text display field (e.g., "bir_file_name")
                    const displayId = b.dataset.display;
                    const displayInput = safeQuery(displayId);
                    if (displayInput) {
                        displayInput.value = 'None';
                    }

                    // 5. Special logic just for the Valid ID cancel button
                    if (inputId === 'valid_id_input') {
                        const validIdTypeHidden = safeQuery('valid_id_type_hidden');
                        if (validIdTypeHidden) {
                            validIdTypeHidden.value = ''; // Clear the hidden ID type
                        }
                        // Now, find the visible display for the ID type and update it
                        const validIdTypeDisplay = safeQuery('valid_id_type_display');
                        if (validIdTypeDisplay) {
                            validIdTypeDisplay.value = 'None'; // Set visible type to 'None'
                        }
                    }
                }));


                /* ---------- Modal Logic ---------- */
                // Valid ID Modal
                const validIdModalElement = safeQuery('validIdModal');
                const validIdTypeSelect = safeQuery('valid_id_type_select');
                const validIdTypeError = safeQuery('valid_id_type_error');
                const validIdTypeHidden = safeQuery('valid_id_type_hidden');
                const confirmValidIdTypeBtn = safeQuery('confirmValidIdType');
                let validIdModal;
                if (validIdModalElement && window.bootstrap) { // Check bootstrap is loaded
                    validIdModal = new bootstrap.Modal(validIdModalElement);
                    window.showValidIdModal = (e) => {
                        if (e) e.preventDefault();
                        validIdTypeSelect.value = validIdTypeHidden.value ||
                            ''; // Pre-select if value exists
                        validIdTypeError.classList.add('d-none'); // Hide error initially
                        validIdModal.show();
                    };
                    confirmValidIdTypeBtn?.addEventListener('click', () => {
                        if (!validIdTypeSelect.value) {
                            validIdTypeError.classList.remove(
                                'd-none'); // Show error if no type selected
                        } else {
                            validIdTypeHidden.value = validIdTypeSelect.value; // Store selected type
                            validIdModal.hide();
                            safeQuery('valid_id_input')
                                .click(); // Trigger file input click after type confirmation
                        }
                    });
                    // Reset selection in modal if closed without confirming
                    validIdModalElement.addEventListener('hidden.bs.modal', function() {
                        if (!validIdTypeHidden.value) validIdTypeSelect.value =
                            ''; // Clear dropdown if no value was stored
                        validIdTypeError.classList.add('d-none'); // Hide error
                    });
                } else {
                    // Fallback if bootstrap modal isn't available - just click input
                    console.warn("Bootstrap modal JS not found for Valid ID Type.");
                    window.showValidIdModal = (e) => {
                        if (e) e.preventDefault();
                        safeQuery('valid_id_input')?.click();
                        // You might want to show a simple prompt or alert here instead
                        // const idType = prompt("Enter Valid ID Type (e.g., UMID, Passport):");
                        // if(idType) validIdTypeHidden.value = idType;
                        // else { toastr.warning("ID Type is required."); return; }
                        // safeQuery('valid_id_input')?.click();
                    };
                }

                // Business Duration Modal
                const businessDurationModalElement = safeQuery('businessDurationModal');
                const durationSelect = safeQuery('business_duration_modal_select');
                const durationSelectError = safeQuery('duration_select_error');
                const businessDurationHidden = safeQuery('business_duration');
                const confirmBusinessDurationBtn = safeQuery('confirmBusinessDuration');
                let businessDurationModal;
                if (businessDurationModalElement && window.bootstrap) { // Check bootstrap is loaded
                    businessDurationModal = new bootstrap.Modal(businessDurationModalElement);
                    window.showBusinessDurationModal = e => {
                        if (e) e.preventDefault();
                        durationSelect.value = businessDurationHidden.value ||
                            ''; // Pre-select if value exists
                        if (durationSelectError) durationSelectError.classList.add(
                            'd-none'); // Hide error initially
                        businessDurationModal.show();
                    };
                    confirmBusinessDurationBtn?.addEventListener('click', () => {
                        if (!durationSelect.value) {
                            if (durationSelectError) durationSelectError.classList.remove(
                                'd-none'); // Show error if no duration
                        } else {
                            businessDurationHidden.value = durationSelect
                                .value; // Store selected duration
                            businessDurationModal.hide();
                            // Clear potential validation error on BIR input since duration is now set
                            safeQuery('bir_registration_input')?.closest('.drop-zone').classList.remove(
                                'is-invalid');
                        }
                    });
                    // Reset selection in modal if closed without confirming
                    businessDurationModalElement.addEventListener('hidden.bs.modal', function() {
                        if (!businessDurationHidden.value) durationSelect.value =
                            ''; // Clear dropdown if no value stored
                        if (durationSelectError) durationSelectError.classList.add(
                            'd-none'); // Hide error
                    });
                } else {
                    // Fallback if bootstrap modal isn't available
                    console.warn("Bootstrap modal JS not found for Business Duration.");
                    window.showBusinessDurationModal = e => {
                        if (e) e.preventDefault();
                        // Simple prompt as fallback
                        const duration = prompt(
                            "How long has your business been operating?\n(Less than 6 months, 6 months to 1 year, 1 to 3 years, More than 3 years)"
                        );
                        const validDurations = ["Less than 6 months", "6 months to 1 year", "1 to 3 years",
                            "More than 3 years"
                        ];
                        if (duration && validDurations.includes(duration)) {
                            businessDurationHidden.value = duration;
                            safeQuery('bir_registration_input')?.closest('.drop-zone').classList.remove(
                                'is-invalid');
                            toastr.info(`Business duration set to: ${duration}`);
                        } else {
                            toastr.warning("Invalid duration entered or cancelled.");
                        }
                    };
                }

                console.info('Form script initialized with Drag & Drop and Previews.');


                // ------- Valid ID display wiring (shows selected ID type to user) -------
                const validIdTypeDisplay = safeQuery(
                    'valid_id_type_display'); // read-only field visible on form
                const validIdInput = safeQuery('valid_id_input'); // file input for valid id

                // Helper to update the visible ID Type display
                function updateValidIdTypeDisplay() {
                    if (!validIdTypeDisplay) return;
                    const v = validIdTypeHidden?.value || '';
                    // If there is a value show it; otherwise show 'None'
                    validIdTypeDisplay.value = v ? v : 'None';
                }

                (function() {
                    // make the visible "Selected ID Type" clickable and keyboard accessible
                    const validIdTypeDisplay = document.getElementById('valid_id_type_display');
                    if (validIdTypeDisplay) {
                        // visual affordance
                        validIdTypeDisplay.style.cursor = 'pointer';
                        validIdTypeDisplay.setAttribute('tabindex',
                            '0'); // make focusable for keyboard users
                        validIdTypeDisplay.setAttribute('role', 'button'); // semantic hint

                        // open modal on click
                        validIdTypeDisplay.addEventListener('click', function(e) {
                            // Use the existing function your script exposes
                            if (typeof window.showValidIdModal === 'function') {
                                window.showValidIdModal(e);
                            } else {
                                // fallback: if modal helper is missing, trigger the import flow
                                document.getElementById('valid_id_import_btn')?.click();
                            }
                        });

                        // also open modal when Enter or Space is pressed (accessibility)
                        validIdTypeDisplay.addEventListener('keydown', function(e) {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                if (typeof window.showValidIdModal === 'function') window
                                    .showValidIdModal(e);
                            }
                        });
                    }
                })();


                // Initialize display on load if server provided old value
                updateValidIdTypeDisplay();

                // When user confirms ID type in modal, set hidden and update UI (existing listener sets hidden + triggers file input click)
                // Extend the existing confirm listener to update display (if you already set the hidden there, this ensures UI refresh)
                confirmValidIdTypeBtn?.addEventListener('click', () => {
                    if (!validIdTypeSelect.value) {
                        validIdTypeError.classList.remove('d-none');
                    } else {
                        validIdTypeHidden.value = validIdTypeSelect.value; // store selected type
                        updateValidIdTypeDisplay(); // update visible element immediately
                        validIdModal.hide();
                        // Trigger file input click after type confirmation
                        safeQuery('valid_id_input')?.click();
                    }
                });

                // If a file was selected (user picks file after confirming type), keep display in sync.
                // Also update file name input (valid_id_file_name) when valid_id_input changes:
                validIdInput?.addEventListener('change', () => {
                    const f = validIdInput.files[0];
                    const fileNameInput = safeQuery('valid_id_file_name');
                    fileNameInput.value = f ? f.name : 'None';
                    // If there's a file but no type selected, show a visible prompt in the ID type display
                    if (f && !(validIdTypeHidden?.value)) {
                        // show a short hint for the user
                        validIdTypeDisplay.value = 'Select ID Type (click Upload)';
                        // Optionally add a tiny toastr:
                        toastr.info('Select Valid ID Type after clicking Upload.');
                    } else {
                        // keep display consistent
                        updateValidIdTypeDisplay();
                    }
                });

                // Also when modal is hidden without confirming, keep the display in sync (existing listener clears selection if none)
                validIdModalElement?.addEventListener('hidden.bs.modal', function() {
                    // if no hidden value, the select should be cleared already — just update display
                    updateValidIdTypeDisplay();
                });

            }); // DOMContentLoaded
        })(); // IIFE
    </script>
@endsection
