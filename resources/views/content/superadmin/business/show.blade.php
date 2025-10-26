@extends('layouts/commonMaster')
@section('title', 'Business Details')
@section('layoutContent')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        #rejectModal .modal-dialog {
            position: absolute;
            /* Position relative to the viewport or nearest positioned ancestor */
            top: 50%;
            /* Move top edge to the vertical center */
            left: 50%;
            /* Move left edge to the horizontal center */
            transform: translate(-50%, -50%);
            /* Shift back by half its own width/height to truly center */
            margin: 0;
            /* Reset default margins */
            /* Or set specific positions: */
            /* top: 100px; */
            /* left: 200px; */
            /* transform: none; */
        }
    </style>

    {{-- Include the navbar --}}
    @include('content.superadmin.partials.navbar')

    {{-- Main content wrapper --}}
    <div class="tab-content-wrapper">

        <div class="container py-4">
            {{-- START: Final Redesigned Header in a Card for Visibility --}}
            <div class="card mb-4 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap py-3 me-5">
                        <div class="d-flex align-items-center mb-3 mb-md-0">
                            @php $hero = $documents['business_image']['url'] ?? null; @endphp
                            @if ($hero)
                                <img src="{{ $hero }}" alt="Business image" class="rounded me-3"
                                    style="width: 14rem; height: 12rem; object-fit: cover;"> {{-- Image size increased here --}}
                            @else
                                <div class="rounded me-3 d-flex align-items-center justify-content-center bg-light"
                                    style="width: 100%; height: 100%;"> {{-- Placeholder size increased here --}}
                                    <i class='bx bx-store fs-3 text-muted'></i>
                                </div>
                            @endif
                            <div>
                                <h3 class="mb-1 fw-bold">{{ $business->business_name }}</h3>
                                <div class="text-muted small fs-6">
                                    Vendor: <strong>{{ optional($business->vendor)->fullname ?? '-' }}</strong>
                                </div>
                                <div class="mt-2">
                                    @php
                                        $badge_class = [
                                            'Pending' => 'bg-warning',
                                            'Approved' => 'bg-success',
                                            'Rejected' => 'bg-danger',
                                        ];
                                        $status_key = $business->verification_status ?? 'Pending';
                                    @endphp
                                    <span
                                        class="badge {{ $badge_class[$status_key] ?? 'bg-secondary' }}">{{ $status_key }}</span>
                                    <span class="text-muted small ms-3">Submitted:
                                        {{ optional($business->created_at)->format('Y-m-d H:i') }}</span>
                                    @if ($business->remarks)
                                        <small class="ms-3 text-muted fst-italic">Remarks:
                                            {{ Str::limit($business->remarks, 100) }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-shrink-0">
                            <a href="{{ route('super-admin.business.edit', $business->business_id) }}"
                                class="btn btn-sm btn-outline-primary me-2"><i class="bx bx-edit-alt me-1"></i>
                                Edit</a>
                            <a href="{{ route('super-admin.dashboard') }}" class="btn btn-sm btn-outline-secondary"><i
                                    class="bx bx-arrow-back me-1"></i> Back</a>
                        </div>
                    </div>
                </div>
            </div>
            {{-- END: Final Redesigned Header --}}

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header border-bottom mb-5">
                            <h6 class="mb-0 fw-bold">Overview & Description</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Type:</strong> <span
                                    class="text-body">{{ $business->business_type }}</span></p>
                            <p class="mb-2"><strong>Location:</strong> {!! nl2br(e($business->business_location)) !!}</p>
                            <hr class="my-3" />
                            <h6 class="mb-2 fw-bold">Full Description</h6>
                            <div class="text-break text-muted">{!! nl2br(e($business->business_description)) !!}</div>
                        </div>
                    </div>

                    <div class="card mb-4 shadow-sm">
                        <div class="card-header border-bottom mb-5">
                            <h6 class="mb-0 fw-bold">Opening Hours</h6>
                        </div>
                        <div class="card-body">
                            @if ($business->openingHours && $business->openingHours->count())
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            @foreach ($business->openingHours as $h)
                                                <tr>
                                                    <td style="width:160px" class="fw-semibold">{{ $h->day_of_week }}
                                                    </td>
                                                    <td>
                                                        @if ($h->is_closed)
                                                            <span class="text-danger">Closed</span>
                                                        @else
                                                            {{-- Check if $h->opens_at is not null before exploding --}}
                                                            @if ($h->opens_at)
                                                                {{ \Carbon\Carbon::createFromFormat('H:i:s', explode('.', $h->opens_at)[0])->format('h:i A') }}
                                                            @endif
                                                            —
                                                            @if ($h->closes_at)
                                                                {{ \Carbon\Carbon::createFromFormat('H:i:s', explode('.', $h->closes_at)[0])->format('h:i A') }}
                                                            @endif
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-muted py-2 text-center">No opening hours configured.</div>
                            @endif
                        </div>
                    </div>

                    <div class="card mb-3 shadow-sm">
                        <div class="card-header d-flex align-items-center justify-content-between border-bottom mb-5">
                            <h6 class="mb-0 fw-bold">Documents & Files</h6>
                            <small class="text-muted">Click thumbnails to preview</small>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach ($documents as $col => $doc)
                                    @php
                                        $raw = $doc['raw'] ?? ($doc['url'] ?? '');
                                        // Filename is still needed for tooltip and modals
                                        $filename = $raw ? basename(parse_url($raw, PHP_URL_PATH) ?: $raw) : 'No file';
                                        $file_ext = $doc['url']
                                            ? pathinfo(parse_url($doc['url'], PHP_URL_PATH), PATHINFO_EXTENSION)
                                            : 'file';
                                    @endphp
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="card h-100 shadow-sm">
                                            <div class="card-body d-flex flex-column p-3">
                                                <div class="mb-2 small text-muted text-uppercase fw-semibold">
                                                    {{ $doc['label'] }}</div>
                                                @if (!$doc['url'])
                                                    <div class="flex-grow-1 d-flex align-items-center justify-content-center bg-light rounded p-3 mb-2"
                                                        style="height:110px;">
                                                        <small class="text-muted">No file</small>
                                                    </div>
                                                @else
                                                    @if ($doc['type'] === 'image')
                                                        <a href="#" class="open-image d-block mb-2"
                                                            data-url="{{ $doc['url'] }}"
                                                            data-label="{{ $doc['label'] }}">
                                                            <img src="{{ $doc['url'] }}" alt="{{ $doc['label'] }}"
                                                                class="img-fluid rounded"
                                                                style="height:110px; width:100%; object-fit:cover;">
                                                        </a>
                                                        {{-- START: Updated buttons for image to match PDF/File pattern --}}
                                                        <div class="mt-auto d-flex gap-2">
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-primary w-50 open-image"
                                                                data-url="{{ $doc['url'] }}"
                                                                data-label="{{ $doc['label'] }}">Preview</button>
                                                            <a href="{{ $doc['url'] }}" target="_blank"
                                                                class="btn btn-sm btn-outline-secondary w-50">Open</a>
                                                        </div>
                                                        {{-- END: Updated buttons --}}
                                                    @elseif ($doc['type'] === 'pdf')
                                                        {{-- The filename display is removed, showing only file type --}}
                                                        <button type="button" class="open-pdf btn p-0 d-block text-start"
                                                            data-url="{{ $doc['url'] }}"
                                                            data-label="{{ $doc['label'] }}" title="{{ $filename }}">
                                                            <div class="d-flex align-items-center justify-content-center bg-light rounded mb-2 w-100"
                                                                style="height:110px;">
                                                                <div class="text-center px-2">
                                                                    <i class='bx bxs-file-pdf fs-1 text-danger'></i>
                                                                    <div class="small text-muted text-uppercase fw-bold">
                                                                        {{ $file_ext ?: 'PDF' }}</div>
                                                                </div>
                                                            </div>
                                                        </button>
                                                        <div class="mt-auto d-flex gap-2">
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-primary w-50 open-pdf"
                                                                data-url="{{ $doc['url'] }}"
                                                                data-label="{{ $doc['label'] }}">Preview</button>
                                                            <a href="{{ $doc['url'] }}" target="_blank"
                                                                class="btn btn-sm btn-outline-secondary w-50">Open</a>
                                                        </div>
                                                    @else
                                                        {{-- The filename display is removed, showing only file type --}}
                                                        <button type="button" class="open-file btn p-0 d-block text-start"
                                                            data-url="{{ $doc['url'] }}"
                                                            data-label="{{ $doc['label'] }}"
                                                            title="{{ $filename }}">
                                                            <div class="d-flex align-items-center justify-content-center bg-light rounded mb-2 w-100"
                                                                style="height:110px;">
                                                                <div class="text-center px-2">
                                                                    <i class='bx bxs-file fs-1 text-secondary'></i>
                                                                    <div class="small text-muted text-uppercase fw-bold">
                                                                        {{ $file_ext ?: 'File' }}</div>
                                                                </div>
                                                            </div>
                                                        </button>
                                                        <div class="mt-auto d-flex gap-2">
                                                            <a href="{{ $doc['url'] }}" target="_blank"
                                                                class="btn btn-sm btn-outline-primary w-50">Open</a>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary w-50 copy-url-btn"
                                                                data-url="{{ $doc['url'] }}">Copy URL</button>
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header border-bottom mb-5">
                            <h6 class="mb-0 fw-bold">Business Details</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li><strong>Status:</strong>
                                    <span
                                        class="badge {{ $badge_class[$status_key] ?? 'bg-secondary' }}">{{ $status_key }}</span>
                                </li>
                                <li class="mt-3"><strong>Preorder lead time:</strong>
                                    <div class="text-body">{{ $business->preorder_lead_time_hours ?? 'N/A' }} hrs
                                    </div>
                                </li>
                                <li class="mt-3"><strong>Latitude / Longitude:</strong><br>
                                    <small class="text-muted">{{ $business->latitude ?? '-' }} ,
                                        {{ $business->longitude ?? '-' }}</small>
                                    @if ($business->latitude && $business->longitude)
                                        <div class="mt-2"><a
                                                href="https://google.com/maps/search/{{ $business->latitude }},{{ $business->longitude }}"
                                                target="_blank" class="small text-primary">Open in Google Maps</a>
                                        </div>
                                    @endif
                                </li>
                            </ul>
                        </div>
                    </div>

                    {{-- *** MODIFIED ADMIN ACTIONS CARD *** --}}
                    <div class="card mb-4 shadow-sm bg-light">
                        <div class="card-header border-bottom mb-5">
                            <h6 class="mb-0 fw-bold">Registration Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">

                                @php
                                    $isVendorApproved = optional($business->vendor)->registration_status === 'Approved';
                                    $tooltipMessage = 'Vendor account must be approved to manage this business';
                                @endphp

                                {{-- Approve Button --}}
                                @if ($isVendorApproved)
                                    <form method="POST"
                                        action="{{ route('super-admin.business.approve', $business->business_id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-success d-grid w-100">Approve
                                            Business</button>
                                    </form>
                                @else
                                    <span class="d-block w-100" data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{{ $tooltipMessage }}">
                                        <button class="btn btn-success d-grid w-100" disabled
                                            style="pointer-events: none;">
                                            Approve Business
                                        </button>
                                    </span>
                                @endif

                                {{-- Reject Button --}}
                                @if ($isVendorApproved)
                                    <button class="btn btn-danger d-grid w-100" id="openRejectModal">Reject
                                        Business</button>
                                @else
                                    <span class="d-block w-100" data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{{ $tooltipMessage }}">
                                        <button class="btn btn-danger d-grid w-100" id="openRejectModal" disabled
                                            style="pointer-events: none;">
                                            Reject Business
                                        </button>
                                    </span>
                                @endif

                                <hr class="my-2">
                                <form method="POST"
                                    action="{{ route('super-admin.business.destroy', $business->business_id) }}"
                                    onsubmit="return confirm('Delete this business? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger d-grid w-100">Delete
                                        Permanently</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    {{-- *** END MODIFIED ADMIN ACTIONS CARD *** --}}


                    {{-- == START: MODIFIED VENDOR INFORMATION CARD == --}}
                    <div class="card shadow-sm">
                        <div class="card-header border-bottom mb-5">
                            <h6 class="mb-0 fw-bold">Vendor Information</h6>
                        </div>
                        <div class="card-body">
                            {{-- Check if vendor exists before trying to display info --}}
                            @if ($business->vendor)
                                <p class="mb-1"><strong>Name:</strong>
                                    <span class="text-body">{{ $business->vendor->fullname }}</span>
                                </p>
                                <p class="mb-1"><strong>Phone:</strong>
                                    <span class="text-body">{{ $business->vendor->phone_number ?? '-' }}</span>
                                </p>
                                <p class="mb-2"><strong>Registration:</strong> {{-- Added mb-2 --}}
                                    @php
                                        $v_badge_class = [
                                            'Pending' => 'bg-warning',
                                            'Approved' => 'bg-success',
                                            'Rejected' => 'bg-danger',
                                        ];
                                        $v_status_key = $business->vendor->registration_status ?? 'Pending';
                                    @endphp
                                    <span
                                        class="badge {{ $v_badge_class[$v_status_key] ?? 'bg-secondary' }}">{{ $v_status_key }}</span>
                                </p>

                                {{-- Add the button here --}}
                                <a href="{{ route('super-admin.vendor.view', $business->vendor->vendor_id) }}"
                                    class="btn btn-sm btn-outline-secondary mt-2"> {{-- Added mt-2 --}}
                                    <i class='bx bx-user-pin me-1'></i> View Full Vendor Details
                                </a>
                            @else
                                <p class="text-muted mb-0">No vendor associated with this business.</p>
                            @endif
                        </div>
                    </div>
                    {{-- == END: MODIFIED VENDOR INFORMATION CARD == --}}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="imageModalTitle" class="modal-title">Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imageModalImg" src="" alt="preview" class="img-fluid rounded"
                        style="max-height:75vh; object-fit:contain;">
                </div>
                <div class="modal-footer">
                    <a id="imageModalOpen" href="#" target="_blank" class="btn btn-primary">Open in new tab</a>
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down">
            <div class="modal-content" style="height:90vh;">
                <div class="modal-header py-5">
                    <h5 id="pdfModalTitle" class="modal-title">PDF Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="pdfFrame" src="" frameborder="0" style="width:100%; height:100%;"></iframe>
                </div>
                <div class="modal-footer">
                    <a id="pdfOpenNew" href="#" target="_blank" class="btn btn-primary">Open in new tab</a>
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Reject Modal --}}
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="rejectForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Business</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="business_id" id="reject-business-id">
                        <div class="mb-3">
                            <label for="reject-reason" class="form-label">Reason (optional)</label>
                            <textarea name="reason" id="reject-reason" rows="4" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    {{-- End Reject Modal --}}
