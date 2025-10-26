@extends('layouts/contentNavbarLayout')

@section('title', 'Orders')

@section('content')

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

    <div class="container py-5">
        <div class="main-content-area">
            <h4 class="fw-bold mb-4">My Orders</h4>

            @if ($errors->any())
                <div class="alert alert-danger rounded-4 shadow-sm">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($orders->isEmpty())
                <div class="alert alert-light text-center p-5 rounded-4 shadow-sm border">
                    <i class="ri-inbox-2-line display-3 d-block mb-3 text-muted"></i>
                    <h4 class="fw-bold mb-2">No Pending Orders</h4>
                    <p class="text-muted mb-3">You have no orders with pending items right now.</p>
                </div>
            @else
                <div class="row g-4">
                    @foreach ($orders as $order)
                        @php
                            $orderNumber =
                                $totalOrders - (($orders->currentPage() - 1) * $orders->perPage() + $loop->index);
                        @endphp

                        <div class="col-12 col-md-6">
                            <div class="card order-card shadow-sm border-0 rounded-4 overflow-hidden">

                                {{-- Optional Preorder note --}}
                                @if ($order->order_type === 'Preorder' && ($order->has_pending_in_session || $order->has_user_pending_preorders))
                                    <div class="order-note d-flex gap-3 align-items-start p-3">
                                        <i class="ri-alert-line fs-4 text-warning me-1" style="line-height:1"></i>
                                        <div>
                                            <strong class="d-block mb-1">Receipt Required</strong>
                                            <div class="small text-muted">Vendor may not process the order because receipt
                                                is not yet uploaded. Please upload your receipt to complete the pre-order.
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Header --}}
                                <div
                                    class="card-header bg-white border-0 p-4 d-flex align-items-start justify-content-between">
                                    <div class="me-3" style="min-width:0;">
                                        <div class="d-flex align-items-center gap-2">
                                            <h6 class="mb-0 fw-bold text-dark">Order #{{ $orderNumber }}</h6>
                                            @if ($order->order_type === 'Preorder')
                                                <span class="badge bg-warning-subtle text-dark ms-2">Preorder</span>
                                            @endif
                                        </div>
                                        <div class="small text-muted mt-2">Placed on
                                            {{ $order->created_at->format('M d, Y h:i A') }}</div>
                                    </div>

                                    <div class="text-end" style="min-width:140px;">
                                        <span
                                            class="status-badge d-inline-block px-3 py-2 rounded-pill fs-7 fw-semibold
                                        {{ $order->order_status === 'Pending' ? 'badge-pending' : '' }}
                                        {{ $order->order_status === 'Preparing' ? 'badge-preparing' : '' }}
                                        {{ $order->order_status === 'For Delivery' ? 'badge-delivery' : '' }}
                                        {{ $order->order_status === 'Completed' ? 'badge-completed' : '' }}
                                        {{ $order->order_status === 'Cancelled' ? 'badge-cancelled' : '' }}">
                                            {{ $order->order_status_label }}
                                        </span>

                                        <div class="small text-muted mt-2">
                                            {{ optional($order->paymentDetails->first())->payment_status ?? 'Pending' }}
                                        </div>

                                        @php
                                            $paymentMethodName =
                                                optional(optional($order->paymentDetails->first())->paymentMethod)
                                                    ->method_name ??
                                                (optional($order->paymentMethod)->method_name ?? null);
                                        @endphp
                                        @if ($paymentMethodName)
                                            <div class="small text-muted mt-1"><strong>Paid via:</strong>
                                                {{ $paymentMethodName }}</div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Body --}}
                                <div class="card-body p-4">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <div class="text-muted small mb-1 fw-semibold">Vendor</div>
                                            <div class="fw-semibold">{{ $order->business?->business_name ?? 'N/A' }}</div>
                                        </div>
                                        <div class="col-6 text-end">
                                            <div class="text-muted small mb-1 fw-semibold">Delivery Date</div>
                                            <div class="fw-semibold">
                                                {{ \Carbon\Carbon::parse($order->delivery_date)->format('M d, Y') }}</div>
                                        </div>
                                    </div>

                                    <h6 class="fw-bold mb-3">Items</h6>

                                    <div class="table-responsive">
                                        <table class="table table-borderless align-middle mb-0">
                                            <thead>
                                                <tr class="bg-light">
                                                    <th class="px-3 py-2">Product</th>
                                                    <th class="text-center px-3 py-2" style="width:80px">Qty</th>
                                                    <th class="text-end px-3 py-2" style="width:120px">Price</th>
                                                    <th class="text-end px-3 py-2" style="width:130px">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($order->items as $item)
                                                    <tr>
                                                        <td class="px-3 py-3">
                                                            <div class="fw-semibold">{{ $item->product_name }}</div>
                                                            @if (!empty($item->order_item_note))
                                                                <div class="small text-muted">
                                                                    {{ Str::limit($item->order_item_note, 50) }}</div>
                                                            @endif
                                                        </td>
                                                        <td class="text-center px-3 py-3">{{ $item->quantity }}</td>
                                                        <td class="text-end px-3 py-3">
                                                            ₱{{ number_format($item->price_at_order_time, 2) }}</td>
                                                        <td class="text-end px-3 py-3 fw-bold">
                                                            ₱{{ number_format($item->quantity * $item->price_at_order_time, 2) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between border-top pt-3 mt-3">
                                        <div class="d-flex flex-column">
                                            <div class="d-flex gap-2 align-items-center">
                                                @if ($order->order_type === 'Preorder' && ($order->has_pending_in_session || $order->has_user_pending_preorders))
                                                    <a href="{{ route('checkout.preorder.proceed', ['business_id' => $order->business_id]) }}"
                                                        class="btn btn-upload btn-sm me-2">Upload Receipt</a>
                                                @endif

                                                @if ($order->all_pending)
                                                    <form
                                                        action="{{ route('orders.cancel', ['order_id' => $order->order_id]) }}"
                                                        method="POST"
                                                        onsubmit="return confirm('Are you sure you want to cancel this order? This will cancel all pending items.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-cancel btn-sm">Cancel</button>
                                                    </form>
                                                @endif
                                            </div>

                                            {{-- small helper text under action buttons --}}
                                            @if ($order->order_type === 'Preorder' && ($order->has_pending_in_session || $order->has_user_pending_preorders))
                                                <div class="small text-muted mt-2">Upload the receipt to complete the
                                                    pre-order.</div>
                                            @endif
                                        </div>

                                        <div class="text-end">
                                            <div class="small text-muted mb-1">Total:</div>
                                            <div class="fw-bold text-primary fs-5">₱{{ number_format($order->total, 2) }}
                                            </div>
                                        </div>
                                    </div>
                                </div> {{-- end card-body --}}
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-center mt-4">
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    <style>
        /* container card */
        .main-content-area {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 6px 20px rgba(14, 30, 37, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        /* card */
        .order-card {
            border-radius: 12px;
        }

        /* header */
        .status-badge {
            min-width: 140px;
            display: inline-block;
            text-align: center;
        }

        /* badge colors (soft) */
        .badge-pending {
            background: #fff2d9;
            color: #b86b00;
        }

        .badge-preparing {
            background: #e9f7ff;
            color: #0b76c6;
        }

        .badge-delivery {
            background: #e8f3ff;
            color: #1a66d0;
        }

        .badge-completed {
            background: #e9fbf1;
            color: #1b8a46;
        }

        .badge-cancelled {
            background: #ffeceb;
            color: #b33a23;
        }

        /* action buttons */
        .btn-upload {
            background: linear-gradient(180deg, #f7b955 0%, #f0a93a 100%);
            color: #fff;
            padding: 8px 14px;
            border-radius: 10px;
            border: none;
            font-weight: 700;
            box-shadow: 0 6px 14px rgba(240, 169, 58, 0.12);
        }

        .btn-upload:hover {
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: linear-gradient(180deg, #d6863c 0%, #c36d26 100%);
            color: #fff;
            padding: 8px 14px;
            border-radius: 10px;
            border: none;
            font-weight: 700;
            box-shadow: 0 6px 14px rgba(195, 109, 38, 0.12);
        }

        /* preorder note */
        .order-note {
            border-radius: 10px;
            background: linear-gradient(180deg, #fff8e6 0%, #fff3d6 100%);
            border: 1px solid rgba(224, 164, 32, 0.08);
            color: #7a5a11;
            margin: 0.8rem 1rem 0;
        }

        /* table */
        .table thead tr th {
            font-size: 0.78rem;
            letter-spacing: 0.4px;
            font-weight: 700;
        }

        .table tbody tr td {
            padding-top: .9rem;
            padding-bottom: .9rem;
            vertical-align: middle;
        }

        /* responsive tweaks */
        @media (max-width: 576px) {
            .status-badge {
                min-width: auto;
            }

            .order-note {
                margin: .5rem 0;
            }

            .btn-upload,
            .btn-cancel {
                display: block;
                width: 100%;
                margin-bottom: 8px;
            }
        }
    </style>

@endsection
