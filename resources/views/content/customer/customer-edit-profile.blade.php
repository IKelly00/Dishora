@extends('layouts/contentNavbarLayout')

@section('title', 'Edit Profile')

@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')

    <!-- Toastr CSS & JS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <style>
        /* Layout helpers */
        .profile-upload-row {
            display: flex;
            gap: 1.25rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .preview-container {
            flex: 0 0 auto;
        }

        .preview-circle {
            width: 140px;
            height: 140px;
            border-radius: 9999px;
            overflow: hidden;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            background: linear-gradient(180deg, #fff, #f8fafc);
        }

        .preview-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Dropzone */
        .image-upload-dropzone {
            border: 2px dashed #d6d8db;
            border-radius: .5rem;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            background-color: #ffffff;
            transition: background-color .15s ease, border-color .15s ease, transform .12s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }

        .image-upload-dropzone.flexible {
            flex: 1 1 360px;
            min-height: 112px;
        }

        .image-upload-dropzone .dz-icon {
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 56px;
            min-height: 56px;
            border-radius: 8px;
            background: linear-gradient(180deg, #f8fafc, #ffffff);
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
        }

        .image-upload-dropzone .dz-text {
            text-align: left;
            pointer-events: none;
        }

        .image-upload-dropzone.drag-over {
            background-color: #f8fbff;
            border-color: var(--bs-primary, #0d6efd);
            transform: translateY(-1px);
        }

        .upload-hint {
            font-size: .85rem;
            color: #6b7280;
        }

        @media (max-width: 575.98px) {
            .profile-upload-row {
                align-items: flex-start;
            }

            .image-upload-dropzone.flexible {
                flex-basis: 100%;
            }
        }
    </style>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                {{-- Session Messages & Validation Errors --}}
                @if (session('success'))
                    <script>
                        toastr.success("{{ session('success') }}");
                    </script>
                @endif
                @if (session('error'))
                    <script>
                        toastr.error("{{ session('error') }}");
                    </script>
                @endif
                @if ($errors->any())
                    <script>
                        @foreach ($errors->all() as $error)
                            toastr.error("{{ $error }}");
                        @endforeach
                    </script>
                @endif

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Edit Profile</h5>

                        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data"
                            id="profileForm" autocomplete="off">
                            @csrf

                            {{-- === Avatar + Dropzone Row === --}}
                            <div class="mb-4">
                                <label class="form-label">Profile Photo</label>

                                <div class="profile-upload-row">
                                    {{-- Avatar preview --}}
                                    <div class="preview-container">
                                        <div class="preview-circle">
                                            @if ($customer->user_image)
                                                <img id="avatarPreview" src="{{ $customer->user_image }}" alt="avatar">
                                            @else
                                                <img id="avatarPreview" src="{{ asset('assets/img/avatars/1.png') }}"
                                                    alt="avatar">
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Dropzone and controls --}}
                                    <div class="flex-grow-1">
                                        <input type="file" name="user_image" id="userImageInput"
                                            accept="image/png,image/jpeg,image/webp" class="d-none">

                                        <label for="userImageInput" id="dropzone" tabindex="0"
                                            class="image-upload-dropzone flexible" role="button"
                                            aria-label="Upload profile photo. Click or drag and drop an image.">
                                            <div class="dz-icon">
                                                <i class="ri-upload-cloud-2-line ri-lg text-muted" aria-hidden="true"></i>
                                            </div>

                                            <div class="dz-text">
                                                <div class="fw-medium">Drag &amp; drop image here</div>
                                                <div class="upload-hint">or <span class="text-primary">click to
                                                        browse</span></div>
                                            </div>
                                        </label>

                                        <div class="mt-2">
                                            <small class="form-text text-muted">Allowed: jpg, png, webp. Max 2MB.</small>
                                            @error('user_image')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- === ADDRESS SECTION === --}}
                            <h5 class="fw-bold text-dark mb-3 mt-4 pt-3 border-top">Address</h5>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Region</label>
                                    {{-- region disabled initially --}}
                                    <select class="form-select" name="region" id="region_select" disabled>
                                        <option value="">{{ old('region') ? old('region') : 'Select Region' }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Province</label>
                                    {{-- province disabled initially --}}
                                    <select class="form-select" name="province" id="province_select" disabled>
                                        <option value="">{{ old('province') ? old('province') : 'Select Province' }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">City / Municipality</label>
                                    {{-- city disabled initially --}}
                                    <select class="form-select" name="city" id="city_select" disabled>
                                        <option value="">{{ old('city') ? old('city') : 'Select City' }}</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Barangay</label>
                                    <select class="form-select" name="barangay" id="barangay_select">
                                        @if ($barangay)
                                            <option value="{{ $barangay }}" selected>{{ $barangay }}</option>
                                        @else
                                            <option value="" selected disabled>Select Barangay</option>
                                        @endif
                                    </select>
                                </div>


                                <div class="col-md-9">
                                    <label class="form-label">Street Name, Building, House No.</label>
                                    <input type="text" class="form-control" name="street_name" id="street_name"
                                        value="{{ old('street_name', $street ?? '') }}">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" name="postal_code" id="postal_code" readonly
                                        placeholder="Auto-populated"
                                        value="{{ old('postal_code') ?? ($postal ?? ($customer->postal_code ?? '')) }}">
                                </div>
                            </div>

                            {{-- Hidden full address input (kept for server) --}}
                            <input type="hidden" name="user_address" id="user_address"
                                value="{{ old('user_address', $customer->user_address ?? '') }}">

                            {{-- === Lat / Long: HIDDEN but included in submit === --}}
                            <input type="hidden" name="latitude" id="latitude"
                                value="{{ old('latitude', $customer->latitude ?? '') }}">
                            <input type="hidden" name="longitude" id="longitude"
                                value="{{ old('longitude', $customer->longitude ?? '') }}">

                            {{-- Contact --}}
                            <div class="mb-3 mt-4 pt-3 border-top">
                                <label for="contact_number" class="form-label">Contact Number</label>
                                <input type="tel" name="contact_number" id="contact_number"
                                    value="{{ old('contact_number', $customer->contact_number ?? '') }}" maxlength="11"
                                    inputmode="numeric" placeholder="e.g., 09123456789"
                                    class="form-control @error('contact_number') is-invalid @enderror">
                                @error('contact_number')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <a href="javascript:history.back()" class="btn btn-outline-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary" id="saveChangesBtn">Save changes</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page-script')
    @vite(['resources/js/philippine-addresses.js'])

    {{-- make the controller-parsed barangay available to JS --}}
    <script>
        const initialBarangay = @json($barangay ?? null);
        const initialCity = @json($city ?? null);
        const initialProvince = @json($province ?? null);
        const initialRegion = @json($region ?? null);
    </script>

    {{-- Alias initMapNew to the real callback to avoid "initMapNew is not a function" --}}
    <script>
        window.initMapNew = function() {
            if (typeof initGeocoder === 'function') {
                try {
                    initGeocoder();
                } catch (e) {
                    console.error(e);
                }
            } else {
                setTimeout(function() {
                    if (typeof initGeocoder === 'function') initGeocoder();
                }, 250);
            }
        };
    </script>

    {{-- Load Google Maps for Geocoding (replace YOUR_GOOGLE_MAPS_API_KEY) --}}
    <script async defer
        src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&libraries=places&callback=initGeocoder">
    </script>

    {{-- Image upload + dropzone --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('userImageInput');
            const preview = document.getElementById('avatarPreview');
            const dropzone = document.getElementById('dropzone');
            const MAX_BYTES = 2 * 1024 * 1024;
            const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

            function showPreviewFromFile(file) {
                if (!file) return;
                if (!ALLOWED.includes(file.type)) {
                    alert('Invalid file type. Allowed: jpg, png, webp.');
                    input.value = '';
                    return;
                }
                if (file.size > MAX_BYTES) {
                    alert('File too large. Max 2MB.');
                    input.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (e) => preview.src = e.target.result;
                reader.readAsDataURL(file);
            }

            dropzone.addEventListener('click', (e) => {
                e.preventDefault();
                input.click();
            });

            dropzone.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    input.click();
                }
            });

            input.addEventListener('change', (e) => {
                const file = e.target.files && e.target.files[0];
                showPreviewFromFile(file);
            });

            ['dragenter', 'dragover'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('drag-over');
                });
            });

            ['dragleave', 'drop', 'dragend'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('drag-over');
                });
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt && dt.files;
                if (!files || !files.length) return;
                const file = files[0];
                try {
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    input.files = dataTransfer.files;
                } catch (err) {
                    console.warn('DataTransfer not supported:', err);
                }
                showPreviewFromFile(file);
            });
        });
    </script>

    {{-- Address helpers --}}
    <script>
        function updateFullAddressInput() {
            const street = document.getElementById('street_name') ? document.getElementById('street_name').value : '';
            const barangay = document.getElementById('barangay_select') ? document.getElementById('barangay_select').value :
                '';
            const city = document.getElementById('city_select') ? document.getElementById('city_select').value : '';
            const province = document.getElementById('province_select') ? document.getElementById('province_select').value :
                '';
            const postal = document.getElementById('postal_code') ? document.getElementById('postal_code').value : '';

            const parts = [street, barangay, city, province].filter(Boolean);
            let fullAddress = parts.join(', ');
            if (postal) fullAddress += ' ' + postal;

            const hiddenAddress = document.getElementById('user_address');
            if (hiddenAddress) hiddenAddress.value = fullAddress;
        }

        function findKey(keys, testers) {
            for (const k of keys) {
                const kk = (k || '').toString();
                for (const t of testers) {
                    if (typeof t === 'string') {
                        if (kk.toLowerCase().includes(t.toLowerCase())) return kk;
                    } else if (t instanceof RegExp) {
                        if (t.test(kk)) return kk;
                    }
                }
            }
            return null;
        }
    </script>

    {{-- Geocoder (fills hidden lat/lng) --}}
    <script>
        let geocoder = null;
        let geoDebounceTimer = null;
        const GEO_DEBOUNCE_MS = 450;

        function initGeocoder() {
            if (window.google && window.google.maps && window.google.maps.Geocoder) {
                geocoder = new google.maps.Geocoder();
                // allow address population to run and defaults to apply, then perform initial geocode
                setTimeout(function() {
                    geocodeAndFillLatLng();
                }, 600);
            } else {
                console.warn('Google Maps not available for geocoding.');
            }
        }

        function buildAddressStringForGeocode() {
            const region = document.getElementById('region_select')?.value || '';
            const province = document.getElementById('province_select')?.value || '';
            const city = document.getElementById('city_select')?.value || '';
            const barangay = document.getElementById('barangay_select')?.value || '';
            const street = document.getElementById('street_name')?.value || '';
            const parts = [street, barangay, city, province, region].filter(Boolean);
            return parts.join(', ');
        }

        function geocodeAndFillLatLng() {
            if (!geocoder) return;
            const addr = buildAddressStringForGeocode();
            if (!addr) return;

            if (geoDebounceTimer) clearTimeout(geoDebounceTimer);
            geoDebounceTimer = setTimeout(function() {
                try {
                    geocoder.geocode({
                        address: addr,
                        componentRestrictions: {
                            country: 'PH'
                        }
                    }, function(results, status) {
                        if (status === 'OK' && results && results[0]) {
                            const loc = results[0].geometry.location;
                            const lat = (typeof loc.lat === 'function') ? loc.lat() : loc.lat;
                            const lng = (typeof loc.lng === 'function') ? loc.lng() : loc.lng;
                            const latInput = document.getElementById('latitude');
                            const lngInput = document.getElementById('longitude');
                            if (latInput) latInput.value = lat;
                            if (lngInput) lngInput.value = lng;
                        } else {
                            console.debug('Geocode no result for:', addr, 'status:', status);
                        }
                    });
                } catch (err) {
                    console.error('geocode error', err);
                }
            }, GEO_DEBOUNCE_MS);
        }
    </script>

    {{-- Address population + wiring (preserves your defaults) --}}
    <script>
        // readiness wrapper for philippineAddresses
        function whenAddressesReadyForProfile(fn) {
            const runner = () => {
                try {
                    fn();
                } catch (e) {
                    console.error('runner error', e);
                }
                applyDefaultsAndGeocode();
            };

            if (window.philippineAddresses && Object.keys(window.philippineAddresses).length) {
                runner();
                return;
            }
            window.addEventListener('philippineAddressesLoaded', () => runner(), {
                once: true
            });
            setTimeout(() => {
                if (window.philippineAddresses && Object.keys(window.philippineAddresses).length) runner();
            }, 400);
        }

        function applyDefaultsAndGeocode() {
            const defaultProvinceName = 'Camarines Sur';
            const defaultCityName = 'Naga City';
            const regionSelect = document.getElementById('region_select');
            const provinceSelect = document.getElementById('province_select');
            const citySelect = document.getElementById('city_select');

            if (!regionSelect) return;

            const allRegions = Object.keys(window.philippineAddresses || {});
            const regionKey = findKey(allRegions, [/^\s*V\s*$/, /Region\s*V/i, 'bicol']);
            if (regionKey) {
                regionSelect.value = regionKey;
                regionSelect.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
            }

            setTimeout(() => {
                if (!provinceSelect) return;
                const provKeys = Array.from(provinceSelect.options).map(o => o.value);
                if (provKeys.includes(defaultProvinceName)) {
                    provinceSelect.value = defaultProvinceName;
                    provinceSelect.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                } else {
                    const foundProv = findKey(provKeys, ['Camarines Sur', /camarines sur/i]);
                    if (foundProv) {
                        provinceSelect.value = foundProv;
                        provinceSelect.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                    }
                }

                setTimeout(() => {
                    if (!citySelect) return;
                    const cityKeys = Array.from(citySelect.options).map(o => o.value);
                    if (cityKeys.includes(defaultCityName)) {
                        citySelect.value = defaultCityName;
                        citySelect.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                    } else {
                        const foundCity = findKey(cityKeys, ['Naga City', /naga/i]);
                        if (foundCity) {
                            citySelect.value = foundCity;
                            citySelect.dispatchEvent(new Event('change', {
                                bubbles: true
                            }));
                        }
                    }
                    if (typeof geocodeAndFillLatLng === 'function') setTimeout(geocodeAndFillLatLng, 150);
                }, 80);
            }, 80);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const regionSelect = document.getElementById('region_select');
            const provinceSelect = document.getElementById('province_select');
            const citySelect = document.getElementById('city_select');
            const barangaySelect = document.getElementById('barangay_select');
            const postalCodeInput = document.getElementById('postal_code');
            const streetInput = document.getElementById('street_name');

            const resetSelect = (el, text) => {
                if (!el) return;
                el.innerHTML = `<option value="">${text}</option>`;
                el.disabled = true;
            };

            /**
             * populateSelect: populates and restores previous value if present.
             * Special-case: if el.id === 'barangay_select' and initialBarangay is present,
             * attempt to auto-select the matching barangay immediately after populating.
             */
            const populateSelect = (el, data, text, leaveDisabled = true) => {
                if (!el) return;
                const currentValue = el.value;
                // Add a placeholder option first
                el.innerHTML = `<option value="">${text}</option>`;
                data.forEach(item => el.add(new Option(item, item)));
                // Restore previous value if it still matches an option
                el.value = currentValue || '';

                // Enable/disable based on leaveDisabled flag
                el.disabled = !!leaveDisabled;

                // dispatch custom event to signal that this select was populated
                try {
                    el.dispatchEvent(new CustomEvent('selectPopulated', {
                        detail: {
                            id: el.id
                        },
                        bubbles: true,
                        cancelable: false
                    }));
                } catch (e) {
                    // older browsers fallback
                    const evt = document.createEvent('Event');
                    evt.initEvent('selectPopulated', true, false);
                    el.dispatchEvent(evt);
                }

                // === Special handling for barangay: try to auto-select initialBarangay now ===
                if (el.id === 'barangay_select' && typeof initialBarangay !== 'undefined' && initialBarangay) {
                    (function trySelect(targetRaw) {
                        const normalize = s => String(s || '').toLowerCase().replace(/\s+/g, ' ').replace(
                            /^brgy\.?\s*/i, '').replace(/^barangay\s*/i, '').trim();
                        const target = normalize(targetRaw);
                        if (!target) return false;

                        // gather options (skip placeholder at index 0)
                        const opts = Array.from(el.options).slice(1).map(o => ({
                            val: (o.value || '').trim(),
                            text: (o.text || '').trim()
                        }));

                        // 1) exact match on value or text
                        for (const o of opts) {
                            if (o.val && normalize(o.val) === target) {
                                el.value = o.val;
                                el.dispatchEvent(new Event('change', {
                                    bubbles: true
                                }));
                                return true;
                            }
                            if (o.text && normalize(o.text) === target) {
                                el.value = o.val || o.text;
                                el.dispatchEvent(new Event('change', {
                                    bubbles: true
                                }));
                                return true;
                            }
                        }

                        // 2) contains / partial match
                        for (const o of opts) {
                            const ov = normalize(o.val || o.text);
                            if (ov.includes(target) || target.includes(ov)) {
                                el.value = o.val || o.text;
                                el.dispatchEvent(new Event('change', {
                                    bubbles: true
                                }));
                                return true;
                            }
                        }

                        // 3) token-match (all tokens present)
                        const tokens = target.split(' ').filter(Boolean);
                        if (tokens.length) {
                            for (const o of opts) {
                                const ov = normalize(o.val || o.text);
                                if (tokens.every(tok => ov.includes(tok))) {
                                    el.value = o.val || o.text;
                                    el.dispatchEvent(new Event('change', {
                                        bubbles: true
                                    }));
                                    return true;
                                }
                            }
                        }

                        return false;
                    })(initialBarangay);
                }
            };

            whenAddressesReadyForProfile(() => {
                if (!regionSelect) return;
                // region/province/city populate -> keep disabled initially
                populateSelect(regionSelect, Object.keys(window.philippineAddresses).sort(),
                    'Select Region', true);

                regionSelect.addEventListener('change', () => {
                    if (provinceSelect) resetSelect(provinceSelect, 'Select Province');
                    if (citySelect) resetSelect(citySelect, 'Select City');
                    if (barangaySelect) resetSelect(barangaySelect, 'Select Barangay');
                    if (postalCodeInput) postalCodeInput.value = '';

                    const regVal = regionSelect.value;
                    if (regVal && window.philippineAddresses[regVal] && window.philippineAddresses[
                            regVal].province_list && provinceSelect) {
                        populateSelect(provinceSelect, Object.keys(window.philippineAddresses[
                            regVal].province_list).sort(), 'Select Province', true);
                    }
                    updateFullAddressInput();
                    if (typeof geocodeAndFillLatLng === 'function') geocodeAndFillLatLng();
                });

                if (provinceSelect) {
                    provinceSelect.addEventListener('change', () => {
                        if (citySelect) resetSelect(citySelect, 'Select City');
                        if (barangaySelect) resetSelect(barangaySelect, 'Select Barangay');
                        if (postalCodeInput) postalCodeInput.value = '';

                        const regVal = regionSelect.value;
                        const provVal = provinceSelect.value;
                        if (regVal && provVal && window.philippineAddresses[regVal] && window
                            .philippineAddresses[regVal].province_list && window
                            .philippineAddresses[regVal].province_list[provVal] && citySelect) {
                            const municipalities = Object.keys(window.philippineAddresses[regVal]
                                .province_list[provVal].municipality_list || {});
                            populateSelect(citySelect, municipalities.sort(), 'Select City', true);
                        }
                        updateFullAddressInput();
                        if (typeof geocodeAndFillLatLng === 'function') geocodeAndFillLatLng();
                    });
                }

                if (citySelect) {
                    citySelect.addEventListener('change', () => {
                        if (barangaySelect) resetSelect(barangaySelect, 'Select Barangay');
                        if (postalCodeInput) postalCodeInput.value = '';

                        const regVal = regionSelect.value;
                        const provVal = provinceSelect.value;
                        const cityVal = citySelect.value;
                        if (regVal && provVal && cityVal && window.philippineAddresses[regVal] &&
                            window.philippineAddresses[regVal].province_list && window
                            .philippineAddresses[regVal].province_list[provVal]) {
                            const cityData = window.philippineAddresses[regVal].province_list[
                                provVal].municipality_list[cityVal] || {};
                            if (cityData.barangay_list && barangaySelect) {
                                // populate barangay and ENABLE it (users should be able to select barangay)
                                populateSelect(barangaySelect, cityData.barangay_list.sort(),
                                    'Select Barangay', false);
                            }
                            if (postalCodeInput) postalCodeInput.value = cityData.postal_code || '';
                        }
                        updateFullAddressInput();
                        if (typeof geocodeAndFillLatLng === 'function') geocodeAndFillLatLng();
                    });
                }

                if (barangaySelect) {
                    // barangay should remain enabled for user interaction
                    barangaySelect.addEventListener('change', () => {
                        updateFullAddressInput();
                        if (typeof geocodeAndFillLatLng === 'function') geocodeAndFillLatLng();
                    });
                }

                if (streetInput) {
                    streetInput.addEventListener('input', () => {
                        updateFullAddressInput();
                        if (typeof geocodeAndFillLatLng === 'function') geocodeAndFillLatLng();
                    });
                }
            });

            // Before the form submits, re-enable selects so their values are included in the POST,
            // and ensure the latest lat/lng are present.
            const form = document.getElementById('profileForm');
            if (form) {
                form.addEventListener('submit', function(ev) {
                    // copy address to hidden field and kick one last geocode attempt
                    updateFullAddressInput();
                    if (typeof geocodeAndFillLatLng === 'function') geocodeAndFillLatLng();

                    // enable the selects (so their values are posted)
                    try {
                        const toEnable = ['region_select', 'province_select', 'city_select',
                            'barangay_select'
                        ];
                        toEnable.forEach(id => {
                            const el = document.getElementById(id);
                            if (el) el.disabled = false;
                        });
                    } catch (e) {
                        console.warn('enable selects failed', e);
                    }
                });
            }

            // phone sanitiser + form-level lightweight validation
            const phoneInput = document.getElementById('contact_number');
            if (phoneInput) {
                phoneInput.addEventListener('input', () => {
                    phoneInput.value = phoneInput.value.replace(/[^0-9]/g, '');
                });
            }

            // lightweight check: require at least one changed field or file
            (function addSubmitGuard() {
                const fileInput = document.getElementById('userImageInput');
                if (!form) return;
                form.addEventListener('submit', (e) => {
                    const hasImage = fileInput && fileInput.files && fileInput.files.length > 0;
                    const street = document.getElementById('street_name')?.value.trim() || '';
                    const contact = document.getElementById('contact_number')?.value.trim() || '';
                    const barangay = document.getElementById('barangay_select')?.value.trim() || '';

                    const hasOtherData = street || contact || barangay;

                    if (!hasImage && !hasOtherData) {
                        e.preventDefault();
                        if (typeof toastr !== 'undefined') toastr.error(
                            'Please update at least one field before saving.');
                        else alert('Please update at least one field before saving.');
                        return false;
                    }
                    return true;
                });
            })();

        }); // end DOMContentLoaded
    </script>
@endsection
