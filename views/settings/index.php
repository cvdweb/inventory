<?php
$settings = systemSettingsGet();
$pageTitle = 'Cài Đặt Hệ Thống';
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-gear-fill me-2" style="color:var(--accent)"></i>Cài Đặt Hệ Thống</h2>
    <p>Đặt tên cửa hàng / doanh nghiệp hiển thị trên đăng nhập, tiêu đề trình duyệt và hóa đơn</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-shop me-2"></i>Thông Tin Cửa Hàng / Doanh Nghiệp</div>
      <div class="card-body">
        <form method="POST" action="index.php?page=settings&action=save" id="settingsForm">
          <?= csrfField() ?>
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label">Tên cửa hàng / doanh nghiệp <span class="text-danger">*</span></label>
              <input class="form-control" name="store_name" id="storeNameInput" required
                value="<?= htmlspecialchars($settings['store_name'] ?? '') ?>"
                oninput="updateSettingsPreview()">
              <div class="form-text">Tên này hiển thị trên trang đăng nhập và phần đầu hóa đơn.</div>
            </div>
            <div class="col-md-5">
              <label class="form-label">Tên hệ thống hiển thị</label>
              <input class="form-control" name="app_name" id="appNameInput"
                value="<?= htmlspecialchars($settings['app_name'] ?? '') ?>"
                oninput="updateSettingsPreview()">
              <div class="form-text">Tiêu đề trình duyệt và phụ đề trang đăng nhập.</div>
            </div>
            <div class="col-md-8">
              <label class="form-label">Địa chỉ</label>
              <input class="form-control" name="address" id="addressInput"
                value="<?= htmlspecialchars($settings['address'] ?? '') ?>"
                oninput="updateSettingsPreview()">
            </div>
            <div class="col-md-4">
              <label class="form-label">Mã số thuế</label>
              <input class="form-control" name="tax_code" id="taxInput"
                value="<?= htmlspecialchars($settings['tax_code'] ?? '') ?>"
                placeholder="10 chữ số hoặc 10 chữ số-3 chữ số" oninput="updateSettingsPreview()">
            </div>
            <div class="col-md-6">
              <label class="form-label">Điện thoại</label>
              <input class="form-control" name="phone" id="phoneInput"
                value="<?= htmlspecialchars($settings['phone'] ?? '') ?>"
                oninput="updateSettingsPreview()">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input class="form-control" name="email" id="emailInput"
                value="<?= htmlspecialchars($settings['email'] ?? '') ?>"
                oninput="updateSettingsPreview()">
            </div>
            <div class="col-12">
              <label class="form-label">Khẩu hiệu / slogan</label>
              <input class="form-control" name="slogan" id="sloganInput"
                value="<?= htmlspecialchars($settings['slogan'] ?? '') ?>"
                oninput="updateSettingsPreview()">
            </div>
            <div class="col-12 d-flex align-items-center justify-content-between gap-2">
              <small class="text-muted">
                <?php if (!empty($settings['updated_at'])): ?>
                  <i class="bi bi-clock-history me-1"></i>Cập nhật lần cuối: <?= date('H:i d/m/Y', strtotime($settings['updated_at'])) ?><?= !empty($settings['updated_by']) ? ' bởi ' . htmlspecialchars($settings['updated_by']) : '' ?>
                <?php else: ?>
                  <i class="bi bi-info-circle me-1"></i>Đang dùng thông tin mặc định trong file cấu hình.
                <?php endif; ?>
              </small>
              <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu cài đặt</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-eye me-2"></i>Xem Trước</div>
      <div class="card-body">
        <div class="p-3 rounded-3 mb-3" style="background:var(--bg-main);border:1px solid var(--border)">
          <div style="font-size:12px;color:#6b7280;font-weight:700;margin-bottom:6px"><i class="bi bi-window-stack me-1"></i>Trang đăng nhập</div>
          <div class="fw-800" id="previewStore" style="font-size:16px"><?= htmlspecialchars($settings['store_name'] ?? '') ?></div>
          <div class="text-muted" id="previewApp" style="font-size:12.5px"><?= htmlspecialchars($settings['app_name'] ?? '') ?></div>
        </div>
        <div class="p-3 rounded-3" style="background:var(--bg-main);border:1px solid var(--border)">
          <div style="font-size:12px;color:#6b7280;font-weight:700;margin-bottom:6px"><i class="bi bi-receipt me-1"></i>Đầu hóa đơn</div>
          <div class="fw-800" id="previewStore2" style="font-size:15px"></div>
          <div id="previewAddress" style="font-size:12px;color:#6b7280;margin-top:2px"></div>
          <div id="previewContact" style="font-size:12px;color:#6b7280"></div>
          <div id="previewSlogan" style="font-size:11.5px;color:#9ca3af;font-style:italic;margin-top:2px"></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-lightbulb me-2 text-warning"></i>Mẹo triển khai</div>
      <div class="card-body">
        <ul class="mb-0 ps-3" style="font-size:13px;line-height:1.7">
          <li>Copy toàn bộ dự án cho khách hàng mới.</li>
          <li>Đăng nhập bằng tài khoản quản trị và vào <strong>Cài Đặt</strong>.</li>
          <li>Đổi <strong>tên cửa hàng</strong> rồi bấm <strong>Lưu cài đặt</strong> là xong.</li>
          <li>Thông tin được lưu tại <code>data/system_settings.json</code> và tự động sao lưu.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
function _sv(id) { const el = document.getElementById(id); return el ? el.value : ''; }
function updateSettingsPreview() {
  const store = _sv('storeNameInput');
  const app = _sv('appNameInput');
  const address = _sv('addressInput');
  const phone = _sv('phoneInput');
  const tax = _sv('taxInput');
  const slogan = _sv('sloganInput');

  document.getElementById('previewStore').textContent = store;
  document.getElementById('previewApp').textContent = app;
  document.getElementById('previewStore2').textContent = store;
  document.getElementById('previewAddress').textContent = address ? ('📍 ' + address) : '';
  let contact = phone ? ('📞 ' + phone) : '';
  if (tax) contact += (contact ? '  |  ' : '') + 'MST: ' + tax;
  document.getElementById('previewContact').textContent = contact;
  document.getElementById('previewSlogan').textContent = slogan;
}
updateSettingsPreview();
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
