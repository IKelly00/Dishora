<div class="modal fade" id="vendorRejectedModal" tabindex="-1" aria-labelledby="vendorRejectedModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-3">
            <div class="modal-header bg-danger text-white border-0 rounded-top py-4">
                <h5 class="modal-title fw-semibold mb-0" id="vendorRejectedModalLabel">
                    Vendor Registration Rejected
                </h5>
            </div>
            <div class="modal-body text-center py-4">
                <p class="mb-0">
                    Your vendor registration has been <strong>rejected</strong>.
                    Please contact support or continue using your customer account.
                </p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal"
                    id="vendorRejectedConfirmBtn">
                    Okay
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const modal = new bootstrap.Modal(document.getElementById('vendorRejectedModal'));
        modal.show();

        document.getElementById('vendorRejectedConfirmBtn').addEventListener('click', () => {
            window.location.href = "{{ route('customer.dashboard') }}";
        });
    });
</script>
