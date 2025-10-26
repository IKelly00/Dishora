@extends('layouts/commonMaster')
@section('title', 'Manage Businesses')

@section('layoutContent')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Include the navbar --}}
    @include('content.superadmin.partials.navbar')

    {{-- Main content wrapper --}}
    <div class="tab-content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">

            {{-- Page Header --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0">Manage Business Submissions</h4>
                <a href="{{ route('super-admin.dashboard') }}" class="btn btn-outline-secondary">
                    <i class="bx bx-arrow-back me-1"></i> Back to Dashboard
                </a>
            </div>

            {{-- Business Table Card --}}
            <div class="card mb-4 shadow-sm">
                {{-- Card Header --}}
                <div class="card-header d-flex align-items-center justify-content-between border-bottom mb-5">
                    <h5 class="mb-0 fw-bold">Business Listings</h5>
                    <a href="#" class="btn btn-outline-primary btn-sm">Export CSV</a>
                </div>

                {{-- Card Body (Filter Form) --}}
                <div class="card-body">
                    <form id="filterForm" class="row g-2 mb-4" method="GET"
                        action="{{ route('super-admin.businesses.index') }}">
                        <div class="col-md-8 col-lg-9">
                            <input type="text" name="q" id="businessSearchInput"
                                class="form-control form-control-sm"
                                placeholder="Search by business name, vendor name, or type" value="{{ $q ?? '' }}">
                        </div>
                        <div class="col-md-4 col-lg-3 d-flex">
                            <select name="status" id="businessStatusFilter" class="form-select form-select-sm me-2">
                                <option value="all" {{ ($status ?? 'all') === 'all' ? 'selected' : '' }}>All Statuses
                                </option>
                                <option value="Pending" {{ ($status ?? '') === 'Pending' ? 'selected' : '' }}>Pending
                                </option>
                                <option value="Approved" {{ ($status ?? '') === 'Approved' ? 'selected' : '' }}>Approved
                                </option>
                                <option value="Rejected" {{ ($status ?? '') === 'Rejected' ? 'selected' : '' }}>Rejected
                                </option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        </div>
                    </form>
                </div>

                {{-- Table --}}
                <div class="table-responsive text-nowrap">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Business</th>
                                <th>Vendor</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($businesses ?? collect()) as $b)
                                <tr id="biz-row-{{ $b->business_id }}">
                                    <td>
                                        <div class="fw-semibold">{{ $b->business_name }}</div>
                                    </td>
                                    <td>{{ optional($b->vendor)->fullname ?? '-' }}</td>
                                    <td>{{ $b->business_type }}</td>
                                    <td>
                                        @php
                                            $badge_class = [
                                                'Pending' => 'bg-warning',
                                                'Approved' => 'bg-success',
                                                'Rejected' => 'bg-danger',
                                            ];
                                            $status_key = $b->verification_status ?? 'Pending';
                                        @endphp
                                        <span
                                            class="badge {{ $badge_class[$status_key] ?? 'bg-secondary' }}">{{ $status_key }}</span>
                                    </td>
                                    <td>{{ optional($b->created_at)->format('Y-m-d H:i') }}</td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button type="button"
                                                class="btn btn-sm btn-icon btn-outline-secondary dropdown-toggle hide-arrow"
                                                data-bs-toggle="dropdown">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a href="{{ route('super-admin.business.view', $b->business_id) }}"
                                                    class="dropdown-item"><i class="bx bx-show me-1"></i> View Details</a>
                                                <a href="{{ route('super-admin.business.edit', $b->business_id) }}"
                                                    class="dropdown-item"><i class="bx bx-edit-alt me-1"></i> Edit</a>
                                                <div class="dropdown-divider"></div>

                                                {{-- *** MODIFIED: Logic for Approve/Reject buttons with Tooltips *** --}}
                                                @php
                                                    $isVendorApproved =
                                                        optional($b->vendor)->registration_status === 'Approved';
                                                    $tooltipMessage =
                                                        'Vendor account must be approved to manage this business';
                                                @endphp

                                                {{-- Approve Button --}}
                                                @if ($isVendorApproved)
                                                    <button class="dropdown-item text-success btn-approve"
                                                        data-id="{{ $b->business_id }}">
                                                        <i class="bx bx-check me-1"></i>
                                                        Approve
                                                    </button>
                                                @else
                                                    {{-- Wrapper for tooltip on disabled element --}}
                                                    <span class="d-block" data-bs-toggle="tooltip" data-bs-placement="left"
                                                        title="{{ $tooltipMessage }}">
                                                        <button class="dropdown-item text-success btn-approve"
                                                            data-id="{{ $b->business_id }}" disabled
                                                            style="pointer-events: none;">
                                                            <i class="bx bx-check me-1"></i>
                                                            Approve
                                                        </button>
                                                    </span>
                                                @endif

                                                {{-- Reject Button --}}
                                                @if ($isVendorApproved)
                                                    <button class="dropdown-item text-danger btn-reject"
                                                        data-id="{{ $b->business_id }}">
                                                        <i class="bx bx-x me-1"></i>
                                                        Reject
                                                    </button>
                                                @else
                                                    {{-- Wrapper for tooltip on disabled element --}}
                                                    <span class="d-block" data-bs-toggle="tooltip" data-bs-placement="left"
                                                        title="{{ $tooltipMessage }}">
                                                        <button class="dropdown-item text-danger btn-reject"
                                                            data-id="{{ $b->business_id }}" disabled
                                                            style="pointer-events: none;">
                                                            <i class="bx bx-x me-1"></i>
                                                            Reject
                                                        </button>
                                                    </span>
                                                @endif
                                                {{-- *** END MODIFICATION *** --}}

                                                <div class="dropdown-divider"></div>
                                                <button class="dropdown-item text-danger btn-delete"
                                                    data-id="{{ $b->business_id }}"><i class="bx bx-trash me-1"></i>
                                                    Delete</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No businesses found matching the
                                        current criteria.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Card Footer for Pagination --}}
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div>Showing {{ $businesses->firstItem() ?? 0 }} - {{ $businesses->lastItem() ?? 0 }} of
                        {{ $businesses->total() ?? 0 }} entries</div>
                    <div>
                        @if (isset($businesses) && method_exists($businesses, 'links'))
                            {{ $businesses->appends(request()->query())->links() }}
                        @endif
                    </div>
                </div>
            </div> {{-- End Business Table Card --}}

        </div> {{-- End container-p-y --}}
    </div> {{-- End tab-content-wrapper --}}


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

    {{-- Toast Container --}}
    <div class="position-fixed top-0 end-0 p-3" style="z-index:1200">
        <div id="saToast" class="toast align-items-center text-white bg-primary border-0" role="alert"
            aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="saToastBody">
                    Action completed.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
        </div>
    </div>
    {{-- End Toast Container --}}

