<?php
$pageTitle = 'Quản Lý Nhóm Hàng';
include BASE_PATH . '/views/layouts/header.php';
$branches  = getAccessibleBranches();
$selBranch = $_GET['branch'] ?? array_key_first($branches);
$branchInfo= BRANCHES[$selBranch];
$cats      = getCategories($selBranch);
$icons     = CAT_ICONS;
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-collection-fill me-2 text-purple" style="color:#8b5cf6"></i>Quản Lý Nhóm Hàng</h2>
    <p>Thêm, sửa, sắp xếp nhóm hàng theo chi nhánh</p>
  </div>
  <button class="btn btn-primary" onclick="_modal('addCatModal').show()">
    <i class="bi bi-plus-lg me-1"></i>Thêm Nhóm Hàng
  </button>
</div>

<!-- Tab chi nhánh -->
<ul class="nav nav-tabs mb-3">
  <?php foreach ($branches as $bId => $b): ?>
  <li class="nav-item">
    <a class="nav-link <?= $selBranch === $bId ? 'active fw-700' : '' ?>"
       href="index.php?page=categories&branch=<?= $bId ?>">
      <i class="bi <?= $b['icon'] ?> me-1"></i><?= htmlspecialchars($b['name']) ?>
      <span class="badge bg-secondary ms-1"><?= count(getCategories($bId)) ?></span>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- Danh sách nhóm -->
<div class="row g-3 mb-3">
<?php foreach ($cats as $idx => $cat):
  $prodFile = DATA_PATH . "/{$selBranch}/" . $cat['file'];
  $prodCount= count(readJson($prodFile));
  $isActive = $cat['active'] ?? true;
?>
<div class="col-md-6 col-lg-4">
  <div class="card h-100 <?= !$isActive ? 'opacity-60' : '' ?>" style="border-left:4px solid <?= $isActive ? '#8b5cf6' : '#e5e7eb' ?>">
    <div class="card-body">
      <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-3">
          <div style="width:44px;height:44px;border-radius:10px;display:grid;place-items:center;font-size:20px;
            background:<?= $isActive ? 'rgba(139,92,246,.12)' : '#f3f4f6' ?>;
            color:<?= $isActive ? '#8b5cf6' : '#9ca3af' ?>">
            <i class="bi <?= htmlspecialchars($cat['icon'] ?? 'bi-box') ?>"></i>
          </div>
          <div>
            <div class="fw-800" style="font-size:15px"><?= htmlspecialchars($cat['name']) ?></div>
            <div style="font-size:11px;color:#9ca3af;font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($cat['key']) ?></div>
          </div>
        </div>
        <?php if (!$isActive): ?>
        <span class="badge bg-danger bg-opacity-10 text-danger" style="font-size:10px">Ẩn</span>
        <?php endif; ?>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <div class="fw-800" style="font-size:22px;color:#111"><?= $prodCount ?></div>
          <div style="font-size:11px;color:#9ca3af">Sản phẩm</div>
        </div>
        <div class="text-end">
          <div style="font-size:11px;color:#9ca3af">Thứ tự</div>
          <div class="fw-700" style="font-size:16px;color:#6b7280">#<?= $cat['sort_order'] ?? ($idx+1) ?></div>
        </div>
        <div class="text-end">
          <div style="font-size:11px;color:#9ca3af">File dữ liệu</div>
          <div style="font-size:11px;font-family:'JetBrains Mono',monospace;color:#6b7280"><?= htmlspecialchars($cat['file']) ?></div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary flex-fill"
          onclick='openEditCat(<?= json_encode([
            "original_key" => $cat["key"],
            "name"         => $cat["name"],
            "icon"         => $cat["icon"] ?? "bi-box",
            "sort_order"   => $cat["sort_order"] ?? ($idx+1),
            "active"       => $cat["active"] ?? true,
          ], JSON_HEX_APOS) ?>)'>
          <i class="bi bi-pencil me-1"></i>Sửa
        </button>

        <a href="index.php?page=categories&branch=<?= $selBranch ?>&action=toggle&key=<?= urlencode($cat['key']) ?>"
           class="btn btn-sm <?= $isActive ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
           title="<?= $isActive ? 'Ẩn nhóm' : 'Hiện nhóm' ?>"
           onclick="return confirm('<?= $isActive ? 'Ẩn nhóm hàng này khỏi danh sách?' : 'Hiện lại nhóm hàng này?' ?>')">
          <i class="bi <?= $isActive ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
        </a>

        <?php if ($prodCount === 0): ?>
        <a href="index.php?page=categories&branch=<?= $selBranch ?>&action=delete&key=<?= urlencode($cat['key']) ?>"
           class="btn btn-sm btn-outline-danger"
           title="Xóa nhóm"
           onclick="return confirm('Xóa nhóm \'<?= htmlspecialchars($cat['name']) ?>\'?')">
          <i class="bi bi-trash"></i>
        </a>
        <?php else: ?>
        <button class="btn btn-sm btn-outline-danger" disabled title="Còn <?= $prodCount ?> sản phẩm, không thể xóa">
          <i class="bi bi-trash"></i>
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if (empty($cats)): ?>
<div class="col-12">
  <div class="empty-state">
    <i class="bi bi-collection"></i>
    <p>Chưa có nhóm hàng nào.<br>Nhấn <b>Thêm Nhóm Hàng</b> để bắt đầu.</p>
  </div>
