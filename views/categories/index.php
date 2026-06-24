<?php
$pageTitle = 'Quản Lý Nhóm Hàng';
include BASE_PATH . '/views/layouts/header.php';

$branches   = getAccessibleBranches();
$selBranch  = $_GET['branch'] ?? array_key_first($branches);
if (!isset($branches[$selBranch])) {
    $selBranch = array_key_first($branches);
}
$branchInfo = getBranchInfo($selBranch);
$cats       = getCategories($selBranch);
$icons      = CAT_ICONS;
$allUnits   = normalizeCategoryUnits(UNITS);
foreach ($cats as $cat) {
    $allUnits = normalizeCategoryUnits(array_merge($allUnits, $cat['units'] ?? []));
}
$categoryProductCounts = [];
$totalProducts = 0;
$activeCategories = 0;
foreach ($cats as $cat) {
    $count = count(readJson(DATA_PATH . "/{$selBranch}/" . $cat['file']));
    $categoryProductCounts[$cat['key']] = $count;
    $totalProducts += $count;
    if ($cat['active'] ?? true) {
        $activeCategories++;
    }
}
?>

<div class="page-header category-page-header">
  <div class="category-heading">
    <h2><span class="category-heading-icon"><i class="bi bi-collection-fill"></i></span>Quản Lý Nhóm Hàng</h2>
    <p>Thêm, sửa, sắp xếp nhóm hàng và đơn vị tính theo chi nhánh</p>
    <div class="category-overview" aria-label="Tổng quan nhóm hàng">
      <span><strong><?= count($cats) ?></strong> nhóm</span>
      <span><strong><?= $activeCategories ?></strong> đang hiển thị</span>
      <span><strong><?= $totalProducts ?></strong> sản phẩm</span>
    </div>
  </div>
  <button type="button" class="btn btn-primary category-add-btn" onclick="_modal('addCatModal').show()">
    <i class="bi bi-plus-lg me-1"></i>Thêm Nhóm Hàng
  </button>
</div>

<ul class="nav category-branch-tabs mb-3" aria-label="Chọn chi nhánh">
  <?php foreach ($branches as $bId => $b): ?>
  <li class="nav-item">
    <a class="nav-link <?= $selBranch === $bId ? 'active' : '' ?>"
       href="index.php?page=categories&branch=<?= urlencode($bId) ?>">
      <i class="bi <?= htmlspecialchars($b['icon']) ?> me-1"></i><?= htmlspecialchars($b['name']) ?>
      <span class="badge bg-secondary ms-1"><?= count(getCategories($bId)) ?></span>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<div class="row g-2 mb-3 category-grid">
<?php foreach ($cats as $idx => $cat):
  $prodCount = $categoryProductCounts[$cat['key']] ?? 0;
  $isActive  = $cat['active'] ?? true;
  $units     = $cat['units'] ?? getCategoryUnits($selBranch, $cat['key']);
  $capabilities = $cat['capabilities'] ?? [];