@endsection

@push('page-script')
    {{-- Business Action JavaScript (Full Code) --}}
    <script>
        (function() {

            // *** ADDED: Initialize Bootstrap Tooltips ***
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            // *** END Tooltip Init ***

            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // --- Helper Functions ---
            function jsonFetch(url, opts = {}) {
                // ... (rest of the function is unchanged) ...
                return fetch(url, {
                    method: opts.method || 'POST',
                    headers: Object.assign({
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }, opts.headers || {}),
                    body: opts.body ? JSON.stringify(opts.body) : undefined
                }).then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            let error = new Error(err.message ||
                                `HTTP error! status: ${response.status}`);
                            error.response = err;
                            throw error;
                        });
                    }
                    if (response.status === 204) {
                        return Promise.resolve({});
                    }
                    return response.text().then(text => text ? JSON.parse(text) : {});
                });
            }

            function showToast(message, isError = false) {
                // ... (rest of the function is unchanged) ...
                const toastEl = document.getElementById('saToast');
                if (!toastEl) {
                    alert(message);
                    return;
                }
                const body = document.getElementById('saToastBody');
                body.innerText = message || (isError ? 'An error occurred.' : 'Success.');
                toastEl.classList.remove('bg-primary', 'bg-danger', 'bg-success');
                toastEl.classList.add(isError ? 'bg-danger' : 'bg-success');

                const toastInstance = bootstrap.Toast.getOrCreateInstance(toastEl);
                toastInstance.show();
            }

            // --- Event Listeners ---
            document.addEventListener('click', function(e) {
                // --- Approve Button ---
                const approveBtn = e.target.closest('.dropdown-item.btn-approve');
                if (approveBtn) {
                    // ... (rest of the logic is unchanged) ...
                    const id = approveBtn.dataset.id;
                    if (!id || !confirm('Are you sure you want to approve this business?')) return;

                    approveBtn.disabled = true;
                    approveBtn.innerHTML =
                        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Approving...';

                    jsonFetch(`/super-admin/business/${id}/approve`, {
                            method: 'POST'
                        })
                        .then(res => {
                            const row = document.getElementById('biz-row-' + id);
                            if (row) {
                                const statusEl = row.querySelector('td span.badge');
                                if (statusEl) {
                                    statusEl.className = 'badge bg-success';
                                    statusEl.textContent = 'Approved';
                                }
                                // Remove the wrapper span, not just the button
                                row.querySelector('.btn-approve')?.closest('span')?.remove();
                                row.querySelector('.btn-reject')?.closest('span')?.remove();
                                // Fallback for already-approved
                                row.querySelector('.btn-approve')?.remove();
                                row.querySelector('.btn-reject')?.remove();
                            }
                            showToast(res.message || 'Business approved successfully.');
                        })
                        .catch(err => {
                            console.error('Approve Error:', err);
                            showToast(err.message || 'Failed to approve business.', true);
                        })
                        .finally(() => {
                            if (document.getElementById('biz-row-' + id)) {
                                approveBtn.innerHTML = '<i class="bx bx-check me-1"></i> Approve';
                                approveBtn.disabled = false;
                            }
                        });
                }

                // --- Reject Button (Opens Modal) ---
                const rejectBtnModal = e.target.closest('.dropdown-item.btn-reject');
                if (rejectBtnModal) {
                    // ... (rest of the logic is unchanged) ...
                    const id = rejectBtnModal.dataset.id;
                    if (!id) return;
                    document.getElementById('reject-business-id').value = id;
                    document.getElementById('reject-reason').value = '';
                    const rejectModalEl = document.getElementById('rejectModal');
                    const modalInstance = bootstrap.Modal.getOrCreateInstance(rejectModalEl);
                    modalInstance.show();
                }

                // --- Delete Button ---
                const deleteBtn = e.target.closest('.dropdown-item.btn-delete');
                if (deleteBtn) {
                    // ... (rest of the logic is unchanged) ...
                    const id = deleteBtn.dataset.id;
                    if (!id || !confirm(
                            'Are you sure you want to delete this business? This action cannot be undone.'))
                        return;

                    deleteBtn.disabled = true;
                    deleteBtn.innerHTML =
                        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';

                    fetch(`/super-admin/business/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(err => {
                                    let error = new Error(err.message ||
                                        `HTTP error! status: ${response.status}`);
                                    error.response = err;
                                    throw error;
                                });
                            }
                            return response.text().then(text => text ? JSON.parse(text) : {});
                        })
                        .then(resp => {
                            document.getElementById('biz-row-' + id)?.remove();
                            showToast(resp.message || 'Business deleted successfully.');
                        })
                        .catch(err => {
                            console.error('Delete Error:', err);
                            showToast(err.message || 'Failed to delete business.', true);
                            if (document.getElementById('biz-row-' + id)) {
                                deleteBtn.innerHTML = '<i class="bx bx-trash me-1"></i> Delete';
                                deleteBtn.disabled = false;
                            }
                        });
                }
            }); // End click listener

            // --- Reject Form Submission ---
            const rejectForm = document.getElementById('rejectForm');
            if (rejectForm) {
                // ... (rest of the logic is unchanged) ...
                rejectForm.addEventListener('submit', function(ev) {
                    ev.preventDefault();
                    const id = document.getElementById('reject-business-id').value;
                    const reason = document.getElementById('reject-reason').value;
                    const submitButton = rejectForm.querySelector('button[type="submit"]');
                    const originalButtonText = submitButton.innerHTML;

                    submitButton.disabled = true;
                    submitButton.innerHTML =
                        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Rejecting...';

                    jsonFetch(`/super-admin/business/${id}/reject`, {
                            method: 'POST',
                            body: {
                                reason: reason
                            }
                        })
                        .then(res => {
                            const modalEl = document.getElementById('rejectModal');
                            const modalInstance = bootstrap.Modal.getInstance(modalEl);
                            modalInstance?.hide();

                            const row = document.getElementById('biz-row-' + id);
                            if (row) {
                                const statusEl = row.querySelector('td span.badge');
                                if (statusEl) {
                                    statusEl.className = 'badge bg-danger';
                                    statusEl.textContent = 'Rejected';
                                }
                                // Remove the wrapper span, not just the button
                                row.querySelector('.btn-approve')?.closest('span')?.remove();
                                row.querySelector('.btn-reject')?.closest('span')?.remove();
                                // Fallback for already-approved
                                row.querySelector('.btn-approve')?.remove();
                                row.querySelector('.btn-reject')?.remove();
                            }
                            showToast(res.message || 'Business rejected successfully.');
                        })
                        .catch(err => {
                            console.error('Reject Error:', err);
                            showToast(err.message || 'Failed to reject business.', true);
                        })
                        .finally(() => {
                            submitButton.innerHTML = originalButtonText;
                            submitButton.disabled = false;
                        });
                });
            } // End if (rejectForm)

        })(); // End IIFE
    </script>
@endpush
