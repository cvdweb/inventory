<?php
$pageTitle = 'Quản Lý Tài Khoản';
$allUsers  = getAllUsers();
$branches  = getBranches();
$accessibleBranches = getAccessibleBranches();
$assignableBranches = $branches;
$roles     = ROLES;
$currentU  = currentUser();
$users     = $allUsers;
if (($currentU['role'] ?? '') === 'admin') {
    $users = array_values(array_filter($allUsers, fn($u) => ($u['role'] ?? '') !== 'superadmin'));
}
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-people-fill me-2 text-danger"></i>Quản Lý Tài Khoản Nhân Viên</h2>
    <p><?= count($users) ?> tài khoản trong hệ thống</p>
  </div>
  <button class="btn btn-primary" onclick="openAddModal()">
    <i class="bi bi-person-plus-fill me-1"></i>Thêm Tài Khoản
  </button>
</div>

<?php if (in_array($currentU['role'] ?? '', ['superadmin','admin'], true)): ?>
<div class="card mb-3 shadow-sm border-0">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <div class="fw-700" style="font-size:14px"><i class="bi bi-buildings-fill text-primary me-2"></i>Cấu Hình Chi Nhánh</div>
    <button class="btn btn-sm btn-primary py-1" onclick="openAddBranchModal()"><i class="bi bi-plus-lg me-1"></i>Thêm</button>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size:13.5px">
        <thead class="table-light">
          <tr>
            <th class="ps-3 border-0">Chi nhánh</th>
            <th class="border-0">Mã hiển thị</th>
            <th class="text-end pe-3 border-0">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($branches as $b): ?>
          <tr>
            <td class="ps-3 fw-600">
              <i class="bi <?= htmlspecialchars($b['icon'] ?? 'bi-shop') ?> me-2 text-<?= htmlspecialchars($b['color'] ?? 'primary') ?>"></i>
              <?= htmlspecialchars($b['name']) ?>
            </td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($b['short']) ?></span></td>
            <td class="text-end pe-3">
              <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" title="Sửa" 
                onclick="openEditBranchModal(<?= htmlspecialchars(json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)">
                <i class="bi bi-pencil" style="font-size:12px"></i>
              </button>
              <?php if (count($branches) > 1): ?>
              <form method="POST" action="index.php?page=users&action=branches_delete" class="d-inline"
                onsubmit="return confirm('CẢNH BÁO: Bạn có chắc chắn muốn xóa chi nhánh \'<?= htmlspecialchars($b['name']) ?>\' không?\nDữ liệu cũ sẽ được lưu trữ lại nhưng chi nhánh sẽ bị ẩn khỏi hệ thống!')">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($b['id']) ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Xóa">
                  <i class="bi bi-trash" style="font-size:12px"></i>
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Thêm/Sửa Chi Nhánh -->
<div class="modal fade" id="branchModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-bottom-0 pb-0">
        <h6 class="modal-title fw-700" id="branchModalTitle">Thêm Chi Nhánh</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php?page=users&action=branches_save">
        <?= csrfField() ?>
        <input type="hidden" name="id_edit" id="branchIdEdit" value="">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-600" style="font-size:13px">Tên chi nhánh *</label>
            <input type="text" name="name" id="branchName" class="form-control form-control-sm" required placeholder="Ví dụ: Kho Tổng">
          </div>
          <div class="mb-3">
            <label class="form-label fw-600" style="font-size:13px">Mã viết tắt (Short) *</label>
            <input type="text" name="short" id="branchShort" class="form-control form-control-sm" required placeholder="Ví dụ: KHO" pattern="[a-zA-Z0-9_]+">
          </div>
          <div class="row g-2">
            <div class="col-6 mb-2">
              <label class="form-label fw-600" style="font-size:13px">Biểu tượng</label>
              <select name="icon" id="branchIcon" class="form-select form-select-sm">
                <option value="bi-shop">Cửa hàng</option>
                <option value="bi-buildings">Tòa nhà</option>
                <option value="bi-house-fill">Ngôi nhà</option>
                <option value="bi-box-seam">Kho hàng</option>
                <option value="bi-truck">Giao vận</option>
              </select>
            </div>
            <div class="col-6 mb-2">
              <label class="form-label fw-600" style="font-size:13px">Màu sắc</label>
              <select name="color" id="branchColor" class="form-select form-select-sm">
                <option value="primary">Xanh dương</option>
                <option value="success">Xanh lá</option>
                <option value="danger">Đỏ</option>
                <option value="warning">Vàng</option>
                <option value="info">Xanh lam</option>
                <option value="secondary">Xám</option>
                <option value="dark">Đen</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-check2 me-1"></i>Lưu lại</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openAddBranchModal() {
  document.getElementById('branchIdEdit').value = '';
  document.getElementById('branchName').value = '';
  document.getElementById('branchShort').value = '';
  document.getElementById('branchIcon').value = 'bi-shop';
  document.getElementById('branchColor').value = 'primary';
  document.getElementById('branchModalTitle').innerHTML = 'Thêm Chi Nhánh';
  new bootstrap.Modal(document.getElementById('branchModal')).show();
}
function openEditBranchModal(b) {
  document.getElementById('branchIdEdit').value = b.id;
  document.getElementById('branchName').value = b.name;
  document.getElementById('branchShort').value = b.short;
  document.getElementById('branchIcon').value = b.icon || 'bi-shop';
  document.getElementById('branchColor').value = b.color || 'primary';
  document.getElementById('branchModalTitle').innerHTML = 'Sửa Chi Nhánh';
  new bootstrap.Modal(document.getElementById('branchModal')).show();
}
</script>
<?php endif; ?>

