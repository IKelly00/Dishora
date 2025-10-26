@php
    $currentRole = session('active_role') ?? 'customer';
    $targetRole = $currentRole === 'customer' ? 'vendor' : 'customer';
    $showRolePopup = session('showRolePopup', false);
@endphp

<div class="modal fade" id="roleSwitchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Switch Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Do you want to switch to <strong>{{ ucfirst($targetRole) }}</strong> role?</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="{{ route('role.switch') }}">
                    @csrf
                    <input type="hidden" name="target_role" value="{{ $targetRole }}">
                    <button type="submit" class="btn btn-primary">Yes, Switch</button>
                </form>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    id="cancelRoleSwitchBtn">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('roleSwitchModal');
        if (!modalEl) return;
        const cancelBtn = modalEl.querySelector('[data-bs-dismiss="modal"]');

        // Debug logs
        //modalEl.addEventListener('show.bs.modal', () => console.log('show fired'));
        //modalEl.addEventListener('hide.bs.modal', () => console.log('hide fired'));

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                //console.log('Cancel button clicked');
                document.querySelectorAll('.menu-item.disabled').forEach(item => {
                    item.classList.remove('disabled');
                    const link = item.querySelector('a');
                    if (link) {
                        link.style.pointerEvents = '';
                        link.style.opacity = '';
                        link.title = '';
                    }
                });
            });
        }
    });
</script>
