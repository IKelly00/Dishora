@extends('layouts/contentNavbarLayout')

@section('title', 'Active Orders')

@section('content')
    <div class="main-content-area order-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Active orders</h5>

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
            <th>Order Status</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($activeOrders as $row)
            <tr>
                <!-- 1. Customer Name -->
                <td>{{ $row->customer_name }}</td>
                
                <!-- 2. Order Date -->
                <td>{{ \Carbon\Carbon::parse($row->order_date)->format('d/m/Y - h:i A') }}</td>
                
                <!-- 3. Payment Method -->
                <td>{{ $row->payment_method }}</td>

                <!-- 4. Status Badge -->
                <td>
                    @php
                        $statusLabel = trim($row->status ?? 'Pending');
                        $norm = strtolower($statusLabel);
                        $badgeClass = 'badge-pending';

                        if ($norm === 'pending') {
                            $badgeClass = 'badge-pending';
                        } elseif ($norm === 'preparing') {
                            $badgeClass = 'badge-preparing';
                        } elseif ($norm === 'for delivery' || $norm === 'for_delivery') {
                            $badgeClass = 'badge-delivery';
                        } elseif ($norm === 'completed' || str_contains($norm, 'paid')) {
                            $badgeClass = 'badge-completed';
                        } elseif ($norm === 'cancelled' || $norm === 'canceled') {
                            $badgeClass = 'badge-cancelled';
                        }
                    @endphp
                    <span class="status-badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                </td>

                <!-- 5. Update Status Dropdown -->
                <td style="min-width:220px;">
                    <form action="{{ route('orders.updateStatus', $row->id) }}" method="POST"
                        class="status-form d-inline-block me-2">
                        @csrf @method('PATCH')
                        <!-- Added ID or Class for JS targeting if needed, but standard class is fine -->
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

                <!-- 6. ACTIONS COLUMN -->
                <td>
                    <!-- A. VIEW PROOF BUTTON (Green) -->
                    @if(!empty($row->proof_of_delivery))
                        <a href="{{ asset('proofs/' . $row->proof_of_delivery) }}" 
                           target="_blank" 
                           class="btn btn-sm btn-success mb-0 me-1" 
                           title="View Uploaded Proof">
                            <i class="fa fa-image"></i>
                        </a>
                    @endif
                
                    @php
                        $isForDelivery = strtolower(trim($row->status)) === 'for delivery';
                    @endphp

                    <form action="{{ route('orders.uploadProof', $row->order_id) }}" 
                          method="POST" 
                          enctype="multipart/form-data" 
                          class="d-inline-block me-1 upload-proof-form {{ $isForDelivery ? '' : 'd-none' }}">
                        @csrf
                        <label class="btn btn-sm btn-outline-secondary mb-0" style="cursor: pointer;" 
                               title="{{ !empty($row->proof_of_delivery) ? 'Change Proof' : 'Upload Proof' }}">
                            <i class="fa fa-upload"></i>
                            <input type="file" name="proof_of_delivery" style="display: none;" onchange="this.form.submit()">
                        </label>
                    </form>
                
                    <!-- C. VIEW DETAILS BUTTON (Yellow/Blue) -->
                    @php
                        $orderJsonAttr = json_encode(
                            $row,
                            JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES,
                        );
                    @endphp
                    <button type="button" class="btn btn-sm btn-upload btn-view-order"
                        data-order='{!! $orderJsonAttr !!}'>
                        <i class="fa fa-eye me-1"></i> View
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center">
                    @if (request('date_from') || request('date_to'))
                        No active orders found for the selected date range.
                    @else
                        No active orders found.
                    @endif
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
        </div>
    </div>

    <!-- ORDER DETAIL MODAL -->
    <div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content modern-modal">
                <div class="modal-header modern-header">
                    <div>
                        <h5 id="md-title" class="mb-0">Order Details</h5>
                    </div>
                    <div>
                        <span id="md-status-badge" class="status-pill"></span>
                    </div>
                </div>

                <div class="modal-body modern-body">
                    <div class="md-grid">
                        <!-- LEFT -->
                        <aside class="md-side">
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
                                        <span>Amount Paid</span>
                                        <strong id="md-advance-paid">₱0.00</strong>
                                    </div>

                                    <hr class="my-2" />
                                    <div class="d-flex justify-content-between amount-due">
                                        <span>Amount Due</span>
                                        <strong id="md-amount-due" class="text-danger">₱0.00</strong>
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

            // Confirm status modal logic
            const statusModalEl = document.getElementById('confirmStatusModal');
            const statusModal = new bootstrap.Modal(statusModalEl);
            let targetForm = null,
                targetSelect = null,
                oldValue = null;
            // statusConfirmed indicates whether the latest requested change was applied successfully
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
                    console.log('[order] change requested:', {
                        oldValue,
                        newValue,
                        formAction: targetForm?.action
                    });
                    statusModal.show();
                });
            });

            function getCsrfToken() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) return meta.getAttribute('content');
                const inp = document.querySelector('input[name="_token"]');
                return inp ? inp.value : null;
            }

            document.getElementById('confirmStatusBtn')?.addEventListener('click', async () => {
                if (!targetForm || !targetSelect) {
                    console.warn('[order] no target form/select found on confirm');
                    statusModal.hide();
                    return;
                }

                const confirmBtn = document.getElementById('confirmStatusBtn');
                confirmBtn.disabled = true;
                const formAction = targetForm.action;
                const newStatus = targetSelect.value;
                const csrf = getCsrfToken();

                const data = new URLSearchParams();
                if (csrf) data.append('_token', csrf);
                data.append('_method', 'PATCH');
                data.append('status', newStatus);

                try {
                    const resp = await fetch(formAction, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: data.toString(),
                        credentials: 'same-origin'
                    });

                    let json = null;
                    try {
                        json = await resp.clone().json();
                    } catch (e) {}

                    if (resp.ok) {
                        // mark confirmed BEFORE hiding modal so hidden handler won't revert
                        statusConfirmed = true;

                        // update UI attribute so future changes use this as baseline
                        targetSelect.setAttribute('data-current', newStatus);

                        // optional: update the status badge in the row immediately (improves UX)
                        try {
                            const tr = targetSelect.closest('tr');
                            if (tr) {
                                const badge = tr.querySelector('.status-badge');
                                if (badge) {
                                    badge.textContent = newStatus;
                                    const norm = newStatus.toLowerCase();
                                    badge.classList.remove('badge-pending', 'badge-preparing',
                                        'badge-delivery', 'badge-completed', 'badge-cancelled');
                                    if (norm === 'pending') badge.classList.add('badge-pending');
                                    else if (norm === 'preparing') badge.classList.add(
                                        'badge-preparing');
                                    else if (norm === 'for delivery' || norm === 'for_delivery') badge
                                        .classList.add('badge-delivery');
                                    else if (norm === 'completed' || norm.includes('paid')) badge
                                        .classList.add('badge-completed');
                                    else if (norm === 'cancelled' || norm === 'canceled') badge
                                        .classList.add('badge-cancelled');
                                }
                            }
                        } catch (e) {
                            console.warn('Failed to update row badge immediately', e);
                        }

                        alert('Status updated successfully.');
                        statusModal.hide();
                    } else {
                        console.error('[order] update failed', {
                            status: resp.status,
                            body: json
                        });
                        alert('Failed to update status. See console for details.');
                        // revert select immediately
                        if (targetSelect && oldValue !== null) targetSelect.value = oldValue;
                        statusModal.hide();
                    }
                } catch (err) {
                    console.error('[order] fetch error', err);
                    alert('Network error when updating status. Check console.');
                    if (targetSelect && oldValue !== null) targetSelect.value = oldValue;
                    statusModal.hide();
                } finally {
                    confirmBtn.disabled = false;
                    // DO NOT reset statusConfirmed here — let the hidden handler clear state after modal closes
                }
            });

            // When modal is hidden, if the change wasn't confirmed, revert select;
            // always clear local state here so we don't rely on timing with the fetch.
            statusModalEl?.addEventListener('hidden.bs.modal', () => {
                if (!statusConfirmed && targetSelect && oldValue !== null) {
                    targetSelect.value = oldValue;
                }
                // clear refs
                targetForm = null;
                targetSelect = null;
                oldValue = null;
                statusConfirmed = false;
            });

            // Note modal
            function openNoteModal(text) {
                const noteBody = document.getElementById('noteModalBody');
                if (noteBody) noteBody.innerText = text || 'No note available.';
                new bootstrap.Modal(document.getElementById('noteModal')).show();
            }

            // populate order modal (unchanged)
            function populateOrderModal(row) {
                if (!row) return;
                const el = id => document.getElementById(id);

                console.log('Populating modal with data:', row);

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

                const list = el('md-items-list');
                if (!list) return;
                list.innerHTML = '';
                let computedSubtotal = 0;
                (row.items || []).forEach(it => {
                    const qty = parseInt(it.quantity ?? 1);
                    const price = parseFloat(it.price ?? 0);
                    const sub = parseFloat(it.subtotal ?? (price * qty));
                    computedSubtotal += sub;

                    const li = document.createElement('li');
                    li.className = 'list-item';

                    const left = document.createElement('div');
                    left.className = 'item-info';

                    const noteBtn = document.createElement('button');
                    noteBtn.type = 'button';
                    noteBtn.className = 'item-note-btn';
                    noteBtn.title = it.note ? 'View note' : 'No note';
                    noteBtn.innerHTML = '<i class="fa fa-sticky-note"></i>';
                    noteBtn.addEventListener('click', (e) => {
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

                    const right = document.createElement('div');
                    right.className = 'item-price';
                    right.innerText = `₱ ${priceFmt(sub)}`;

                    li.appendChild(left);
                    li.appendChild(right);
                    list.appendChild(li);
                });

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

                el('md-items-total') && (el('md-items-total').innerText = `₱ ${priceFmt(itemsSub)}`);
                el('md-advance-paid') && (el('md-advance-paid').innerText = `₱ ${priceFmt(advancePaid)}`);
                el('md-amount-due') && (el('md-amount-due').innerText = `₱ ${priceFmt(amountDue)}`);

                const receiptLink = el('md-receipt-link');
                if (row.receipt_url) {
                    if (receiptLink) {
                        receiptLink.href = row.receipt_url;
                        receiptLink.classList.remove('d-none');
                    }
                } else {
                    if (receiptLink) receiptLink.classList.add('d-none');
                }
            }

            window.populateOrderModal = populateOrderModal;

            // attach view buttons
            document.querySelectorAll('.btn-view-order').forEach(btn => {
                btn.addEventListener('click', function() {
                    const raw = this.getAttribute('data-order');
                    if (!raw) return;
                    try {
                        const row = JSON.parse(raw);
                        populateOrderModal(row);
                        new bootstrap.Modal(document.getElementById('orderDetailModal')).show();
                    } catch (e) {
                        console.error('Invalid data-order JSON', e);
                    }
                });
            });

            // tooltips
            [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(function(el) {
                return new bootstrap.Tooltip(el);
            });

        });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Select all status dropdowns in the table
        const statusDropdowns = document.querySelectorAll('.status-dropdown');

        statusDropdowns.forEach(dropdown => {
            dropdown.addEventListener('change', function() {
                // 1. Get the selected value (e.g., "For Delivery")
                const selectedStatus = this.value.trim().toLowerCase();
                
                // 2. Find the parent row (<tr>) of this specific dropdown
                const row = this.closest('tr');

                // 3. Find the Upload Form inside this specific row
                const uploadForm = row.querySelector('.upload-proof-form');

                // 4. Toggle visibility
                if (uploadForm) {
                    if (selectedStatus === 'for delivery') {
                        // Remove 'd-none' to SHOW the button
                        uploadForm.classList.remove('d-none');
                    } else {
                        // Add 'd-none' to HIDE the button
                        uploadForm.classList.add('d-none');
                    }
                }
            });
        });
    });
</script>
@endpush
