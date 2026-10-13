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
    <?php $themes = systemThemes(); $activeTheme = currentThemeKey(); ?>
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-palette-fill me-2"></i>Màu Giao Diện</div>
      <div class="card-body">
        <p class="text-muted mb-3" style="font-size:12.5px">Chọn màu phù hợp với cửa hàng vật liệu xây dựng. Thay đổi được xem trước ngay và lưu cùng cài đặt.</p>
        <div class="theme-grid">
          <?php foreach ($themes as $key => $theme): ?>
          <label class="theme-option <?= $activeTheme === $key ? 'is-selected' : '' ?>">
            <input type="radio" name="theme" value="<?= htmlspecialchars($key) ?>" form="settingsForm"
              <?= $activeTheme === $key ? 'checked' : '' ?> onchange="applyThemePreview('<?= htmlspecialchars($key) ?>')">
            <span class="theme-swatch" style="--sw:<?= htmlspecialchars($theme['accent']) ?>;--sw2:<?= htmlspecialchars($theme['accent_dark']) ?>;--sw3:<?= htmlspecialchars($theme['sidebar']) ?>">
              <i class="bi bi-check-lg"></i>
            </span>
            <span class="theme-name"><?= htmlspecialchars($theme['label']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

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

<style>
.theme-grid{display:grid;gap:10px;grid-template-columns:repeat(3,minmax(0,1fr))}
.theme-option{align-items:center;border:1px solid var(--border);border-radius:8px;cursor:pointer;display:flex;gap:10px;margin:0;padding:10px;transition:.15s}
.theme-option:hover{border-color:var(--accent)}
.theme-option.is-selected{background:var(--accent-softer);border-color:var(--accent);box-shadow:0 0 0 2px var(--accent-ring)}
.theme-option input{opacity:0;pointer-events:none;position:absolute}
.theme-swatch{align-items:center;background:linear-gradient(135deg,var(--sw) 0%,var(--sw) 55%,var(--sw2) 55%,var(--sw2) 100%);border-radius:7px;box-shadow:inset 0 0 0 2px rgba(255,255,255,.35);color:#fff;display:flex;flex-shrink:0;height:34px;justify-content:center;width:34px}
.theme-swatch i{font-size:16px;opacity:0;transition:.15s}
.theme-option.is-selected .theme-swatch i{opacity:1}
.theme-name{color:var(--text-mid);font-size:12.5px;font-weight:700}
.theme-option.is-selected .theme-name{color:var(--accent-dark)}
@media(max-width:575.98px){.theme-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>

<script>
const THEMES = <?= json_encode($themes, JSON_UNESCAPED_UNICODE) ?>;
function _hexToRgb(hex) {
  hex = String(hex).replace('#', '');
  if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
  const n = parseInt(hex, 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255].join(',');
}
function applyThemePreview(key) {
  const t = THEMES[key];
  if (!t) return;
  const rgb = _hexToRgb(t.accent);
  const r = document.documentElement.style;
  r.setProperty('--accent', t.accent);
  r.setProperty('--accent-dark', t.accent_dark);
  r.setProperty('--sidebar-active', t.accent);
  r.setProperty('--bg-sidebar', t.sidebar);
  r.setProperty('--sidebar-active-bg', `rgba(${rgb},.12)`);
  r.setProperty('--accent-soft', `rgba(${rgb},.12)`);
  r.setProperty('--accent-softer', `rgba(${rgb},.08)`);
  r.setProperty('--accent-ring', `rgba(${rgb},.15)`);
  r.setProperty('--accent-glow', `rgba(${rgb},.3)`);
  r.setProperty('--accent-glow-strong', `rgba(${rgb},.4)`);
  r.setProperty('--accent-avatar', `rgba(${rgb},.2)`);
  document.querySelectorAll('.theme-option').forEach(l => l.classList.toggle('is-selected', l.querySelector('input').value === key));
}
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
