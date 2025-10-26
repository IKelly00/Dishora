<div class="modal fade" id="vendorStatusModal" tabindex="-1" aria-labelledby="vendorStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-3">
            <div class="modal-header bg-warning text-dark border-0 rounded-top py-4">
                <h5 class="modal-title fw-semibold mb-0" id="vendorStatusModalLabel">
                    Vendor Registration Pending
                </h5>
            </div>
            <div class="modal-body text-center py-4">
                <p class="mb-0">
                    Your vendor registration is still <strong>pending approval</strong>.
                </p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-warning px-4" data-bs-dismiss="modal" id="vendorStatusConfirmBtn">
                    Okay
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('vendorStatusConfirmBtn').addEventListener('click', () => {
        const modalEl = document.getElementById('vendorStatusModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) {
            modal.hide();
        }

        // Optional: Set session flag so it doesn't show again until status changes
        fetch("{{ route('dismiss.vendor.status.modal') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                dismissed: true
            })
        }).catch(err => {
            console.warn('Could not set dismissal flag:', err);
            // Still okay — modal is closed at least
        });
    });
</script>
