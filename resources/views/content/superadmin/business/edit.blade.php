@extends('layouts/commonMaster')
@section('title', 'Edit Business')

@section('layoutContent')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

    <style>
        .main-content-area {
            background: #ffffff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .card-header {
            padding-bottom: 0.5rem;
        }

        /* Style for required fields */
        .form-label .text-danger {
            margin-left: 3px;
        }

        .current-address-display {
            background-color: #f8f9fa;
            /* Light grey background */
            border: 1px solid #dee2e6;
            padding: 0 !important;
            border-radius: 5px;
            font-size: 0.875rem;
            white-space: pre-wrap;
            /* Ensures text wraps and respects newlines */
        }
    </style>

    <div class="container py-4">
        <div class="main-content-area">
            {{-- Header Section --}}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Edit Business: <strong class="text-primary">{{ $business->business_name }}</strong></h4>
                <a href="{{ route('super-admin.business.view', $business->business_id) }}"
                    class="btn btn-outline-secondary btn-sm">
                    <i class="bx bx-arrow-back me-1"></i> Back to Details
                </a>
            </div>

            <form action="{{ route('super-admin.business.update', $business->business_id) }}" method="POST"
                id="edit-business-form">
                @csrf
                @method('PUT')

                <div class="row g-4">
                    {{-- Column 1: Core Details --}}
                    <div class="col-lg-8">
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header border-bottom mb-5">
                                <h6 class="mb-0 fw-bold">Overview & Description</h6>
                            </div>
                            <div class="card-body">
                                {{-- Business Name --}}
                                <div class="mb-3">
                                    <label for="business_name" class="form-label">Business Name <span
                                            class="text-danger">*</span></label>
                                    <input name="business_name" id="business_name" class="form-control"
                                        value="{{ old('business_name', $business->business_name) }}" required>
                                    @error('business_name')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Business Type --}}
                                <div class="mb-3">
                                    <label for="business_type" class="form-label">Business Type <span
                                            class="text-danger">*</span></label>
                                    <input name="business_type" id="business_type" class="form-control"
                                        value="{{ old('business_type', $business->business_type) }}" required>
                                    @error('business_type')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Description --}}
                                <div class="mb-0">
                                    <label for="business_description" class="form-label">Full Description <span
                                            class="text-danger">*</span></label>
                                    <textarea name="business_description" id="business_description" class="form-control" rows="6" required>{{ old('business_description', $business->business_description) }}</textarea>
                                    @error('business_description')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Business Location & Geolocation --}}
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header border-bottom mb-5">
                                <h6 class="mb-0 fw-bold">Location & Coordinates</h6>
                            </div>
                            <div class="card-body">

                                {{-- START: Current Address Display --}}
                                <div class="mb-4">
                                    <h6 class="mb-2 text-muted">Current Saved Address:</h6>
                                    @if ($business->business_location)
                                        <div class="current-address-display">
                                            {!! nl2br(e($business->business_location)) !!}
                                        </div>
                                    @else
                                        <div class="current-address-display text-muted fst-italic">
                                            No formatted location saved. Please update using the fields below.
                                        </div>
                                    @endif
                                </div>
                                {{-- END: Current Address Display --}}

                                <h6 class="mb-3 text-muted">Philippine Address Selection</h6>
                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Region <span class="text-danger">*</span></label>
                                        <select class="form-select" name="region" id="region_select" required></select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Province <span class="text-danger">*</span></label>
                                        <select class="form-select" name="province" id="province_select" required
                                            disabled></select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">City / Municipality <span
                                                class="text-danger">*</span></label>
                                        <select class="form-select" name="city" id="city_select" required
                                            disabled></select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Barangay <span class="text-danger">*</span></label>
                                        <select class="form-select" name="barangay" id="barangay_select" required
                                            disabled></select>
                                    </div>
                                    <div class="col-md-9 mb-3">
                                        <label class="form-label">Street Name, Building, House No. <span
                                                class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="street" id="street_name"
                                            value="{{ old('street', $business->street_name ?? '') }}" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Postal Code</label>
                                        <input type="text" class="form-control" name="postal_code" id="postal_code"
                                            readonly placeholder="Auto-populated"
                                            value="{{ old('postal_code', $business->postal_code ?? '') }}">
                                    </div>
                                </div>

                                {{-- Business Location Textarea (Hidden to use the selected address as value) --}}
                                <input type="hidden" name="business_location" id="business_location_input"
                                    value="{{ old('business_location', $business->business_location) }}">

                                <h6 class="mt-4 mb-3 text-muted">Geographical Coordinates (Auto-Updated)</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="latitude" class="form-label">Latitude</label>
                                        @php
                                            // Determine if we should show coordinates initially
                                            $hasAddress =
                                                !empty($business->business_location) || !empty($business->region);
                                            $initialLat = $hasAddress ? old('latitude', $business->latitude) : '';
                                        @endphp
                                        <input type="text" name="latitude" id="latitude" class="form-control"
                                            value="{{ $initialLat }}" readonly>
                                        @error('latitude')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="longitude" class="form-label">Longitude</label>
                                        @php
                                            $initialLng = $hasAddress ? old('longitude', $business->longitude) : '';
                                        @endphp
                                        <input type="text" name="longitude" id="longitude" class="form-control"
                                            value="{{ $initialLng }}" readonly>
                                        @error('longitude')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Column 2: Status & Settings --}}
                    <div class="col-lg-4">
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header border-bottom mb-5">
                                <h6 class="mb-0 fw-bold">Status & Remarks</h6>
                            </div>
                            <div class="card-body">
                                {{-- Verification Status --}}
                                <div class="mb-3">
                                    <label for="verification_status" class="form-label">Verification Status <span
                                            class="text-danger">*</span></label>
                                    @php $currentStatus = old('verification_status', $business->verification_status); @endphp
                                    <select name="verification_status" id="verification_status" class="form-select">
                                        <option value="Pending" {{ $currentStatus === 'Pending' ? 'selected' : '' }}>
                                            Pending
                                        </option>
                                        <option value="Approved" {{ $currentStatus === 'Approved' ? 'selected' : '' }}>
                                            Approved</option>
                                        <option value="Rejected" {{ $currentStatus === 'Rejected' ? 'selected' : '' }}>
                                            Rejected</option>
                                    </select>
                                    @error('verification_status')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Admin Remarks --}}
                                <div class="mb-3">
                                    <label for="remarks" class="form-label">Admin Remarks (Rejection Reason)</label>
                                    <textarea name="remarks" id="remarks" class="form-control" rows="3">{{ old('remarks', $business->remarks) }}</textarea>
                                    @error('remarks')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Other Settings (e.g., Preorder Time) --}}
                        <div class="card shadow-sm">
                            <div class="card-header border-bottom mb-5">
                                <h6 class="mb-0 fw-bold">Business Settings</h6>
                            </div>
                            <div class="card-body">
                                {{-- Preorder Lead Time --}}
                                <div class="mb-0">
                                    <label for="preorder_lead_time_hours" class="form-label">Preorder Lead Time
                                        (Hours)</label>
                                    <input type="number" name="preorder_lead_time_hours" id="preorder_lead_time_hours"
                                        class="form-control"
                                        value="{{ old('preorder_lead_time_hours', $business->preorder_lead_time_hours) ?? 48 }}"
                                        min="0" step="1">
                                    <small class="text-muted">Required minimum time before a customer can receive a
                                        pre-order
                                        (e.g., 48 hours).</small>
                                    @error('preorder_lead_time_hours')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Form Submission Actions --}}
                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bx bx-save me-1"></i> Save
                        Changes</button>
                    <a href="{{ route('super-admin.business.view', $business->business_id) }}"
                        class="btn btn-outline-secondary btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('page-script')
    {{-- Include your Philippine Address JS data file here, usually from a Vite or assets path --}}
    @vite(['resources/js/philippine-addresses.js'])

    <script>
        // Use a safe function to get DOM elements
        function safeQuery(id) {
            return document.getElementById(id) || null;
        }

        // Helper to clear coordinates
        function clearCoordinates() {
            const latEl = safeQuery('latitude');
            const lngEl = safeQuery('longitude');
            const businessLocationEl = safeQuery('business_location_input');

            if (latEl) latEl.value = '';
            if (lngEl) lngEl.value = '';
            if (businessLocationEl) businessLocationEl.value = '';

            const postalCodeEl = safeQuery('postal_code');
            if (postalCodeEl) postalCodeEl.value = '';

            console.log('Coordinates and related fields cleared.');
        }

        // --- GOOGLE MAPS GEOCODING SETUP ---

        // Global initMap used as callback for Google Maps script loader
        window.initMap = function() {
            try {
                if (window.google && window.google.maps) {
                    window.geocoder = new google.maps.Geocoder();
                    console.info('Google Maps geocoder ready');
                }
            } catch (e) {
                console.warn('initMap error', e);
            }
        };

        // Inject Google Maps script (if not already loaded)
        (function loadGoogleMaps() {
            const existing = Array.from(document.scripts).find(s => (s.src || '').includes('maps.googleapis.com'));
            if (existing) {
                return;
            }
            const s = document.createElement('script');
            s.src =
                'https://maps.googleapis.com/maps/api/js?key=AIzaSyCvpcdeUJTkj9qPV9tZDSIQB184oR8Mwrc&libraries=geocoding&callback=initMap';
            s.async = true;
            s.defer = true;
            s.onerror = (err) => console.warn('Failed to load Google Maps script', err);
            document.head.appendChild(s);
        })();

        // Debounce utility to prevent too many API calls
        function debounce(fn, ms) {
            let t;
            return function(...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), ms);
            };
        }

        // Geocoding function to update Lat/Long
        async function geocodeAddress() {
            if (!window.geocoder) return;

            // 1. Check if the minimal address (Region) is selected.
            const region = safeQuery('region_select')?.value;
            if (!region) {
                clearCoordinates(); // Clear if region is empty
                return;
            }

            // 2. Build the full address string from the form fields
            const addressParts = [
                safeQuery('street_name')?.value,
                safeQuery('barangay_select')?.value,
                safeQuery('city_select')?.value,
                safeQuery('province_select')?.value,
                region,
                'Philippines'
            ].filter(Boolean).join(', ');

            const latEl = safeQuery('latitude');
            const lngEl = safeQuery('longitude');
            const businessLocationEl = safeQuery('business_location_input');

            try {
                const res = await window.geocoder.geocode({
                    address: addressParts,
                    componentRestrictions: {
                        country: 'PH'
                    }
                });

                if (res && res.results && res.results[0]) {
                    const loc = res.results[0].geometry.location;
                    const formattedAddress = res.results[0].formatted_address;

                    // Update Lat/Long inputs
                    if (latEl) latEl.value = loc.lat();
                    if (lngEl) lngEl.value = loc.lng();

                    // Update the hidden business_location field with the best address string
                    if (businessLocationEl) businessLocationEl.value = formattedAddress;
                } else {
                    // Clear fields if address cannot be geocoded
                    clearCoordinates();
                    if (businessLocationEl) businessLocationEl.value = addressParts; // Fallback to raw input
                    console.log('Geocoding result empty. Coords cleared.');
                }
            } catch (err) {
                clearCoordinates();
                console.warn('Geocode failed', err);
            }
        }
        const geocodeDebounced = debounce(geocodeAddress, 800);


        // --- ADDRESS SELECTS WIRING ---

        document.addEventListener('DOMContentLoaded', function() {
            // Load Philippine address data (assuming it's loaded via a separate script, e.g., philippine-addresses.js)
            if (typeof philippineAddresses === 'undefined') {
                console.error("Philippine address data is not loaded. Geocoding will not function correctly.");
                return;
            }

            const regionSelect = safeQuery('region_select');
            const provinceSelect = safeQuery('province_select');
            const citySelect = safeQuery('city_select');
            const barangaySelect = safeQuery('barangay_select');
            const streetInput = safeQuery('street_name');
            const postalCodeInput = safeQuery('postal_code');

            // This function is updated below to also call clearCoordinates() on empty selection
            const resetSelect = (el, ph) => {
                if (!el) return;
                el.innerHTML = `<option value="">${ph}</option>`;
                el.disabled = true;
            };
            const populateSelect = (el, data, ph, initialValue = null) => {
                if (!el) return;
                el.innerHTML = `<option value="">${ph}</option>`;
                data.forEach(item => {
                    const option = new Option(item, item);
                    if (item === initialValue) {
                        option.selected = true;
                    }
                    el.add(option);
                });
                el.disabled = false;
            };

            // --- Geocoding Triggers & Clear Logic ---

            // Street Input: always triggers geocode on input
            streetInput?.addEventListener('input', geocodeDebounced);

            // Region Select: Clears everything if selection becomes empty
            regionSelect?.addEventListener('change', () => {
                if (!regionSelect.value) {
                    clearCoordinates();
                }
                resetSelect(provinceSelect, 'Select Province');
                resetSelect(citySelect, 'Select City / Municipality');
                resetSelect(barangaySelect, 'Select Barangay');
                if (postalCodeInput) postalCodeInput.value = '';
                if (regionSelect.value) {
                    populateSelect(provinceSelect, Object.keys(philippineAddresses[regionSelect.value]
                        ?.province_list || {}).sort(), 'Select Province');
                }
                geocodeDebounced();
            });

            // Province Select: Clears next fields if selection becomes empty
            provinceSelect?.addEventListener('change', () => {
                if (!provinceSelect.value) {
                    clearCoordinates();
                }
                resetSelect(citySelect, 'Select City / Municipality');
                resetSelect(barangaySelect, 'Select Barangay');
                if (postalCodeInput) postalCodeInput.value = '';
                if (provinceSelect.value && regionSelect?.value) {
                    populateSelect(citySelect, Object.keys(philippineAddresses[regionSelect.value]
                            ?.province_list[provinceSelect.value]?.municipality_list || {}).sort(),
                        'Select City / Municipality');
                }
                geocodeDebounced();
            });

            // City Select: Clears next fields if selection becomes empty
            citySelect?.addEventListener('change', () => {
                if (!citySelect.value) {
                    clearCoordinates();
                }
                resetSelect(barangaySelect, 'Select Barangay');
                if (postalCodeInput) postalCodeInput.value = '';
                if (citySelect.value && provinceSelect?.value && regionSelect?.value) {
                    const cityData = philippineAddresses[regionSelect.value]?.province_list[provinceSelect
                        .value]?.municipality_list[citySelect.value];
                    if (cityData?.barangay_list) {
                        populateSelect(barangaySelect, cityData.barangay_list.sort(), 'Select Barangay');
                    }
                    if (postalCodeInput) postalCodeInput.value = cityData?.postal_code || '';
                }
                geocodeDebounced();
            });

            // Barangay Select: Clears if selection becomes empty
            barangaySelect?.addEventListener('change', () => {
                if (!barangaySelect.value) {
                    clearCoordinates();
                }
                geocodeDebounced();
            });


            // --- Initial Population Logic ---
            const initialAddress = {
                region: '{{ $business->region ?? '' }}',
                province: '{{ $business->province ?? '' }}',
                city: '{{ $business->city ?? '' }}',
                barangay: '{{ $business->barangay ?? '' }}',
                postal_code: '{{ $business->postal_code ?? '' }}'
            };

            // **Initial check to clear coordinates if essential address parts are missing.**
            const isAddressIncomplete = !initialAddress.region || !safeQuery('street_name').value.trim();
            if (isAddressIncomplete) {
                // Clear coordinates on load if the address is clearly incomplete
                clearCoordinates();
            }


            // Initialize Regions
            populateSelect(regionSelect, Object.keys(philippineAddresses).sort(), 'Select Region', initialAddress
                .region);

            // Cascade population based on initial values
            if (initialAddress.region && provinceSelect) {
                const provinces = Object.keys(philippineAddresses[initialAddress.region]?.province_list || {})
                    .sort();
                populateSelect(provinceSelect, provinces, 'Select Province', initialAddress.province);
            }

            if (initialAddress.province && citySelect) {
                const cities = Object.keys(philippineAddresses[initialAddress.region]?.province_list[initialAddress
                    .province]?.municipality_list || {}).sort();
                populateSelect(citySelect, cities, 'Select City / Municipality', initialAddress.city);
            }

            if (initialAddress.city && barangaySelect) {
                const barangays = philippineAddresses[initialAddress.region]?.province_list[initialAddress.province]
                    ?.municipality_list[initialAddress.city]?.barangay_list || [];
                populateSelect(barangaySelect, barangays.sort(), 'Select Barangay', initialAddress.barangay);
                if (postalCodeInput) {
                    // Postal code is handled here only if city is selected (auto-populated)
                    postalCodeInput.value = philippineAddresses[initialAddress.region]?.province_list[initialAddress
                        .province]?.municipality_list[initialAddress.city]?.postal_code || '';
                }
            }

            // Re-run geocoding once after initial population IF the address was complete
            if (!isAddressIncomplete && initialAddress.region) {
                geocodeDebounced();
            }


            // --- Form Submission Logic ---
            document.getElementById('edit-business-form').addEventListener('submit', async function(e) {
                // Prevent default form submission to ensure geocoding is complete
                e.preventDefault();

                const submitButton = document.querySelector('button[type="submit"]');
                const originalButtonHtml = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm"></span> Saving...';

                // Ensure the geocoding runs one final time before submission
                await geocodeAddress();

                // If coordinates were successfully retrieved OR the Region is empty (user intends to clear address), proceed.
                const lat = safeQuery('latitude')?.value;
                const region = safeQuery('region_select')?.value;

                if (lat || !region) {
                    this.submit();
                } else {
                    // Re-enable button and show error if geocoding failed AND region is required/selected.
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonHtml;
                    alert(
                        'Error: Could not verify location address. Please ensure the address is complete and try again.'
                    );
                }
            });
        });
    </script>
@endpush