</div>
<?php endif; ?>
</div>

<!-- Ghi chú -->
<div class="card" style="border-left:4px solid #f59e0b">
  <div class="card-body py-2" style="font-size:13px;color:#6b7280">
    <i class="bi bi-info-circle-fill text-warning me-2"></i>
    <b>Lưu ý:</b> Chỉ xóa được nhóm hàng khi không còn sản phẩm nào. Nút <i class="bi bi-trash"></i> chỉ hiện khi nhóm rỗng. 
    Ẩn nhóm sẽ không hiện trong form nhập hàng và hóa đơn nhưng dữ liệu vẫn được giữ nguyên.
  </div>
</div>

<!-- ===== MODAL THÊM NHÓM ===== -->
<div class="modal fade" id="addCatModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2" style="color:#8b5cf6"></i>Thêm Nhóm Hàng Mới</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php?page=categories&branch=<?= $selBranch ?>&action=save">
        <input type="hidden" name="action_type" value="add">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-8">
              <label class="form-label">Tên nhóm hàng *</label>
              <input type="text" name="name" id="addCatName" class="form-control" required
                placeholder="VD: Sơn Nước, Vật Tư Điện..."
                oninput="previewKey(this.value,'addCatKeyPreview')">
              <div class="form-text">
                Key tự động:
                <code id="addCatKeyPreview" style="color:#8b5cf6">—</code>
              </div>
            </div>
            <div class="col-4">
              <label class="form-label">Thứ tự hiển thị</label>
              <input type="number" name="sort_order" class="form-control" min="1" value="<?= count($cats)+1 ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Icon</label>
              <div class="d-flex flex-wrap gap-2" id="addIconPicker">
                <?php foreach ($icons as $iClass => $iLabel): ?>
                <label class="icon-opt" title="<?= $iLabel ?>" style="cursor:pointer">
                  <input type="radio" name="icon" value="<?= $iClass ?>" style="display:none"
                    <?= $iClass === 'bi-box' ? 'checked' : '' ?>
                    onchange="highlightIcon(this)">
                  <div class="icon-btn" style="width:40px;height:40px;border-radius:8px;display:grid;place-items:center;font-size:18px;
                    border:2px solid #e5e7eb;transition:all .15s;<?= $iClass === 'bi-box' ? 'border-color:#8b5cf6;background:rgba(139,92,246,.1);color:#8b5cf6' : 'color:#6b7280' ?>">
                    <i class="bi <?= $iClass ?>"></i>
                  </div>
                </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Thêm nhóm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== MODAL SỬA NHÓM ===== -->
