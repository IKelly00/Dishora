@extends('layouts/contentNavbarLayout')

@section('title', 'My Pre-Orders')

@section('content')
    @if (session('success'))
        <script>
            toastr.success("{{ session('success') }}");
        </script>
    @endif

    <div class="container py-4 py-lg-5">
        {{-- ADDED .main-content-area wrapper --}}
        <div class="main-content-area">

            <h4 class="fw-bold mb-4">My Pre-Orders</h4>

            @if ($groupedProducts->isEmpty())
                <div class="alert alert-light text-center p-5 rounded-4 shadow-sm border">
                    <i class="ri-inbox-2-line display-3 d-block mb-3 text-muted"></i>
                    <h4 class="fw-bold mb-2">No Pre-Orders Yet</h4>
                    <p class="text-muted mb-3">Your pre-order list is empty. Add some items to see them here!</p>
                </div>
            @else
                <div class="row g-4">
                    {{-- Loop through each BUSINESS group --}}
                    @foreach ($groupedProducts as $businessId => $products)
                        @php
                            $business = $products->first()->business;
                            $businessTotal = 0;
                        @endphp
                        <div class="col-12">
                            <div class="card shadow-sm border-0 rounded-4 order-card">

                                <!-- Business Header -->
                                <div
                                    class="card-header bg-white border-0 d-flex justify-content-between align-items-center p-4 rounded-top-4">
                                    <div>
                                        <h5 class="mb-0 fw-bolder">{{ $business->business_name ?? 'Unknown Vendor' }}</h5>
                                    </div>
                                    <span class="badge bg-warning-subtle text-dark ms-2 fs-6">Pre-order</span>
                                </div>

                                <!-- Items Table -->
                                <div class="card-body p-4">
                                    <div class="table-responsive">
                                        <table class="table table-borderless align-middle mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="px-4 py-3" style="width: 50%;">Product</th>
                                                    <th class="text-center px-4 py-3">Quantity</th>
                                                    <th class="text-end px-4 py-3">Price</th>
                                                    <th class="text-end px-4 py-3">Subtotal</th>
                                                    <th class="text-center px-4 py-3">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {{-- Loop through each PRODUCT within the business group --}}
                                                @foreach ($products as $product)
                                                    @php
                                                        $isUnavailable = !$product->is_available;
                                                        $subtotal = $isUnavailable
                                                            ? 0
                                                            : $product->price * $product->quantity;
                                                        if (!$isUnavailable) {
                                                            $businessTotal += $subtotal;
                                                        }
                                                    @endphp
                                                    <tr class="product-row" data-product-id="{{ $product->product_id }}">
                                                        <td class="px-4 py-3">
                                                            <div class="d-flex align-items-center">
                                                                <img src="{{ $product->image_url ?? asset('images/no-image.jpg') }}"
                                                                    alt="{{ $product->item_name }}" class="rounded me-3"
                                                                    style="width: 60px; height: 60px; object-fit: cover;">
                                                                <div>
                                                                    <span
                                                                        class="fw-semibold">{{ $product->item_name }}</span>
                                                                    @if ($isUnavailable)
                                                                        <br><span
                                                                            class="badge bg-danger-subtle text-danger">Unavailable</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="text-center px-4 py-3">
                                                            @if (!$isUnavailable)
                                                                <div
                                                                    class="d-inline-flex justify-content-center align-items-center gap-2 qty-box">
                                                                    <button class="btn-qty decrease">-</button>
                                                                    <span
                                                                        class="px-2 quantity">{{ $product->quantity }}</span>
                                                                    <button class="btn-qty increase">+</button>
                                                                </div>
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td class="text-end px-4 py-3 price"
                                                            data-price="{{ $isUnavailable ? 0 : $product->price }}">
                                                            ₱{{ number_format($isUnavailable ? 0 : $product->price, 2) }}
                                                        </td>
                                                        <td class="text-end px-4 py-3 fw-bold subtotal">
                                                            ₱{{ number_format($subtotal, 2) }}
                                                        </td>
                                                        <td class="text-center px-4 py-3">
                                                            <form action="{{ route('preorder.remove') }}" method="POST"
                                                                onsubmit="return confirm('Remove this item?')">
                                                                @csrf
                                                                <input type="hidden" name="product_id"
                                                                    value="{{ $product->product_id }}">
                                                                <button type="submit"
                                                                    class="btn btn-sm btn-outline-danger btn-icon">
                                                                    <i class="ri-delete-bin-line"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Footer with Total and Checkout Button -->
                                <div
                                    class="card-footer bg-white border-top d-flex justify-content-end align-items-center p-4">
                                    <div class="text-end">
                                        <p class="mb-1 fw-semibold">Total:</p>
                                        <h5 class="fw-bold text-primary mb-0 business-total">
                                            ₱{{ number_format($businessTotal, 2) }}</h5>
                                    </div>
                                    @php
                                        // check session preorders for pending transaction for this business
                                        $hasPendingInSession = collect(session('preorder', []))->contains(function (
                                            $it,
                                        ) use ($businessId) {
                                            return isset($it['business_id']) &&
                                                $it['business_id'] == $businessId &&
                                                !empty($it['transaction_id']);
                                        });

                                        // check DB preorders linked to this business for this user that are awaiting receipt
                                        $userPendingPreorders = \App\Models\PreOrder::whereHas('order', function (
                                            $q,
                                        ) use ($businessId) {
                                            $q->where('user_id', Auth::user()->user_id)->where(
                                                'business_id',
                                                $businessId,
                                            );
                                        })
                                            ->whereNull('receipt_url')
                                            ->get();
                                    @endphp

                                    @if ($hasPendingInSession || $userPendingPreorders->isNotEmpty())
                                        <a href="{{ route('checkout.preorder.proceed', ['business_id' => $businessId]) }}"
                                            class="btn btn-warning ms-4">
                                            Upload Receipt
                                        </a>
                                    @else
                                        <a href="{{ route('checkout.preorder.proceed', ['business_id' => $businessId]) }}"
                                            class="btn btn-primary ms-4">
                                            Proceed to Checkout
                                        </a>
                                    @endif
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div> {{-- End .main-content-area --}}
    </div>

    {{-- STYLES COPIED FROM YOUR REFERENCE --}}
    <style>
        .main-content-area {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .table th {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.95rem;
        }

        .qty-box {
            background: #f2f4f7;
            border-radius: 20px;
            padding: 4px;
            font-size: 14px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .btn-qty {
            border: none;
            background: #e9eef5;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }

        .btn-qty:hover {
            background: #cfd6e0;
        }

        .btn-icon {
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* BUTTON STYLE FROM CHECKOUT PAGE */
        .btn-primary {
            background: linear-gradient(135deg, #fbbf24 0%, #f97316 100%);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            box-shadow: 0 3px 8px rgba(249, 115, 22, 0.3);
            transition: all .25s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(249, 115, 22, 0.4);
        }

        #toast-container.toast-top-right {
            margin-top: 60px;
            /* Adjust this value as needed */
        }
    </style>

    {{-- JAVASCRIPT IS UNCHANGED --}}
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const updateBusinessCardTotals = (businessCard) => {
                let businessTotal = 0;
                businessCard.querySelectorAll(".product-row").forEach(productRow => {
                    const priceEl = productRow.querySelector(".price");
                    const qtyEl = productRow.querySelector(".quantity");
                    if (priceEl && qtyEl) {
                        const price = parseFloat(priceEl.dataset.price);
                        const qty = parseInt(qtyEl.innerText);
                        const subtotal = price * qty;
                        productRow.querySelector(".subtotal").innerHTML = "₱" + subtotal.toLocaleString(
                            'en-US', {
                                minimumFractionDigits: 2
                            });
                        businessTotal += subtotal;
                    }
                });
                businessCard.querySelector(".business-total").innerHTML = "₱" + businessTotal.toLocaleString(
                    'en-US', {
                        minimumFractionDigits: 2
                    });
            };

            const updatePreorderInSession = (productId, quantity) => {
                fetch("{{ route('preorder.update') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: quantity
                    })
                }).catch(err => console.error("Update failed:", err));
            };

            document.querySelectorAll(".increase").forEach(btn => {
                btn.addEventListener("click", () => {
                    const productRow = btn.closest('.product-row');
                    const productId = productRow.dataset.productId;
                    const qtyEl = productRow.querySelector(".quantity");

                    let newQty = parseInt(qtyEl.innerText) + 1;
                    qtyEl.innerText = newQty;

                    updatePreorderInSession(productId, newQty);
                    updateBusinessCardTotals(btn.closest('.card.order-card'));
                });
            });

            document.querySelectorAll(".decrease").forEach(btn => {
                btn.addEventListener("click", () => {
                    const productRow = btn.closest('.product-row');
                    const productId = productRow.dataset.productId;
                    const qtyEl = productRow.querySelector(".quantity");

                    let currentQty = parseInt(qtyEl.innerText);
                    if (currentQty > 1) {
                        let newQty = currentQty - 1;
                        qtyEl.innerText = newQty;
                        updatePreorderInSession(productId, newQty);
                        updateBusinessCardTotals(btn.closest('.card.order-card'));
                    }
                });
            });
        });
    </script>
@endsection
