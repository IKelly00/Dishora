@extends('layouts/contentNavbarLayout')

@section('title', 'Pre-Orders')

@section('content')
    <div class="main-content-area order-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Pre-Orders</h5>

            <div class="d-flex flex-wrap gap-2">
                @php $statuses = ['All', 'Pending', 'Preparing', 'For Delivery', 'Completed', 'Cancelled']; @endphp
                @foreach ($statuses as $filterStatus)
                    <a href="{{ route('vendor.orders-preorder', ['status' => $filterStatus, 'date_from' => request('date_from'), 'date_to' => request('date_to')]) }}"
                        class="badge rounded-pill px-3 py-2 text-decoration-none {{ ($statusFilter ?? 'All') === $filterStatus ? 'bg-primary text-white' : 'bg-light text-dark border' }}">
                        {{ $filterStatus }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Date Range Filter --}}
        <div class="row mb-3">
            <div class="col-12">
                <form method="GET" action="{{ route('vendor.orders-preorder') }}"
                    class="d-flex gap-2 align-items-end flex-wrap">
                    <input type="hidden" name="status" value="{{ request('status', 'All') }}">
                    <div class="form-group mb-0">
                        <label for="date_from" class="form-label small text-muted mb-1">From Date</label>
                        <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                            value="{{ request('date_from') }}" max="{{ date('Y-m-d') }}">
                    </div>

                    <div class="form-group mb-0">
                        <label for="date_to" class="form-label small text-muted mb-1">To Date</label>
                        <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                            value="{{ request('date_to') }}" max="{{ date('Y-m-d') }}">
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
                    @if (request('date_from') || request('date_to'))
                        <a href="{{ route('vendor.orders-preorder', ['status' => request('status', 'All')]) }}"
                            class="btn btn-outline-secondary btn-sm"><i class="fa fa-times"></i> Clear Dates</a>
                    @endif
                </form>
            </div>
        </div>

        @if (request('date_from') || request('date_to'))
            <div class="alert alert-info py-2 mb-3">
                <small>
                    <i class="fa fa-info-circle"></i> Showing orders
                    @if (request('date_from'))
                        from <strong>{{ \Carbon\Carbon::parse(request('date_from'))->format('M d, Y') }}</strong>
                    @endif
                    @if (request('date_to'))
                        to <strong>{{ \Carbon\Carbon::parse(request('date_to'))->format('M d, Y') }}</strong>
                    @endif
                </small>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Order Date</th>
                        <th>Payment</th>
                        <th>Preorder Status</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($preorderOrders as $row)
                        <tr>
                            <td>{{ $row->customer_name }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->order_date)->format('d/m/Y - h:i A') }}</td>
                            <td>{{ $row->payment_method }}</td>
                            <td>
                                @php
                                    $statusLabel = trim($row->preorder_status ?? ($row->status ?? 'Pending'));
                                    $norm = strtolower($statusLabel);
                                @endphp
                                @php
                                    $badgeClass = 'badge-pending';
                                    if ($norm === 'pending') {
                                        $badgeClass = 'badge-pending';
                                    } elseif ($norm === 'preparing') {
                                        $badgeClass = 'badge-preparing';
                                    } elseif ($norm === 'for delivery' || $norm === 'for_delivery') {
                                        $badgeClass = 'badge-delivery';
                                    } elseif ($norm === 'completed') {
                                        $badgeClass = 'badge-completed';
                                    } elseif ($norm === 'cancelled' || $norm === 'canceled') {
                                        $badgeClass = 'badge-cancelled';
                                    } elseif (str_contains($norm, 'paid')) {
                                        $badgeClass = 'badge-completed';
                                    }
                                @endphp
                                <span class="status-badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                            </td>

                            <td style="min-width:220px;">
                                <form action="{{ route('preorders.updateStatus', $row->id) }}" method="POST"
                                    class="status-form d-inline-block me-2">
                                    @csrf @method('PATCH')
                                    <select name="status" class="form-select form-select-sm status-dropdown"
                                        data-current="{{ $row->status }}">
                                        @php $statuses = ['Pending','Preparing','For Delivery','Completed','Cancelled']; @endphp
                                        @foreach ($statuses as $s)
                                            <option value="{{ $s }}" @selected($row->status === $s)>
                                                {{ $s }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>

                            <td>
                                @php
                                    // JSON-encode row safely for attribute
                                    $orderJsonAttr = json_encode(
                                        $row,
                                        JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_TAG |
                                            JSON_HEX_AMP |
                                            JSON_UNESCAPED_SLASHES,
                                    );
                                @endphp
                                <button type="button" class="btn btn-sm btn-upload btn-view-preorder"
                                    data-order='{!! $orderJsonAttr !!}'>
                                    <i class="fa fa-eye me-1"></i> View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">
                                @if (request('date_from') || request('date_to'))
                                    No preorders found for the selected date range.
                                @else
                                    No preorders found.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- PREORDER DETAIL MODAL -->
    <div class="modal fade" id="preorderDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content modern-modal">
                <div class="modal-header modern-header">
                    <div>
                        <h5 id="md-title" class="mb-0">Preorder Details</h5>
                        {{-- <small id="md-sub-info" class="text-muted"></small> --}}
                    </div>
                    <div>
                        <span id="md-status-badge" class="status-pill"></span>
                    </div>
                </div>

                <div class="modal-body modern-body">
                    <div class="md-grid">
                        <!-- LEFT -->
                        <aside class="md-side">
                            {{-- <div class="md-image-wrap">
                                <img id="md-food-image" src="{{ asset('images/no-image.png') }}" alt="Food image"
                                    class="md-food-image">
                            </div> --}}

                            <div class="md-info">
                                <div class="info-row"><strong>Customer</strong>
                                    <div class="text-muted" id="md-customer-name">-</div>
                                </div>
                                <div class="info-row"><strong>Order Date</strong>
                                    <div class="text-muted" id="md-order-date">-</div>
                                </div>
                                <div class="info-row"><strong>Delivery</strong>
                                    <div class="text-muted" id="md-delivery-date">-</div>
                                </div>
                                <div class="info-row"><strong>Payment</strong>
                                    <div class="text-muted" id="md-payment">-</div>
                                </div>
                            </div>

                            {{-- <div class="order-note mt-3">
                                <strong>Notes</strong>
                                <div id="md-notes" class="small text-muted" style="white-space:pre-wrap">-</div>
                            </div> --}}

                        </aside>

                        <!-- RIGHT -->
                        <section class="md-main">
                            <div class="items-card">
                                <ul id="md-items-list" class="list-group mb-0"></ul>

                                <div class="receipt-summary">
                                    <div class="d-flex justify-content-between">
                                        <span>Items Total</span>
                                        <strong id="md-items-total">₱0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Advance Paid</span>
                                        <strong id="md-advance-paid">₱0.00</strong>
                                    </div>
                                    <hr class="my-2" />
                                    <div class="d-flex justify-content-between amount-due">
                                        <span>Amount Due</span>
                                        <strong id="md-amount-due" class="text-danger">₱0.00</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 d-grid gap-2">
                                <a id="md-receipt-link" href="#" target="_blank"
                                    class="btn btn-outline-secondary d-none"><i class="fa fa-file-alt me-2"></i> Open
                                    Receipt</a>
                            </div>
                        </section>

                    </div>
                </div>

                <div class="modal-footer modern-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- NOTE MODAL -->
    <div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="noteModalLabel">Order Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="noteModalBody"></div>
            </div>
        </div>
    </div>

    <!-- CONFIRM STATUS CHANGE MODAL -->
    <div class="modal fade" id="confirmStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Status Change</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="statusConfirmMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmStatusBtn">Yes, Change Status</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* container card */
        .main-content-area {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 6px 20px rgba(14, 30, 37, .06);
            border: 1px solid rgba(0, 0, 0, .04);
        }

        .order-card {
            border-radius: 12px;
        }

        .status-badge,
        #md-status-badge {
            min-width: 140px;
            display: inline-block;
            text-align: center;
            padding: .4rem .6rem;
            border-radius: 999px;
            font-weight: 600
        }

        .badge-pending {
            background: #fff2d9;
            color: #b86b00
        }

        .badge-preparing {
            background: #e9f7ff;
            color: #0b76c6
        }

        .badge-delivery {
            background: #e8f3ff;
            color: #1a66d0
        }

        .badge-completed {
            background: #e9fbf1;
            color: #1b8a46
        }

        .badge-cancelled {
            background: #ffeceb;
            color: #b33a23
        }

        .btn-upload {
            background: linear-gradient(180deg, #f7b955 0%, #f0a93a 100%);
            color: #fff;
            padding: 8px 14px;
            border-radius: 10px;
            border: none;
            font-weight: 700;
            box-shadow: 0 6px 14px rgba(240, 169, 58, .12)
        }

        .btn-upload:hover {
            transform: translateY(-2px)
        }

        .table thead tr th {
            font-size: .78rem;
            letter-spacing: .4px;
            font-weight: 700;
            color: #6b7280
        }

        .modern-modal {
            border-radius: 14px;
            overflow: hidden;
            border: none;
            background: #fff;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .08)
        }

        .modern-header {
            background: #fafbfc;
            border-bottom: 1px solid #f0f0f0;
            padding: 1.25rem 1.5rem !important;
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .modern-header h5 {
            font-weight: 700;
            font-size: 1.05rem;
            color: #1f2937
        }

        .modern-body {
            padding: 1.25rem 1.5rem
        }

        .md-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.5rem
        }

        .md-side {
            background: #f9fafb;
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid #f2f4f7
        }

        .md-food-image {
            width: 100%;
            max-width: 240px;
            height: 180px;
            object-fit: cover;
            border-radius: 10px;
            margin: 0 auto 1rem;
            display: block;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .05)
        }

        .md-info .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: .6rem;
            font-size: .9rem
        }

        .order-note {
            background: #fffdf4;
            border: 1px solid #fff1c0;
            color: #7a5a11;
            border-radius: 10px;
            padding: .75rem;
            margin-top: 1rem
        }

        .items-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            padding: 0;
            overflow: hidden;
        }

        #md-items-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        /* Items list row (single line) */
        #md-items-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 18px;
            border-bottom: 1px solid #f0f0f0;
        }

        .item-info {
            display: flex;
            align-items: center;
            gap: 12px;
            /* space between icon and text */
            min-width: 0;
            /* allow truncation if needed */
        }

        #md-items-list li:last-child {
            border-bottom: none;
        }

        .receipt-summary {
            background: #f9fafb;
            padding: 12px 18px;
            font-size: 14px;
            border-top: 1px solid #e5e7eb;
        }

        .receipt-summary strong {
            font-weight: 600;
        }

        .receipt-summary .amount-due {
            font-weight: 700;
        }

        /* note icon / button */
        .item-note-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e6e9ec;
            background: #fff;
            color: #6b7280;
            cursor: pointer;
            flex-shrink: 0;
        }

        /* remove focus shift (no outline change) */
        .item-note-btn:focus {
            outline: none;
            box-shadow: none;
        }

        /* container for name and inline meta (qty) */
        .item-text {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            /* prevent overflow issues */
        }

        /* product name */
        .item-text .name {
            font-weight: 600;
            color: #111827;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-meta {
            color: #6b7280;
            font-size: .95rem;
            margin-left: 6px;
            white-space: nowrap;
        }

        .item-price {
            font-weight: 700;
            color: #d97706;
            margin-left: 12px;
            flex-shrink: 0;
        }

        .list-group-item.bg-light {
            background: #f3f4f6 !important;
        }

        .small-totals {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #eceff1;
            padding: 12px;
            margin-top: 12px
        }

        #md-receipt-area a {
            color: #0b76c6;
            text-decoration: none
        }

        @media (max-width:768px) {
            .md-grid {
                grid-template-columns: 1fr
            }

            .md-side {
                border: none;
                background: #fff;
                padding: 0
            }

            .md-food-image {
                max-width: 100%;
                height: auto
            }

            #md-items-list .list-item {
                padding: 10px
            }

            .item-note-btn {
                width: 32px;
                height: 32px
            }
        }
    </style>