<div class="modal fade" id="editCatModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Sửa Nhóm Hàng</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php?page=categories&branch=<?= $selBranch ?>&action=save">
        <input type="hidden" name="action_type" value="edit">
        <input type="hidden" name="original_key" id="editCatOrigKey">
        <div class="modal-body">
          <div class="mb-2 p-2 rounded-3" style="background:#f9fafb;border:1px solid #e5e7eb;font-size:12px;color:#9ca3af">
            Key: <code id="editCatKeyDisplay" style="color:#8b5cf6"></code>
            &nbsp;·&nbsp; File: <code id="editCatFileDisplay"></code>
          </div>
          <div class="row g-3">
            <div class="col-8">
              <label class="form-label">Tên nhóm hàng *</label>
              <input type="text" name="name" id="editCatName" class="form-control" required>
            </div>
            <div class="col-4">
              <label class="form-label">Thứ tự</label>
              <input type="number" name="sort_order" id="editCatOrder" class="form-control" min="1">
            </div>
            <div class="col-6">
              <label class="form-label">Trạng thái</label>
              <select name="active" id="editCatActive" class="form-select">
                <option value="1">Hiển thị</option>
                <option value="0">Ẩn</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Icon</label>
              <div class="d-flex flex-wrap gap-2" id="editIconPicker">
                <?php foreach ($icons as $iClass => $iLabel): ?>
                <label class="icon-opt" title="<?= $iLabel ?>">
                  <input type="radio" name="icon" value="<?= $iClass ?>" style="display:none"
                    onchange="highlightIcon(this)">
                  <div class="icon-btn" style="width:40px;height:40px;border-radius:8px;display:grid;place-items:center;font-size:18px;
                    border:2px solid #e5e7eb;transition:all .15s;color:#6b7280;cursor:pointer">
                    <i class="bi <?= $iClass ?>"></i>
                  </div>
                </label>
                <?php endforeach; ?>
              </div>
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

<style>
.icon-opt input:checked + .icon-btn {
  border-color: #8b5cf6 !important;
  background: rgba(139,92,246,.1) !important;
  color: #8b5cf6 !important;
}
.icon-btn:hover { border-color:#8b5cf6 !important; color:#8b5cf6 !important; }
.opacity-60 { opacity:.6; }
</style>

<script>
function _modal(id) {
  return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

// Preview key khi gõ tên nhóm
function previewKey(val, previewId) {
  const slug = val.toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
    .replace(/đ/g,'d').replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
  document.getElementById(previewId).textContent = slug || '—';
}

// Highlight icon được chọn
function highlightIcon(radio) {
  const picker = radio.closest('.d-flex');
  picker.querySelectorAll('.icon-btn').forEach(b => {
    b.style.borderColor = '#e5e7eb';
    b.style.background  = '';
    b.style.color       = '#6b7280';
  });
  const btn = radio.nextElementSibling;
  btn.style.borderColor = '#8b5cf6';
  btn.style.background  = 'rgba(139,92,246,.1)';
  btn.style.color       = '#8b5cf6';
}

// Mở modal sửa và điền dữ liệu
function openEditCat(c) {
  document.getElementById('editCatOrigKey').value         = c.original_key;
  document.getElementById('editCatKeyDisplay').textContent= c.original_key;
  document.getElementById('editCatFileDisplay').textContent = 'products_' + c.original_key + '.json';
  document.getElementById('editCatName').value            = c.name;
  document.getElementById('editCatOrder').value           = c.sort_order;
  document.getElementById('editCatActive').value          = c.active ? '1' : '0';

  // Chọn icon tương ứng
  const picker = document.getElementById('editIconPicker');
  picker.querySelectorAll('input[type=radio]').forEach(r => {
    r.checked = (r.value === c.icon);
    const btn = r.nextElementSibling;
    if (r.checked) {
      btn.style.borderColor = '#8b5cf6';
      btn.style.background  = 'rgba(139,92,246,.1)';
      btn.style.color       = '#8b5cf6';
    } else {
      btn.style.borderColor = '#e5e7eb';
      btn.style.background  = '';
      btn.style.color       = '#6b7280';
    }
  });

  _modal('editCatModal').show();
}
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
