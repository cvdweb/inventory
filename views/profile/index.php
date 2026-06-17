<?php
$pageTitle = 'Tài Khoản Của Tôi';
$currentU  = currentUser();
$username  = $currentU['username'] ?? '';
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex align-items-center gap-3">
  <div style="width:56px;height:56px;border-radius:14px;display:grid;place-items:center;font-size:26px;
    background:rgba(245,158,11,.15);color:#f59e0b;flex-shrink:0">
    <i class="bi <?= htmlspecialchars($currentU['icon'] ?? 'bi-person') ?>"></i>
  </div>
  <div>
    <h2 style="margin:0"><?= htmlspecialchars($currentU['name'] ?? '') ?></h2>
    <p style="margin:2px 0 0">
      <?= match($currentU['role'] ?? '') {
        'superadmin' => '<span class="badge bg-danger">Super Admin</span>',
        'admin'      => '<span class="badge bg-warning text-dark">Quản trị viên</span>',
        'sales'      => '<span class="badge bg-primary">Bán hàng</span>',
        'warehouse'  => '<span class="badge bg-success">Nhập hàng</span>',
        default      => ''
      } ?>
      <span class="text-muted ms-2" style="font-size:13px">@<?= htmlspecialchars($username) ?></span>
    </p>
  </div>
</div>

<div class="row g-3">

  <!-- Thông tin cá nhân -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header fw-700">
        <i class="bi bi-person-fill me-2 text-primary"></i>Thông Tin Cá Nhân
      </div>
      <div class="card-body">

        <?php if (!empty($_SESSION['profile_success'])): ?>
        <div class="alert alert-success alert-dismissible py-2" style="font-size:13px">
          <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['profile_success']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['profile_success']); endif; ?>

        <?php if (!empty($_SESSION['profile_error'])): ?>
        <div class="alert alert-danger alert-dismissible py-2" style="font-size:13px">
          <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['profile_error']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['profile_error']); endif; ?>

        <form method="POST" action="index.php?page=profile&action=update_info">
        <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Tên đăng nhập</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($username) ?>"
              readonly style="background:#f9fafb;font-family:'JetBrains Mono',monospace">
            <div class="form-text">Không thể thay đổi</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Họ và tên *</label>
            <input type="text" name="name" class="form-control" required
              value="<?= htmlspecialchars($currentU['name'] ?? '') ?>"
              placeholder="Nhập họ tên đầy đủ">
          </div>
          <div class="mb-3">
            <label class="form-label">Chi nhánh phụ trách</label>
            <?php
            $branches    = getUserBranches();
            $branchNames = empty($branches)
              ? '— Tất cả chi nhánh —'
              : implode(', ', array_map(fn($b) => BRANCHES[$b]['name'] ?? $b, $branches));
            ?>
            <input type="text" class="form-control" value="<?= htmlspecialchars($branchNames) ?>"
              readonly style="background:#f9fafb">
            <div class="form-text">Được thiết lập bởi quản trị viên</div>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2 me-2"></i>Lưu thông tin
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Đổi mật khẩu -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header fw-700">
        <i class="bi bi-lock-fill me-2 text-warning"></i>Đổi Mật Khẩu
      </div>
      <div class="card-body">

        <?php if (!empty($_SESSION['pwd_success'])): ?>
        <div class="alert alert-success alert-dismissible py-2" style="font-size:13px">
          <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['pwd_success']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['pwd_success']); endif; ?>

        <?php if (!empty($_SESSION['pwd_error'])): ?>
        <div class="alert alert-danger alert-dismissible py-2" style="font-size:13px">
          <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['pwd_error']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['pwd_error']); endif; ?>

        <form method="POST" action="index.php?page=profile&action=change_password">
        <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Mật khẩu hiện tại *</label>
            <div class="input-group">
              <input type="password" name="current_password" id="curPwd"
                class="form-control" required placeholder="Nhập mật khẩu hiện tại">
              <button type="button" class="btn btn-outline-secondary"
                onclick="togglePwd('curPwd',this)"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Mật khẩu mới *</label>
            <div class="input-group">
              <input type="password" name="new_password" id="newPwd"
                class="form-control" required minlength="6"
                placeholder="Tối thiểu 6 ký tự"
                oninput="checkStrength(this.value)">
              <button type="button" class="btn btn-outline-secondary"
                onclick="togglePwd('newPwd',this)"><i class="bi bi-eye"></i></button>
            </div>
            <div id="strengthWrap" style="display:none;margin-top:6px">
              <div style="height:4px;border-radius:2px;background:#e5e7eb;overflow:hidden">
                <div id="strengthBar" style="height:100%;width:0;transition:all .3s;border-radius:2px"></div>
              </div>
              <div id="strengthLabel" style="font-size:11.5px;margin-top:3px"></div>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label">Xác nhận mật khẩu mới *</label>
            <div class="input-group">
              <input type="password" name="confirm_password" id="confirmPwd"
                class="form-control" required placeholder="Nhập lại mật khẩu mới">
              <button type="button" class="btn btn-outline-secondary"
                onclick="togglePwd('confirmPwd',this)"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <button type="submit" class="btn btn-warning" onclick="return validatePwd()">
            <i class="bi bi-key-fill me-2"></i>Đổi mật khẩu
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Thông tin phiên -->
  <div class="col-12">
    <div class="card">
      <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-4 align-items-center" style="font-size:13px;color:#6b7280">
          <span>
            <i class="bi bi-clock me-1"></i>Đăng nhập lúc:
            <strong style="color:#374151"><?= date('H:i d/m/Y', $_SESSION['login_time'] ?? time()) ?></strong>
          </span>
          <span>
            <i class="bi bi-shield-check me-1 text-success"></i>Phiên hết hạn sau:
            <strong style="color:#374151">
              <?= max(0, round((SESSION_TIMEOUT - (time() - ($_SESSION['login_time'] ?? time()))) / 60)) ?> phút
            </strong>
          </span>
          <a href="index.php?page=logout" class="btn btn-sm btn-outline-danger ms-auto">
            <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất
          </a>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
