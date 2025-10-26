@extends('layouts/commonMaster')
@section('title', 'Manage Users')
@section('layoutContent')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('content.superadmin.partials.navbar')

    <div class="tab-content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0">Manage System Users</h4>
                <a href="{{ route('super-admin.dashboard') }}" class="btn btn-outline-secondary">
                    <i class="bx bx-arrow-back me-1"></i> Back to Dashboard
                </a>
            </div>

            {{-- User Table Card --}}
            <div class="card mb-4 shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between border-bottom mb-5">
                    <h5 class="mb-0 fw-bold">System Users</h5>
                    {{-- <a href="{{ route('super-admin.users.create') }}" class="btn btn-primary btn-sm">
                    <i class="bx bx-plus me-1"></i> Add New User
                </a> --}}
                </div>

                <div class="card-body">
                    <form id="filterForm" class="row g-2 mb-4">
                        <div class="col-12">
                            <input type="text" name="q" id="searchInput" class="form-control form-control-sm"
                                placeholder="Search by name, email, or username" value="{{ request('q') }}">
                            <button type="submit" style="display: none;"></button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive text-nowrap">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User Name</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        {{-- This content is rendered initially by Laravel --}}
                        <tbody id="user-table-body">
                            @forelse($users as $user)
                                <tr id="user-row-{{ $user->user_id }}">
                                    <td>
                                        <div class="d-flex justify-content-start align-items-center">
                                            <div class="d-flex flex-column">
                                                <span class="fw-medium">{{ $user->username }}</span>
                                                <small class="text-muted">{{ $user->email }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $user->fullname }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        {{-- Adjust this logic if your status column is different --}}
                                        @if (isset($user->is_active) && !$user->is_active)
                                            <span class="badge bg-label-secondary me-1">Inactive</span>
                                        @else
                                            <span class="badge bg-label-success me-1">Active</span>
                                        @endif
                                    </td>
                                    <td>{{ $user->created_at->format('M d, Y') }}</td>
                                    <td class="text-center">
                                        {{-- Action Dropdown matching business table style --}}
                                        <div class="dropdown">
                                            <button type="button"
                                                class="btn btn-sm btn-icon btn-outline-secondary dropdown-toggle hide-arrow"
                                                data-bs-toggle="dropdown">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                {{-- Edit Link --}}
                                                <a class="dropdown-item"
                                                    href="{{ route('super-admin.users.edit', $user->user_id) }}">
                                                    <i class="bx bx-edit-alt me-1"></i> Edit
                                                </a>

                                                {{-- Optional: View Details (if you create a view page) --}}
                                                <a href="{{ route('super-admin.users.show', $user->user_id) }}"
                                                    class="dropdown-item">
                                                    <i class="bx bx-show me-1"></i> View Details
                                                </a>

                                                {{-- Divider --}}
                                                <div class="dropdown-divider"></div>

                                                {{-- Delete Button --}}
                                                <button class="dropdown-item text-danger btn-delete"
                                                    data-id="{{ $user->user_id }}">
                                                    <i class="bx bx-trash me-1"></i> Delete
                                                </button>

                                                {{-- Approve/Reject don't apply here directly, handled in Edit form --}}
                                                {{--
            <button class="dropdown-item text-success btn-approve-user" data-id="{{ $user->user_id }}">
                <i class="bx bx-check me-1"></i> Activate
            </button>
            <button class="dropdown-item text-danger btn-reject-user" data-id="{{ $user->user_id }}">
                <i class="bx bx-x me-1"></i> Deactivate
            </button>
            --}}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No users found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center" id="pagination-footer">
                    <div id="summary-text">Showing {{ $users->firstItem() ?? 0 }} - {{ $users->lastItem() ?? 0 }} of
                        {{ $users->total() ?? 0 }} entries</div>
                    <div id="pagination-links">
                        @if (isset($users) && method_exists($users, 'links'))
                            {{ $users->appends(request()->query())->links() }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation JS --}}
    @push('page-script')
        <script>
            // Utility function to debounce actions
            function debounce(fn, delay) {
                let timerId;
                return function(...args) {
                    clearTimeout(timerId);
                    timerId = setTimeout(() => {
                        fn.apply(this, args);
                    }, delay);
                };
            }

            // Helper function to prevent XSS attacks when building HTML from JSON
            function escapeHTML(str) {
                if (str === null || str === undefined) {
                    return '';
                }
                return str.toString().replace(/[&<>"']/g, function(m) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    } [m];
                });
            }

            // Function to perform AJAX search
            async function fetchUsers(query) {
                const url = new URL('{{ route('super-admin.users.index') }}');
                url.searchParams.set('q', query);
                url.searchParams.set('page', 1); // Ensure page 1 is requested on new query

                const tableBody = document.getElementById('user-table-body');
                const paginationFooter = document.getElementById('pagination-footer');

                tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Loading...</td></tr>';
                paginationFooter.style.opacity = 0.5;

                try {
                    const response = await fetch(url.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        console.error('HTTP Error:', response.status, response.statusText);
                        throw new Error(`Server returned status ${response.status}`);
                    }

                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.includes("application/json")) {
                        const data = await response.json();

                        // Build table rows from JSON data
                        let tableHtml = '';
                        if (data.users_data && data.users_data.length > 0) {
                            data.users_data.forEach(user => {
                                tableHtml += `
                                    <tr id="user-row-${user.user_id}">
                                        <td>
                                            <div class="d-flex justify-content-start align-items-center">
                                                <div class="d-flex flex-column">
                                                    <span class="fw-medium">${escapeHTML(user.username)}</span>
                                                    <small class="text-muted">${escapeHTML(user.email)}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>${escapeHTML(user.fullname)}</td>
                                        <td>${escapeHTML(user.email)}</td>
                                        <td>${user.status_html}</td> <td>${escapeHTML(user.registered_date)}</td>
                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                    <i class="bx bx-dots-vertical-rounded"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="${user.edit_url}">
                                                        <i class="bx bx-edit-alt me-1"></i> Edit
                                                    </a>
                                                    <button class="dropdown-item btn-delete" data-id="${user.user_id}">
                                                        <i class="bx bx-trash me-1"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            });
                        } else {
                            tableHtml = '<tr><td colspan="6" class="text-center py-4 text-muted">No users found.</td></tr>';
                        }

                        tableBody.innerHTML = tableHtml;

                        // Inject the new pagination HTML
                        document.getElementById('pagination-links').innerHTML = data.pagination_links;
                        document.getElementById('summary-text').innerHTML =
                            `Showing ${data.from ?? 0} - ${data.to ?? 0} of ${data.total ?? 0} entries`;

                    } else {
                        throw new Error("Received non-JSON response from server.");
                    }

                } catch (error) {
                    console.error('AJAX search failed:', error);
                    tableBody.innerHTML =
                        '<tr><td colspan="6" class="text-center py-4 text-danger">Error fetching data. Check console for details.</td></tr>';
                } finally {
                    paginationFooter.style.opacity = 1;
                }
            }


            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('searchInput');
                const filterForm = document.getElementById('filterForm');

                if (searchInput) {
                    const debouncedFetch = debounce(function() {
                        fetchUsers(searchInput.value);
                    }, 300); // 300ms delay

                    searchInput.addEventListener('input', debouncedFetch);
                }

                // Prevent the form from submitting via the browser when hitting Enter
                if (filterForm) {
                    filterForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        fetchUsers(searchInput.value);
                    });
                }
            });

            // Note: showToast function is assumed to be globally available or included via commonMaster
            function showToast(message, isError = false) {
                // Placeholder/Example implementation of showToast
                console.log('Toast:', message, 'Error:', isError);
                // ... (your actual showToast logic) ...
            }

            document.addEventListener('click', function(e) {
                // ... (delete logic) ...
                const d = e.target.closest('.dropdown-item.btn-delete');
                if (d) {
                    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;

                    const userId = d.dataset.id;
                    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                    fetch(`/super-admin/users/${userId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        }
                    }).then(r => r.json()).then(resp => {
                        if (resp.message) {
                            document.getElementById('user-row-' + userId)?.remove();
                            showToast(resp.message || 'User deleted successfully.');
                        } else {
                            showToast('Failed to delete user.', true);
                        }
                    }).catch(err => {
                        showToast('Error deleting user.', true);
                        console.error(err);
                    });
                }
            });
        </script>
    @endpush
@endsection
