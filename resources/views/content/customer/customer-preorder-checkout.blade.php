@extends('layouts/contentNavbarLayout')

@section('title', 'Pre-Order Checkout')

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>

    <div class="container py-4 py-lg-5">
        <div class="main-content-area">
            <div class="section-header">
                <h4 class="fw-bold mb-0">Pre-Order Checkout</h4>
            </div>

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

            <form id="checkoutForm" action="{{ route('checkout.preorder.store') }}" method="POST" autocomplete="off"
                novalidate>
                @csrf
                <input type="hidden" name="business_id" value="{{ $business_id }}">

                {{-- === DELIVERY INFORMATION & ADDRESS === --}}
                <div class="checkout-section mb-4">
                    <h5 class="fw-bold text-dark mb-3">Delivery Information</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Delivery Date</label>
                            <button type="button" class="form-control btn btn-outline-secondary text-start"
                                id="date-picker-button">Select a Delivery Date...</button>
                            <input type="hidden" name="delivery_date" id="delivery_date" required>
                            <div class="invalid-feedback">Please select a delivery date.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Delivery Time</label>
                            <select class="form-select" name="delivery_time" id="delivery_time" required>
                                <option value="">Select delivery time</option>
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
                                <option value="">{{ old('province') ? old('province') : 'Select Province' }}
                                </option>
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
                                <option value="">{{ old('barangay') ? old('barangay') : 'Select Barangay' }}
                                </option>
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

                <div class="checkout-section mb-4">
                    <h5 class="fw-bold text-dark mb-3">Delivery Pin Location</h5>
                    <div id="map" class="rounded shadow-sm border" style="height:400px;"></div>
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                </div>

                {{-- === ORDERS & PAYMENT === --}}
                <div class="checkout-section mb-5">
                    <h5 class="fw-bold text-dark mb-3">Your Pre-Order</h5>
                    @php
                        $business = $vendors->first();
                        $methods = $business?->paymentMethods ?? collect([]);
                    @endphp

                    <div class="checkout-shop mb-4">
                        <div class="shop-header mb-3">
                            <h6 class="fw-bold text-dark mb-0">{{ $business?->business_name ?? 'Unknown Business' }}
                            </h6>
                        </div>

                        <ul class="list-group mb-5 shadow-sm">
                            @foreach ($preorders as $item)
                                @php $product = $products[$item['product_id']] ?? null; @endphp
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-2"
                                            onclick="openNoteModal({{ $business->business_id }}, {{ $item['product_id'] }}, this)"><i
                                                class="bx bx-note"></i></button>
                                        <span>{{ $product?->item_name ?? 'Unknown Item' }} ×
                                            {{ $item['quantity'] }}</span>
                                    </div>
                                    <strong
                                        class="text-primary">₱{{ number_format($item['price'] * $item['quantity'], 2) }}</strong>

                                    {{-- hidden field where note JS writes the note text --}}
                                    <input type="hidden"
                                        name="item_notes[{{ $business->business_id }}][{{ $item['product_id'] }}]"
                                        class="item-note-field" data-business-id="{{ $business->business_id }}"
                                        data-product-id="{{ $item['product_id'] }}">
                                </li>
                            @endforeach
                            <li class="list-group-item d-flex justify-content-between bg-light fw-semibold">
                                <span>Total</span><span>₱{{ number_format($total, 2) }}</span>
                            </li>
                        </ul>

                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h6 class="card-title fw-bold mb-0">Payment</h6>
                            </div>
                            <div class="card-body">

                                @if ($total_advance_required > 0)
                                    <div class="mt-4 mb-4 p-4 rounded" style="background-color: #fff8eb;">
                                        <h6 class="fw-bold mb-2 text-dark">
                                            <i class="bx bx-coin-stack text-warning me-1"></i>
                                            Advance Payment Required
                                        </h6>
                                        <ul class="px-4 list-unstyled small mb-3">
                                            @foreach ($preorders as $item)
                                                @php
                                                    $product = $products[$item['product_id']] ?? null;
                                                    $advanceForItem =
                                                        ($product->advance_amount ?? 0) * $item['quantity'];
                                                @endphp
                                                @if ($product && $advanceForItem > 0)
                                                    <li class="d-flex justify-content-between py-1 border-bottom">
                                                        <span>{{ $product->item_name }} ×
                                                            {{ $item['quantity'] }}</span>
                                                        <span
                                                            class="fw-semibold">₱{{ number_format($advanceForItem, 2) }}</span>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                        <div class="d-flex justify-content-between fw-bold text-dark px-4">
                                            <span>Total Advance Required:</span>
                                            <span
                                                class="text-warning">₱{{ number_format($total_advance_required, 2) }}</span>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <h6 class="fw-bold small text-uppercase text-muted mb-3">Choose Payment Option
                                        </h6>
                                        <div class="list-group">
                                            <label
                                                class="list-group-item list-group-item-action d-flex gap-3 waves-effect">
                                                <input class="form-check-input flex-shrink-0" type="radio"
                                                    name="payment_option" id="pay_advance" value="advance" checked>
                                                <div class="d-flex flex-column w-100">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-0 fw-semibold text-dark">Pay Advance Now</h6>
                                                        <span
                                                            class="fw-bold">₱{{ number_format($total_advance_required, 2) }}</span>
                                                    </div>
                                                    <small class="text-muted">Pay remaining on delivery.</small>
                                                </div>
                                            </label>

                                            <label
                                                class="list-group-item list-group-item-action d-flex gap-3 waves-effect">
                                                <input class="form-check-input flex-shrink-0" type="radio"
                                                    name="payment_option" id="pay_full" value="full">
                                                <div class="d-flex flex-column w-100">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-0 fw-semibold text-dark">Pay Full Amount Now</h6>
                                                        <span class="fw-bold">₱{{ number_format($total, 2) }}</span>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                @endif

                                <div>
                                    <h6 class="fw-bold small text-uppercase text-muted mb-3 payment-label mt-3">Select
                                        Payment Method</h6>
                                    <div id="cod-info-text" class="alert alert-info d-none" role="alert">
                                        An advance payment of
                                        <strong>₱{{ number_format($total_advance_required, 2) }}</strong> is required
                                        to
                                        confirm your COD order. You will be redirected to pay this amount online.
                                    </div>

                                    <div class="row g-3 payment-group"
                                        data-business-id="{{ $business->business_id ?? 1 }}">
                                        @if ($methods->isEmpty())
                                            <div class="col-12 text-danger small">This vendor currently does not accept
                                                payments.</div>
                                        @else
                                            @foreach ($methods as $i => $method)
                                                <div class="col-12 col-md-6">
                                                    <label class="payment-option w-100">
                                                        <input type="radio" class="d-none" name="payment_method"
                                                            value="{{ $method->payment_method_id }}"
                                                            data-method-name="{{ strtolower($method->method_name) }}"
                                                            @if ($i === 0) required @endif>
                                                        @php
                                                            $methodNameLower = strtolower($method->method_name ?? '');
                                                            $isCodMethod =
                                                                str_contains($methodNameLower, 'cash on delivery') ||
                                                                str_contains($methodNameLower, 'cod') ||
                                                                str_contains($methodNameLower, 'card on delivery');

                                                            $codDescription =
                                                                $total_advance_required > 0
                                                                    ? 'Advance required — you will be redirected to pay the advance now'
                                                                    : 'Pay with cash upon delivery';
                                                        @endphp

                                                        <div class="option-tile">
                                                            <strong>{{ $method->method_name }}</strong>
                                                            <div class="small text-muted mt-1">
                                                                @if ($method->description)
                                                                    {{ $method->description }}
                                                                @elseif($isCodMethod)
                                                                    {{ $codDescription }}
                                                                @endif
                                                            </div>
                                                        </div>

                                                    </label>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-lg btn-primary">Place Pre-Order</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODALS --}}
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vendor Pre-Order Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="schedule-calendar"></div>
                    <div id="calendar-note" class="calendar-note">
                        <em>Pre-orders start tomorrow. Select any green or open date above</em>
                    </div>
                </div>
                <div class="modal-footer justify-content-center flex-wrap gap-1 small text-muted">
                    <span class="d-flex align-items-center">
                        <span class="badge rounded-pill bg-secondary me-2 px-3 py-2"><i class='bx bx-time-five me-1'></i>
                            Past Date</span>
                    </span>
                    <span class="d-flex align-items-center">
                        <span class="badge rounded-pill bg-success me-2 px-3 py-2"><i class='bx bx-check-circle me-1'></i>
                            Available</span>
                    </span>
                    <span class="d-flex align-items-center">
                        <span class="badge rounded-pill bg-danger me-2 px-3 py-2"><i class='bx bx-block me-1'></i> Fully
                            Booked</span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="noteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Note for Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea class="form-control" id="noteTextarea" rows="3" placeholder="e.g., Deliver after 5pm..."></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary"
                        onclick="saveNote()">Save Note</button></div>
            </div>
        </div>
    </div>

    {{-- STYLES --}}
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

        /* === Calendar visual tweaks === */
        .fc-day-disabled {
            background-color: #f5f5f5 !important;
            color: #aaa !important;
            cursor: not-allowed !important;
        }

        .fc-day-disabled .fc-daygrid-day-number {
            opacity: 0.4;
        }

        /* Style for the note row below calendar title */
        .calendar-note {
            text-align: center;
            font-size: 0.9rem;
            color: #555;
            margin-top: 0.5rem;
        }

        /* Subtle box for today's date if needed */
        .fc-day-today {
            background-color: #fffce6 !important;
        }
    </style>

    {{-- EXTERNAL SCRIPTS --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
    @vite(['resources/js/philippine-addresses.js'])

    <script>
        // queue stub so Google Maps can call initMapNew even if real init not yet defined
        (function() {
            window.__gm_init_called = window.__gm_init_called || false;
            window.__gm_init_queue = window.__gm_init_queue || 0; // just count calls

            window.initMapNew = function() {
                // increment queue count so real init (later) will know it fired
                window.__gm_init_queue = (window.__gm_init_queue || 0) + 1;
            };
        })();
    </script>

    <script async
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCvpcdeUJTkj9qPV9tZDSIQB184oR8Mwrc&libraries=marker&callback=initMapNew&loading=async">
    </script>

    {{-- ========================================================= --}}
    {{-- ============= ADDED SCRIPT (from cart blade) ============ --}}
    {{-- ========================================================= --}}
    <script>
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
    </script>

    <script>
        (function() {
            // Make sure DOM is ready
            document.addEventListener('DOMContentLoaded', function() {

                const phoneInput = document.getElementById('phone_number');
                if (phoneInput) {
                    phoneInput.addEventListener('input', () => {
                        phoneInput.value = phoneInput.value.replace(/[^0-9]/g, '');
                    });
                }

                /* ======== Shared / helper functions ======== */
                function pad2(n) {
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

                    var out = [];
                    var MINUTES_IN_DAY = 1440; // 24 * 60

                    if (endMin <= startMin) {
                        // --- Overnight Case (e.g., 18:00 to 02:00) ---

                        // Part 1: From startMin to midnight (e.g., 18:02 ... 23:32)
                        for (var t = startMin; t + step <= MINUTES_IN_DAY; t += step) {
                            var hh = Math.floor(t / 60),
                                mm = t % 60;
                            out.push(pad2(hh) + ':' + pad2(mm));
                        }

                        // Part 2: From midnight (0) to endMin (e.g., 00:00 ... 01:30)
                        for (var t = 0; t + step <= endMin; t += step) {
                            var hh = Math.floor(t / 60),
                                mm = t % 60;
                            out.push(pad2(hh) + ':' + pad2(mm));
                        }

                    } else {
                        // --- Normal Case (e.g., 09:00 to 17:00) ---
                        for (var t = startMin; t + step <= endMin; t += step) {
                            var hh = Math.floor(t / 60),
                                mm = t % 60;
                            out.push(pad2(hh) + ':' + pad2(mm));
                        }
                    }
                    return out;
                }

                function localTodayYMD() {
                    var n = new Date();
                    return n.getFullYear() + '-' + pad2(n.getMonth() + 1) + '-' + pad2(n.getDate());
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

                /* ======== openingHours injected by backend ======== */
                var openingHours = @json($openingHours ?? []);

                /* ======== Google Maps globals: reuse window.gmap if present to avoid re-declare ======== */
                // Google maps callback must be global: expose initMapNew on window
                (function defineRealInit() {
                    // store the original body you already have for initialization:
                    function realInit() {
                        // --- your existing init code here ---
                        window.gmap = window.gmap || new google.maps.Map(document.getElementById("map"), {
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
                        window.geocoder = window.geocoder || new google.maps.Geocoder();
                        // --- end existing init code ---

                        // NOW, call the function that sets address defaults and centers the map
                        // if (typeof applyAddressDefaults === 'function') {
                        //     applyAddressDefaults();
                        // }
                    }

                    // replace the stub with the real init
                    window.initMapNew = realInit;

                    // If Google Maps already invoked the stub before we defined the real init,
                    // __gm_init_queue will be > 0 — call real init to match expected behavior.
                    if (window.__gm_init_queue && window.__gm_init_queue > 0) {
                        try {
                            window.initMapNew();
                        } catch (e) {
                            console.error('realInit failed:', e);
                        }
                        window.__gm_init_queue = 0;
                    }
                })();

                // placeDraggableMarker / geocode use window.gmap & window.geocoder
                async function placeDraggableMarker(lat, lng) {
                    if (!window.google || !google.maps.importLibrary) {
                        console.error("Google Maps library not loaded.");
                        return;
                    }
                    const {
                        AdvancedMarkerElement
                    } = await google.maps.importLibrary("marker");
                    if (window.gmarker) {
                        window.gmarker.position = {
                            lat,
                            lng
                        };
                    } else {
                        window.gmarker = new AdvancedMarkerElement({
                            position: {
                                lat,
                                lng
                            },
                            map: window.gmap,
                            gmpDraggable: false
                        });
                    }
                    var latEl = document.getElementById('latitude'),
                        lngEl = document.getElementById('longitude');
                    if (latEl) latEl.value = lat;
                    if (lngEl) lngEl.value = lng;
                }
                async function geocodeAndCenterMap() {
                    const addressString = [document.getElementById('barangay_select').value, document
                        .getElementById('city_select').value, document.getElementById('province_select')
                        .value, document.getElementById('region_select').value
                    ].filter(Boolean).join(', ');
                    let zoomLevel = 6;
                    if (document.getElementById('province_select').value) zoomLevel = 9;
                    if (document.getElementById('city_select').value) zoomLevel = 12;
                    if (document.getElementById('barangay_select').value) zoomLevel = 15;
                    if (!addressString) return;
                    try {
                        const resp = await window.geocoder.geocode({
                            address: addressString,
                            componentRestrictions: {
                                country: 'PH'
                            }
                        });
                        if (resp && resp.results && resp.results[0]) {
                            const loc = resp.results[0].geometry.location;
                            window.gmap.panTo(loc);
                            window.gmap.setZoom(zoomLevel);
                            await placeDraggableMarker(loc.lat(), loc.lng());
                        }
                    } catch (error) {
                        console.error("Geocoding failed:", error);
                    }
                }

                /* ======== NOTE modal helpers (unchanged behaviour) ======== */
                var currentBusinessId, currentProductId, currentNoteButton;
                window.openNoteModal = window.openNoteModal || function(businessId, productId, button) {
                    currentNoteButton = button;
                    currentBusinessId = businessId;
                    currentProductId = productId;
                    const selector =
                        `.item-note-field[data-business-id="${businessId}"][data-product-id="${productId}"]`;
                    document.getElementById("noteTextarea").value = document.querySelector(selector)
                        ?.value || "";
                    new bootstrap.Modal(document.getElementById("noteModal")).show();
                };
                window.saveNote = window.saveNote || function() {
                    const noteValue = document.getElementById("noteTextarea").value.trim();
                    const selector =
                        `.item-note-field[data-business-id="${currentBusinessId}"][data-product-id="${currentProductId}"]`;
                    const field = document.querySelector(selector);
                    if (field) field.value = noteValue;
                    if (noteValue) {
                        currentNoteButton.innerHTML = '<i class="fa-solid fa-note-sticky"></i>';
                        currentNoteButton.classList.add("btn-warning", "text-dark");
                        currentNoteButton.classList.remove("btn-outline-secondary");
                    } else {
                        currentNoteButton.innerHTML = '<i class="fa-regular fa-note-sticky"></i>';
                        currentNoteButton.classList.add("btn-outline-secondary");
                        currentNoteButton.classList.remove("btn-warning", "text-dark");
                    }
                    bootstrap.Modal.getInstance(document.getElementById("noteModal")).hide();
                };

                {{-- ========================================================= --}}
                {{-- ============ MODIFIED ADDRESS SCRIPT BLOCK ============== --}}
                {{-- ========================================================= --}}

                /* ======== ADDRESS LOGIC (from Cart) ======== */
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

                // This is the new part that wraps the logic
                whenAddressesReady(() => {
                    if (!regionSelect) return;
                    populateSelect(regionSelect, Object.keys(philippineAddresses).sort(),
                        'Select Region');
                    if (regionSelect) regionSelect.disabled = true; // RE-DISABLE

                    regionSelect.addEventListener('change', () => {
                        if (provinceSelect) resetSelect(provinceSelect, 'Select Province');
                        if (citySelect) resetSelect(citySelect, 'Select City');
                        if (barangaySelect) resetSelect(barangaySelect, 'Select Barangay');
                        if (postalCodeInput) postalCodeInput.value = '';

                        const regVal = regionSelect.value;
                        if (regVal && philippineAddresses[regVal]?.province_list &&
                            provinceSelect) {
                            populateSelect(provinceSelect, Object.keys(philippineAddresses[
                                    regVal]
                                .province_list).sort(), 'Select Province');
                            if (provinceSelect) provinceSelect.disabled = true; // RE-DISABLE
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
                            if (regVal && provVal && philippineAddresses[regVal]?.province_list
                                ?.[
                                    provVal
                                ]?.municipality_list) {
                                populateSelect(citySelect, Object.keys(philippineAddresses[
                                            regVal]
                                        .province_list[provVal].municipality_list).sort(),
                                    'Select City');
                                if (citySelect) citySelect.disabled = true; // RE-DISABLE
                            }
                            if (typeof geocodeAndCenterMap === 'function')
                                geocodeAndCenterMap();
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
                                const cityData = philippineAddresses[regVal].province_list[
                                        provVal]
                                    .municipality_list[cityVal];
                                if (cityData?.barangay_list && barangaySelect) populateSelect(
                                    barangaySelect, cityData.barangay_list.sort(),
                                    'Select Barangay'
                                ); // This ENABLES barangay
                                if (postalCodeInput) postalCodeInput.value = cityData
                                    ?.postal_code ||
                                    '';
                            }
                            if (typeof geocodeAndCenterMap === 'function')
                                geocodeAndCenterMap();
                        });
                    }

                    if (barangaySelect) {
                        barangaySelect.addEventListener('change', () => {
                            if (typeof geocodeAndCenterMap === 'function')
                                geocodeAndCenterMap();
                        });
                    }
                });
                /* ======== END OF ADDRESS LOGIC ======== */

                /* ======== Payment UI (unchanged) ======== */
                const totalAdvanceRequired = {{ $total_advance_required ?? 0 }};
                const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
                const submitButton = document.querySelector('#checkoutForm button[type="submit"]');
                const codInfoText = document.getElementById('cod-info-text');
                const payFullOption = document.getElementById('pay_full');
                const payAdvanceOption = document.getElementById('pay_advance');

                function updatePaymentUI() {
                    const selectedPaymentMethod = document.querySelector(
                        'input[name="payment_method"]:checked');
                    if (!selectedPaymentMethod) return;
                    const methodName = selectedPaymentMethod.dataset.methodName || '';
                    const isCod = methodName.includes('cash on delivery') || methodName.includes('cod');
                    submitButton.textContent = 'Place Pre-Order';
                    if (totalAdvanceRequired > 0) {
                        if (isCod) {
                            codInfoText.classList.remove('d-none');
                            if (payFullOption) payFullOption.disabled = true;
                            if (payAdvanceOption) payAdvanceOption.checked = true;
                            submitButton.textContent = 'Proceed to Pay Advance';
                        } else {
                            codInfoText.classList.add('d-none');
                            if (payFullOption) payFullOption.disabled = false;
                            submitButton.textContent = 'Proceed to Payment';
                        }
                    } else if (isCod) {
                        submitButton.textContent = 'Place COD Order';
                    } else {
                        submitButton.textContent = 'Proceed to Payment';
                    }
                }
                paymentMethodRadios.forEach(r => r.addEventListener('change', updatePaymentUI));
                document.querySelectorAll('input[name="payment_option"]').forEach(r => r.addEventListener(
                    'change', updatePaymentUI));
                updatePaymentUI();

                /* ======== Date picker / calendar / delivery time ======== */
                const scheduleModalEl = document.getElementById('scheduleModal');
                const scheduleModal = new bootstrap.Modal(scheduleModalEl);
                const calendarEl = document.getElementById('schedule-calendar');
                const datePickerButton = document.getElementById('date-picker-button');
                const hiddenDateInput = document.getElementById('delivery_date');
                const businessId = document.querySelector('input[name="business_id"]').value;
                const deliveryTimeSelect = document.getElementById('delivery_time');

                // Keep delivery_time disabled initially
                if (deliveryTimeSelect) deliveryTimeSelect.disabled = true;

                // Set min to tomorrow for preorders (prevents selecting today via other means)
                if (hiddenDateInput) {
                    const t = new Date();
                    t.setDate(t.getDate() + 1);
                    const yyyy = t.getFullYear(),
                        mm = String(t.getMonth() + 1).padStart(2, '0'),
                        dd = String(t.getDate()).padStart(2, '0');
                    hiddenDateInput.min = `${yyyy}-${mm}-${dd}`;
                }

                /* ---------- populateForSelectedDate MUST be defined before calendar.create (so select callback sees it) ---------- */
                var lastPopulateAt = 0;

                function canPopulate() {
                    var now = Date.now();
                    if (now - lastPopulateAt < 120) return false;
                    lastPopulateAt = now;
                    return true;
                }

                function populateForSelectedDate() {
                    if (!canPopulate()) return;
                    try {
                        var dateEl = document.getElementById('delivery_date');
                        var select = document.getElementById('delivery_time');
                        if (!dateEl || !select) return;

                        // If no date chosen yet, show placeholder and keep disabled
                        if (!dateEl.value || !dateEl.value.length) {
                            select.innerHTML = '';
                            var placeholder = document.createElement('option');
                            placeholder.value = '';
                            placeholder.text = 'Select delivery time';
                            placeholder.disabled = true;
                            placeholder.selected = true;
                            select.appendChild(placeholder);
                            select.disabled = true;
                            return;
                        }

                        var selectedDate = dateEl.value;
                        var dayKey = weekdayKeyFromYMD(selectedDate);

                        //console.log('[Delivery Time] Selected Date:', selectedDate, 'Day Key:', dayKey);

                        var previousValue = select.value;
                        select.innerHTML = '';
                        var placeholder2 = document.createElement('option');
                        placeholder2.value = '';
                        placeholder2.text = 'Select delivery time';
                        placeholder2.disabled = true;
                        placeholder2.hidden = false;
                        placeholder2.selected = true;
                        select.appendChild(placeholder2);

                        var hours = openingHours[dayKey];
                        //console.log('[Delivery Time] Hours for this day:', hours);

                        if (!hours || hours.is_closed) {
                            //console.warn('[Delivery Time] Vendor is closed on this day.');

                            var optClosed = document.createElement('option');
                            optClosed.value = '';
                            optClosed.text = 'Vendor is closed on ' + (dayKey.charAt(0).toUpperCase() + dayKey
                                .slice(1));
                            optClosed.disabled = true;
                            select.appendChild(optClosed);
                            select.disabled = true;
                            return;
                        }

                        var startMin = parseToMinutes(hours.opens_at);
                        var endMin = parseToMinutes(hours.closes_at);
                        //console.log('[Delivery Time] Parsed Minutes -> Start:', startMin, 'End:', endMin);

                        if (startMin === null || endMin === null) {
                            //console.error('[Delivery Time] Failed to parse start/end times.');

                            var optErr = document.createElement('option');
                            optErr.value = '';
                            optErr.text = 'Invalid vendor hours';
                            optErr.disabled = true;
                            select.appendChild(optErr);
                            select.disabled = true;
                            return;
                        }

                        var todayYmd = localTodayYMD();
                        var nowMin = nowLocalMinutes();

                        if (selectedDate === todayYmd) {
                            // For completeness (shouldn't hit for preorders with min=tomorrow), keep same-day logic
                            if (nowMin < startMin) {
                                var optNotOpen = document.createElement('option');
                                optNotOpen.value = '';
                                optNotOpen.text = 'Vendor not yet open today';
                                optNotOpen.disabled = true;
                                select.appendChild(optNotOpen);
                                select.disabled = true;
                                return;
                            }
                            if (nowMin >= endMin) {
                                var optClosedNow = document.createElement('option');
                                optClosedNow.value = '';
                                optClosedNow.text = 'Vendor already closed today';
                                optClosedNow.disabled = true;
                                select.appendChild(optClosedNow);
                                select.disabled = true;
                                return;
                            }
                            var nowPlus = nowMin + 15;
                            var earliest = Math.max(startMin, nowPlus);
                            var allSlots = buildSlots(startMin, endMin, 30);
                            var usable = [];
                            for (var i = 0; i < allSlots.length; i++) {
                                var p = allSlots[i].split(':');
                                var minutes = parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
                                if (minutes >= earliest && minutes < endMin) usable.push(allSlots[i]);
                            }
                            if (usable.length === 0) {
                                var optNone = document.createElement('option');
                                optNone.value = '';
                                optNone.text = 'No delivery slots available today';
                                optNone.disabled = true;
                                select.appendChild(optNone);
                                select.disabled = true;
                                return;
                            }
                            for (var j = 0; j < usable.length; j++) {
                                var slot = usable[j];
                                var opt = document.createElement('option');
                                opt.value = slot + ':00';
                                opt.text = to12HourLabel(slot);
                                select.appendChild(opt);
                            }
                            if (previousValue) {
                                try {
                                    for (var k = 0; k < select.options.length; k++) {
                                        if (select.options[k].value === previousValue) {
                                            select.selectedIndex = k;
                                            break;
                                        }
                                    }
                                } catch (e) {}
                            }
                            select.disabled = false;
                            return;
                        }

                        //console.log('[Delivery Time] Building slots for future date...');

                        // Future date path (preorder)
                        var allSlotsFuture = buildSlots(startMin, endMin, 30);

                        //console.log('[Delivery Time] Built slots:', allSlotsFuture);

                        for (var m = 0; m < allSlotsFuture.length; m++) {
                            var s = allSlotsFuture[m];
                            var o = document.createElement('option');
                            o.value = s + ':00';
                            o.text = to12HourLabel(s);
                            select.appendChild(o);
                        }
                        if (previousValue) {
                            try {
                                for (var k2 = 0; k2 < select.options.length; k2++) {
                                    if (select.options[k2].value === previousValue) {
                                        select.selectedIndex = k2;
                                        break;
                                    }
                                }
                            } catch (e) {}
                        }
                        select.disabled = false;
                    } catch (err) {
                        //console.error('[delivery-time] populate error', err);
                    }
                }

                /* ---------- Create calendar AFTER populateForSelectedDate is defined ---------- */
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    height: 'auto',
                    headerToolbar: {
                        left: 'prev',
                        center: 'title',
                        right: 'next'
                    },
                    selectable: true,
                    dayCellDidMount: function(info) {
                        // Disable past dates visually
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        if (info.date < today) {
                            info.el.classList.add('fc-day-disabled');
                        }
                    },
                    eventContent: function(arg) {
                        const evt = arg.event;
                        const props = evt.extendedProps || {};
                        const color = props.indicatorColor || evt.indicatorColor || '#6c757d';
                        const state = props.state || evt.state || 'available';
                        const currentOrders = props.currentOrders ?? evt.currentOrders ?? 0;
                        const maxOrders = props.maxOrders ?? evt.maxOrders ?? 0;
                        const remaining = Math.max(maxOrders - currentOrders, 0);

                        let bgColor = color;
                        if (state === 'full' || state === 'booked') bgColor = '#dc3545';
                        else if (state === 'available') bgColor = '#198754';
                        else if (state === 'past') bgColor = '#6c757d';

                        return {
                            html: `
    <div style="
      background:${bgColor};
      color:white;
      border-radius:4px;
      font-size:0.7rem;
      line-height:1.2;
      text-align:center;
      padding:3px 0;">
      ${remaining > 0 ? `${remaining} slot${remaining===1?'':'s'} left` : 'Full'}
    </div>`
                        };
                    },
                    eventDidMount: function(info) {
                        const props = info.event.extendedProps || {};
                        const tooltip =
                            `${props.currentOrders ?? 0}/${props.maxOrders ?? 0} orders\n${props.state === 'available' ? 'Slots open' : 'Fully booked'}`;
                        info.el.setAttribute('title', tooltip);
                    },
                    selectAllow: function(selectInfo) {
                        const selectedDate = selectInfo.start;
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        const tomorrow = new Date(today);
                        tomorrow.setDate(today.getDate() + 1);

                        // Prevent past or today
                        if (selectedDate < tomorrow) return false;

                        // Then check for availability event
                        const eventOnDay = calendar.getEvents().find(ev =>
                            ev.start.toDateString() === selectedDate.toDateString()
                        );
                        if (!eventOnDay) {
                            toastr.info('Pre-orders start from future available dates.');
                            return false;
                        }
                        if (eventOnDay.extendedProps && eventOnDay.extendedProps.state === 'full') {
                            toastr.warning('This date is fully booked and cannot be selected.');
                            return false;
                        }
                        return true;
                    },
                    select: function(selectionInfo) {
                        const selectedDate = selectionInfo.start;
                        const year = selectedDate.getFullYear();
                        const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
                        const day = String(selectedDate.getDate()).padStart(2, '0');
                        hiddenDateInput.value = `${year}-${month}-${day}`;

                        const friendlyDate = selectedDate.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        datePickerButton.textContent = `Selected: ${friendlyDate}`;
                        datePickerButton.classList.remove('is-invalid');
                        datePickerButton.classList.add('is-valid');

                        scheduleModal.hide();
                        populateForSelectedDate();
                    },
                    events: `/api/customer-schedule/${businessId}/availability`
                });

                // show modal & render calendar when opened
                datePickerButton?.addEventListener('click', function() {
                    scheduleModal.show();
                });
                scheduleModalEl?.addEventListener('shown.bs.modal', function() {
                    calendar.render();
                    calendar.updateSize();
                });

                {{-- ========================================================= --}}
                {{-- ============= MODIFIED SUBMIT HANDLER BLOCK ============= --}}
                {{-- ========================================================= --}}
                /* ---------- Form submit validation (MODIFIED) ---------- */
                var form = document.getElementById("checkoutForm");
                if (form) {
                    form.addEventListener("submit", function(e) {
                        e.preventDefault(); // Prevent submit immediately
                        var isValid = true;
                        form.querySelectorAll(
                                "input[required]:not([type='hidden']), select[required]:not(:disabled)")
                            .forEach(function(i) {
                                i.classList.remove("is-invalid");
                                if (!i.value.trim()) {
                                    i.classList.add("is-invalid");
                                    isValid = false;
                                }
                            });
                        if (!hiddenDateInput.value) {
                            datePickerButton.classList.add('is-invalid');
                            isValid = false;
                        }

                        var paymentGroup = document.querySelector('.payment-group');
                        var paymentLabel = document.querySelector('.payment-label');
                        paymentLabel.classList.remove('text-danger');
                        if (!paymentGroup.querySelector('input[type="radio"]:checked')) {
                            isValid = false;
                            paymentLabel.classList.add('text-danger');
                        }

                        if (!isValid) {
                            toastr.error(
                                'Please fill all required fields, including a valid delivery date and payment method.'
                            );
                            window.scrollTo({
                                top: 0,
                                behavior: 'smooth'
                            });
                            return; // Stop if invalid
                        }

                        // ADDED: Re-enable address fields just before submit
                        try {
                            document.getElementById('region_select').disabled = false;
                            document.getElementById('province_select').disabled = false;
                            document.getElementById('city_select').disabled = false;
                            // barangay_select is already enabled, so no need to touch it
                        } catch (err) {
                            console.error("Failed to re-enable address fields", err);
                        }
                        // END OF ADDED BLOCK

                        submitButton.disabled = true;
                        submitButton.innerHTML =
                            `<span class="spinner-border spinner-border-sm"></span> Processing...`;
                        form.submit(); // Now submit the form
                    });
                }

                // initial populate (keeps time disabled unless a date already exists)
                setTimeout(populateForSelectedDate, 40);

                // wire up delivery_time triggers
                hiddenDateInput?.addEventListener('change', populateForSelectedDate);
                if (deliveryTimeSelect) {
                    deliveryTimeSelect.addEventListener('focus', function() {
                        if (deliveryTimeSelect.disabled || deliveryTimeSelect.options.length <= 1)
                            populateForSelectedDate();
                    });
                }

            }); // end DOMContentLoaded
        })();
    </script>


@endsection
