@extends('layouts/commonMaster')
@section('title', 'Manage Vendors')

@section('layoutContent')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Include the navbar --}}
    @include('content.superadmin.partials.navbar')

    {{-- Main content wrapper --}}
    <div class="tab-content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">

            {{-- Page Header --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0">Manage Vendors</h4>
                <a href="{{ route('super-admin.dashboard') }}" class="btn btn-outline-secondary">
                    <i class="bx bx-arrow-back me-1"></i> Back to Dashboard
                </a>
            </div>

            {{-- Vendor Table Card --}}
            <div class="card mb-4 shadow-sm">
                {{-- Card Header --}}
                <div class="card-header d-flex align-items-center justify-content-between border-bottom mb-5">
                    <h5 class="mb-0 fw-bold">Vendor Listings</h5>
                    {{-- Optional Add Button Placeholder --}}
                    {{-- <a href="#" class="btn btn-primary btn-sm"><i class="bx bx-plus me-1"></i> Add New Vendor</a> --}}
                </div>

                {{-- Card Body (Filter Form) --}}
                <div class="card-body">
                    <form id="filterForm" class="row g-2 mb-4" method="GET"
                        action="{{ route('super-admin.vendors.index') }}">
                        <div class="col-md-8 col-lg-9">
                            <input type="text" name="q" id="vendorSearchInput" class="form-control form-control-sm"
                                placeholder="Search by name, phone, or business name" value="{{ $q ?? '' }}">
                        </div>
                        <div class="col-md-4 col-lg-3 d-flex">
                            <select name="status" id="vendorStatusFilter" class="form-select form-select-sm me-2">
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
                                <th>Vendor Name</th>
                                <th>Phone Number</th>
                                <th>Valid ID No. (Primary Business)</th> {{-- Clarified label --}}
                                <th>Registration Status</th>
                                <th>Registered On</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($vendors ?? collect()) as $vendor)
                                <tr id="vendor-row-{{ $vendor->vendor_id }}">
                                    <td>{{ $vendor->fullname }}</td>
                                    <td>{{ $vendor->phone_number ?? '-' }}</td>
                                    {{-- Access first business detail's valid_id_no --}}
                                    <td>
                                        @if ($vendor->businessDetails->isNotEmpty())
                                            {{ $vendor->businessDetails->first()->valid_id_no ?? 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $badge_class = [
                                                'Pending' => 'bg-warning',
                                                'Approved' => 'bg-success',
                                                'Rejected' => 'bg-danger',
                                            ];
                                            $status_key = $vendor->registration_status ?? 'Pending';
                                        @endphp
                                        <span
                                            class="badge {{ $badge_class[$status_key] ?? 'bg-secondary' }}">{{ $status_key }}</span>
                                    </td>
                                    <td>{{ optional($vendor->created_at)->format('Y-m-d H:i') }}</td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button type="button"
                                                class="btn btn-sm btn-icon btn-outline-secondary dropdown-toggle hide-arrow"
                                                data-bs-toggle="dropdown">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                {{-- Placeholder links - Replace '#' with actual routes when created --}}
                                                <a href="{{ route('super-admin.vendor.view', $vendor->vendor_id) }}"
                                                    class="dropdown-item"><i class="bx bx-show me-1"></i> View Details</a>
                                                <a href="#" class="dropdown-item disabled"><i
                                                        class="bx bx-edit-alt me-1"></i> Edit</a>

                                                {{-- Show Approve/Reject only if status is Pending --}}
                                                @if ($vendor->registration_status === 'Pending')
                                                    <div class="dropdown-divider"></div>
                                                    {{-- Action buttons with data-id --}}
                                                    <button class="dropdown-item text-success btn-approve"
                                                        data-id="{{ $vendor->vendor_id }}"><i class="bx bx-check me-1"></i>
                                                        Approve Registration.</button>
                                                    <button class="dropdown-item text-danger btn-reject"
                                                        data-id="{{ $vendor->vendor_id }}"><i class="bx bx-x me-1"></i>
                                                        Reject Registration.</button>
                                                @endif

                                                <div class="dropdown-divider"></div>
                                                {{-- Removed 'disabled' class to make it functional --}}
                                                <button class="dropdown-item text-danger btn-delete-vendor"
                                                    data-id="{{ $vendor->vendor_id }}"><i class="bx bx-trash me-1"></i>
                                                    Delete</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    {{-- Adjusted colspan to 6 --}}
                                    <td colspan="6" class="text-center py-4 text-muted">No vendors found matching the
                                        current criteria.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Card Footer for Pagination --}}
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div>Showing {{ $vendors->firstItem() ?? 0 }} - {{ $vendors->lastItem() ?? 0 }} of
                        {{ $vendors->total() ?? 0 }} entries</div>
                    <div>
                        @if (isset($vendors) && method_exists($vendors, 'links'))
                            {{ $vendors->appends(request()->query())->links() }}
                        @endif
                    </div>
                </div>
            </div> {{-- End Vendor Table Card --}}

        </div> {{-- End container-p-y --}}
    </div> {{-- End tab-content-wrapper --}}

    {{-- *** ADDED: Reject Modal (Adapted for Vendors) *** --}}
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="rejectForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Vendor Registration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
    {{-- End Reject Modal --}}

    {{-- Existing Toast Container --}}
    <div class="position-fixed top-0 end-0 p-3" style="z-index:1200">
        <div id="vendorActionToast" class="toast align-items-center text-white bg-success border-0" role="alert"
            aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="vendorActionToastBody">
                    Action completed.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
        </div>
    </div>

    {{-- *** REMOVED: Old inline/broken script *** --}}

@endsection


{{-- *** ADDED: Full page script from business page, adapted for vendors *** --}}
@push('page-script')
    <script>
        (function() {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // --- Helper Functions ---
            function jsonFetch(url, opts = {}) {
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
                // *** UPDATED to use vendorActionToast ***
                const toastEl = document.getElementById('vendorActionToast');
                if (!toastEl) {
                    alert(message);
                    return;
                }
                const body = document.getElementById('vendorActionToastBody');
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
                    const id = approveBtn.dataset.id;
                    if (!id || !confirm('Are you sure you want to approve this vendor?')) return;

                    approveBtn.disabled = true;
                    approveBtn.innerHTML =
                        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Approving...';

                    // *** UPDATED URL for vendor approval ***
                    jsonFetch(`/super-admin/vendor/${id}/approve`, {
                            method: 'POST'
                        })
                        .then(res => {
                            // *** UPDATED Row ID ***
                            const row = document.getElementById('vendor-row-' + id);
                            if (row) {
                                const statusEl = row.querySelector('td span.badge');
                                if (statusEl) {
                                    statusEl.className = 'badge bg-success';
                                    statusEl.textContent = 'Approved';
                                }
                                // Hide action buttons after approval
                                row.querySelector('.btn-approve')?.remove();
                                row.querySelector('.btn-reject')?.remove();
                            }
                            // *** UPDATED Toast Message ***
                            showToast(res.message || 'Vendor approved successfully.');
                        })
                        .catch(err => {
                            console.error('Approve Error:', err);
                            // *** UPDATED Toast Message ***
                            showToast(err.message || 'Failed to approve vendor.', true);
                        })
                        .finally(() => {
                            // *** UPDATED Button Text ***
                            approveBtn.innerHTML = '<i class="bx bx-check me-1"></i> Approve Reg.';
                            approveBtn.disabled = false;
                        });
                }

                // --- Reject Button (Opens Modal) ---
                const rejectBtnModal = e.target.closest('.dropdown-item.btn-reject');
                if (rejectBtnModal) {
                    const id = rejectBtnModal.dataset.id;
                    if (!id) return;
                    // *** UPDATED Modal Input ID ***
                    document.getElementById('reject-vendor-id').value = id;
                    document.getElementById('reject-reason').value = '';
                    const rejectModalEl = document.getElementById('rejectModal');
                    const modalInstance = bootstrap.Modal.getOrCreateInstance(rejectModalEl);
                    modalInstance.show();
                }

                // --- Delete Button ---
                // *** UPDATED Button Selector ***
                const deleteBtn = e.target.closest('.dropdown-item.btn-delete-vendor');
                if (deleteBtn) {
                    const id = deleteBtn.dataset.id;
                    // *** UPDATED Confirm Message ***
                    if (!id || !confirm(
                            'Are you sure you want to delete this vendor? This action cannot be undone.'))
                        return;

                    deleteBtn.disabled = true;
                    deleteBtn.innerHTML =
                        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';

                    // *** UPDATED URL for vendor delete ***
                    fetch(`/super-admin/vendor/${id}`, {
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
                            // *** UPDATED Row ID ***
                            document.getElementById('vendor-row-' + id)?.remove();
                            // *** UPDATED Toast Message ***
                            showToast(resp.message || 'Vendor deleted successfully.');
                        })
                        .catch(err => {
                            console.error('Delete Error:', err);
                            // *** UPDATED Toast Message ***
                            showToast(err.message || 'Failed to delete vendor.', true);
                            // *** UPDATED Row ID ***
                            if (document.getElementById('vendor-row-' + id)) {
                                deleteBtn.innerHTML = '<i class="bx bx-trash me-1"></i> Delete';
                                deleteBtn.disabled = false;
                            }
                        });
                }
            }); // End click listener

            // --- Reject Form Submission ---
            const rejectForm = document.getElementById('rejectForm');
            if (rejectForm) {
                rejectForm.addEventListener('submit', function(ev) {
                    ev.preventDefault();
                    // *** UPDATED Modal Input ID ***
                    const id = document.getElementById('reject-vendor-id').value;
                    const reason = document.getElementById('reject-reason').value;
                    const submitButton = rejectForm.querySelector('button[type="submit"]');
                    const originalButtonText = submitButton.innerHTML;

                    submitButton.disabled = true;
                    submitButton.innerHTML =
                        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Rejecting...';

                    // *** UPDATED URL for vendor reject ***
                    jsonFetch(`/super-admin/vendor/${id}/reject`, {
                            method: 'POST',
                            body: {
                                reason: reason
                            }
                        })
                        .then(res => {
                            const modalEl = document.getElementById('rejectModal');
                            const modalInstance = bootstrap.Modal.getInstance(modalEl);
                            modalInstance?.hide();

                            // *** UPDATED Row ID ***
                            const row = document.getElementById('vendor-row-' + id);
                            if (row) {
                                const statusEl = row.querySelector('td span.badge');
                                if (statusEl) {
                                    statusEl.className = 'badge bg-danger';
                                    statusEl.textContent = 'Rejected';
                                }
                                // Hide action buttons after rejection
                                row.querySelector('.btn-approve')?.remove();
                                row.querySelector('.btn-reject')?.remove();
                            }
                            // *** UPDATED Toast Message ***
                            showToast(res.message || 'Vendor rejected successfully.');
                        })
                        .catch(err => {
                            console.error('Reject Error:', err);
                            // *** UPDATED Toast Message ***
                            showToast(err.message || 'Failed to reject vendor.', true);
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
