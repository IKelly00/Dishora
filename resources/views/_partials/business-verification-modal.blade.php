<div class="modal fade d-none" id="roleSwitchModal" tabindex="-1" aria-labelledby="roleSwitchLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-3">

            <!-- Header -->
            <div class="modal-header bg-warning text-dark border-0 rounded-top py-4">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                    <h5 class="modal-title fw-semibold mb-0" id="verificationModalLabel">
                        Business Verification Pending
                    </h5>
                </div>
                {{-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> --}}
            </div>

            <!-- Body -->
            <div class="modal-body text-center py-4">
                <i class="bi bi-clock-history text-warning display-4 mb-3"></i>
                <p class="mb-0">
                    Your selected business is still <strong>pending verification</strong>.
                    Some features may be restricted until approval is complete.
                </p>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-warning px-4" data-bs-dismiss="modal">Okay</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('roleSwitchModal');
        modalEl.classList.remove('d-none');

        const roleModal = new bootstrap.Modal(modalEl, {
            backdrop: 'static',
            keyboard: false
        });

        roleModal.show();
    });
</script>
