@extends('layouts/contentNavbarLayout')

@section('title', 'Upload Receipt')

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" />

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card p-3 border-0 shadow-sm">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3 display-6 text-primary"><i class="bx bxs-wallet"></i></div>
                        <div>
                            <h4 class="mb-0">Upload Payment Receipt</h4>
                            <small class="text-muted">Attach the picture of your payment receipt to confirm your
                                pre-order.</small>
                        </div>
                    </div>

                    <form id="receiptForm" action="{{ route('preorder.confirm', $order) }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-md-7">
                                <div class="mb-3">
                                    <label for="receipt" class="form-label fw-semibold">Receipt Image</label>

                                    <div class="d-flex flex-column align-items-start">
                                        <label class="w-100" for="receipt">
                                            <div
                                                class="file-dropzone p-3 w-100 d-flex align-items-center justify-content-between rounded-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <i class="bx bx-image-add fs-3"></i>
                                                    </div>
                                                    <div>
                                                        <div class="small fw-semibold">Click to choose a file or drag it
                                                            here</div>
                                                        <div class="small text-muted">PNG, JPG, JPEG — max 2MB</div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <button type="button"
                                                        class="btn btn-outline-secondary btn-sm">Browse</button>
                                                </div>
                                            </div>
                                        </label>

                                        <input class="form-control d-none @error('receipt') is-invalid @enderror"
                                            type="file" id="receipt" name="receipt"
                                            accept="image/png, image/jpeg, image/jpg">

                                        @error('receipt')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror

                                        <div class="form-text mt-2">Accepted formats: PNG, JPG, JPEG. Max size: 2MB.</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Preview</label>
                                    <div class="preview-box border rounded-3 p-2 d-flex align-items-center justify-content-center bg-light"
                                        id="previewBox">
                                        <span class="text-muted">No image selected</span>
                                    </div>
                                </div>

                                @if (session('success'))
                                    <div class="alert alert-success">{{ session('success') }}</div>
                                @endif
                                @if (session('error'))
                                    <div class="alert alert-danger">{{ session('error') }}</div>
                                @endif

                            </div>

                            <div class="col-md-5">
                                <div class="card border-0 shadow-xs">
                                    <div class="card-body">
                                        <h6 class="fw-semibold">Order Summary</h6>
                                        <hr>
                                        <p class="mb-1 small text-muted">Business</p>
                                        <p class="mb-2 fw-medium">{{ $order->business?->business_name ?? '—' }}</p>

                                        <p class="mb-1 small text-muted">Order Total</p>
                                        <p class="mb-2 fw-medium">₱{{ number_format($total ?? 0, 2) }}</p>

                                        <p class="mb-1 small text-muted">Advance Paid</p>
                                        <p class="mb-2 fw-medium">
                                            ₱{{ number_format($preorder->advance_paid_amount ?? 0, 2) }}</p>

                                        <div class="d-grid gap-2 mt-3">
                                            <button type="submit" id="submitBtn" class="btn btn-primary">Confirm
                                                Pre-Order</button>
                                            <a href="{{ route('customer.orders.index') }}"
                                                class="btn btn-outline-secondary">Back to Orders</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 text-center small text-muted">
                                    <i class="bx bx-info-circle"></i>
                                    Please allow up to a few minutes for your payment to be validated by the vendor.
                                </div>

                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .card {
            box-shadow: 0 6px 20px rgba(18, 38, 63, 0.06);
        }

        .file-dropzone {
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease;
            border: 1px dashed #e6e9ee;
        }

        .file-dropzone:hover {
            background: #fbfcfe;
        }

        .preview-box {
            min-height: 160px;
            max-height: 240px;
            overflow: hidden;
        }

        .preview-box img {
            max-height: 220px;
            width: auto;
            display: block;
        }

        .fw-medium {
            font-weight: 600;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var input = document.getElementById('receipt');
            var previewBox = document.getElementById('previewBox');
            var form = document.getElementById('receiptForm');

            // server-side indicator whether a receipt already exists
            var hasServerReceipt = @json(!empty($preorder->receipt_url));

            if (!input || !form) return;

            // Allow clicking the visual dropzone to open file selector
            var dropzone = document.querySelector('.file-dropzone');
            if (dropzone) {
                dropzone.addEventListener('click', function(e) {
                    input.click();
                });

                // Optional drag & drop
                dropzone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    dropzone.classList.add('drag-over');
                });
                dropzone.addEventListener('dragleave', function(e) {
                    dropzone.classList.remove('drag-over');
                });
                dropzone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    dropzone.classList.remove('drag-over');
                    var files = e.dataTransfer.files;
                    if (files && files.length) {
                        input.files = files;
                        triggerChange();
                    }
                });
            }

            input.addEventListener('change', triggerChange);

            form.addEventListener('submit', function(e) {
                // If there's already an uploaded receipt on server, allow submit even with no new file
                if (hasServerReceipt) return;

                var f = input.files && input.files[0];
                if (!f) {
                    e.preventDefault();
                    // show toastr if available, otherwise fallback to alert
                    if (typeof toastr !== 'undefined' && toastr.error) {
                        toastr.options = {
                            closeButton: true,
                            progressBar: true,
                            positionClass: 'toast-top-right'
                        };
                        toastr.error('Please upload a receipt image before confirming.');
                    } else {
                        alert('Please upload a receipt image before confirming.');
                    }
                    return false;
                }
                // file size check (2MB) - block submit if too big
                if (f.size > 2 * 1024 * 1024) {
                    e.preventDefault();
                    if (typeof toastr !== 'undefined' && toastr.error) {
                        toastr.options = {
                            closeButton: true,
                            progressBar: true,
                            positionClass: 'toast-top-right'
                        };
                        toastr.error('File is too large. Maximum allowed size is 2MB.');
                    } else {
                        alert('File is too large. Maximum allowed size is 2MB.');
                    }
                    return false;
                }

                // allow submit
            });

            function triggerChange() {
                var f = input.files && input.files[0];
                if (!f) {
                    previewBox.innerHTML = '<span class="text-muted">No image selected</span>';
                    return;
                }

                if (f.size > 2 * 1024 * 1024) {
                    if (typeof toastr !== 'undefined' && toastr.error) {
                        toastr.options = {
                            closeButton: true,
                            progressBar: true,
                            positionClass: 'toast-top-right'
                        };
                        toastr.error('File is too large. Maximum allowed size is 2MB.');
                    } else {
                        alert('File is too large. Maximum allowed size is 2MB.');
                    }
                    input.value = '';
                    previewBox.innerHTML = '<span class="text-muted">No image selected</span>';
                    return;
                }

                var reader = new FileReader();
                reader.onload = function(e) {
                    previewBox.innerHTML = '<img src="' + e.target.result +
                        '" alt="Receipt preview" class="img-fluid" />';
                };
                reader.readAsDataURL(f);
            }
        });
    </script>

@endsection
