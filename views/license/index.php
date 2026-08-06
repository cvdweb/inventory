<?php
$license = licenseGet();
$status = licenseStatus($license);
$customer = $license['customer'] ?? [];
$pricing = $license['pricing'] ?? [];
$policy = $license['policy'] ?? [];
$payments = array_reverse($license['payments'] ?? []);
$monthlyPrice = (float)($pricing['monthly_price'] ?? 200000);
$totalPaid = array_sum(array_map(fn($p) => (float)($p['amount'] ?? 0), $license['payments'] ?? []));
$featureSettings = featureGetSettings();
$featureProfiles = featureProfiles();
$featureDefinitions = featureDefinitions();
$currentFeatureProfile = $featureSettings['profile'] ?? 'full';
$featureReadiness = [];
foreach(array_keys($featureProfiles) as $profileKey) $featureReadiness[$profileKey] = featureProfileReadiness($profileKey);
$stateColor = match($status['state']) {
    'active' => 'success',
    'warning' => 'warning',
    'grace' => 'warning',
    'expired', 'locked' => 'danger',
    default => 'secondary',
};
$pageTitle = 'Quản Lý Giấy Phép';
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-key-fill me-2 text-danger"></i>Quản Lý Giấy Phép</h2>
    <p>Cấu hình thời hạn sử dụng, thanh toán và quyền ghi dữ liệu của khách hàng</p>
  </div>
  <div class="d-flex align-items-center gap-2"><a href="index.php?page=help&topic=license" class="btn btn-outline-secondary context-help-btn" title="Hướng dẫn quản lý giấy phép"><i class="bi bi-question-circle"></i><span class="context-help-label">Hướng dẫn</span></a><span class="badge bg-<?= $stateColor ?>" style="font-size:13px;padding:8px 12px"><?= htmlspecialchars($status['label']) ?></span></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card stat-<?= $stateColor === 'danger' ? 'red' : ($stateColor === 'warning' ? 'amber' : 'green') ?>">
      <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
      <div class="stat-value" style="font-size:18px"><?= date('d/m/Y', strtotime($status['end_date'])) ?></div>
      <div class="stat-label">Ngày hết hạn</div>
      <div class="stat-bg"><i class="bi bi-calendar-check"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-blue">
      <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-value"><?= (int)$status['days_remaining'] ?></div>
      <div class="stat-label">Ngày còn lại</div>
      <div class="stat-bg"><i class="bi bi-hourglass-split"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-value" style="font-size:18px"><?= licenseFormatMoney($totalPaid) ?></div>
      <div class="stat-label">Tổng đã thanh toán</div>
      <div class="stat-bg"><i class="bi bi-cash-stack"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-amber">
      <div class="stat-icon"><i class="bi bi-tag-fill"></i></div>
      <div class="stat-value" style="font-size:18px"><?= licenseFormatMoney($monthlyPrice) ?></div>
      <div class="stat-label">Phí mỗi tháng</div>
      <div class="stat-bg"><i class="bi bi-tag-fill"></i></div>
    </div>
  </div>
</div>

<?php if ($status['write_blocked']): ?>
<div class="alert alert-danger">
  <i class="bi bi-lock-fill me-2"></i>
  Hệ thống đang bị chặn quyền ghi dữ liệu. Khách vẫn xem được dữ liệu và sao lưu, nhưng không thể tạo/sửa hóa đơn, sản phẩm, công nợ, nhập hàng.
</div>
<?php endif; ?>