?>
<div class="col-md-6 col-lg-4">
  <div class="card h-100 category-card <?= !$isActive ? 'is-inactive' : '' ?>">
    <div class="card-body">
      <div class="category-card-head">
        <div class="category-identity">
          <div class="category-icon">
            <i class="bi <?= htmlspecialchars($cat['icon'] ?? 'bi-box') ?>"></i>
          </div>
          <div>
            <div class="category-name"><?= htmlspecialchars($cat['name']) ?></div>
          </div>
        </div>
        <span class="category-status <?= $isActive ? 'is-active' : 'is-hidden' ?>">
          <i class="bi <?= $isActive ? 'bi-check-circle-fill' : 'bi-eye-slash-fill' ?>"></i>
          <?= $isActive ? 'Hiển thị' : 'Đang ẩn' ?>
        </span>
      </div>

      <div class="category-stats">
        <div class="category-stat category-stat-primary">
          <strong><?= $prodCount ?></strong>
          <span>Sản phẩm</span>
        </div>
        <div class="category-stat">
          <strong><?= count($units) ?></strong>
          <span>Đơn vị</span>
        </div>
        <div class="category-stat">
          <strong>#<?= $cat['sort_order'] ?? ($idx + 1) ?></strong>
          <span>Thứ tự</span>
        </div>
      </div>

      <div class="category-units">
        <div class="category-section-label">Đơn vị tính</div>
        <div class="category-unit-list">
          <?php foreach (array_slice($units, 0, 4) as $unit): ?>
          <span class="category-unit-badge"><?= htmlspecialchars($unit) ?></span>
          <?php endforeach; ?>
          <?php if (count($units) > 4): ?>
          <span class="category-unit-more">+<?= count($units) - 4 ?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php if (in_array('color_surcharge', $capabilities, true)): ?>
      <div class="category-capability-badge">
        <i class="bi bi-palette"></i><span>Màu đặc biệt và phụ phí</span>
      </div>
      <?php endif; ?>

      <div class="category-actions">
        <button type="button" class="btn btn-sm btn-outline-primary category-edit-btn"
          onclick='openEditCat(<?= json_encode([
            "original_key" => $cat["key"],
            "name"         => $cat["name"],
            "icon"         => $cat["icon"] ?? "bi-box",
            "sort_order"   => $cat["sort_order"] ?? ($idx + 1),
            "active"       => $cat["active"] ?? true,
            "units"        => $units,
            "capabilities" => $capabilities,
          ], JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)'>
          <i class="bi bi-pencil me-1"></i>Sửa
        </button>

        <form method="POST" action="index.php?page=categories&branch=<?= urlencode($selBranch) ?>&action=toggle&key=<?= urlencode($cat['key']) ?>" class="d-inline"
           onsubmit="return confirm('<?= $isActive ? 'Ẩn nhóm hàng này khỏi danh sách?' : 'Hiện lại nhóm hàng này?' ?>')">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
             title="<?= $isActive ? 'Ẩn nhóm' : 'Hiện nhóm' ?>">
            <i class="bi <?= $isActive ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
          </button>
        </form>

        <?php if ($prodCount === 0): ?>
        <form method="POST" action="index.php?page=categories&branch=<?= urlencode($selBranch) ?>&action=delete&key=<?= urlencode($cat['key']) ?>" class="d-inline"
           onsubmit="return confirm('Xóa nhóm <?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>?')">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa nhóm">
            <i class="bi bi-trash"></i>
          </button>
        </form>
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

<div class="category-note">
  <span class="category-note-icon"><i class="bi bi-info-circle-fill"></i></span>
  <div>
    <strong>Quy tắc nhóm hàng</strong>
    <p>Mỗi nhóm có danh sách đơn vị và tính năng riêng. Trường Màu đặc biệt chỉ xuất hiện trong form sản phẩm và file CSV khi nhóm đã bật tính năng màu và phụ phí.</p>
  </div>
</div>

<?php
function renderUnitPicker(string $prefix, array $allUnits, array $checkedUnits = []): void
{
    ?>
    <div class="unit-picker" id="<?= $prefix ?>UnitPicker">
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($allUnits as $unit): ?>
        <label class="unit-chip">
          <input type="checkbox" name="units[]" value="<?= htmlspecialchars($unit) ?>" <?= in_array($unit, $checkedUnits, true) ? 'checked' : '' ?>>
          <span><?= htmlspecialchars($unit) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="mt-2">
      <label class="form-label">Thêm đơn vị mới</label>
      <input type="text" name="new_units" class="form-control" placeholder="VD: kiện, cây, bao, thùng...">
      <div class="form-text">Có thể nhập nhiều đơn vị, cách nhau bằng dấu phẩy.</div>
    </div>
    <?php
}
?>

