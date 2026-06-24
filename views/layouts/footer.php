  </div><!-- .content-body -->
</div><!-- .main-content -->

<nav class="pwa-browser-nav" id="pwaBrowserNav" aria-label="Điều hướng ứng dụng">
  <div class="pwa-browser-nav-inner">
    <button type="button" class="pwa-browser-nav-btn" id="pwaNavBack" title="Quay lại" aria-label="Quay lại">
      <i class="bi bi-arrow-left"></i>
    </button>
    <button type="button" class="pwa-browser-nav-btn" id="pwaNavForward" title="Đi tới" aria-label="Đi tới">
      <i class="bi bi-arrow-right"></i>
    </button>
    <button type="button" class="pwa-browser-nav-btn" id="pwaNavReload" title="Tải lại trang" aria-label="Tải lại trang">
      <i class="bi bi-arrow-clockwise"></i>
    </button>
  </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/toast.js?v=<?= filemtime(BASE_PATH . '/assets/js/toast.js') ?>"></script>
<script src="assets/js/app.js?v=<?= filemtime(BASE_PATH . '/assets/js/app.js') ?>"></script>
</body>
</html>