<div class="card mb-4" id="featureProfileCard">
  <div class="card-header d-flex align-items-center justify-content-between gap-2"><span><i class="bi bi-ui-checks-grid me-2" style="color:var(--accent)"></i>Chế Độ Sử Dụng Của Khách Hàng</span><span class="badge" style="background:var(--accent);color:#fff"><?= htmlspecialchars($featureProfiles[$currentFeatureProfile]['label'] ?? 'Đầy đủ') ?></span></div>
  <div class="card-body">
    <div class="p-3 rounded-3 mb-3 feature-profile-note" style="background:var(--bg-main);border:1px solid var(--border)"><i class="bi bi-info-circle" style="color:var(--accent);margin-top:2px"></i><span>Chuyển chế độ chỉ thu gọn giao diện và quyền truy cập, không xóa dữ liệu. Superadmin luôn nhìn thấy toàn bộ chức năng để hỗ trợ khách hàng.</span></div>
    <form method="POST" action="index.php?page=license&action=features_save" id="featureProfileForm">
      <?= csrfField() ?>
      <div class="feature-profile-grid">
      <?php foreach($featureProfiles as $profileKey=>$profile): ?>
        <label class="feature-profile-option <?= $currentFeatureProfile===$profileKey?'is-selected':'' ?>">
          <input class="form-check-input" type="radio" name="feature_profile" value="<?= htmlspecialchars($profileKey) ?>" <?= $currentFeatureProfile===$profileKey?'checked':'' ?> onchange="selectFeatureProfile(this)">
          <span class="feature-profile-copy"><strong><?= htmlspecialchars($profile['label']) ?></strong><small><?= htmlspecialchars($profile['description']) ?></small></span>
          <?php if($currentFeatureProfile===$profileKey): ?><span class="badge bg-success">Đang dùng</span><?php elseif(!($featureReadiness[$profileKey]['success']??true)): ?><span class="badge bg-danger" title="<?= htmlspecialchars(implode(' ',$featureReadiness[$profileKey]['blockers']??[])) ?>">Cần xử lý</span><?php endif; ?>
        </label>
      <?php endforeach; ?>
      </div>
      <div class="feature-preview mt-3">
        <div class="feature-preview-head"><strong>Chức năng khách hàng nhìn thấy</strong><small>Các chức năng cốt lõi luôn hoạt động</small></div>
        <div class="feature-core"><span><i class="bi bi-check-circle-fill"></i>Sản phẩm & Nhóm hàng</span><span><i class="bi bi-check-circle-fill"></i>Nhập hàng</span><span><i class="bi bi-check-circle-fill"></i>Lập & xem hóa đơn</span><span><i class="bi bi-check-circle-fill"></i>Trả hàng từ hóa đơn</span><span><i class="bi bi-check-circle-fill"></i>Sao lưu & Hướng dẫn</span></div>
        <div class="feature-module-grid"><?php foreach($featureDefinitions as $key=>$definition): ?><div class="feature-module" data-feature="<?= htmlspecialchars($key) ?>"><i class="bi <?= htmlspecialchars($definition['icon']) ?>"></i><div><strong><?= htmlspecialchars($definition['label']) ?></strong><small><?= htmlspecialchars($definition['description']) ?></small></div><i class="bi feature-state-icon"></i></div><?php endforeach; ?></div>
      </div>
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
        <small class="text-muted"><i class="bi bi-shield-check me-1"></i>Hệ thống sẽ chặn hạ chế độ nếu còn công nợ hoặc phiếu kiểm kê chờ duyệt.</small>
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Áp Dụng Chế Độ</button>
      </div>
    </form>
    <?php if(!empty($featureSettings['history'])): $lastFeatureChange=end($featureSettings['history']); ?><div class="feature-history mt-3"><i class="bi bi-clock-history me-1"></i>Lần đổi gần nhất: <?= htmlspecialchars($featureProfiles[$lastFeatureChange['from']??'full']['label']??'') ?> → <?= htmlspecialchars($featureProfiles[$lastFeatureChange['to']??'full']['label']??'') ?>, <?= !empty($lastFeatureChange['changed_at'])?date('H:i d/m/Y',strtotime($lastFeatureChange['changed_at'])):'' ?> bởi <?= htmlspecialchars($lastFeatureChange['changed_by']??'') ?></div><?php endif; ?>
  </div>
</div>

