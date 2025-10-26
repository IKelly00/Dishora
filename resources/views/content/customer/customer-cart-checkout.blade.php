@extends('layouts/contentNavbarLayout')

@section('title', 'Checkout')

@section('content')
    <!-- Toastr CSS & JS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <div class="container py-4 py-lg-5">
        <div class="main-content-area">
            <div class="section-header">
                <h4 class="fw-bold mb-0">Checkout</h4>
            </div>

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

            <!-- === Checkout Form === -->
            <form id="checkoutForm" action="{{ route('checkout.store') }}" method="POST" autocomplete="off" novalidate>
                @csrf

                {{-- Hidden: business this checkout is for --}}
                <input type="hidden" name="business_id" value="{{ $business_id }}">

                {{-- === DELIVERY INFORMATION === --}}
                <div class="checkout-section mb-4">
                    <h5 class="fw-bold text-dark mb-3">Delivery Information</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Delivery Date</label>
                            <!-- date is forced to client's current date and readonly -->
                            <input type="date" class="form-control" name="delivery_date" id="delivery_date" required
                                min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d') }}" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Delivery Time</label>
                            <select class="form-select" name="delivery_time" id="delivery_time" required>
                                <option value="">Select delivery time</option>
                                {{-- options will be populated by JS using openingHours --}}
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name"
                                value="{{ old('full_name') ?? ($fullName ?? '') }}" required readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone_number" id="phone_number" maxlength="11"
                                inputmode="numeric" placeholder="e.g., 09123456789" required
                                value="{{ old('phone_number') ?? ($contactNumber && $contactNumber !== '000-000-0000' ? $contactNumber : '') }}">
                        </div>
                    </div>
                </div>

                {{-- === DELIVERY ADDRESS === --}}
                <div class="checkout-section mb-4">
                    <h5 class="fw-bold text-dark mb-3">Delivery Address</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Region</label>
                            <select class="form-select" name="region" id="region_select" required disabled>
                                <option value="">{{ old('region') ? old('region') : 'Select Region' }}</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Province</label>
                            <select class="form-select" name="province" id="province_select" required disabled>
                                <option value="">{{ old('province') ? old('province') : 'Select Province' }}</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">City / Municipality</label>
                            <select class="form-select" name="city" id="city_select" required disabled>
                                <option value="">{{ old('city') ? old('city') : 'Select City' }}</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Barangay</label>
                            <select class="form-select" name="barangay" id="barangay_select" required disabled>
                                <option value="">{{ old('barangay') ? old('barangay') : 'Select Barangay' }}</option>
                            </select>
                        </div>

                        <div class="col-md-9">
                            <label class="form-label">Street Name, Building, House No.</label>
                            <input type="text" class="form-control" name="street_name" id="street_name" required
                                value="{{ old('street_name') ?? '' }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" id="postal_code" readonly
                                placeholder="Auto-populated" required value="{{ old('postal_code') ?? '' }}">
                        </div>
                    </div>
                </div>

                {{-- === MAP === --}}
                <div class="checkout-section mb-4">
                    <h5 class="fw-bold text-dark mb-3">Delivery Pin Location</h5>
                    <div id="map" class="rounded shadow-sm border" style="height:400px;"></div>
                    <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') ?? '' }}">
                    <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') ?? '' }}">
                </div>

                {{-- === ORDERS & PAYMENT (SINGLE BUSINESS) === --}}
                <div class="checkout-section mb-5">
                    <h5 class="fw-bold text-dark mb-3">Your Order</h5>

                    @php
                        // vendor for this business
                        $vendor = $vendors[$business_id] ?? null;
                        $methods = $vendor?->paymentMethods ?? collect();
                        $subtotal = collect($cart)->sum(fn($item) => (float) $item['price'] * (int) $item['quantity']);
                    @endphp

                    <div class="checkout-shop mb-4">
                        <div class="shop-header mb-3">
                            <h6 class="fw-bold text-dark mb-0">
                                {{ $vendor?->business_name ?? 'Business #' . $business_id }}
                            </h6>
                        </div>

                        <ul class="list-group mb-3 shadow-sm">
                            @foreach ($cart as $item)
                                @php
                                    $product = $products[$item['product_id']] ?? null;
                                    $lineTotal = number_format((float) $item['price'] * (int) $item['quantity'], 2);
                                @endphp

                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-2"
                                            onclick="openNoteModal({{ $business_id }}, {{ $item['product_id'] }}, this)">
                                            <i class="fa-regular fa-note-sticky"></i>
                                        </button>
                                        <span>{{ $product?->item_name ?? 'Unknown Item' }} ×
                                            {{ $item['quantity'] }}</span>
                                    </div>

                                    <strong class="text-primary">₱{{ $lineTotal }}</strong>

                                    {{-- hidden field where note JS will write the note text --}}
                                    <input type="hidden"
                                        name="item_notes[{{ $business_id }}][{{ $item['product_id'] }}]"
                                        class="item-note-field" data-business-id="{{ $business_id }}"
                                        data-product-id="{{ $item['product_id'] }}"
                                        value="{{ old('item_notes.' . $business_id . '.' . $item['product_id']) ?? '' }}">
                                </li>
                            @endforeach

                            <li class="list-group-item d-flex justify-content-between bg-light fw-semibold">
                                <span>Total</span>
                                <span>₱{{ number_format($subtotal, 2) }}</span>
                            </li>
                        </ul>

                        <h6 class="fw-bold small text-uppercase text-muted mb-3">Available Payment Methods</h6>

                        <div class="row g-3" data-business-id="{{ $business_id }}">
                            @if ($methods->isEmpty())
                                <div class="col-12 text-danger small">
                                    This vendor currently does not accept payments.
                                </div>
                            @else
                                @foreach ($methods as $i => $method)
                                    <div class="col-12 col-md-6">
                                        <label class="payment-option w-100">
                                            <input type="radio" class="d-none" name="payment_method"
                                                value="{{ $method->payment_method_id }}"
                                                data-method-name="{{ strtolower($method->method_name) }}"
                                                @if ($i === 0) required @endif>
                                            <div class="option-tile">
                                                <strong>{{ $method->method_name }}</strong>
                                                @if ($method->description)
                                                    <div class="small text-muted mt-1">{{ $method->description }}</div>
                                                @endif
                                            </div>
                                        </label>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>

                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary">Place Order</button>
                </div>
            </form>
        </div>
    </div>

    <!-- === NOTE MODAL === -->
    <div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="noteModalLabel">Add Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea id="noteTextarea" class="form-control" rows="4" placeholder="Enter note for this item..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveNoteBtn">Save Note</button>
                </div>
            </div>
        </div>
    </div>

    <!-- === STYLES === -->
    <style>
        .main-content-area {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 20px rgba(0, 0, 0, .08)
        }

        .checkout-section {
            border: 1px solid #f3f4f6;
            border-radius: 10px;
            padding: 1.5rem;
            background: #fcfcfc
        }

        .option-tile {
            border: 1px solid #ddd;
            padding: 10px 15px;
            border-radius: 10px;
            transition: all 0.2s ease;
            cursor: pointer;
            background-color: #fff;
        }

        .option-tile:hover {
            border-color: #0d6efd;
            box-shadow: 0 0 5px rgba(13, 110, 253, 0.3);
        }

        input[type="radio"]:checked+.option-tile {
            border-color: #0d6efd;
            background-color: #f0f8ff;
        }

        .checkout-section h5 {
            border-bottom: 2px solid #eee;
            padding-bottom: 0.5rem;
        }
    </style>

    <!-- === SCRIPTS === -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
    @vite(['resources/js/philippine-addresses.js'])
    <script async
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCvpcdeUJTkj9qPV9tZDSIQB184oR8Mwrc&libraries=marker&callback=initMapNew&loading=async">
    </script>

    <script>
        /* Reused JS (map, notes, addresses) — unchanged except it works on single-business page */

        let gmap = null;
        let gmarker = null;
        let geocoder = null;
        let currentNoteButton = null;
        let currentBusinessId = null;
        let currentProductId = null;

        function openNoteModal(businessId, productId, button) {
            currentNoteButton = button || null;
            currentBusinessId = businessId;
            currentProductId = productId;
            const selector = `.item-note-field[data-business-id="${businessId}"][data-product-id="${productId}"]`;
            const field = document.querySelector(selector);
            document.getElementById("noteTextarea").value = field?.value || "";
            const modalEl = document.getElementById("noteModal");
            if (modalEl) new bootstrap.Modal(modalEl).show();
        }

        function saveNote() {
            const noteValue = document.getElementById("noteTextarea")?.value?.trim() || "";
            const selector =
                `.item-note-field[data-business-id="${currentBusinessId}"][data-product-id="${currentProductId}"]`;
            const field = document.querySelector(selector);
            if (field) field.value = noteValue;

            if (currentNoteButton) {
                if (noteValue) {
                    currentNoteButton.innerHTML = '<i class="fa-solid fa-note-sticky"></i>';
                    currentNoteButton.classList.add("btn-warning", "text-dark");
                    currentNoteButton.classList.remove("btn-outline-secondary");
                } else {
                    currentNoteButton.innerHTML = '<i class="fa-regular fa-note-sticky"></i>';
                    currentNoteButton.classList.remove("btn-warning", "text-dark");
                    currentNoteButton.classList.add("btn-outline-secondary");
                }
            }

            const modalEl = document.getElementById("noteModal");
            if (modalEl) {
                const inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
            }
            if (typeof toastr !== 'undefined') toastr.success('Note saved successfully');
        }

        document.addEventListener('click', (e) => {
            if (!e.target) return;
            const btn = e.target.closest && e.target.closest('#saveNoteBtn');
            if (btn) {
                e.preventDefault();
                saveNote();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const phoneInput = document.getElementById('phone_number');
            if (phoneInput) {
                phoneInput.addEventListener('input', () => {
                    phoneInput.value = phoneInput.value.replace(/[^0-9]/g, '');
                });
            }
        });

        function initMapNew() {
            const mapEl = document.getElementById("map");
            if (!mapEl) return;
            gmap = new google.maps.Map(mapEl, {
                center: {
                    lat: 12.8797,
                    lng: 121.7740
                },
                zoom: 5.5,
                mapId: '2264679aceb18180d68ab9b4',
                zoomControl: true,
                mapTypeControl: false,
                streetViewControl: false
            });
            geocoder = new google.maps.Geocoder();
        }

        async function placeDraggableMarker(lat, lng) {
            if (!window.google) {
                console.error("Google Maps not loaded.");
                return;
            }

            try {
                if (google.maps.importLibrary) {
                    const {
                        AdvancedMarkerElement
                    } = await google.maps.importLibrary("marker");
                    if (gmarker && gmarker.position) {
                        gmarker.position = {
                            lat,
                            lng
                        };
                    } else {
                        gmarker = new AdvancedMarkerElement({
                            position: {
                                lat,
                                lng
                            },
                            map: gmap,
                            gmpDraggable: false
                        });
                    }
                } else {
                    if (gmarker && typeof gmarker.setPosition === 'function') {
                        gmarker.setPosition({
                            lat,
                            lng
                        });
                    } else {
                        gmarker = new google.maps.Marker({
                            position: {
                                lat,
                                lng
                            },
                            map: gmap,
                            draggable: false
                        });
                    }
                }
            } catch (err) {
                try {
                    if (gmarker && typeof gmarker.setPosition === 'function') {
                        gmarker.setPosition({
                            lat,
                            lng
                        });
                    } else {
                        gmarker = new google.maps.Marker({
                            position: {
                                lat,
                                lng
                            },
                            map: gmap,
                            draggable: false
                        });
                    }
                } catch (innerErr) {
                    console.error("placing marker failed:", innerErr);
                }
            }

            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;
        }

        async function geocodeAndCenterMap() {
            if (!geocoder) return;
            const region = document.getElementById('region_select')?.value || '';
            const province = document.getElementById('province_select')?.value || '';
            const city = document.getElementById('city_select')?.value || '';
            const barangay = document.getElementById('barangay_select')?.value || '';
            const addressString = [barangay, city, province, region].filter(Boolean).join(', ');
            if (!addressString) return;

            let zoomLevel = 6;
            if (province) zoomLevel = 9;
            if (city) zoomLevel = 12;
            if (barangay) zoomLevel = 15;

            try {
                const resp = await geocoder.geocode({
                    address: addressString,
                    componentRestrictions: {
                        country: 'PH'
                    }
                });
                if (resp && resp.results && resp.results[0]) {
                    const loc = resp.results[0].geometry.location;
                    gmap.panTo(loc);
                    gmap.setZoom(zoomLevel);
                    await placeDraggableMarker(loc.lat(), loc.lng());
                }
            } catch (err) {
                console.error("Geocoding failed:", err);
            }
        }

        function whenAddressesReady(fn) {
            // --- DEFAULTS FOR REGION / PROVINCE / CITY ---
            const oldRegion = @json(old('region') ?? '');
            const oldProvince = @json(old('province') ?? '');
            const oldCity = @json(old('city') ?? '');
            const defaultProvinceName = 'Camarines Sur';
            const defaultCityName = 'Naga City';

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

            // The default-applyer (run AFTER your populate callback)
            function applyAddressDefaults() {
                // Guard: region/province/city select elements must exist
                const regionSelect = document.getElementById('region_select');
                const provinceSelect = document.getElementById('province_select');
                const citySelect = document.getElementById('city_select');

                if (!regionSelect) return;

                // If user has old() values, respect them
                if (oldRegion) {
                    regionSelect.value = oldRegion;
                    regionSelect.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                    if (oldProvince) {
                        setTimeout(() => {
                            if (provinceSelect) {
                                provinceSelect.value = oldProvince;
                                provinceSelect.dispatchEvent(new Event('change', {
                                    bubbles: true
                                }));
                                if (oldCity) {
                                    setTimeout(() => {
                                        if (citySelect) {
                                            citySelect.value = oldCity;
                                            citySelect.dispatchEvent(new Event('change', {
                                                bubbles: true
                                            }));
                                        }
                                    }, 50);
                                }
                            }
                        }, 50);
                    }
                    return;
                }

                // No old() -> pick a Region key that looks like Region V / Bicol
                const allRegions = Object.keys(philippineAddresses || {});
                const regionKey = findKey(allRegions, [/^\s*V\s*$/, /Region\s*V/i, 'bicol']);
                if (regionKey && regionSelect) {
                    regionSelect.value = regionKey;
                    regionSelect.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }

                // Wait for province options to populate, then pick Camarines Sur
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

                    // Wait for city options then pick Naga City
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

                        // optional: ask the map to geocode & center now (if function exists)
                        if (typeof geocodeAndCenterMap === 'function') {
                            setTimeout(geocodeAndCenterMap, 120);
                        }
                    }, 60);
                }, 60);
            }

            // Run the populate callback and then apply defaults
            const runner = () => {
                try {
                    fn(); // this is your original populate code that fills selects
                } catch (e) {
                    console.error('whenAddressesReady: callback error', e);
                }
                // apply the defaults after populate
                applyAddressDefaults();
            };

            // original readiness logic (preserved)
            if (window.philippineAddresses && Object.keys(window.philippineAddresses).length) {
                runner();
                return;
            }
            window.addEventListener('philippineAddressesLoaded', () => runner(), {
                once: true
            });
            setTimeout(() => {
                if (window.philippineAddresses && Object.keys(window.philippineAddresses).length) runner();
            }, 300);
        }


        document.addEventListener('DOMContentLoaded', () => {
            const regionSelect = document.getElementById('region_select');
            const provinceSelect = document.getElementById('province_select');
            const citySelect = document.getElementById('city_select');
            const barangaySelect = document.getElementById('barangay_select');
            const postalCodeInput = document.getElementById('postal_code');

            const resetSelect = (el, text) => {
                if (!el) return;
                el.innerHTML = `<option value="">${text}</option>`;
                el.disabled = true;
            };
            const populateSelect = (el, data, text) => {
                if (!el) return;
                el.innerHTML = `<option value="">${text}</option>`;
                data.forEach(item => el.add(new Option(item, item)));
                el.disabled = false;
            };

            whenAddressesReady(() => {
                if (!regionSelect) return;
                populateSelect(regionSelect, Object.keys(philippineAddresses).sort(), 'Select Region');
                if (regionSelect) regionSelect.disabled = true;

                regionSelect.addEventListener('change', () => {
                    if (provinceSelect) resetSelect(provinceSelect, 'Select Province');
                    if (citySelect) resetSelect(citySelect, 'Select City');
                    if (barangaySelect) resetSelect(barangaySelect, 'Select Barangay');
                    if (postalCodeInput) postalCodeInput.value = '';

                    const regVal = regionSelect.value;
                    if (regVal && philippineAddresses[regVal]?.province_list && provinceSelect) {
                        populateSelect(provinceSelect, Object.keys(philippineAddresses[regVal]
                            .province_list).sort(), 'Select Province');
                        if (provinceSelect) provinceSelect.disabled = true;
                    }
                    if (typeof geocodeAndCenterMap === 'function') geocodeAndCenterMap();
                });

                if (provinceSelect) {
                    provinceSelect.addEventListener('change', () => {
                        if (citySelect) resetSelect(citySelect, 'Select City');
                        if (barangaySelect) resetSelect(barangaySelect, 'Select Barangay');
                        if (postalCodeInput) postalCodeInput.value = '';

                        const regVal = regionSelect.value;
                        const provVal = provinceSelect.value;
                        if (regVal && provVal && philippineAddresses[regVal]?.province_list?.[
                                provVal
                            ]?.municipality_list) {
                            populateSelect(citySelect, Object.keys(philippineAddresses[regVal]
                                    .province_list[provVal].municipality_list).sort(),
                                'Select City');
                            if (citySelect) citySelect.disabled = true;
                        }
                        if (typeof geocodeAndCenterMap === 'function') geocodeAndCenterMap();
                    });
                }

                if (citySelect) {
                    citySelect.addEventListener('change', () => {
                        if (barangaySelect) resetSelect(barangaySelect, 'Select Barangay');
                        if (postalCodeInput) postalCodeInput.value = '';

                        const regVal = regionSelect.value;
                        const provVal = provinceSelect.value;
                        const cityVal = citySelect.value;
                        if (regVal && provVal && cityVal) {
                            const cityData = philippineAddresses[regVal].province_list[provVal]
                                .municipality_list[cityVal];
                            if (cityData?.barangay_list && barangaySelect) populateSelect(
                                barangaySelect, cityData.barangay_list.sort(), 'Select Barangay'
                            );
                            if (postalCodeInput) postalCodeInput.value = cityData?.postal_code ||
                                '';
                        }
                        if (typeof geocodeAndCenterMap === 'function') geocodeAndCenterMap();
                    });
                }

                if (barangaySelect) {
                    barangaySelect.addEventListener('change', () => {
                        if (typeof geocodeAndCenterMap === 'function') geocodeAndCenterMap();
                    });
                }
            });
        });
    </script>

    <!-- Delivery-time: full script with cutoffMinutes support -->
    <script>
        (function() {
            // openingHours injected by Blade
            var openingHours = @json($openingHours ?? []);
            // total cutoff minutes computed server-side (sum cutoff_minutes * qty)
            var cutoffMinutes = parseInt(@json($cutoffMinutes ?? 0), 10) || 0;
            // expose for debugging
            window.cutoffMinutes = cutoffMinutes;

            function pad(n) {
                return n < 10 ? '0' + n : '' + n;
            }

            function to12HourLabel(hhmm) {
                if (!hhmm) return '';
                var p = hhmm.split(':');
                if (p.length < 2) return hhmm;
                var hh = parseInt(p[0], 10),
                    mm = parseInt(p[1], 10);
                if (isNaN(hh) || isNaN(mm)) return hhmm;
                var ampm = hh >= 12 ? 'PM' : 'AM';
                hh = hh % 12;
                if (hh === 0) hh = 12;
                return hh + ':' + (mm < 10 ? '0' + mm : mm) + ' ' + ampm;
            }

            function parseToMinutes(timeStr) {
                if (!timeStr) return null;
                var m = timeStr.match(/^(\d{1,2}):(\d{2})/);
                if (!m) return null;
                var hh = parseInt(m[1], 10),
                    mm = parseInt(m[2], 10);
                if (isNaN(hh) || isNaN(mm)) return null;
                return hh * 60 + mm;
            }

            function buildSlots(startMin, endMin, step) {
                step = step || 30;
                if (startMin === null || endMin === null) return [];
                if (startMin >= endMin) return [];
                var out = [];
                for (var t = startMin; t + step <= endMin; t += step) {
                    var hh = Math.floor(t / 60),
                        mm = t % 60;
                    out.push((hh < 10 ? '0' + hh : '' + hh) + ':' + (mm < 10 ? '0' + mm : '' + mm));
                }
                return out;
            }

            function localTodayYMD() {
                var n = new Date();
                return n.getFullYear() + '-' + pad(n.getMonth() + 1) + '-' + pad(n.getDate());
            }

            function weekdayKeyFromYMD(yyyyMmDd) {
                var p = ('' + yyyyMmDd).split('-');
                if (p.length !== 3) return '';
                var y = parseInt(p[0], 10),
                    m = parseInt(p[1], 10) - 1,
                    d = parseInt(p[2], 10);
                if (isNaN(y) || isNaN(m) || isNaN(d)) return '';
                var dt = new Date(y, m, d);
                return dt.toLocaleDateString('en-US', {
                    weekday: 'long'
                }).toLowerCase();
            }

            function nowLocalMinutes() {
                var n = new Date();
                return n.getHours() * 60 + n.getMinutes();
            }

            // Debounce guard so multiple triggers within short time don't rebuild while user interacts
            var lastPopulateAt = 0;

            function canPopulate() {
                var now = Date.now();
                if (now - lastPopulateAt < 120) return false; // 120ms guard
                lastPopulateAt = now;
                return true;
            }

            // Single authoritative populate function
            function populateForSelectedDate() {
                if (!canPopulate()) {
                    console.debug('[delivery-time] populate suppressed by debounce');
                    return;
                }
                try {
                    var dateEl = document.getElementById('delivery_date');
                    var select = document.getElementById('delivery_time');
                    if (!dateEl || !select) {
                        console.debug('[delivery-time] missing elements');
                        return;
                    }

                    var selectedDate = dateEl.value && dateEl.value.length ? dateEl.value : localTodayYMD();
                    var dayKey = weekdayKeyFromYMD(selectedDate);
                    console.debug('[delivery-time] populateForSelectedDate', {
                        selectedDate: selectedDate,
                        dayKey: dayKey,
                        cutoffMinutes: cutoffMinutes
                    });

                    // Keep track of current selection so we can restore it if appropriate
                    var previousValue = select.value;

                    // Reset options
                    select.innerHTML = '';

                    // Placeholder (visible until real options appended)
                    var placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.text = 'Select delivery time';
                    placeholder.disabled = true;
                    placeholder.hidden = false;
                    placeholder.selected = true;
                    select.appendChild(placeholder);

                    var hours = openingHours[dayKey];
                    if (!hours || hours.is_closed) {
                        var optClosed = document.createElement('option');
                        optClosed.value = '';
                        optClosed.text = 'Vendor is closed on ' + (dayKey.charAt(0).toUpperCase() + dayKey.slice(1));
                        optClosed.disabled = true;
                        select.appendChild(optClosed);
                        select.disabled = true;
                        console.debug('[delivery-time] vendor closed for day', dayKey);
                        return;
                    }

                    var startMin = parseToMinutes(hours.opens_at);
                    var endMin = parseToMinutes(hours.closes_at);

                    // apply product cutoff: disallow slots that are within cutoffMinutes before closing
                    var effectiveEnd = (endMin !== null && !isNaN(endMin)) ? (endMin - (cutoffMinutes || 0)) : null;

                    // defensive validations
                    if (startMin === null || effectiveEnd === null) {
                        var optErr = document.createElement('option');
                        optErr.value = '';
                        optErr.text = 'Invalid vendor hours';
                        optErr.disabled = true;
                        select.appendChild(optErr);
                        select.disabled = true;
                        console.debug('[delivery-time] invalid opening hours', hours);
                        return;
                    }

                    if (effectiveEnd <= startMin) {
                        var optNone = document.createElement('option');
                        optNone.value = '';
                        optNone.text = 'No delivery slots available (product cutoff prevents scheduling)';
                        optNone.disabled = true;
                        select.appendChild(optNone);
                        select.disabled = true;
                        console.debug('[delivery-time] effectiveEnd <= startMin due to cutoff', {
                            effectiveEnd,
                            startMin,
                            cutoffMinutes
                        });
                        return;
                    }

                    var todayYmd = localTodayYMD();
                    var nowMin = nowLocalMinutes();

                    if (selectedDate === todayYmd) {
                        // if before open => disabled
                        if (nowMin < startMin) {
                            var optNotOpen = document.createElement('option');
                            optNotOpen.value = '';
                            optNotOpen.text = 'Vendor not yet open today';
                            optNotOpen.disabled = true;
                            select.appendChild(optNotOpen);
                            select.disabled = true;
                            console.debug('[delivery-time] not yet open. nowMin, startMin:', nowMin, startMin);
                            return;
                        }
                        // if after actual close => disabled
                        if (nowMin >= endMin) {
                            var optClosedNow = document.createElement('option');
                            optClosedNow.value = '';
                            optClosedNow.text = 'Vendor already closed today';
                            optClosedNow.disabled = true;
                            select.appendChild(optClosedNow);
                            select.disabled = true;
                            console.debug('[delivery-time] already closed. nowMin, endMin:', nowMin, endMin);
                            return;
                        }

                        // within opening hours -> build usable slots with buffer
                        var nowPlus = nowMin + 15;
                        var earliest = Math.max(startMin, nowPlus);

                        // use effectiveEnd so slots don't enter cutoff window
                        var allSlots = buildSlots(startMin, effectiveEnd, 30);
                        var usable = [];
                        for (var i = 0; i < allSlots.length; i++) {
                            var p = allSlots[i].split(':');
                            var minutes = parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
                            if (minutes >= earliest && minutes < effectiveEnd) usable.push(allSlots[i]);
                        }

                        if (usable.length === 0) {
                            var optNone = document.createElement('option');
                            optNone.value = '';
                            optNone.text = 'No delivery slots available today';
                            optNone.disabled = true;
                            select.appendChild(optNone);
                            select.disabled = true;
                            console.debug('[delivery-time] no usable slots for today', {
                                earliest: earliest,
                                effectiveEnd: effectiveEnd,
                                allSlotsCount: allSlots.length
                            });
                            return;
                        }

                        for (var j = 0; j < usable.length; j++) {
                            var slot = usable[j];
                            var opt = document.createElement('option');
                            opt.value = slot + ':00';
                            opt.text = to12HourLabel(slot);
                            select.appendChild(opt);
                        }

                        // Try to restore previous value if it still exists in new options
                        if (previousValue) {
                            try {
                                var restored = false;
                                for (var k = 0; k < select.options.length; k++) {
                                    if (select.options[k].value === previousValue) {
                                        select.selectedIndex = k;
                                        restored = true;
                                        break;
                                    }
                                }
                                if (restored) console.debug('[delivery-time] restored previous selection',
                                    previousValue);
                            } catch (e) {}
                        }

                        select.disabled = false;
                        console.debug('[delivery-time] populated usable slots for today', {
                            earliest: earliest,
                            usableCount: usable.length,
                            effectiveEnd: effectiveEnd
                        });
                        return;
                    }

                    // Future date: show full slots but constrained to effectiveEnd
                    var allSlotsFuture = buildSlots(startMin, effectiveEnd, 30);
                    for (var m = 0; m < allSlotsFuture.length; m++) {
                        var s = allSlotsFuture[m];
                        var o = document.createElement('option');
                        o.value = s + ':00';
                        o.text = to12HourLabel(s);
                        select.appendChild(o);
                    }

                    // restore previous if still present
                    if (previousValue) {
                        try {
                            var restored2 = false;
                            for (var k2 = 0; k2 < select.options.length; k2++) {
                                if (select.options[k2].value === previousValue) {
                                    select.selectedIndex = k2;
                                    restored2 = true;
                                    break;
                                }
                            }
                            if (restored2) console.debug('[delivery-time] restored previous selection on future date',
                                previousValue);
                        } catch (e) {}
                    }

                    select.disabled = false;
                    console.debug('[delivery-time] populated future date slots, count:', select.options.length - 1,
                        'effectiveEnd:', effectiveEnd);

                } catch (err) {
                    console.error('[delivery-time] populateForSelectedDate error', err);
                }
            }

            // final validation guard before submit
            function validateBeforeSubmit(ev) {
                try {
                    var dateEl = document.getElementById('delivery_date');
                    var sel = document.getElementById('delivery_time');
                    var dateVal = dateEl && dateEl.value ? dateEl.value : localTodayYMD();

                    if (!sel || sel.disabled) {
                        ev.preventDefault();
                        (typeof toastr !== 'undefined') ? toastr.error(
                            'No delivery times available for the selected date.'): alert(
                            'No delivery times available for the selected date.');
                        return false;
                    }
                    if (!sel.value) {
                        ev.preventDefault();
                        (typeof toastr !== 'undefined') ? toastr.error('Please select a delivery time.'): alert(
                            'Please select a delivery time.');
                        return false;
                    }

                    // final server-safe checks (same-day buffer & inside hours & cutoff)
                    var parts = sel.value.split(':');
                    var selMin = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
                    var dayKey = weekdayKeyFromYMD(dateVal);
                    var hrs = openingHours[dayKey];
                    if (!hrs || hrs.is_closed) {
                        ev.preventDefault();
                        (typeof toastr !== 'undefined') ? toastr.error('Vendor is closed on selected date.'): alert(
                            'Vendor is closed on selected date.');
                        return false;
                    }
                    var start = parseToMinutes(hrs.opens_at),
                        end = parseToMinutes(hrs.closes_at);

                    // compute effectiveEnd for double-check
                    var effectiveEndCheck = (end !== null && !isNaN(end)) ? (end - (cutoffMinutes || 0)) : null;
                    if (selMin < start || selMin >= end) {
                        ev.preventDefault();
                        (typeof toastr !== 'undefined') ? toastr.error('Selected time is outside vendor hours.'): alert(
                            'Selected time is outside vendor hours.');
                        return false;
                    }
                    if (effectiveEndCheck !== null && selMin >= effectiveEndCheck) {
                        ev.preventDefault();
                        (typeof toastr !== 'undefined') ? toastr.error(
                            'Selected time is too close to closing time given product cut-off.'): alert(
                            'Selected time is too close to closing time given product cut-off.');
                        return false;
                    }

                    if (dateVal === localTodayYMD()) {
                        var nowMin = nowLocalMinutes();
                        var earliest = Math.max(start, nowMin + 15);
                        if (selMin < earliest) {
                            ev.preventDefault();
                            (typeof toastr !== 'undefined') ? toastr.error('Selected time is too soon.'): alert(
                                'Selected time is too soon.');
                            return false;
                        }
                    }

                    // Re-enable address fields just before submit so their values are sent
                    try {
                        document.getElementById('region_select').disabled = false;
                        document.getElementById('province_select').disabled = false;
                        document.getElementById('city_select').disabled = false;
                    } catch (e) {}

                    return true;
                } catch (e) {
                    ev.preventDefault();
                    (typeof toastr !== 'undefined') ? toastr.error('Unable to validate delivery time.'): alert(
                        'Unable to validate delivery time.');
                    return false;
                }
            }

            // Setup
            document.addEventListener('DOMContentLoaded', function() {
                // force delivery_date to client's today and min
                try {
                    var dateEl = document.getElementById('delivery_date');
                    if (dateEl) {
                        var today = localTodayYMD();
                        dateEl.value = today;
                        dateEl.min = today;
                    }
                } catch (e) {}

                // initial populate
                setTimeout(populateForSelectedDate, 40);

                // re-populate only when date changes
                var dEl = document.getElementById('delivery_date');
                if (dEl) dEl.addEventListener('change', populateForSelectedDate);

                // use focus (not click) and don't blindly repopulate on every focus
                var tEl = document.getElementById('delivery_time');
                if (tEl) {
                    // log changes so we can see whether something else resets the select
                    tEl.addEventListener('change', function(ev) {
                        console.debug('[delivery-time] select.change value:', tEl.value);
                    });
                    tEl.addEventListener('focus', function() {
                        if (tEl.disabled || tEl.options.length <= 1) populateForSelectedDate();
                    });
                    // Also protect against accidental programmatic resets by remembering last chosen value
                    tEl.addEventListener('input', function() {
                        console.debug('[delivery-time] select.input value:', tEl.value);
                    });
                }

                var form = document.getElementById('checkoutForm');
                if (form) form.addEventListener('submit', validateBeforeSubmit);
            });

        })();
    </script>



@endsection