<div class="modal fade category-modal" id="addCatModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="category-modal-eyebrow">NHÓM HÀNG</div>
          <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i>Thêm Nhóm Hàng Mới</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php?page=categories&branch=<?= urlencode($selBranch) ?>&action=save">
        <?= csrfField() ?>
        <input type="hidden" name="action_type" value="add">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label">Tên nhóm hàng *</label>
              <input type="text" name="name" id="addCatName" class="form-control" required
                placeholder="VD: Sơn nước, Vật tư điện...">
            </div>
            <div class="col-md-4">
              <label class="form-label">Thứ tự hiển thị</label>
              <input type="number" name="sort_order" class="form-control" min="1" value="<?= count($cats) + 1 ?>">
            </div>
            <div class="col-12">
              <div class="category-form-section">
                <strong>Đơn vị tính *</strong>
                <span>Chọn các đơn vị được phép dùng cho sản phẩm thuộc nhóm này.</span>
              </div>
              <?php renderUnitPicker('add', $allUnits, ['cái']); ?>
            </div>
            <div class="col-12">
              <label class="category-capability-control" for="addColorSurcharge">
                <span class="category-capability-icon"><i class="bi bi-palette"></i></span>
                <span class="category-capability-copy">
                  <strong>Màu đặc biệt và phụ phí màu</strong>
                  <small>Bật cho nhóm sơn hoặc nhóm có sản phẩm bán theo màu với giá phụ thu riêng.</small>
                </span>
                <span class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" name="capabilities[]" value="color_surcharge" id="addColorSurcharge">
                </span>
              </label>
            </div>
            <div class="col-12">
              <div class="category-form-section">
                <strong>Biểu tượng</strong>
                <span>Giúp nhận biết nhóm nhanh hơn trong danh sách.</span>
              </div>
              <div class="d-flex flex-wrap gap-2" id="addIconPicker">
                <?php foreach ($icons as $iClass => $iLabel): ?>
                <label class="icon-opt" title="<?= htmlspecialchars($iLabel) ?>">
                  <input type="radio" name="icon" value="<?= htmlspecialchars($iClass) ?>"
                    <?= $iClass === 'bi-box' ? 'checked' : '' ?> onchange="highlightIcon(this)">
                  <div class="icon-btn" style="width:40px;height:40px;border-radius:8px;display:grid;place-items:center;font-size:18px;
                    border:2px solid #e5e7eb;transition:all .15s;<?= $iClass === 'bi-box' ? 'border-color:#f59e0b;background:#fffbeb;color:#d97706' : 'color:#6b7280' ?>">
                    <i class="bi <?= htmlspecialchars($iClass) ?>"></i>
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