<!-- Danh sách tài khoản -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Tài khoản</th>
            <th>Họ tên</th>
            <th>Vai trò</th>
            <th>Chi nhánh</th>
            <th>Trạng thái</th>
            <th>Cập nhật</th>
            <th class="text-center">Thao tác</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u):
          $isSelf      = ($u['username'] === $currentU['username']);
          $isSuperAdmin = ($u['role'] === 'superadmin');
          $canManageTarget = canManageTargetUser($u);
          $isActive    = $u['active'] ?? true;
          $roleInfo    = $roles[$u['role']] ?? ['label' => $u['role'], 'color' => 'secondary', 'icon' => 'bi-person'];
          $branchName  = '— Tất cả —';
          if (!empty($u['branch'])) {
              if (is_array($u['branch'])) {
                  $branchList = array_map(fn($b) => $branches[$b]['name'] ?? $b, $u['branch']);
                  $branchName = implode(', ', $branchList);
              } else {
                  $branchName = $branches[$u['branch']]['name'] ?? $u['branch'];
              }
          }
        ?>
        <tr class="<?= !$isActive ? 'opacity-50' : '' ?>">
          <td>
            <div class="d-flex align-items-center gap-2">
              <div style="width:34px;height:34px;border-radius:8px;display:grid;place-items:center;font-size:16px;
                background:<?= match($u['role']){'superadmin'=>'rgba(239,68,68,.12)','admin'=>'rgba(245,158,11,.12)',default=>'rgba(59,130,246,.12)'} ?>;
                color:<?= match($u['role']){'superadmin'=>'#ef4444','admin'=>'#f59e0b',default=>'#3b82f6'} ?>">
                <i class="bi <?= $u['icon'] ?? 'bi-person' ?>"></i>
              </div>
              <div>
                <div class="fw-700" style="font-size:13.5px"><?= htmlspecialchars($u['username']) ?></div>
                <?php if ($isSelf): ?>
                <span style="font-size:10px;color:#f59e0b;font-weight:700">● Bạn</span>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td class="fw-600"><?= htmlspecialchars($u['name']) ?></td>
          <td>
            <span class="badge bg-<?= $roleInfo['color'] ?> bg-opacity-15 text-<?= $roleInfo['color'] ?>" style="font-size:11.5px;font-weight:700">
              <i class="bi <?= $roleInfo['icon'] ?> me-1"></i><?= $roleInfo['label'] ?>
            </span>
          </td>
          <td style="font-size:13px;color:#6b7280"><?= htmlspecialchars($branchName) ?></td>
          <td>
            <?php if ($isActive): ?>
              <span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle me-1"></i>Hoạt động</span>
            <?php else: ?>
              <span class="badge bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-circle me-1"></i>Đã khóa</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#9ca3af"><?= substr($u['updated_at'] ?? '', 0, 16) ?></td>
          <td class="text-center">
            <div class="d-flex gap-1 justify-content-center">
              <?php if ($canManageTarget): ?>
              <button class="btn btn-sm btn-outline-primary" title="Sửa thông tin"
                onclick='openEditModal(<?= json_encode([
                  "username"  => $u["username"],
                  "name"      => $u["name"],
                  "role"      => $u["role"],
                  "branch"    => $u["branch"] ?? "",
                  "active"    => $u["active"] ?? true,
                ], JSON_HEX_APOS) ?>)'>
                <i class="bi bi-pencil"></i>
              </button>

              <!-- Reset mật khẩu -->
              <button class="btn btn-sm btn-outline-warning" title="Reset mật khẩu"
                onclick="openResetModal('<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['name']) ?>')">
                <i class="bi bi-key"></i>
              </button>

              <!-- Bật/tắt -->
              <form method="POST" action="index.php?page=users&action=toggle" class="d-inline"
                onsubmit="return confirm('<?= $isActive ? 'Khóa tài khoản này?' : 'Kích hoạt tài khoản này?' ?>')">
                <?= csrfField() ?>
                <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                  title="<?= $isActive ? 'Khóa tài khoản' : 'Kích hoạt' ?>">
                  <i class="bi <?= $isActive ? 'bi-lock' : 'bi-lock-fill' ?>"></i>
                </button>
              </form>

              <!-- Xóa -->
              <form method="POST" action="index.php?page=users&action=delete" class="d-inline"
                onsubmit="return confirm('Xóa tài khoản \'<?= htmlspecialchars($u['name']) ?>\'? Hành động này không thể hoàn tác!')">
                <?= csrfField() ?>
                <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa tài khoản">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php else: ?>
              <span class="text-muted" title="Tài khoản được bảo vệ">—</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Ghi chú phân quyền -->
