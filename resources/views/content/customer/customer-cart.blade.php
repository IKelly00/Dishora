@extends('layouts/contentNavbarLayout')

@section('title', 'My Cart')

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

    <div class="container py-4 py-lg-5">
        <div class="main-content-area">
            <h4 class="fw-bold mb-4">My Cart</h4>

            @php
                // Normalize cart into a collection of items with product model and quantity
                $cartItems = collect($cart ?? [])->map(function ($item) use ($products) {
                    $product = $products[$item['product_id']] ?? null;
                    return (object) [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'] ?? 1,
                        'product' => $product,
                    ];
                });

                // Group by business id (use product->business->id when available)
                $grouped = $cartItems
                    ->filter(function ($it) {
                        return $it->product !== null;
                    })
                    ->groupBy(function ($it) {
                        return $it->product->business->id ?? ($it->product->business_id ?? 'unknown');
                    });

                // Also keep any items whose product is missing (unavailable) grouped under 'unknown' with product=null
                $missing = $cartItems->filter(function ($it) {
                    return $it->product === null;
                });
                if ($missing->isNotEmpty()) {
                    $grouped['unknown'] = isset($grouped['unknown']) ? $grouped['unknown']->concat($missing) : $missing;
                }
            @endphp

            @if ($grouped->isEmpty())
                <div class="alert alert-light text-center p-5 rounded-4 shadow-sm border">
                    <i class="ri-inbox-2-line display-3 d-block mb-3 text-muted"></i>
                    <h4 class="fw-bold mb-2">Your cart is empty</h4>
                    <p class="text-muted mb-3">Add items to your cart to see them here.</p>
                </div>
            @else
                <div class="row g-4">
                    @foreach ($grouped as $businessId => $items)
                        @php
                            // Determine business object (if available) from first item
                            $first = $items->first();
                            $business = $first && $first->product ? $first->product->business ?? null : null;
                            $businessTotal = 0;
                        @endphp

                        <div class="col-12">
                            <div class="card shadow-sm border-0 rounded-4 order-card">

                                <!-- Business Header -->
                                <div
                                    class="card-header bg-white border-0 d-flex justify-content-between align-items-center p-4 rounded-top-4">
                                    <div>
                                        <h5 class="mb-0 fw-bolder">{{ $business->business_name ?? 'Unknown Vendor' }}</h5>
                                        @if ($business && $business->address)
                                            <small class="text-muted">{{ $business->address }}</small>
                                        @endif
                                    </div>
                                    <span class="badge bg-secondary-subtle text-dark ms-2 fs-6">Cart</span>
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
                                                @foreach ($items as $entry)
                                                    @php
                                                        $product = $entry->product;
                                                        $isUnavailable = !$product || !($product->is_available ?? true);
                                                        $price = $isUnavailable ? 0 : $product->price ?? 0;
                                                        $subtotal = $isUnavailable ? 0 : $price * $entry->quantity;
                                                        if (!$isUnavailable) {
                                                            $businessTotal += $subtotal;
                                                        }
                                                    @endphp
                                                    <tr class="product-row" data-product-id="{{ $entry->product_id }}">
                                                        <td class="px-4 py-3">
                                                            <div class="d-flex align-items-center">
                                                                <img src="{{ $product && $product->image_url ? secure_asset($product->image_url) : secure_asset('images/no-image.jpg') }}"
                                                                    alt="{{ $product->item_name ?? 'No image' }}"
                                                                    class="rounded me-3"
                                                                    style="width: 60px; height: 60px; object-fit: cover;">
                                                                <div>
                                                                    <span
                                                                        class="fw-semibold">{{ $product->item_name ?? 'Product #' . $entry->product_id }}</span>
                                                                    @if ($isUnavailable)
                                                                        <br><span
                                                                            class="badge bg-danger-subtle text-danger">Unavailable</span>
                                                                    @else
                                                                        @if (!empty($product->variation_name))
                                                                            <div class="text-muted small">
                                                                                {{ $product->variation_name }}</div>
                                                                        @endif
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td class="text-center px-4 py-3">
                                                            @if (!$isUnavailable)
                                                                <div
                                                                    class="d-inline-flex justify-content-center align-items-center gap-2 qty-box">
                                                                    <button class="btn-qty decrease"
                                                                        data-id="{{ $entry->product_id }}">-</button>
                                                                    <span class="px-2 quantity"
                                                                        data-id="{{ $entry->product_id }}">{{ $entry->quantity }}</span>
                                                                    <button class="btn-qty increase"
                                                                        data-id="{{ $entry->product_id }}">+</button>
                                                                </div>
                                                            @else
                                                                -
                                                            @endif
                                                        </td>

                                                        <td class="text-end px-4 py-3 price"
                                                            data-price="{{ $price }}">
                                                            ₱{{ number_format($price, 2) }}
                                                        </td>

                                                        <td class="text-end px-4 py-3 fw-bold subtotal">
                                                            ₱{{ number_format($subtotal, 2) }}
                                                        </td>

                                                        <td class="text-center px-4 py-3">
                                                            <form action="{{ route('cart.remove') }}" method="POST"
                                                                onsubmit="return confirm('Remove this item?')">
                                                                @csrf
                                                                <input type="hidden" name="product_id"
                                                                    value="{{ $entry->product_id }}">
                                                                <input type="hidden" name="type" value="cart">
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

                                    <a href="{{ route('checkout.proceed', ['business_id' => $businessId]) }}"
                                        class="btn btn-primary ms-4">
                                        Proceed to Checkout
                                    </a>
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div> {{-- End .main-content-area --}}
    </div>

    {{-- STYLES (based on preorder design) --}}
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

        .badge.bg-secondary-subtle {
            background-color: #f3f4f6;
            color: #374151;
        }

        #toast-container.toast-top-right {
            margin-top: 60px;
        }
    </style>

    {{-- JAVASCRIPT: cart update + totals per business (keeps same UX as preorder but hits cart routes) --}}
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const updateBusinessCardTotals = (businessCard) => {
                let businessTotal = 0;
                businessCard.querySelectorAll('.product-row').forEach(productRow => {
                    const priceEl = productRow.querySelector('.price');
                    const qtyEl = productRow.querySelector('.quantity');
                    if (priceEl && qtyEl) {
                        const price = parseFloat(priceEl.dataset.price) || 0;
                        const qty = parseInt(qtyEl.innerText) || 0;
                        const subtotal = price * qty;
                        productRow.querySelector('.subtotal').innerHTML = '₱' + subtotal.toLocaleString(
                            'en-US', {
                                minimumFractionDigits: 2
                            });
                        businessTotal += subtotal;
                    }
                });
                businessCard.querySelector('.business-total').innerHTML = '₱' + businessTotal.toLocaleString(
                    'en-US', {
                        minimumFractionDigits: 2
                    });
            };

            const updateCartInSession = (productId, quantity) => {
                fetch("{{ route('cart.update') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        type: 'cart',
                        quantity: quantity
                    })
                }).then(r => r.json()).then(data => {
                    if (!data.success) console.error('Cart update failed', data);
                }).catch(err => console.error('Update failed:', err));
            };

            // Wire up increase/decrease buttons globally
            document.querySelectorAll('.increase').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    const qtyEl = document.querySelector(`.quantity[data-id="${id}"]`);
                    if (!qtyEl) return;
                    let qty = parseInt(qtyEl.innerText) || 0;
                    qty++;
                    qtyEl.innerText = qty;
                    updateCartInSession(id, qty);
                    const businessCard = btn.closest('.card.order-card');
                    if (businessCard) updateBusinessCardTotals(businessCard);
                });
            });

            document.querySelectorAll('.decrease').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    const qtyEl = document.querySelector(`.quantity[data-id="${id}"]`);
                    if (!qtyEl) return;
                    let qty = parseInt(qtyEl.innerText) || 0;
                    if (qty > 1) {
                        qty--;
                        qtyEl.innerText = qty;
                        updateCartInSession(id, qty);
                        const businessCard = btn.closest('.card.order-card');
                        if (businessCard) updateBusinessCardTotals(businessCard);
                    }
                });
            });

            // Initialize totals for each business card
            document.querySelectorAll('.card.order-card').forEach(card => updateBusinessCardTotals(card));
        });
    </script>
@endsection