function togglePwd(id, btn) {
  const inp = document.getElementById(id);
  const ico = btn.querySelector('i');
  inp.type  = inp.type === 'password' ? 'text' : 'password';
  ico.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

function checkStrength(val) {
  const wrap = document.getElementById('strengthWrap');
  const bar  = document.getElementById('strengthBar');
  const lbl  = document.getElementById('strengthLabel');
  if (!val) { wrap.style.display = 'none'; return; }
  wrap.style.display = '';
  let s = 0;
  if (val.length >= 6)  s++;
  if (val.length >= 10) s++;
  if (/[A-Z]/.test(val)) s++;
  if (/[0-9]/.test(val)) s++;
  if (/[^A-Za-z0-9]/.test(val)) s++;
  const L = [
    {pct:20,color:'#ef4444',text:'Rất yếu'},
    {pct:40,color:'#f97316',text:'Yếu'},
    {pct:60,color:'#f59e0b',text:'Trung bình'},
    {pct:80,color:'#10b981',text:'Mạnh'},
    {pct:100,color:'#059669',text:'Rất mạnh'},
  ][Math.min(s,4)];
  bar.style.width = L.pct+'%'; bar.style.background = L.color;
  lbl.textContent = L.text; lbl.style.color = L.color;
}

function validatePwd() {
  const np = document.getElementById('newPwd').value;
  const cp = document.getElementById('confirmPwd').value;
  if (np.length < 6)  { alert('Mật khẩu mới phải ít nhất 6 ký tự!'); return false; }
  if (np !== cp)       { alert('Mật khẩu xác nhận không khớp!'); return false; }
  return true;
}
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
