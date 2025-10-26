@extends('layouts/commonMaster')
@section('title', 'View Vendor Details')

@section('layoutContent')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        #rejectModal .modal-dialog {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0;
        }
    </style>

    {{-- Navbar --}}
    @include('content.superadmin.partials.navbar')

    {{-- Page Wrapper --}}
    <div class="tab-content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">

            {{-- Header --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0">Vendor Details: {{ $vendor->fullname }}</h4>
                <div>
                    {{-- <a href="#" class="btn btn-primary me-2 disabled">Edit Vendor (TBA)</a> --}}
                    <a href="{{ route('super-admin.vendors.index') }}" class="btn btn-outline-secondary">
                        <i class="bx bx-arrow-back me-1"></i> Back to Vendor List
                    </a>
                </div>
            </div>

            {{-- Vendor Information + Actions --}}
            <div class="row g-4 mb-4">
                {{-- Info --}}
                <div class="col-lg-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header border-bottom mb-5 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">Vendor Information</h5>
                            {{-- Small status shown in header too (optional) --}}
                            <div>
                                @php
                                    $badge_class = [
                                        'Pending' => 'bg-warning',
                                        'Approved' => 'bg-success',
                                        'Rejected' => 'bg-danger',
                                    ];
                                    $status_key = $vendor->registration_status ?? 'Pending';
                                @endphp
                                <span class="badge {{ $badge_class[$status_key] ?? 'bg-secondary' }} me-0"
                                    id="vendor-status-badge-header">{{ $status_key }}</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-4">Full Name</dt>
                                <dd class="col-sm-8">{{ $vendor->fullname }}</dd>

                                <dt class="col-sm-4">Phone Number</dt>
                                <dd class="col-sm-8">{{ $vendor->phone_number ?? 'N/A' }}</dd>

                                <dt class="col-sm-4">Registration Status</dt>
                                <dd class="col-sm-8" id="vendor-status-section">
                                    <span class="badge {{ $badge_class[$status_key] ?? 'bg-secondary' }}"
                                        id="vendor-status-badge">{{ $status_key }}</span>
                                </dd>

                                <dt class="col-sm-4">Registered On</dt>
                                <dd class="col-sm-8">{{ optional($vendor->created_at)->format('M d, Y H:i A') ?? 'N/A' }}
                                </dd>

                                <dt class="col-sm-4">Last Updated</dt>
                                <dd class="col-sm-8">{{ optional($vendor->updated_at)->format('M d, Y H:i A') ?? 'N/A' }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>

                {{-- Actions column: always present (buttons enabled/disabled by status) --}}
                <div class="col-lg-4">
                    <div class="card shadow-sm bg-light h-100">
                        <div class="card-header border-bottom">
                            <h5 class="mb-0 fw-bold">Registration Actions</h5>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-center">
                            <div class="d-grid gap-2">
                                @php $isApproved = ($status_key === 'Approved'); @endphp

                                <button class="btn btn-success d-grid w-100 btn-approve" data-id="{{ $vendor->vendor_id }}"
                                    data-bs-toggle="modal" data-bs-target="#approveRegModal"
                                    @if ($isApproved) disabled aria-disabled="true" title="Already approved" @endif
                                    id="btn-approve">
                                    Approve Registration
                                </button>

                                <button class="btn btn-danger d-grid w-100 btn-reject" data-id="{{ $vendor->vendor_id }}"
                                    id="btn-reject">
                                    Reject Registration
                                </button>

                                {{-- *** ADDED DELETE BUTTON *** --}}
                                <hr class="my-2">
                                <form method="POST" action="{{ route('super-admin.vendor.destroy', $vendor->vendor_id) }}"
                                    onsubmit="return confirm('Are you sure you want to delete this vendor? This action CANNOT be undone and will delete all associated businesses.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger d-grid w-100">Delete
                                        Vendor Permanently</button>
                                </form>
                                {{-- *** END ADDED DELETE BUTTON *** --}}

                            </div>
                        </div>
                    </div>
                </div>
            </div>


            {{-- Business Details --}}
            <div class="card mb-4 shadow-sm">
                <div class="card-header border-bottom mb-5">
                    <h5 class="mb-0 fw-bold">Associated Business Details</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Business Name</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Valid ID No.</th>
                                    <th>Valid ID File</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($vendor->businessDetails as $business)
                                    <tr>
                                        <td>{{ $business->business_name }}</td>
                                        <td>{{ $business->business_type ?? 'N/A' }}</td>
                                        <td>
                                            @php
                                                $biz_badge_class = [
                                                    'Pending' => 'bg-warning',
                                                    'Approved' => 'bg-success',
                                                    'Rejected' => 'bg-danger',
                                                ];
                                                $biz_status_key = $business->verification_status ?? 'Pending';
                                            @endphp
                                            <span class="badge {{ $biz_badge_class[$biz_status_key] ?? 'bg-secondary' }}">
                                                {{ $biz_status_key }}
                                            </span>
                                        </td>
                                        <td>{{ $business->valid_id_no ?? 'N/A' }}</td>
                                        <td>
                                            @if ($business->valid_id_file)
                                                <button type="button" class="btn btn-xs btn-outline-info btn-preview"
                                                    data-bs-toggle="modal" data-bs-target="#previewModal"
                                                    data-file-url="{{ $business->valid_id_file }}"
                                                    data-file-name="Valid ID">
                                                    <i class='bx bx-show-alt me-1'></i> Preview ID
                                                </button>
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('super-admin.business.view', $business->business_id) }}"
                                                class="btn btn-xs btn-outline-secondary"><i class='bx bx-show'></i></a>
                                            <a href="{{ route('super-admin.business.edit', $business->business_id) }}"
                                                class="btn btn-xs btn-outline-primary ms-1"><i
                                                    class='bx bx-edit-alt'></i></a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            No business details associated.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Approve Modal (NEW) --}}
    <div class="modal fade" id="approveRegModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="approveRegForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Approve Vendor Registration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="vendor_id" id="approve-vendor-id">
                        <p>Are you sure you want to approve this vendor's registration?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Confirm Approve</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Reject Modal --}}
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="rejectRegForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Vendor Registration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="vendor_id" id="reject-vendor-id">
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

    {{-- Preview Modal --}}
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">File Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="previewModalBody"
                    style="min-height:400px;display:flex;justify-content:center;align-items:center;">
                    <p>Loading preview...</p>
                </div>
                <div class="modal-footer">
                    <a href="#" id="previewOpenNewTab" target="_blank" class="btn btn-outline-primary btn-sm">Open
                        in New Tab</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div class="position-fixed top-0 end-0 p-3" style="z-index:1200">
        <div id="actionToast" class="toast align-items-center text-white bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body" id="actionToastBody">Action completed.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
