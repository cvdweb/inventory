<?php
$pageTitle = 'Quản Lý Tài Khoản';
$users     = getAllUsers();
$branches  = BRANCHES;
$roles     = ROLES;
$currentU  = currentUser();
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
          $isActive    = $u['active'] ?? true;
          $roleInfo    = $roles[$u['role']] ?? ['label' => $u['role'], 'color' => 'secondary', 'icon' => 'bi-person'];
          // branch có thể là string (cũ) hoặc array (mới)
          $userBranches = is_array($u['branch'] ?? null) ? $u['branch'] : ($u['branch'] ? [$u['branch']] : []);
          if (empty($userBranches)) {
              $branchName = '— Tất cả —';
          } else {
              $bNames = array_map(fn($bid) => $branches[$bid]['short'] ?? $bid, $userBranches);
              $branchName = implode(', ', $bNames);
          }
        ?>
        <tr class="<?= !$isActive ? 'opacity-50' : '' ?>">
          <td>
            <div class="d-flex align-items-center gap-2">
              <div style="width:34px;height:34px;border-radius:8px;display:grid;place-items:center;font-size:16px;
                background:<?= match($u['role']){'superadmin'=>'rgba(239,68,68,.12)','admin'=>'rgba(245,158,11,.12)','sales'=>'rgba(59,130,246,.12)',default=>'rgba(16,185,129,.12)'} ?>;
                color:<?= match($u['role']){'superadmin'=>'#ef4444','admin'=>'#f59e0b','sales'=>'#3b82f6',default=>'#10b981'} ?>">
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
              <!-- Sửa thông tin -->
              <button class="btn btn-sm btn-outline-primary" title="Sửa thông tin"
                onclick='openEditModal(<?= json_encode([
                  "username"  => $u["username"],
                  "name"      => $u["name"],
                  "role"      => $u["role"],
                  "branch"    => is_array($u["branch"] ?? null)
                                   ? $u["branch"]
                                   : ($u["branch"] ? [$u["branch"]] : []),
                  "active"    => $u["active"] ?? true,
                ], JSON_HEX_APOS) ?>)'>
                <i class="bi bi-pencil"></i>
              </button>

              <!-- Reset mật khẩu -->
              <button class="btn btn-sm btn-outline-warning" title="Reset mật khẩu"
                onclick="openResetModal('<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['name']) ?>')">
                <i class="bi bi-key"></i>
              </button>

              <?php if (!$isSelf && !$isSuperAdmin): ?>
              <!-- Bật/tắt -->
              <a href="index.php?page=users&action=toggle&username=<?= urlencode($u['username']) ?>"
                class="btn btn-sm <?= $isActive ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                title="<?= $isActive ? 'Khóa tài khoản' : 'Kích hoạt' ?>"
                onclick="return confirm('<?= $isActive ? 'Khóa tài khoản này?' : 'Kích hoạt tài khoản này?' ?>')">
                <i class="bi <?= $isActive ? 'bi-lock' : 'bi-lock-fill' ?>"></i>
              </a>

              <!-- Xóa -->
              <a href="index.php?page=users&action=delete&username=<?= urlencode($u['username']) ?>"
                class="btn btn-sm btn-outline-danger" title="Xóa tài khoản"
                onclick="return confirm('Xóa tài khoản \'<?= htmlspecialchars($u['name']) ?>\'? Hành động này không thể hoàn tác!')">
                <i class="bi bi-trash"></i>
              </a>
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
      <div class="col-md-3">
        <div class="p-3 rounded-3" style="background:var(--bg-main);border:1px solid var(--border)">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi <?= $rInfo['icon'] ?> text-<?= $rInfo['color'] ?>"></i>
            <span class="fw-700" style="font-size:13px"><?= $rInfo['label'] ?></span>
          </div>
          <div style="font-size:12px;color:#6b7280">
            <?= match($rKey) {
              'superadmin' => 'Toàn quyền hệ thống, quản lý tài khoản, xem tất cả chi nhánh',
              'admin'      => 'Quản lý sản phẩm, xem báo cáo tất cả chi nhánh',
              'sales'      => 'Lập hóa đơn bán hàng tại chi nhánh được gán',
              'warehouse'  => 'Nhập hàng cho tất cả chi nhánh',
              default      => ''
            } ?>
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
      <form method="POST" action="index.php?page=users&action=save">
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
                <input type="password" name="password" id="addPwd" class="form-control" required minlength="6" placeholder="Tối thiểu 6 ký tự">
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
                  <?php if ($rKey === 'superadmin') continue; // Không cho tạo superadmin mới ?>
                  <option value="<?= $rKey ?>"><?= $rInfo['label'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6" id="addBranchWrap">
              <label class="form-label">Chi nhánh <span id="addBranchHint" style="font-size:11px;color:#9ca3af">(Sales: bắt buộc chọn)</span></label>
              <div style="border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;background:#f9fafb" id="addBranchBox">
                <div style="font-size:12px;color:#9ca3af;margin-bottom:6px">Chọn chi nhánh được phép truy cập:</div>
                <?php foreach ($branches as $bId => $b): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="branch[]"
                    value="<?= $bId ?>" id="add_br_<?= $bId ?>">
                  <label class="form-check-label" for="add_br_<?= $bId ?>" style="font-size:13.5px">
                    <i class="bi <?= $b['icon'] ?> me-1 text-<?= $b['color'] ?>"></i>
                    <?= htmlspecialchars($b['name']) ?>
                  </label>
                </div>
                <?php endforeach; ?>
                <div class="form-text mt-1">Admin/Warehouse: để trống = toàn bộ chi nhánh</div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary" onclick="return validatePwd('addPwd','addPwdConfirm')">
            <i class="bi bi-check2 me-1"></i>Tạo tài khoản
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== MODAL SỬA TÀI KHOẢN ===== -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Sửa Thông Tin Tài Khoản</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php?page=users&action=save">
        <input type="hidden" name="action_type" value="edit">
        <input type="hidden" name="id_edit" id="editIdEdit">
        <div class="modal-body">
          <div class="mb-3 p-2 rounded-3" style="background:#f9fafb;border:1px solid #e5e7eb">
            <div style="font-size:12px;color:#9ca3af">Tên đăng nhập (không thể thay đổi)</div>
            <div class="fw-700" id="editUsernameDisplay" style="font-family:'JetBrains Mono',monospace"></div>
          </div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Họ tên *</label>
              <input type="text" name="name" id="editName" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label">Vai trò *</label>
              <select name="role" id="editRole" class="form-select" onchange="onRoleChange(this,'editBranch')" required>
                <?php foreach ($roles as $rKey => $rInfo): ?>
                  <?php if ($rKey === 'superadmin') continue; ?>
                  <option value="<?= $rKey ?>"><?= $rInfo['label'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Chi nhánh</label>
              <div style="border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;background:#f9fafb" id="editBranchBox">
                <?php foreach ($branches as $bId => $b): ?>
                <div class="form-check">
                  <input class="form-check-input edit-branch-chk" type="checkbox"
                    name="branch[]" value="<?= $bId ?>" id="edit_br_<?= $bId ?>">
                  <label class="form-check-label" for="edit_br_<?= $bId ?>" style="font-size:13.5px">
                    <i class="bi <?= $b['icon'] ?> me-1 text-<?= $b['color'] ?>"></i>
                    <?= htmlspecialchars($b['name']) ?>
                  </label>
                </div>
                <?php endforeach; ?>
                <div class="form-text">Admin/Warehouse: để trống = toàn bộ</div>
              </div>
            </div>
            <div class="col-6">
              <label class="form-label">Trạng thái</label>
              <select name="active" id="editActive" class="form-select">
                <option value="1">Hoạt động</option>
                <option value="0">Khóa tài khoản</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Lưu thay đổi</button>
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
        <input type="hidden" name="username" id="resetUsername">
        <div class="modal-body">
          <div class="mb-3">
            <div style="font-size:12px;color:#9ca3af">Tài khoản</div>
            <div class="fw-700" id="resetNameDisplay"></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Mật khẩu mới *</label>
            <div class="input-group">
              <input type="password" name="new_password" id="resetPwd" class="form-control" required minlength="6" placeholder="Tối thiểu 6 ký tự">
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
          <button type="submit" class="btn btn-warning" onclick="return validatePwd('resetPwd','resetPwdConfirm')">
            <i class="bi bi-key me-1"></i>Đổi mật khẩu
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function _modal(id) {
  return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

function openAddModal() {
  _modal('addUserModal').show();
}

function openEditModal(u) {
  document.getElementById('editIdEdit').value               = u.username;
  document.getElementById('editUsernameDisplay').textContent = u.username;
  document.getElementById('editName').value                 = u.name;
  document.getElementById('editRole').value                 = u.role;
  document.getElementById('editActive').value               = u.active ? '1' : '0';

  // Chuẩn hóa branch thành array (có thể là string cũ hoặc array mới)
  let branches = [];
  if (u.branch) {
    branches = Array.isArray(u.branch) ? u.branch : [u.branch];
  }

  // Tick đúng checkbox chi nhánh
  document.querySelectorAll('.edit-branch-chk').forEach(chk => {
    chk.checked = branches.includes(chk.value);
  });

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
  const needBranch = sel.value === 'sales';
  if (!needBranch) br.value = '';
}

// Xác thực mật khẩu khớp nhau
function validatePwd(id1, id2) {
  const a = document.getElementById(id1).value;
  const b = document.getElementById(id2).value;
  if (a !== b) { alert('Mật khẩu xác nhận không khớp!'); return false; }
  if (a.length < 6) { alert('Mật khẩu phải ít nhất 6 ký tự!'); return false; }
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
