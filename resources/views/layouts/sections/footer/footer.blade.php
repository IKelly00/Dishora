@php
    $containerFooter = !empty($containerNav) ? $containerNav : 'container-fluid';
@endphp

<!-- Footer -->
<footer class="content-footer footer bg-footer-theme">
    <div class="{{ $containerFooter }}">
        <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
            <div class="text-body">
                <span class="text-danger"><i class="tf-icons ri-heart-fill"></i></span>
                <a href="{{ !empty(config('variables.creatorUrl')) ? config('variables.creatorUrl') : '' }}"
                    target="_blank"
                    class="footer-link">{{ !empty(config('variables.creatorName')) ? config('variables.creatorName') : '' }}</a>
                ©
                <script>
                    document.write(new Date().getFullYear())
                </script>
            </div>
            <div class="d-none d-lg-inline-block">
                <a href="/" target="_blank" class="footer-link me-4">License</a>
                <a href="/" target="_blank" class="footer-link me-4">Privacy</a>
                <a href="/" target="_blank" class="footer-link d-none d-sm-inline-block">About Us</a>
            </div>
        </div>
    </div>
</footer>
<!--/ Footer -->