<div class="card mt-3">
  <div class="card-header" style="font-size:13px"><i class="bi bi-info-circle me-2"></i>Phân Quyền Vai Trò</div>
  <div class="card-body">
    <div class="row g-2">
      <?php foreach ($roles as $rKey => $rInfo): ?>
      <div class="col-md-4">
        <div class="p-3 rounded-3" style="background:var(--bg-main);border:1px solid var(--border)">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi <?= $rInfo['icon'] ?> text-<?= $rInfo['color'] ?>"></i>
            <span class="fw-700" style="font-size:13px"><?= $rInfo['label'] ?></span>
          </div>
          <div style="font-size:12px;color:#6b7280">
            <?= htmlspecialchars($rInfo['desc'] ?? '') ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ===== MODAL THÊM TÀI KHOẢN ===== -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Thêm Tài Khoản Mới</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php?page=users&action=save" onsubmit="return validatePwd('addPwd','addPwdConfirm') && validateRoleBranches(this,'addBranch')">
        <?= csrfField() ?>
        <input type="hidden" name="action_type" value="add">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Tên đăng nhập *</label>
              <input type="text" name="username" class="form-control" required
                pattern="[a-zA-Z0-9_]+" title="Chỉ dùng chữ, số, gạch dưới"
                placeholder="vd: nv_kho2">
              <div class="form-text">Chỉ chữ không dấu, số, gạch dưới</div>
            </div>
            <div class="col-6">
              <label class="form-label">Họ tên *</label>
              <input type="text" name="name" class="form-control" required placeholder="Nguyễn Văn A">
            </div>
            <div class="col-6">
              <label class="form-label">Mật khẩu *</label>
              <div class="input-group">
                <input type="password" name="password" id="addPwd" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>" placeholder="Tối thiểu <?= PASSWORD_MIN_LENGTH ?> ký tự">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('addPwd',this)"><i class="bi bi-eye"></i></button>
              </div>
            </div>
            <div class="col-6">
              <label class="form-label">Xác nhận mật khẩu *</label>
              <input type="password" name="password_confirm" id="addPwdConfirm" class="form-control" required placeholder="Nhập lại">
            </div>
            <div class="col-6">
              <label class="form-label">Vai trò *</label>
              <select name="role" class="form-select" onchange="onRoleChange(this,'addBranch')" required>
                <option value="">-- Chọn vai trò --</option>
                <?php foreach ($roles as $rKey => $rInfo): ?>
                  <?php if ($rKey === 'superadmin') continue; ?>
                  <?php if (($currentU['role'] ?? '') === 'admin' && $rKey !== 'employee') continue; ?>
                  <option value="<?= $rKey ?>"><?= $rInfo['label'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6" id="addBranchWrap">
              <label class="form-label">Chi nhánh</label>
              <div class="branch-check-grid" id="addBranch">
                <?php foreach ($assignableBranches as $bId => $b): ?>
                <label class="branch-check-card">
                  <input class="form-check-input branch-check" type="checkbox" name="branch[]" value="<?= htmlspecialchars($bId) ?>">
                  <span class="branch-check-icon"><i class="bi <?= htmlspecialchars($b['icon'] ?? 'bi-shop') ?>"></i></span>
                  <span class="branch-check-text">
                    <span><?= htmlspecialchars($b['name']) ?></span>
                    <small><?= htmlspecialchars($b['short'] ?? $bId) ?></small>
                  </span>
                </label>
                <?php endforeach; ?>
              </div>
              <div class="form-text" id="addBranchHelp">Nhân viên chỉ thấy và thao tác tại các chi nhánh được chọn.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2 me-1"></i>Tạo tài khoản
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.user-edit-modal {
  border: 0;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
}
.user-edit-header {
  align-items: flex-start;
  border-bottom: 1px solid var(--border);
  background: #fff;
  padding: 18px 22px;
}
.modal-eyebrow {
  color: #6b7280;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: .08em;
  margin-bottom: 3px;
  text-transform: uppercase;
}
.user-edit-grid {
  display: grid;
  grid-template-columns: 230px minmax(0, 1fr);
  gap: 18px;
}
.user-edit-summary {
  align-self: stretch;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 18px;
}
.user-edit-avatar {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  background: #111827;
  color: #fff;
  font-size: 24px;
  margin-bottom: 14px;
}
.user-edit-username {
  color: #111827;
  font-family: 'JetBrains Mono', monospace;
  font-size: 16px;
  font-weight: 800;
  overflow-wrap: anywhere;
}
.user-edit-caption {
  color: #6b7280;
  font-size: 12px;
  line-height: 1.45;
  margin-top: 4px;
}
.user-edit-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 16px;
}
.user-edit-chip {
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 800;
  padding: 6px 10px;
}
.user-edit-chip-role {
  background: rgba(245, 158, 11, .12);
  border-color: rgba(245, 158, 11, .25);
  color: var(--accent-dark);
}
.user-edit-chip-active {
  background: rgba(16, 185, 129, .12);
  border-color: rgba(16, 185, 129, .25);
  color: #047857;
}
.user-edit-chip-locked {
  background: rgba(239, 68, 68, .1);
  border-color: rgba(239, 68, 68, .22);
  color: #b91c1c;
}
.user-edit-fields {
  min-width: 0;
}
.user-edit-section {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
.user-edit-section + .user-edit-section {
  margin-top: 14px;
}
.user-edit-section-title {
  align-items: center;
  color: #111827;
  display: flex;
  font-size: 13px;
  font-weight: 800;
  gap: 8px;
  margin-bottom: 12px;
}
.branch-check-grid {
  display: grid;
  gap: 8px;
}
.branch-check-card {
  align-items: center;
  background: #fff;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  cursor: pointer;
  display: grid;
  gap: 10px;
  grid-template-columns: auto 34px minmax(0, 1fr);
  padding: 10px 12px;
  transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
}
.branch-check-card:hover {
  border-color: rgba(245, 158, 11, .55);
  background: #fffdf7;
}
.branch-check-card:has(.branch-check:checked) {
  background: rgba(245, 158, 11, .08);
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(245, 158, 11, .12);
}
.branch-check-card:has(.branch-check:disabled) {
  cursor: default;
  opacity: .62;
}
.branch-check-card .branch-check {
  margin: 0;
  width: 18px;
  height: 18px;
}
.branch-check-card .branch-check:checked {
  background-color: var(--accent);
  border-color: var(--accent);
}
.branch-check-icon {
  align-items: center;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  color: var(--text-mid);
  display: flex;
  height: 34px;
  justify-content: center;
  width: 34px;
}
.branch-check-card:has(.branch-check:checked) .branch-check-icon {
  background: #fff7ed;
  border-color: rgba(245, 158, 11, .3);
  color: var(--accent-dark);
}
.branch-check-text {
  display: grid;
  min-width: 0;
}
.branch-check-text span {
  color: #111827;
  font-size: 13px;
  font-weight: 800;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.branch-check-text small {
  color: #6b7280;
  font-size: 11px;
  font-weight: 700;
  margin-top: 2px;
}
.user-edit-help {
  align-items: flex-start;
  color: #6b7280;
  display: flex;
  font-size: 12px;
  gap: 7px;
  line-height: 1.45;
  margin-top: 9px;
}
.user-edit-footer {
  background: #f8fafc;
  border-top: 1px solid var(--border);
  justify-content: flex-end;
  padding: 14px 22px;
  width: 100%;
}
@media (max-width: 575.98px) {
  .user-edit-modal {
    border-radius: 0;
    min-height: 100%;
  }
  .user-edit-grid {
    grid-template-columns: 1fr;
  }
  .user-edit-summary {
    display: grid;
    grid-template-columns: 52px 1fr;
    column-gap: 12px;
    padding: 14px;
  }
  .user-edit-avatar {
    grid-row: span 3;
    margin: 0;
  }
  .user-edit-meta {
    margin-top: 8px;
  }
}
</style>

<!-- ===== MODAL SỬA TÀI KHOẢN ===== -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content user-edit-modal">
      <div class="modal-header user-edit-header">
        <div>
          <div class="modal-eyebrow">Quản trị tài khoản</div>
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Sửa Thông Tin Tài Khoản</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php?page=users&action=save" onsubmit="return validateRoleBranches(this,'editBranch')">
        <?= csrfField() ?>
        <input type="hidden" name="action_type" value="edit">
        <input type="hidden" name="id_edit" id="editIdEdit">
        <div class="modal-body">
          <div class="user-edit-grid">
            <aside class="user-edit-summary">
              <div class="user-edit-avatar"><i class="bi bi-person-badge"></i></div>
              <div class="user-edit-username" id="editUsernameDisplay"></div>
              <div class="user-edit-caption">Tên đăng nhập không thể thay đổi</div>
              <div class="user-edit-meta">
                <span id="editRoleBadge" class="badge rounded-pill user-edit-chip user-edit-chip-role">Vai trò</span>
                <span id="editStatusBadge" class="badge rounded-pill user-edit-chip user-edit-chip-active">Hoạt động</span>
              </div>
            </aside>

            <section class="user-edit-fields">
              <div class="user-edit-section">
                <div class="user-edit-section-title">
                  <i class="bi bi-person-lines-fill"></i>
                  <span>Thông tin cơ bản</span>
                </div>
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">Họ tên *</label>
                    <input type="text" name="name" id="editName" class="form-control form-control-lg" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Vai trò *</label>
                    <select name="role" id="editRole" class="form-select" onchange="onRoleChange(this,'editBranch')" required>
                      <?php foreach ($roles as $rKey => $rInfo): ?>
                        <?php if ($rKey === 'superadmin') continue; ?>
                        <?php if (($currentU['role'] ?? '') === 'admin' && $rKey !== 'employee') continue; ?>
                        <option value="<?= $rKey ?>"><?= $rInfo['label'] ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select name="active" id="editActive" class="form-select">
                      <option value="1">Hoạt động</option>
                      <option value="0">Khóa tài khoản</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="user-edit-section">
                <div class="user-edit-section-title">
                  <i class="bi bi-shop-window"></i>
                  <span>Phạm vi chi nhánh</span>
                </div>
                <div class="branch-check-grid" id="editBranch">
                  <?php foreach ($assignableBranches as $bId => $b): ?>
                  <label class="branch-check-card">
                    <input class="form-check-input branch-check" type="checkbox" name="branch[]" value="<?= htmlspecialchars($bId) ?>">
                    <span class="branch-check-icon"><i class="bi <?= htmlspecialchars($b['icon'] ?? 'bi-shop') ?>"></i></span>
                    <span class="branch-check-text">
                      <span><?= htmlspecialchars($b['name']) ?></span>
                      <small><?= htmlspecialchars($b['short'] ?? $bId) ?></small>
                    </span>
                  </label>
                  <?php endforeach; ?>
                </div>
                <div class="user-edit-help">
                  <i class="bi bi-info-circle"></i>
                  <span id="editBranchHelp">Admin, bán hàng, nhập hàng chỉ thấy chi nhánh được chọn.</span>
                </div>
              </div>
            </section>
          </div>
        </div>
        <div class="modal-footer user-edit-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            Hủy
          </button>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check2 me-1"></i>Lưu thay đổi
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== MODAL RESET MẬT KHẨU ===== -->
<div class="modal fade" id="resetPwdModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-key-fill me-2 text-warning"></i>Reset Mật Khẩu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php?page=users&action=reset_password">
        <?= csrfField() ?>
        <input type="hidden" name="username" id="resetUsername">
        <div class="modal-body">
          <div class="mb-3">
            <div style="font-size:12px;color:#9ca3af">Tài khoản</div>
            <div class="fw-700" id="resetNameDisplay"></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Mật khẩu mới *</label>
            <div class="input-group">
              <input type="password" name="new_password" id="resetPwd" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>" placeholder="Tối thiểu <?= PASSWORD_MIN_LENGTH ?> ký tự">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('resetPwd',this)"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <div class="mb-1">
            <label class="form-label">Xác nhận mật khẩu *</label>
            <input type="password" name="confirm_password" id="resetPwdConfirm" class="form-control" required placeholder="Nhập lại">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary" onclick="return validatePwd('resetPwd','resetPwdConfirm')">
            <i class="bi bi-key me-1"></i>Đổi mật khẩu
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const USER_ROLE_LABELS = <?= json_encode(array_map(fn($r) => $r['label'] ?? '', $roles), JSON_UNESCAPED_UNICODE) ?>;
const PASSWORD_MIN_LENGTH = <?= PASSWORD_MIN_LENGTH ?>;
function _modal(id) {
  return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

function openAddModal() {
  const form = document.querySelector('#addUserModal form');
  if (form) form.reset();
  const role = document.querySelector('#addUserModal select[name="role"]');
  if (role) onRoleChange(role, 'addBranch');
  _modal('addUserModal').show();
}

function openEditModal(u) {
  document.getElementById('editIdEdit').value               = u.username;
  document.getElementById('editUsernameDisplay').textContent = u.username;
  document.getElementById('editName').value                 = u.name;
  document.getElementById('editRole').value                 = u.role;
  setMultiSelectValue('editBranch', u.branch || []);
  document.getElementById('editActive').value               = u.active ? '1' : '0';
  onRoleChange(document.getElementById('editRole'), 'editBranch');
  updateEditSummary();
  _modal('editUserModal').show();
}

function openResetModal(username, name) {
  document.getElementById('resetUsername').value             = username;
  document.getElementById('resetNameDisplay').textContent    = name + ' (' + username + ')';
  document.getElementById('resetPwd').value                  = '';
  document.getElementById('resetPwdConfirm').value           = '';
  _modal('resetPwdModal').show();
}

// Ẩn/hiện chi nhánh dựa theo role
function onRoleChange(sel, branchId) {
  const br = document.getElementById(branchId);
  const role = sel.value;
  const isSuperAdmin = role === 'superadmin';
  const checks = [...br.querySelectorAll('.branch-check')];
  if (isSuperAdmin) {
    checks.forEach(input => {
      input.checked = false;
      input.disabled = true;
    });
  } else {
    checks.forEach(input => {
      input.disabled = false;
    });
  }
  const help = document.getElementById(branchId + 'Help');
  if (help) {
    if (role === 'superadmin') {
      help.textContent = 'Vai trò này có quyền cao nhất, không giới hạn chi nhánh.';
    } else if (role === 'admin') {
      help.textContent = 'Chọn các chi nhánh quản lý (Để trống nếu quản lý tất cả chi nhánh).';
    } else {
      help.textContent = 'Tài khoản này chỉ xem và thao tác trong các chi nhánh được chọn.';
    }
  }
  updateEditSummary();
}

function setMultiSelectValue(id, values) {
  const group = document.getElementById(id);
  const selected = Array.isArray(values) ? values : (values ? [values] : []);
  group.querySelectorAll('.branch-check').forEach(input => {
    input.checked = selected.includes(input.value);
  });
}

function updateEditSummary() {
  const role = document.getElementById('editRole')?.value || '';
  const active = document.getElementById('editActive')?.value === '1';
  const roleBadge = document.getElementById('editRoleBadge');
  const statusBadge = document.getElementById('editStatusBadge');
  if (roleBadge) {
    roleBadge.className = 'badge rounded-pill user-edit-chip user-edit-chip-role';
    roleBadge.textContent = USER_ROLE_LABELS[role] || 'Vai trò';
  }
  if (statusBadge) {
    statusBadge.className = 'badge rounded-pill user-edit-chip ' + (active ? 'user-edit-chip-active' : 'user-edit-chip-locked');
    statusBadge.textContent = active ? 'Hoạt động' : 'Đang khóa';
  }
}

document.getElementById('editActive')?.addEventListener('change', updateEditSummary);
document.getElementById('editBranch')?.addEventListener('change', updateEditSummary);

// Xác thực mật khẩu khớp nhau
function validateRoleBranches(form, branchId) {
  const role = form.querySelector('select[name="role"]')?.value || '';
  if (role !== 'employee') return true;
  const checked = document.querySelectorAll(`#${branchId} .branch-check:checked`).length;
  if (checked > 0) return true;
  showToast('Vui lòng chọn ít nhất một chi nhánh cho tài khoản này.', 'warning');
  return false;
}

function validatePwd(id1, id2) {
  const a = document.getElementById(id1).value;
  const b = document.getElementById(id2).value;
  if (a !== b) { showToast('Mật khẩu xác nhận không khớp.', 'warning'); return false; }
  if (a.length < PASSWORD_MIN_LENGTH) { showToast(`Mật khẩu phải ít nhất ${PASSWORD_MIN_LENGTH} ký tự.`, 'warning'); return false; }
  if (!/[A-Za-z]/.test(a) || !/\d/.test(a)) { showToast('Mật khẩu phải có ít nhất một chữ và một số.', 'warning'); return false; }
  return true;
}

// Toggle hiển thị mật khẩu
function togglePwd(inputId, btn) {
  const inp = document.getElementById(inputId);
  const icon = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
