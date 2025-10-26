@extends('layouts/contentNavbarLayout')

@section('title', 'Order History')

@section('content')

    <!-- Toastr from session -->
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
            <h4 class="fw-bold mb-4">Order History</h4>

            {{-- server validation errors --}}
            @if ($errors->any())
                <div class="alert alert-danger rounded-4 shadow-sm mb-4">
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
                    <h4 class="fw-bold mb-2">No Orders Yet</h4>
                    <p class="text-muted mb-3">It looks quiet here... Start shopping and your orders will appear here!</p>
                </div>
            @else
                <div class="row g-4">
                    @foreach ($orders as $order)
                        @php
                            // Calculate order number (keeps same numbering across pagination)
                            $orderNumber =
                                $totalOrders - (($orders->currentPage() - 1) * $orders->perPage() + $loop->index);

                            // Payment display text & style
                            $paymentStatus = $order->paymentDetails->payment_status ?? 'Pending';
                            $paymentMethodName =
                                $order->paymentDetails->paymentMethod->method_name ??
                                ($order->paymentMethod->method_name ?? null);

                            // small helper to show "Paid via" text when appropriate
                            $paymentLabel =
                                $paymentStatus === 'Paid' || $paymentStatus === 'Completed'
                                    ? 'Paid via ' . ($paymentMethodName ?? 'N/A')
                                    : $paymentStatus . ($paymentMethodName ? ': ' . $paymentMethodName : '');

                            // reuse existing computed order_status or fallback
                            $displayStatus = $order->order_status_label ?? ($order->order_status ?? 'Pending');
                        @endphp

                        <div class="col-12 col-md-6">
                            <div class="card order-card shadow-sm border-0 rounded-4 overflow-hidden">

                                <!-- Header -->
                                <div
                                    class="card-header bg-white border-0 p-4 d-flex align-items-start justify-content-between">
                                    <div style="min-width:0;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold text-dark">Order #{{ $orderNumber }}</span>
                                            @if ($order->items->where('is_pre_order', true)->count() > 0)
                                                <span class="badge bg-warning-subtle text-dark ms-2">Preorder</span>
                                            @endif
                                        </div>
                                        <div class="small text-muted mt-2">Placed on
                                            {{ $order->created_at->format('M d, Y h:i A') }}</div>
                                    </div>

                                    <div class="text-end" style="min-width:150px;">
                                        @php
                                            // Calculate order number (keeps same numbering across pagination)
                                            $orderNumber =
                                                $totalOrders -
                                                (($orders->currentPage() - 1) * $orders->perPage() + $loop->index);

                                            // Payment display text & style
                                            $paymentStatus = $order->paymentDetails->payment_status ?? 'Pending';
                                            $paymentMethodName =
                                                $order->paymentDetails->paymentMethod->method_name ??
                                                ($order->paymentMethod->method_name ?? null);

                                            // small helper to show "Paid via" text when appropriate
                                            $paymentLabel =
                                                $paymentStatus === 'Paid' || Str::lower($paymentStatus) === 'paid'
                                                    ? 'Paid via ' . ($paymentMethodName ?? 'N/A')
                                                    : $paymentStatus .
                                                        ($paymentMethodName ? ': ' . $paymentMethodName : '');

                                            // --- derive order status from items (case-insensitive, robust) ---
                                            $itemStatuses = $order->items
                                                ->pluck('order_item_status')
                                                ->filter() // remove nulls
                                                ->map(function ($s) {
                                                    return trim(Str::lower($s));
                                                });

                                            if ($itemStatuses->isEmpty()) {
                                                $displayStatus = 'Pending';
                                            } elseif (
                                                $itemStatuses->every(function ($s) {
                                                    return $s === 'completed' || $s === 'cancelled';
                                                })
                                            ) {
                                                $displayStatus = 'Completed';
                                            } elseif ($itemStatuses->contains('preparing')) {
                                                $displayStatus = 'Preparing';
                                            } elseif (
                                                $itemStatuses->contains('for delivery') ||
                                                $itemStatuses->contains('for_delivery')
                                            ) {
                                                $displayStatus = 'For Delivery';
                                            } elseif (
                                                $itemStatuses->contains('cancelled') ||
                                                $itemStatuses->contains('canceled')
                                            ) {
                                                $displayStatus = 'Cancelled';
                                            } elseif ($itemStatuses->contains('pending')) {
                                                $displayStatus = 'Pending';
                                            } else {
                                                // fallback to any possible computed label on the model, else Pending
                                                $displayStatus =
                                                    $order->order_status_label ?? ($order->order_status ?? 'Pending');
                                            }
                                        @endphp


                                        <span
                                            class="status-badge d-inline-block px-3 py-2 rounded-pill fs-7 fw-semibold
                                            @if ($displayStatus === 'Pending' || Str::contains($displayStatus, 'Pending')) badge-pending
                                            @elseif ($displayStatus === 'Preparing' || Str::contains($displayStatus, 'Preparing')) badge-preparing
                                            @elseif ($displayStatus === 'For Delivery') badge-delivery
                                            @elseif ($displayStatus === 'Completed') badge-completed
                                            @elseif ($displayStatus === 'Cancelled') badge-cancelled @endif">
                                            {{ $displayStatus }}
                                        </span>

                                        <div class="small text-muted mt-2">
                                            {{ $paymentLabel }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Body -->
                                <div class="card-body p-4">
                                    <!-- Vendor & Delivery -->
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

                                    <!-- Items -->
                                    <h6 class="fw-bold mb-3">Items</h6>
                                    <div class="table-responsive mb-4">
                                        <table class="table table-borderless align-middle mb-0">
                                            <thead class="bg-light">
                                                <tr>
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

                                    <!-- Total -->
                                    <div class="border-top pt-3 d-flex justify-content-end">
                                        <div class="text-end">
                                            <p class="mb-1 text-muted small">Total:</p>
                                            <h5 class="fw-bold text-primary mb-0">
                                                ₱{{ number_format($order->total, 2) }}
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    <style>
        .main-content-area {
            background: #ffffff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 6px 20px rgba(14, 30, 37, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
            margin-bottom: 2rem;
        }

        .order-card {
            border-radius: 12px;
        }

        .status-badge {
            min-width: 140px;
            display: inline-block;
            text-align: center;
        }

        /* badge styles */
        .badge-pending {
            background: #fff3db;
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

        .table thead tr th {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
        }

        .table tbody tr td {
            vertical-align: middle;
            font-size: 0.95rem;
        }

        /* feedback button */
        .btn-outline-primary {
            border-radius: 50px;
            padding: .45rem .9rem;
            font-weight: 600;
        }
    </style>
@endsection