@endsection

@push('page-script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // helpers
            const priceFmt = (v) => new Intl.NumberFormat(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(Number(v || 0));

            // Confirm status modal logic (reused)//
            // --- Confirm status modal logic with logging + fetch submit ---
            const statusModalEl = document.getElementById('confirmStatusModal');
            const statusModal = new bootstrap.Modal(statusModalEl);
            let targetForm = null,
                targetSelect = null,
                oldValue = null;
            let statusConfirmed = false;

            document.querySelectorAll('.status-dropdown').forEach(select => {
                select.addEventListener('change', function() {
                    targetForm = this.closest('form');
                    targetSelect = this;
                    oldValue = this.getAttribute('data-current') ?? this.value;
                    statusConfirmed = false;

                    const newValue = this.value;
                    let message =
                        `Are you sure you want to change the status from "${oldValue}" to "${newValue}"?`;
                    if (newValue === 'Completed' || newValue === 'Cancelled') {
                        message =
                            `Are you sure you want to change the status from "${oldValue}" to "${newValue}"? Once changed, the status cannot be edited again.`;
                    } else if (newValue === 'For Delivery' || newValue === 'Preparing') {
                        message =
                            `Are you sure you want to change the status from "${oldValue}" to "${newValue}"? After this, the status cannot be cancelled or set back to pending.`;
                    }

                    document.getElementById('statusConfirmMessage').innerText = message;
                    console.log('[preorder] change requested:', {
                        oldValue,
                        newValue,
                        formAction: targetForm?.action
                    });
                    statusModal.show();
                });
            });

            // Helper to read _token from page
            function getCsrfToken() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) return meta.getAttribute('content');
                // fallback to hidden input in form
                const inp = document.querySelector('input[name="_token"]');
                return inp ? inp.value : null;
            }

            document.getElementById('confirmStatusBtn')?.addEventListener('click', async () => {
                if (!targetForm || !targetSelect) {
                    console.warn('[preorder] no target form/select found on confirm');
                    statusModal.hide();
                    return;
                }

                statusConfirmed = true;
                const confirmBtn = document.getElementById('confirmStatusBtn');
                confirmBtn.disabled = true;
                const formAction = targetForm.action;
                const newStatus = targetSelect.value;
                const csrf = getCsrfToken();

                console.log('[preorder] confirming status change ->', {
                    formAction,
                    newStatus,
                    csrfExists: !!csrf
                });

                // build FormData (include _method=PATCH since route expects PATCH)
                const data = new URLSearchParams();
                if (csrf) data.append('_token', csrf);
                data.append('_method', 'PATCH');
                data.append('status', newStatus);

                try {
                    const resp = await fetch(formAction, {
                        method: 'POST', // use POST + _method override to simulate PATCH
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: data.toString(),
                        credentials: 'same-origin'
                    });

                    console.log('[preorder] fetch response status:', resp.status);

                    // try to parse JSON; if server returned redirect/html, fallback
                    let json = null;
                    try {
                        json = await resp.clone().json();
                    } catch (e) {
                        /* not JSON */
                    }

                    if (resp.ok) {
                        console.log('[preorder] update success:', json ?? 'no-json-response');
                        // show success, update UI current data-current attr
                        targetSelect.setAttribute('data-current', newStatus);
                        alert('Status updated successfully.');
                        // optionally close modal
                        statusModal.hide();
                        // optionally reload the page if you prefer server render
                        // location.reload();
                    } else {
                        // show error, revert UI
                        console.error('[preorder] update failed', {
                            status: resp.status,
                            body: json
                        });
                        alert('Failed to update status. See console for details.');
                        // revert select to old value
                        if (targetSelect && oldValue !== null) targetSelect.value = oldValue;
                        statusModal.hide();
                    }
                } catch (err) {
                    console.error('[preorder] fetch error', err);
                    alert('Network error when updating status. Check console.');
                    if (targetSelect && oldValue !== null) targetSelect.value = oldValue;
                    statusModal.hide();
                } finally {
                    confirmBtn.disabled = false;
                    // clear refs (will be re-set on next change)
                    targetForm = null;
                    targetSelect = null;
                    oldValue = null;
                    statusConfirmed = false;
                }
            });

            // ensure modal hidden handler does not revert after confirm (we handle revert in fetch error)
            statusModalEl?.addEventListener('hidden.bs.modal', () => {
                if (!statusConfirmed && targetSelect && oldValue !== null) {
                    targetSelect.value = oldValue;
                }
            });


            // Show note modal when note button clicked
            function openNoteModal(text) {
                const noteBody = document.getElementById('noteModalBody');
                if (noteBody) noteBody.innerText = text || 'No note available.';
                new bootstrap.Modal(document.getElementById('noteModal')).show();
            }

            // populate function (exposed)
            function populatePreorderModal(row) {
                if (!row) return;
                // safe DOM getters
                const el = id => document.getElementById(id);

                // header
                // el('md-sub-info') && (el('md-sub-info').innerText = row.order_date ? 'Placed on ' + (new Date(row
                //     .order_date)).toLocaleString() : '');
                const statusText = (row.preorder_status ?? row.status ?? 'Pending').toString();
                const statusBadge = el('md-status-badge');
                if (statusBadge) {
                    statusBadge.innerText = statusText;
                    statusBadge.className = 'status-pill';
                    const n = statusText.toLowerCase();
                    if (n.includes('pending')) statusBadge.classList.add('badge-pending');
                    else if (n.includes('preparing')) statusBadge.classList.add('badge-preparing');
                    else if (n.includes('for delivery') || n.includes('for_delivery')) statusBadge.classList.add(
                        'badge-delivery');
                    else if (n.includes('completed') || n.includes('paid')) statusBadge.classList.add(
                        'badge-completed');
                    else if (n.includes('cancel')) statusBadge.classList.add('badge-cancelled');
                    else statusBadge.classList.add('badge-pending');
                }

                // image
                // const imgEl = el('md-food-image');
                // const fallback = "{{ asset('images/no-image.png') }}";
                // let foodImg = row.food_image || (row.items && row.items[0] && row.items[0].image_url) || null;
                // if (imgEl) {
                //     if (foodImg && typeof foodImg === 'string') {
                //         if (foodImg.startsWith('http')) imgEl.src = foodImg;
                //         else imgEl.src = window.location.origin + '/' + String(foodImg).replace(/^\/+/, '');
                //     } else imgEl.src = fallback;
                // }

                el('md-customer-name') && (el('md-customer-name').innerText = row.customer_name ?? '-');
                // Format order date without seconds
                if (el('md-order-date')) {
                    let formattedOrderDate = '-';
                    if (row.order_date) {
                        try {
                            const d = new Date(row.order_date);
                            if (!isNaN(d.getTime())) {
                                formattedOrderDate = d.toLocaleString([], {
                                    year: 'numeric',
                                    month: '2-digit',
                                    day: '2-digit',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                            } else {
                                formattedOrderDate = String(row.order_date);
                            }
                        } catch (e) {
                            formattedOrderDate = String(row.order_date);
                        }
                    }
                    el('md-order-date').innerText = formattedOrderDate;
                }


                // Delivery date + time (show time beside date if available)
                if (el('md-delivery-date')) {
                    const rawDate = row.delivery_date ?? null;
                    const rawTime = row.delivery_time ?? null;

                    // helper: try to parse ISO-like datetime or time strings
                    const tryParseTime = (t) => {
                        if (!t && rawDate) {
                            // maybe delivery_date includes time (e.g. "2025-10-25 14:30:00" or ISO)
                            const m = String(rawDate).match(/\b(\d{1,2}:\d{2}(?::\d{2})?)\b/);
                            if (m) t = m[1];
                        }
                        if (!t) return null;
                        t = String(t).trim();

                        // If format is "HH:MM" or "HH:MM:SS"
                        if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(t)) {
                            // create a fixed date so Date parsing works consistently
                            try {
                                const d = new Date(`1970-01-01T${t}`);
                                if (!isNaN(d.getTime())) {
                                    return d.toLocaleTimeString([], {
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                                }
                            } catch (e) {
                                /* fallback */
                            }
                        }

                        // If it's an ISO datetime or contains a date/time
                        try {
                            const d2 = new Date(t);
                            if (!isNaN(d2.getTime())) {
                                return d2.toLocaleTimeString([], {
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                            }
                        } catch (e) {
                            /* fallback */
                        }

                        // otherwise return original string
                        return t;
                    };

                    // Build date string
                    let dateText = '-';
                    if (rawDate) {
                        try {
                            const d = new Date(rawDate);
                            if (!isNaN(d.getTime())) dateText = d.toLocaleDateString();
                            else dateText = String(rawDate).split('T')[0] || String(rawDate);
                        } catch (e) {
                            dateText = String(rawDate);
                        }
                    }

                    const timeText = tryParseTime(rawTime);
                    el('md-delivery-date').innerText = dateText + (timeText ? ', ' + timeText : '');
                }


                el('md-payment') && (el('md-payment').innerText = row.payment_method ?? '-');
                // el('md-notes') && (el('mds-notes').innerText = row.notes ?? '-');

                // items list (stacked)
                const list = el('md-items-list');
                if (!list) {
                    console.warn('md-items-list not found');
                    return;
                }
                // clear list then build items (single-row layout)
                list.innerHTML = '';
                let computedSubtotal = 0;
                (row.items || []).forEach(it => {
                    const qty = parseInt(it.quantity ?? 1);
                    const price = parseFloat(it.price ?? 0);
                    const sub = parseFloat(it.subtotal ?? (price * qty));
                    computedSubtotal += sub;

                    const li = document.createElement('li');
                    li.className = 'list-item';

                    // left area: icon + name + inline qty
                    const left = document.createElement('div');
                    left.className = 'item-info';

                    const noteBtn = document.createElement('button');
                    noteBtn.type = 'button';
                    noteBtn.className = 'item-note-btn';
                    noteBtn.title = it.note ? 'View note' : 'No note';
                    noteBtn.innerHTML = '<i class="fa fa-sticky-note"></i>';
                    noteBtn.addEventListener('click', (e) => {
                        // prevent any reflow / focus outline
                        e.preventDefault();
                        openNoteModal(it.note || 'No note available.');
                    });

                    const textWrap = document.createElement('div');
                    textWrap.className = 'item-text';

                    const nameEl = document.createElement('div');
                    nameEl.className = 'name';
                    nameEl.innerText = it.product_name || 'N/A';

                    const metaEl = document.createElement('div');
                    metaEl.className = 'item-meta';
                    metaEl.innerText = `× ${qty}`;

                    textWrap.appendChild(nameEl);
                    textWrap.appendChild(metaEl);

                    left.appendChild(noteBtn);
                    left.appendChild(textWrap);

                    // right area: price
                    const right = document.createElement('div');
                    right.className = 'item-price';
                    right.innerText = `₱ ${priceFmt(sub)}`;

                    li.appendChild(left);
                    li.appendChild(right);
                    list.appendChild(li);
                });


                // Total row (below items) and breakdown
                const itemsSub = parseFloat(row.items_subtotal ?? computedSubtotal) || computedSubtotal;
                const advancePaid = parseFloat(row.advance_paid ?? row.advance_paid_amount ?? 0) || 0;
                let amountDue = 0;
                if (row.amount_due !== undefined && row.amount_due !== null) {
                    amountDue = parseFloat(row.amount_due) || 0;
                } else {
                    const totalAdvanceRequired = parseFloat(row.total_advance_required ?? 0) || 0;
                    amountDue = Math.max(0, totalAdvanceRequired - advancePaid);
                }
                const finalTotal = parseFloat(row.total ?? itemsSub) || itemsSub;

                // show items subtotal in the "Items Total" element
                el('md-items-total') && (el('md-items-total').innerText = `₱ ${priceFmt(itemsSub)}`);

                // advance paid & amount due (already in markup)
                el('md-advance-paid') && (el('md-advance-paid').innerText = `₱ ${priceFmt(advancePaid)}`);
                el('md-amount-due') && (el('md-amount-due').innerText = `₱ ${priceFmt(amountDue)}`);

                // if you have a grand total element in future, you can set it here.
                // el('md-grand-total') && (el('md-grand-total').innerText = `₱ ${priceFmt(finalTotal)}`);


                // receipt link
                const receiptArea = el('md-receipt-area');
                const receiptLink = el('md-receipt-link');
                if (receiptArea) receiptArea.innerHTML = '';
                if (row.receipt_url) {
                    if (receiptArea) receiptArea.innerHTML =
                        `<a href="${row.receipt_url}" target="_blank">View receipt</a>`;
                    if (receiptLink) {
                        receiptLink.href = row.receipt_url;
                        receiptLink.classList.remove('d-none');
                    }
                } else {
                    if (receiptArea) receiptArea.innerHTML =
                        '<div class="text-muted small">No receipt available.</div>';
                    if (receiptLink) receiptLink.classList.add('d-none');
                }
            }

            // expose helper (optional)
            window.populatePreorderModal = populatePreorderModal;

            // attach view buttons: parse safe JSON and call populate
            document.querySelectorAll('.btn-view-preorder').forEach(btn => {
                btn.addEventListener('click', function() {
                    const raw = this.getAttribute('data-order');
                    if (!raw) return;
                    try {
                        const row = JSON.parse(raw);
                        populatePreorderModal(row);
                        new bootstrap.Modal(document.getElementById('preorderDetailModal')).show();
                    } catch (e) {
                        console.error('Invalid data-order JSON', e);
                    }
                });
            });

            // tooltips (optional)
            [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(function(el) {
                return new bootstrap.Tooltip(el);
            });

        });
    </script>
@endpush
