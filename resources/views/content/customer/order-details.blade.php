@extends('layouts/contentNavbarLayout')

@section('content')
    <div class="container py-5">
        <h2 class="mb-4">Order #{{ $order->order_id }}</h2>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <strong>Status:</strong>
                <span
                    class="badge
                @if (($order->paymentDetails->payment_status ?? '') === 'Completed') bg-success
                @elseif(($order->paymentDetails->payment_status ?? '') === 'Pending') bg-warning text-dark
                @else bg-secondary @endif">
                    {{ $order->paymentDetails->payment_status ?? 'Pending' }}
                </span>
            </div>
            <div class="card-body">
                <p><strong>Vendor:</strong> {{ $order->business?->business_name ?? 'N/A' }}</p>
                <p><strong>Total:</strong> ₱{{ number_format($order->total, 2) }}</p>
                <p><strong>Delivery on:</strong> {{ \Carbon\Carbon::parse($order->delivery_date)->format('M d, Y') }}</p>

                <h6 class="mt-4 fw-bold">Items</h6>
                <ul class="list-group list-group-flush mb-3">
                    @foreach ($order->items as $item)
                        <li class="list-group-item d-flex justify-content-between">
                            <div>
                                <span class="fw-semibold">{{ $item->product_name }}</span><br>
                                {{ $item->quantity }} × ₱{{ number_format($item->price_at_order_time, 2) }}
                                @if ($item->is_pre_order)
                                    <span class="badge bg-warning text-dark ms-2">Preorder</span>
                                @endif
                            </div>
                            <div>
                                ₱{{ number_format($item->quantity * $item->price_at_order_time, 2) }}
                            </div>
                        </li>
                    @endforeach
                </ul>

                <h6 class="fw-bold">Payment Details</h6>
                @if ($order->paymentDetails)
                    <p><strong>Method:</strong> {{ $order->paymentDetails->paymentMethod?->method_name ?? 'N/A' }}</p>
                    <p><strong>Status:</strong> {{ $order->paymentDetails->payment_status }}</p>
                    <p><strong>Amount Paid:</strong> ₱{{ number_format($order->paymentDetails->amount_paid, 2) }}</p>
                @else
                    <p class="text-muted">No payment details recorded.</p>
                @endif
            </div>
        </div>

        <a href="{{ route('customer-order-history') }}" class="btn btn-outline-secondary">
            <i class="ri-arrow-left-line me-1"></i> Back to Order History
        </a>
    </div>
@endsection