<div class="modal fade category-modal" id="editCatModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="category-modal-eyebrow">NHÓM HÀNG</div>
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Sửa Nhóm Hàng</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php?page=categories&branch=<?= urlencode($selBranch) ?>&action=save">
        <?= csrfField() ?>
        <input type="hidden" name="action_type" value="edit">
        <input type="hidden" name="original_key" id="editCatOrigKey">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label">Tên nhóm hàng *</label>
              <input type="text" name="name" id="editCatName" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Thứ tự</label>
              <input type="number" name="sort_order" id="editCatOrder" class="form-control" min="1">
            </div>
            <div class="col-12">
              <div class="category-status-control">
                <div>
                  <strong>Hiển thị nhóm hàng</strong>
                  <span>Nhóm đang hiển thị có thể được sử dụng khi quản lý sản phẩm.</span>
                </div>
                <div class="form-check form-switch m-0">
                  <input type="hidden" name="active" value="0">
                  <input class="form-check-input" type="checkbox" name="active" value="1" id="editCatActive">
                </div>
              </div>
            </div>
            <div class="col-12">
              <label class="category-capability-control" for="editColorSurcharge">
                <span class="category-capability-icon"><i class="bi bi-palette"></i></span>
                <span class="category-capability-copy">
                  <strong>Màu đặc biệt và phụ phí màu</strong>
                  <small>Form sản phẩm và file CSV chỉ hiển thị trường màu khi tùy chọn này được bật.</small>
                </span>
                <span class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" name="capabilities[]" value="color_surcharge" id="editColorSurcharge">
                </span>
              </label>
            </div>
            <div class="col-12">
              <div class="category-form-section">
                <strong>Đơn vị tính *</strong>
                <span>Chọn các đơn vị được phép dùng cho sản phẩm thuộc nhóm này.</span>
              </div>
              <?php renderUnitPicker('edit', $allUnits); ?>
            </div>
            <div class="col-12">
              <div class="category-form-section">
                <strong>Biểu tượng</strong>
                <span>Giúp nhận biết nhóm nhanh hơn trong danh sách.</span>
              </div>
              <div class="d-flex flex-wrap gap-2" id="editIconPicker">
                <?php foreach ($icons as $iClass => $iLabel): ?>
                <label class="icon-opt" title="<?= htmlspecialchars($iLabel) ?>">
                  <input type="radio" name="icon" value="<?= htmlspecialchars($iClass) ?>" onchange="highlightIcon(this)">
                  <div class="icon-btn" style="width:40px;height:40px;border-radius:8px;display:grid;place-items:center;font-size:18px;
                    border:2px solid #e5e7eb;transition:all .15s;color:#6b7280;cursor:pointer">
                    <i class="bi <?= htmlspecialchars($iClass) ?>"></i>
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
.category-page-header {
  display:flex;
  align-items:flex-end;
  justify-content:space-between;
  gap:20px;
  margin-bottom:18px;
}
.category-heading h2 {
  display:flex;
  align-items:center;
  gap:10px;
}
.category-heading-icon {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:36px;
  height:36px;
  border-radius:8px;
  background:#fffbeb;
  color:#d97706;
  font-size:17px;
}
.category-heading p { margin:4px 0 0 46px; }
.category-overview {
  display:flex;
  align-items:center;
  gap:0;
  margin:10px 0 0 46px;
  color:#6b7280;
  font-size:12px;
}
.category-overview span {
  padding:0 10px;
  border-right:1px solid #e5e7eb;
}
.category-overview span:first-child { padding-left:0; }
.category-overview span:last-child { border-right:0; }
.category-overview strong { color:#111827; font-weight:800; }
.category-add-btn { min-height:42px; white-space:nowrap; }
.category-capability-badge {
  display:inline-flex;
  align-items:center;
  gap:6px;
  align-self:flex-start;
  margin-top:12px;
  padding:5px 8px;
  border:1px solid #fde68a;
  border-radius:6px;
  background:#fffbeb;
  color:#92400e;
  font-size:10.5px;
  font-weight:800;
}
.category-capability-control {
  display:grid;
  grid-template-columns:40px minmax(0,1fr) auto;
  align-items:center;
  gap:11px;
  padding:12px;
  border:1px solid #e5e7eb;
  border-radius:8px;
  background:#fafafa;
  cursor:pointer;
}
.category-capability-icon {
  display:grid;
  place-items:center;
  width:40px;
  height:40px;
  border-radius:8px;
  background:#fffbeb;
  color:#d97706;
  font-size:18px;
}
.category-capability-copy strong,
.category-capability-copy small { display:block; }
.category-capability-copy strong { color:#374151; font-size:12.5px; }
.category-capability-copy small { margin-top:2px; color:#9ca3af; font-size:11.5px; line-height:1.45; }

.category-branch-tabs {
  display:flex;
  flex-wrap:nowrap;
  gap:4px;
  padding:4px;
  overflow-x:auto;
  border:1px solid #e5e7eb;
  border-radius:8px;
  background:#f3f4f6;
  scrollbar-width:none;
}
.category-branch-tabs::-webkit-scrollbar { display:none; }
.category-branch-tabs .nav-item { flex:0 0 auto; }
.category-branch-tabs .nav-link {
  display:flex;
  align-items:center;
  min-height:38px;
  padding:7px 12px;
  border:0;
  border-radius:6px;
  color:#4b5563;
  font-size:12.5px;
  font-weight:700;
  white-space:nowrap;
}
.category-branch-tabs .nav-link:hover { color:#111827; background:#fff; }
.category-branch-tabs .nav-link.active {
  color:#92400e;
  background:#fff;
  box-shadow:0 1px 3px rgba(17,24,39,.1);
}
.category-branch-tabs .badge {
  background:#e5e7eb !important;
  color:#4b5563;
}
.category-branch-tabs .nav-link.active .badge {
  background:#fef3c7 !important;
  color:#92400e;
}

.category-grid > div { display:flex; }
.category-card {
  width:100%;
  overflow:hidden;
  border:1px solid #e5e7eb;
  border-top:3px solid #f59e0b;
  border-radius:8px;
  box-shadow:0 1px 2px rgba(17,24,39,.04);
  transition:border-color .15s, box-shadow .15s, transform .15s;
}
.category-card:hover {
  border-color:#fcd34d;
  box-shadow:0 5px 14px rgba(17,24,39,.08);
  transform:translateY(-1px);
}
.category-card.is-inactive {
  border-top-color:#d1d5db;
  background:#fafafa;
  opacity:.78;
}
.category-card .card-body {
  display:flex;
  flex-direction:column;
  padding:13px;
}
.category-card-head {
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:9px;
}
.category-identity {
  display:flex;
  align-items:center;
  gap:9px;
  min-width:0;
}
.category-icon {
  display:grid;
  place-items:center;
  flex:0 0 36px;
  width:36px;
  height:36px;
  border-radius:8px;
  background:#fffbeb;
  color:#d97706;
  font-size:17px;
}
.is-inactive .category-icon { background:#f3f4f6; color:#9ca3af; }
.category-name {
  overflow:hidden;
  color:#111827;
  font-size:14px;
  font-weight:800;
  line-height:1.35;
  text-overflow:ellipsis;
}
.category-status {
  display:inline-flex;
  align-items:center;
  gap:4px;
  flex:0 0 auto;
  padding:4px 7px;
  border-radius:6px;
  font-size:10.5px;
  font-weight:800;
}
.category-status.is-active { background:#ecfdf5; color:#047857; }
.category-status.is-hidden { background:#f3f4f6; color:#6b7280; }
.category-stats {
  display:grid;
  grid-template-columns:repeat(3, 1fr);
  gap:0;
  margin:11px 0;
  padding:8px 0;
  border-top:1px solid #f3f4f6;
  border-bottom:1px solid #f3f4f6;
}
.category-stat {
  min-width:0;
  padding:0 10px;
  border-right:1px solid #e5e7eb;
}
.category-stat:first-child { padding-left:0; }
.category-stat:last-child { padding-right:0; border-right:0; }
.category-stat strong,
.category-stat span { display:block; }
.category-stat strong {
  overflow:hidden;
  color:#374151;
  font-size:14px;
  font-weight:800;
  line-height:1.35;
  text-overflow:ellipsis;
  white-space:nowrap;
}
.category-stat-primary strong { color:#d97706; font-size:18px; line-height:1; }
.category-stat span { margin-top:4px; color:#9ca3af; font-size:10.5px; }
.category-units { flex:1; min-height:49px; }
.category-section-label {
  margin-bottom:7px;
  color:#6b7280;
  font-size:10.5px;
  font-weight:800;
  text-transform:uppercase;
}
.category-unit-list { display:flex; flex-wrap:wrap; gap:5px; }
.category-unit-badge {
  display:inline-flex;
  align-items:center;
  min-height:26px;
  padding:3px 8px;
  border:1px solid #fde68a;
  border-radius:6px;
  background:#fffbeb;
  color:#92400e;
  font-size:11.5px;
  font-weight:700;
}
.category-unit-more {
  display:inline-flex;
  align-items:center;
  min-height:26px;
  padding:3px 7px;
  border-radius:6px;
  background:#f3f4f6;
  color:#6b7280;
  font-size:11px;
  font-weight:800;
}
.category-actions {
  display:flex;
  gap:7px;
  margin:11px -13px -13px;
  padding:9px 13px;
  border-top:1px solid #f3f4f6;
  background:#fafafa;
}
.category-actions .category-edit-btn { flex:1; min-height:36px; }
.category-actions form { flex:0 0 auto; }
.category-actions form .btn,
.category-actions > .btn:not(.category-edit-btn) {
  width:38px;
  height:36px;
  padding:0;
}

.category-note {
  display:grid;
  grid-template-columns:34px minmax(0,1fr);
  gap:11px;
  align-items:start;
  padding:13px 15px;
  border:1px solid #fde68a;
  border-radius:8px;
  background:#fffbeb;
}
.category-note-icon {
  display:grid;
  place-items:center;
  width:32px;
  height:32px;
  border-radius:7px;
  background:#fef3c7;
  color:#d97706;
}
.category-note strong { display:block; color:#78350f; font-size:12.5px; }
.category-note p { margin:3px 0 0; color:#92400e; font-size:12px; line-height:1.55; }

.category-modal .modal-content { overflow:hidden; border:0; border-radius:8px; }
.category-modal .modal-header { align-items:flex-start; padding:16px 20px; }
.category-modal .modal-title { color:#111827; font-size:17px; font-weight:800; }
.category-modal .modal-title i { color:#d97706; }
.category-modal-eyebrow { margin-bottom:3px; color:#9ca3af; font-size:10px; font-weight:800; }
.category-modal .modal-body { padding:18px 20px 20px; }
.category-modal .modal-footer { padding:12px 20px; background:#f9fafb; }
.category-modal .form-label { margin-bottom:6px; color:#374151; font-size:12px; font-weight:700; }
.category-modal .form-control,
.category-modal .form-select { min-height:42px; border-radius:7px; }
.category-modal code { color:#b45309; }
.category-form-section { margin:5px 0 9px; }
.category-form-section strong,
.category-form-section span { display:block; }
.category-form-section strong { color:#374151; font-size:12px; }
.category-form-section span { margin-top:2px; color:#9ca3af; font-size:11.5px; }
.category-status-control {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:18px;
  padding:12px 14px;
  border:1px solid #e5e7eb;
  border-radius:8px;
  background:#f9fafb;
}
.category-status-control strong,
.category-status-control span { display:block; }
.category-status-control strong { color:#374151; font-size:12.5px; }
.category-status-control span { margin-top:2px; color:#6b7280; font-size:11.5px; }
.category-status-control .form-check-input { width:42px; height:22px; cursor:pointer; }
.category-status-control .form-check-input:checked { border-color:#f59e0b; background-color:#f59e0b; }

.icon-opt input:checked + .icon-btn {
  border-color:#f59e0b !important;
  background:#fffbeb !important;
  color:#d97706 !important;
}
.icon-opt { position:relative; }
.icon-opt input,
.unit-chip input {
  position:absolute;
  width:1px;
  height:1px;
  opacity:0;
}
.icon-btn { cursor:pointer; }
.icon-btn:hover { border-color:#f59e0b !important; color:#d97706 !important; }
.icon-opt input:focus-visible + .icon-btn { outline:3px solid rgba(245,158,11,.22); }
.unit-chip { position:relative; }
.unit-chip span {
  display:inline-flex;
  align-items:center;
  min-height:34px;
  padding:6px 11px;
  border:1px solid #d1d5db;
  border-radius:7px;
  color:#374151;
  background:#fff;
  font-size:13px;
  font-weight:700;
  cursor:pointer;
}
.unit-chip input:checked + span {
  border-color:#f59e0b;
  background:#fffbeb;
  color:#92400e;
}
.unit-chip input:focus-visible + span { outline:3px solid rgba(245,158,11,.2); }

@media (max-width: 768px) {
  .category-page-header { align-items:stretch; flex-direction:column; gap:14px; }
  .category-heading p,
  .category-overview { margin-left:0; }
  .category-overview { overflow-x:auto; white-space:nowrap; }
  .category-add-btn { width:100%; min-height:46px; }
  .category-branch-tabs { margin-right:-8px; margin-left:-8px; border-right:0; border-left:0; border-radius:0; }
  .category-card:hover { transform:none; }
  .category-stats { grid-template-columns:repeat(3, 1fr); }
  .category-actions .btn { min-height:42px; }
  .category-actions form .btn,
  .category-actions > .btn:not(.category-edit-btn) { width:44px; height:42px; }
  .category-note { grid-template-columns:30px minmax(0,1fr); padding:12px; }
  .category-note-icon { width:30px; height:30px; }
  .category-modal .modal-dialog { margin:0; min-height:100%; }
  .category-modal .modal-content { min-height:100dvh; border-radius:0; }
  .category-modal .modal-header { padding:14px 16px; }
  .category-modal .modal-body { padding:16px; }
  .category-modal .modal-footer { position:sticky; bottom:0; z-index:2; padding:10px 16px calc(10px + env(safe-area-inset-bottom)); }
  .category-modal .modal-footer .btn { min-height:44px; }
  .unit-chip span { min-height:40px; padding:8px 12px; }
}
</style>

<script>
function _modal(id) {
  return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

function highlightIcon(radio) {
  const picker = radio.closest('.d-flex');
  picker.querySelectorAll('.icon-btn').forEach(b => {
    b.style.borderColor = '#e5e7eb';
    b.style.background  = '';
    b.style.color       = '#6b7280';
  });
  const btn = radio.nextElementSibling;
  btn.style.borderColor = '#f59e0b';
  btn.style.background  = '#fffbeb';
  btn.style.color       = '#d97706';
}

function setUnitChecks(scopeId, units) {
  const scope = document.getElementById(scopeId);
  if (!scope) return;
  const selected = new Set(units || []);
  scope.querySelectorAll('input[name="units[]"]').forEach(input => {
    input.checked = selected.has(input.value);
  });
}

function openEditCat(c) {
  document.getElementById('editCatOrigKey').value = c.original_key;
  document.getElementById('editCatName').value = c.name;
  document.getElementById('editCatOrder').value = c.sort_order;
  document.getElementById('editCatActive').checked = Boolean(c.active);
  document.getElementById('editColorSurcharge').checked = (c.capabilities || []).includes('color_surcharge');
  setUnitChecks('editUnitPicker', c.units || []);

  const picker = document.getElementById('editIconPicker');
  picker.querySelectorAll('input[type=radio]').forEach(r => {
    r.checked = (r.value === c.icon);
    const btn = r.nextElementSibling;
    if (r.checked) {
      btn.style.borderColor = '#f59e0b';
      btn.style.background  = '#fffbeb';
      btn.style.color       = '#d97706';
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