@endsection

{{-- This script block is unchanged as the delete button uses a standard form --}}
@push('page-script')
    <script>
        // ---- Guard the theme error globally ----
        (function patchNavTabsAnimation() {
            const tryPatch = () => {
                const H = window.Helpers;
                if (!H || typeof H.navTabsAnimation !== 'function') {
                    return setTimeout(tryPatch, 100);
                }
                const original = H.navTabsAnimation;
                H.navTabsAnimation = function(...args) {
                    try {
                        return original.apply(this, args);
                    } catch (e) {
                        if (e && /closest/i.test(e.message)) {
                            console.warn('⚠️ Ignored navTabsAnimation .closest() error');
                            return;
                        }
                        throw e;
                    }
                };
            };
            tryPatch();
        })();

        // ---- Your vendor management logic ----
        (function() {
            const token = document.querySelector('meta[name="csrf-token"]').content;

            function vendorJsonFetch(url, opts = {}) {
                return fetch(url, {
                    method: opts.method || 'POST',
                    headers: Object.assign({
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    }, opts.headers || {}),
                    body: opts.body ? JSON.stringify(opts.body) : undefined
                }).then(r => r.ok ? r.json() : r.json().then(e => Promise.reject(e)));
            }

            function showVendorToast(msg, isErr = false) {
                const el = document.getElementById('actionToast');
                const body = document.getElementById('actionToastBody');
                if (!el) return alert(msg);
                body.textContent = msg;
                el.classList.remove('bg-success', 'bg-danger');
                el.classList.add(isErr ? 'bg-danger' : 'bg-success');
                bootstrap.Toast.getOrCreateInstance(el).show();
            }

            function safeRemoveEl(el) {
                try {
                    if (!el) return;
                    setTimeout(() => el.remove?.(), 100);
                } catch (e) {
                    console.warn('safeRemoveEl ignored:', e);
                }
            }

            function updateStatusDisplay(newStatus, badgeClass) {
                // update primary badge in details
                const statusBadge = document.getElementById('vendor-status-badge');
                if (statusBadge) {
                    statusBadge.textContent = newStatus;
                    statusBadge.className = 'badge ' + badgeClass;
                }

                // update small header badge if present
                const headerBadge = document.getElementById('vendor-status-badge-header');
                if (headerBadge) {
                    headerBadge.textContent = newStatus;
                    headerBadge.className = 'badge ' + badgeClass;
                }

                // Only manage the Approve button here — do NOT disable the Reject button.
                const approveBtn = document.getElementById('btn-approve');

                if (approveBtn) {
                    if (newStatus === 'Approved') {
                        approveBtn.disabled = true;
                        approveBtn.setAttribute('aria-disabled', 'true');
                        approveBtn.classList.add('disabled');
                        approveBtn.title = 'Already approved';
                    } else {
                        // enable approve for Pending/Rejected/other statuses
                        approveBtn.disabled = false;
                        approveBtn.removeAttribute('aria-disabled');
                        approveBtn.classList.remove('disabled');
                        approveBtn.title = '';
                    }
                }

                // keep theme helper safe
                if (window.Helpers?.updateNavbarFixed) {
                    try {
                        window.Helpers.updateNavbarFixed();
                    } catch (_) {}
                }
            }


            // --- Approve and Reject modal wiring ---
            document.addEventListener('click', e => {
                // APPROVE: open modal pre-filled
                const approveBtn = e.target.closest?.('.btn-approve');
                if (approveBtn) {
                    const id = approveBtn.dataset.id;
                    // populate hidden input in approve modal
                    document.getElementById('approve-vendor-id').value = id;
                    // clear optional note
                    // The button uses data-bs-toggle/data-bs-target, so modal will open automatically.
                }

                // REJECT: open reject modal (same as before)
                const rejectBtn = e.target.closest?.('.btn-reject');
                if (rejectBtn) {
                    const id = rejectBtn.dataset.id;
                    document.getElementById('reject-vendor-id').value = id;
                    document.getElementById('reject-reason').value = '';
                    bootstrap.Modal.getOrCreateInstance(
                        document.getElementById('rejectModal')
                    ).show();
                }
            });

            // --- Approve form submit (AJAX) ---
            const approveForm = document.getElementById('approveRegForm');
            approveForm?.addEventListener('submit', ev => {
                ev.preventDefault();
                const id = document.getElementById('approve-vendor-id').value;
                const btn = approveForm.querySelector('[type=submit]');
                const old = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Approving...';

                vendorJsonFetch(`/super-admin/vendor/${id}/approve`, {})
                    .then(res => {
                        bootstrap.Modal.getInstance(document.getElementById('approveRegModal'))?.hide();
                        updateStatusDisplay('Approved', 'bg-success');
                        showVendorToast(res.message || 'Approved.');
                    })
                    .catch(err => {
                        console.error(err);
                        // attempt to read sensible message
                        const msg = (err && (err.message || (err.errors && Object.values(err.errors).flat()
                            .join(', ')))) || 'Failed to approve.';
                        showVendorToast(msg, true);
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = old;
                    });
            });

            // --- reject form submit ---
            const rejectForm = document.getElementById('rejectRegForm');
            rejectForm?.addEventListener('submit', ev => {
                ev.preventDefault();
                const id = document.getElementById('reject-vendor-id').value;
                const reason = document.getElementById('reject-reason').value;
                const btn = rejectForm.querySelector('[type=submit]');
                const old = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Rejecting...';
                vendorJsonFetch(`/super-admin/vendor/${id}/reject`, {
                        body: {
                            reason
                        }
                    })
                    .then(res => {
                        bootstrap.Modal.getInstance(document.getElementById('rejectModal'))?.hide();
                        updateStatusDisplay('Rejected', 'bg-danger');
                        showVendorToast(res.message || 'Rejected.');
                    })
                    .catch(err => {
                        console.error(err);
                        showVendorToast(err.message || 'Failed', true);
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = old;
                    });
            });

            // --- preview modal logic ---
            const pm = document.getElementById('previewModal');
            const pmBody = document.getElementById('previewModalBody');
            const pmLabel = document.getElementById('previewModalLabel');
            const openNew = document.getElementById('previewOpenNewTab');

            pm?.addEventListener('show.bs.modal', ev => {
                const btn = ev.relatedTarget;
                const url = btn?.getAttribute('data-file-url');
                const name = btn?.getAttribute('data-file-name') || 'File';
                pmLabel.textContent = 'Preview: ' + name;
                openNew.href = url || '#';
                pmBody.innerHTML = '<div class="spinner-border text-primary"></div>';

                if (!url) {
                    pmBody.innerHTML = '<p class="text-danger">No file URL provided.</p>';
                    return;
                }

                const ext = url.split('.').pop().toLowerCase();
                let embed = '';
                if (ext === 'pdf')
                    embed = `<iframe src="${url}" style="width:100%;height:70vh;border:none"></iframe>`;
                else if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].includes(ext))
                    embed = `<img src="${url}" class="img-fluid" style="max-height:75vh;margin:auto;">`;
                else
                    embed = `<p class="text-danger">Preview unavailable.</p>
                               <p><a href="${url}" target="_blank">Open file directly</a></p>`;

                setTimeout(() => pmBody.innerHTML = embed, 80);
            });

            pm?.addEventListener('hidden.bs.modal', () => {
                pmBody.innerHTML = '';
                pmLabel.textContent = 'File Preview';
                openNew.href = '#';
            });
        })();
    </script>
@endpush