<style>
.feature-profile-note{align-items:flex-start;display:flex;font-size:12.5px;gap:8px}.feature-profile-grid{display:grid;gap:10px;grid-template-columns:repeat(3,minmax(0,1fr))}.feature-profile-option{align-items:flex-start;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;display:flex;gap:10px;min-height:82px;padding:13px;transition:.15s}.feature-profile-option:hover,.feature-profile-option.is-selected{background:#fffbeb;border-color:#f59e0b}.feature-profile-copy{display:flex;flex:1;flex-direction:column;gap:4px}.feature-profile-copy strong{font-size:14px}.feature-profile-copy small{color:#6b7280;font-size:11.5px;line-height:1.45}.feature-preview{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px}.feature-preview-head{align-items:center;display:flex;justify-content:space-between}.feature-preview-head small{color:#6b7280}.feature-core{display:flex;flex-wrap:wrap;gap:7px 14px;margin-top:10px}.feature-core span{font-size:11.5px;font-weight:700}.feature-core i{color:#059669;margin-right:5px}.feature-module-grid{display:grid;gap:8px;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:12px}.feature-module{align-items:flex-start;background:#fff;border:1px solid #e5e7eb;border-radius:7px;display:grid;gap:9px;grid-template-columns:22px 1fr 18px;padding:10px}.feature-module>i:first-child{color:#6b7280}.feature-module strong,.feature-module small{display:block}.feature-module strong{font-size:12px}.feature-module small{color:#6b7280;font-size:10.5px;margin-top:2px}.feature-module.is-enabled{border-color:#a7f3d0}.feature-module.is-enabled>i:first-child,.feature-module.is-enabled .feature-state-icon{color:#059669}.feature-module.is-enabled .feature-state-icon:before{content:'\F26A'}.feature-module:not(.is-enabled){opacity:.55}.feature-module:not(.is-enabled) .feature-state-icon:before{content:'\F62A'}.feature-history{border-top:1px solid #e5e7eb;color:#6b7280;font-size:11.5px;padding-top:10px}@media(max-width:767px){.feature-profile-grid,.feature-module-grid{grid-template-columns:1fr}.feature-preview-head{align-items:flex-start;flex-direction:column;gap:2px}}
</style>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-building me-2"></i>Thông Tin Khách Hàng & Chính Sách</div>
      <div class="card-body">
        <form method="POST" action="index.php?page=license&action=settings_save">
          <?= csrfField() ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Tên cửa hàng / khách hàng</label>
              <input class="form-control" name="customer_name" value="<?= htmlspecialchars($customer['name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tên hệ thống hiển thị</label>
              <input class="form-control" name="system_name" value="<?= htmlspecialchars($customer['system_name'] ?? APP_NAME) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Người mua hệ thống</label>
              <input class="form-control" name="customer_owner" value="<?= htmlspecialchars($customer['owner'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Số điện thoại</label>
              <input class="form-control" name="customer_phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Mã số thuế</label>
              <input class="form-control" name="customer_tax_code" value="<?= htmlspecialchars($customer['tax_code'] ?? '') ?>" placeholder="10 chữ số hoặc 10 chữ số-3 chữ số">
            </div>
            <div class="col-md-8">
              <label class="form-label">Địa chỉ</label>
              <input class="form-control" name="customer_address" value="<?= htmlspecialchars($customer['address'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Ngày bắt đầu chu kỳ sử dụng</label>
              <input type="hidden" name="started_at" id="licenseStartedAtIso" value="<?= htmlspecialchars($customer['started_at'] ?? date('Y-m-d')) ?>">
              <input type="text" class="form-control" data-vn-date-target="licenseStartedAtIso" value="<?= htmlspecialchars($customer['started_at'] ?? date('Y-m-d')) ?>">

            </div>
            <input type="hidden" name="activated_at" value="<?= htmlspecialchars($customer['activated_at'] ?? $customer['started_at'] ?? date('Y-m-d')) ?>">
            <div class="col-md-4">
              <label class="form-label">Phí tháng</label>
              <input type="number" class="form-control" name="monthly_price" min="0" step="1" value="<?= htmlspecialchars((string)$monthlyPrice) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Cảnh báo trước hạn</label>
              <div class="input-group">
                <input type="number" class="form-control" name="warn_before_days" min="0" step="1" value="<?= (int)($policy['warn_before_days'] ?? 15) ?>">
                <span class="input-group-text">ngày</span>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Gia hạn sau quá hạn</label>
              <div class="input-group">
                <input type="number" class="form-control" name="grace_days" min="0" step="1" value="<?= (int)($policy['grace_days'] ?? 7) ?>">
                <span class="input-group-text">ngày</span>
              </div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="enabled" id="licenseEnabled" <?= ($license['enabled'] ?? true) ? 'checked' : '' ?>>
                <label class="form-check-label fw-700" for="licenseEnabled">Bật kiểm tra giấy phép</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="block_write_when_expired" id="blockWrite" <?= ($policy['block_write_when_expired'] ?? true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="blockWrite">Hết hạn thì chặn tạo/sửa dữ liệu</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="allow_backup_when_expired" id="allowBackup" <?= ($policy['allow_backup_when_expired'] ?? true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="allowBackup">Hết hạn vẫn cho sao lưu dữ liệu</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Ghi chú kỹ thuật</label>
              <textarea class="form-control" name="note" rows="2"><?= htmlspecialchars($customer['note'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu cấu hình</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-receipt-cutoff me-2"></i>Lịch Sử Thanh Toán Giấy Phép</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr>
              <th>Ngày</th><th>Gói</th><th class="text-end">Số tiền</th><th>Hình thức</th><th>Người ghi</th><th></th>
            </tr></thead>
            <tbody>
            <?php if (empty($payments)): ?>
              <tr><td colspan="6"><div class="empty-state py-4"><i class="bi bi-receipt"></i><p>Chưa có thanh toán giấy phép</p></div></td></tr>
            <?php else: foreach ($payments as $p): ?>
              <tr>
                <td>
                  <div class="fw-700"><?= date('d/m/Y', strtotime($p['paid_at'] ?? 'now')) ?></div>
                  <code style="font-size:10px"><?= htmlspecialchars($p['id'] ?? '') ?></code>
                </td>
                <td>
                  <?= (int)($p['package_months'] ?? 0) ?> tháng
                  <?php if ((int)($p['free_months'] ?? 0) > 0): ?>
                    <span class="badge bg-success ms-1">Giảm phí <?= (int)$p['free_months'] ?> tháng</span>
                  <?php endif; ?>
                  <div class="text-muted" style="font-size:12px">Trả <?= (int)($p['pay_months'] ?? 0) ?> tháng</div>
                </td>
                <td class="text-end money fw-800"><?= licenseFormatMoney((float)($p['amount'] ?? 0)) ?></td>
                <td><?= htmlspecialchars(match($p['method'] ?? '') {'cash'=>'Tiền mặt','transfer'=>'Chuyển khoản', default => ($p['method'] ?? '')}) ?></td>
                <td><?= htmlspecialchars($p['created_by'] ?? '') ?></td>
                <td class="text-end">
                  <form method="POST" action="index.php?page=license&action=payment_delete" onsubmit="return confirm('Xóa lần thanh toán này?')" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="payment_id" value="<?= htmlspecialchars($p['id'] ?? '') ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php if (!empty($p['note'])): ?>
              <tr><td colspan="6" class="text-muted" style="font-size:12px">Ghi chú: <?= htmlspecialchars($p['note']) ?></td></tr>
              <?php endif; ?>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-plus-circle me-2 text-success"></i>Thêm Thanh Toán</div>
      <div class="card-body">
        <form method="POST" action="index.php?page=license&action=payment_add">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Chọn gói</label>
            <select class="form-select" name="package_months" id="packageMonths" onchange="updateLicensePackagePreview()">
              <?php foreach ($pricing['packages'] ?? [] as $pkg): $calc = licensePaymentAmount($license, (int)$pkg['months']); ?>
              <option value="<?= (int)$pkg['months'] ?>" data-amount="<?= (float)$calc['amount'] ?>" data-pay="<?= (int)$calc['pay_months'] ?>" data-free="<?= (int)$calc['free_months'] ?>">
                Gói <?= (int)$pkg['months'] ?> tháng
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3 p-3 rounded-3" style="background:var(--bg-main);border:1px solid var(--border)">
            <div style="font-size:12px;color:#6b7280;font-weight:700">Số tiền gợi ý</div>
            <div class="money fw-800 text-success" id="packageAmountPreview" style="font-size:22px"></div>
            <div class="text-muted" id="packageDetailPreview" style="font-size:12px"></div>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Ngày ghi nhận thanh toán</label>
              <input type="hidden" name="paid_at" id="licensePaidAtIso" value="<?= date('Y-m-d') ?>">
              <input type="text" class="form-control" data-vn-date-target="licensePaidAtIso" value="<?= date('Y-m-d') ?>">

            </div>
            <div class="col-6">
              <label class="form-label">Hình thức</label>
              <select class="form-select" name="method">
                <option value="cash">Tiền mặt</option>
                <option value="transfer">Chuyển khoản</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Số tiền thực thu</label>
              <input type="number" class="form-control" name="amount" id="licenseAmount" min="1" step="1">
              <div class="form-text">Để trống thì dùng số tiền gợi ý theo gói</div>
            </div>
            <div class="col-12">
              <label class="form-label">Ghi chú</label>
              <input class="form-control" name="payment_note" placeholder="VD: Khách chuyển khoản gói 6 tháng">
            </div>
            <div class="col-12">
              <button class="btn btn-primary w-100"><i class="bi bi-check2-circle me-1"></i>Ghi nhận thanh toán</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-lock-fill me-2 text-danger"></i>Khóa / Mở Khóa Quyền Ghi</div>
      <div class="card-body">
        <form method="POST" action="index.php?page=license&action=lock_update">
          <?= csrfField() ?>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="locked" id="licenseLocked" <?= ($license['status']['locked'] ?? false) ? 'checked' : '' ?>>
            <label class="form-check-label fw-700" for="licenseLocked">Khóa quyền tạo/sửa dữ liệu</label>
          </div>
          <label class="form-label">Lý do khóa</label>
          <textarea class="form-control mb-3" name="lock_reason" rows="3" placeholder="VD: Quá hạn thanh toán nhiều ngày"><?= htmlspecialchars($license['status']['lock_reason'] ?? '') ?></textarea>
          <button class="btn btn-outline-danger w-100"><i class="bi bi-shield-lock me-1"></i>Cập nhật trạng thái khóa</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function licenseMoney(n) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(n) || 0);
}
function updateLicensePackagePreview() {
  const sel = document.getElementById('packageMonths');
  const opt = sel?.selectedOptions?.[0];
  if (!opt) return;
  const months = sel.value || '0';
  const amount = Number(opt.dataset.amount || 0);
  const pay = opt.dataset.pay || '0';
  const free = opt.dataset.free || '0';
  document.getElementById('packageAmountPreview').textContent = licenseMoney(amount);
  document.getElementById('packageDetailPreview').textContent = `Sử dụng ${months} tháng, tính tiền ${pay} tháng${Number(free) > 0 ? `, giảm phí ${free} tháng` : ''}`;
  const amountInput = document.getElementById('licenseAmount');
  if (amountInput && !amountInput.value) amountInput.placeholder = String(amount);
}
const FEATURE_PROFILES = <?= json_encode(array_map(fn($profile)=>$profile['features']??[],$featureProfiles),JSON_UNESCAPED_UNICODE) ?>;
function selectFeatureProfile(input){document.querySelectorAll('.feature-profile-option').forEach(label=>label.classList.toggle('is-selected',label.contains(input)&&input.checked));const enabled=FEATURE_PROFILES[input.value]||[];document.querySelectorAll('.feature-module').forEach(module=>module.classList.toggle('is-enabled',enabled.includes(module.dataset.feature)))}
selectFeatureProfile(document.querySelector('[name="feature_profile"]:checked'));
updateLicensePackagePreview();
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