@endsection
@push('page-script')
    <script>
        (function() {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const businessId = '{{ $business->business_id }}'; // Pass business ID

            function showToast(message, isError = false) {
                // Assuming you have the dashboard toast HTML included in commonMaster or layouts
                const toastEl = document.getElementById('saToast');
                if (!toastEl) {
                    // Fallback alert if toast isn't available
                    alert(message);
                    return;
                }
                const body = document.getElementById('saToastBody');
                body.innerText = message;
                toastEl.classList.toggle('bg-danger', !!isError);
                toastEl.classList.toggle('bg-primary', !isError);
                new bootstrap.Toast(toastEl).show();
            }

            function updateStatusBadge(status) {
                // Select badge in the new header card area
                const headerBadgeEl = document.querySelector('.card.mb-4 .badge');
                if (headerBadgeEl) {
                    headerBadgeEl.textContent = status;
                    headerBadgeEl.className = 'badge';
                    if (status === 'Approved') headerBadgeEl.classList.add('bg-success');
                    else if (status === 'Rejected') headerBadgeEl.classList.add('bg-danger');
                    else headerBadgeEl.classList.add('bg-warning');
                }
                // Also update the badge in Business Details card
                const detailsBadgeEl = document.querySelector('.col-lg-4 .card-body .badge');
                if (detailsBadgeEl) {
                    detailsBadgeEl.textContent = status;
                    detailsBadgeEl.className = 'badge';
                    if (status === 'Approved') detailsBadgeEl.classList.add('bg-success');
                    else if (status === 'Rejected') detailsBadgeEl.classList.add('bg-danger');
                    else detailsBadgeEl.classList.add('bg-warning');
                }
            }

            // --- Document & Media Handlers ---

            // Image modal
            document.querySelectorAll('.open-image').forEach(a => {
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = this.dataset.url;
                    const label = this.dataset.label;
                    document.getElementById('imageModalTitle').textContent = label;
                    document.getElementById('imageModalImg').src = url;
                    document.getElementById('imageModalOpen').href = url;
                    new bootstrap.Modal(document.getElementById('imageModal')).show();
                });
            });

            // PDF modal
            document.querySelectorAll('.open-pdf').forEach(btn => {
                btn.addEventListener('click', function() {
                    const url = this.dataset.url;
                    const label = this.dataset.label;

                    // Uses #zoom=150 to set a slightly larger initial view, while keeping the native toolbar for controls.
                    const previewUrl = `${url}#zoom=100`;

                    document.getElementById('pdfModalTitle').textContent = `PDF Preview: ${label}`;
                    document.getElementById('pdfFrame').src = previewUrl; // Use the modified URL
                    document.getElementById('pdfOpenNew').href =
                        url; // Use original URL for opening in new tab
                    new bootstrap.Modal(document.getElementById('pdfModal')).show();
                });
            });

            // Copy URL
            document.querySelectorAll('.copy-url-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const url = this.dataset.url;
                    navigator.clipboard.writeText(url).then(() => {
                        showToast('URL copied to clipboard!');
                    }).catch(err => {
                        console.error('Could not copy text: ', err);
                        showToast('Failed to copy URL.', true);
                    });
                });
            });


            // --- Admin Actions ---

            // Open Reject Modal
            const openRejectModalBtn = document.getElementById('openRejectModal');
            if (openRejectModalBtn) {
                openRejectModalBtn.addEventListener('click', function() {
                    new bootstrap.Modal(document.getElementById('rejectModal')).show();
                });
            }

            // Reject form submit (Details Page)
            const detailsRejectForm = document.getElementById('detailsRejectForm');
            if (detailsRejectForm) {
                detailsRejectForm.addEventListener('submit', function(ev) {
                    ev.preventDefault();
                    const reason = document.getElementById('details-reject-reason').value;

                    fetch(`/super-admin/business/${businessId}/reject`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                reason
                            })
                        })
                        .then(r => r.json())
                        .then(res => {
                            const modalEl = document.getElementById('rejectModal');
                            bootstrap.Modal.getInstance(modalEl)?.hide();
                            if (res.status === 'ok' || res.status === 'Rejected' || res.message) {
                                updateStatusBadge('Rejected');
                                showToast(res.message || 'Business Rejected');
                            } else showToast('Failed to reject', true);
                        }).catch(err => {
                            showToast('Error processing rejection.', true);
                            console.error(err);
                        });
                });
            }
        })();
    </script>
@endpush
