<?php
$reqBranch = $_GET['branch'] ?? array_key_first(BRANCHES);
if (!canAccessBranch($reqBranch)) {
    $_SESSION['flash'] = ['type'=>'danger','message'=>'Không có quyền truy cập chi nhánh này'];
    header('Location: index.php'); exit;
}

$branchInfo    = BRANCHES[$reqBranch];
$category      = $_GET['cat'] ?? '';
$search        = $_GET['q'] ?? '';
$categoriesRaw = getCategories($reqBranch, true);
$categories    = [];
foreach ($categoriesRaw as $c2) { $categories[$c2['key']] = $c2; }
$products      = productList($reqBranch, $category, $search);
$pageTitle     = 'Sản Phẩm — ' . $branchInfo['name'];
$canManage     = in_array(currentUser()['role'], ['superadmin', 'admin']);
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex flex-wrap align-items-start gap-3 justify-content-between">
  <div>
    <h2><i class="bi bi-box2-fill me-2 text-<?= $branchInfo['color'] ?>"></i><?= htmlspecialchars($branchInfo['name']) ?></h2>
    <p>Quản lý sản phẩm — <?= count($products) ?> sản phẩm</p>
  </div>
  <?php if ($canManage): ?>
  <button class="btn btn-primary" onclick="openAddModal()">
    <i class="bi bi-plus-lg me-1"></i>Thêm Sản Phẩm
  </button>
  <?php endif; ?>
</div>

<!-- Filter bar -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form class="d-flex flex-wrap gap-2 align-items-center" method="GET">
      <input type="hidden" name="page" value="products">
      <input type="hidden" name="branch" value="<?= $reqBranch ?>">
      <div class="search-box" style="min-width:220px;position:relative">
        <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none"></i>
        <input type="text" name="q" class="form-control form-control-sm" style="padding-left:32px"
          placeholder="Tìm mã, tên..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="cat" class="form-select form-select-sm" style="width:180px">
        <option value="">Tất cả nhóm</option>
        <?php foreach ($categories as $cKey => $cInfo): ?>
        <option value="<?= $cKey ?>" <?= $category === $cKey ? 'selected' : '' ?>><?= htmlspecialchars($cInfo['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-sm btn-primary">Lọc</button>
      <a href="index.php?page=products&branch=<?= $reqBranch ?>" class="btn btn-sm btn-outline-secondary">Đặt lại</a>
    </form>
  </div>
</div>

<!-- Product table -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>Mã SP</th><th>Tên sản phẩm</th><th>Nhóm</th><th>ĐVT</th>
          <th class="text-end">Giá nhập</th><th class="text-end">Giá bán</th>
          <th class="text-end">Tồn kho</th><th class="text-center">Trạng thái</th>
          <?php if ($canManage): ?><th class="text-center">Thao tác</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php if (empty($products)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <i class="bi bi-box-seam"></i>
            <p>Chưa có sản phẩm nào<?= $search ? " khớp với \"$search\"" : '' ?></p>
            <?php if ($canManage && !$search): ?>
            <button class="btn btn-sm btn-primary mt-2" onclick="openAddModal()">
              <i class="bi bi-plus-lg me-1"></i>Thêm sản phẩm đầu tiên
            </button>
            <?php endif; ?>
          </div>
        </td></tr>
        <?php else: foreach ($products as $p): $lowStock = ($p['stock'] ?? 0) < ($p['min_stock'] ?? 5); ?>
        <tr class="<?= $lowStock ? 'stock-low-row' : '' ?>">
          <td><code><?= htmlspecialchars($p['code'] ?? '') ?></code></td>
          <td class="fw-600"><?= htmlspecialchars($p['name'] ?? '') ?>
            <?php if (!empty($p['special_colors'])): ?>
            <span class="badge ms-1" style="background:rgba(139,92,246,.15);color:#7c3aed;font-size:10px;font-weight:600">
              <i class="bi bi-palette me-1"></i><?= count($p['special_colors']) ?> màu ĐB
            </span>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= htmlspecialchars($p['category_name'] ?? '') ?></span></td>
          <td><?= htmlspecialchars($p['unit'] ?? '') ?></td>
          <td class="text-end money"><?= formatMoney($p['price_in'] ?? 0) ?></td>
          <td class="text-end money fw-700"><?= formatMoney($p['price_out'] ?? 0) ?></td>
          <td class="text-end <?= $lowStock ? 'stock-low' : 'stock-ok' ?>">
            <?= number_format($p['stock'] ?? 0, 2, ',', '.') ?> <?= htmlspecialchars($p['unit'] ?? '') ?>
          </td>
          <td class="text-center">
            <?php if ($lowStock): ?>
              <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Sắp hết</span>
            <?php else: ?>
              <span class="badge bg-success bg-opacity-10 text-success">Bình thường</span>
            <?php endif; ?>
          </td>
          <?php if ($canManage): ?>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-primary"
              onclick='openEditModal(<?= json_encode([
                "id"             => $p["id"],
                "code"           => $p["code"],
                "name"           => $p["name"],
                "unit"           => $p["unit"],
                "stock"          => $p["stock"] ?? 0,
                "price_in"       => $p["price_in"] ?? 0,
                "price_out"      => $p["price_out"] ?? 0,
                "min_stock"      => $p["min_stock"] ?? 5,
                "category_key"   => $p["category_key"] ?? "",
                "special_colors" => $p["special_colors"] ?? [],
              ], JSON_HEX_APOS|JSON_UNESCAPED_UNICODE) ?>)'>
              <i class="bi bi-pencil"></i>
            </button>
            <a href="index.php?page=products&branch=<?= $reqBranch ?>&action=delete&id=<?= urlencode($p['id'] ?? '') ?>&cat=<?= urlencode($p['category_key'] ?? '') ?>"
               class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Xóa sản phẩm \'<?= htmlspecialchars(addslashes($p['name'] ?? '')) ?>\'?')">
              <i class="bi bi-trash"></i>
            </a>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($canManage): ?>
<!-- Modal Thêm / Sửa Sản Phẩm -->
<div class="modal fade" id="productModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Thêm Sản Phẩm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php?page=products&branch=<?= $reqBranch ?>&action=save">
        <!-- id rỗng = thêm mới, có giá trị = sửa -->
        <input type="hidden" name="id" id="pId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Mã sản phẩm *</label>
              <input type="text" name="code" id="pCode" class="form-control" required
                placeholder="VD: XM001" autocomplete="off">
            </div>
            <div class="col-md-8">
              <label class="form-label">Tên sản phẩm *</label>
              <input type="text" name="name" id="pName" class="form-control" required
                placeholder="Nhập tên đầy đủ của sản phẩm">
            </div>

            <div class="col-md-4">
              <label class="form-label">Nhóm hàng *</label>
              <select name="category" id="pCategory" class="form-select" required>
                <option value="">-- Chọn nhóm --</option>
                <?php foreach ($categories as $cKey => $cInfo): ?>
                <option value="<?= $cKey ?>"><?= htmlspecialchars($cInfo['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Đơn vị tính *</label>
              <select name="unit" id="pUnit" class="form-select" required>
                <?php foreach (UNITS as $u): ?>
                <option value="<?= $u ?>"><?= $u ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tồn kho tối thiểu</label>
              <input type="number" name="min_stock" id="pMinStock" class="form-control" onfocus="this.select()"
                value="5" min="0" step="0.01">
              <div class="form-text">Cảnh báo khi tồn kho dưới mức này</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Giá nhập (₫)</label>
              <input type="number" name="price_in" id="pPriceIn" class="form-control" onfocus="this.select()"
                value="0" min="0" step="1000">
            </div>
            <div class="col-md-4">
              <label class="form-label">Giá bán (₫) *</label>
              <input type="number" name="price_out" id="pPriceOut" class="form-control" onfocus="this.select()"
                value="0" min="0" step="1000" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tồn kho ban đầu</label>
              <input type="number" name="stock" id="pStock" class="form-control" onfocus="this.select()"
                value="0" min="0" step="0.01">
              <div class="form-text">Chỉ điền khi thêm mới</div>
            </div>

            <!-- Màu đặc biệt (phụ thu thêm) -->
            <div class="col-12">
              <div style="border:1.5px dashed #e5e7eb;border-radius:8px;padding:14px 16px;background:#fafafa">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <label class="form-label mb-0" style="color:#6b7280">
                    <i class="bi bi-palette me-1" style="color:#8b5cf6"></i>
                    Màu Đặc Biệt <span style="font-weight:400;font-size:11.5px">(phụ thu thêm vào giá bán)</span>
                  </label>
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="addColorRow()">
                    <i class="bi bi-plus-lg me-1"></i>Thêm màu
                  </button>
                </div>
                <div id="specialColorsContainer">
                  <!-- Các dòng màu sẽ được render ở đây -->
                </div>
                <div id="specialColorsEmpty" style="font-size:12.5px;color:#9ca3af;text-align:center;padding:8px 0">
                  Chưa có màu đặc biệt — sản phẩm chỉ có 1 mức giá bán
                </div>
                <input type="hidden" name="special_colors" id="pSpecialColors" value="[]">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2 me-1"></i><span id="modalBtnText">Thêm sản phẩm</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ── Quản lý màu đặc biệt ─────────────────────────────────────
let specialColors = [];

function renderColors() {
  const container = document.getElementById('specialColorsContainer');
  const empty     = document.getElementById('specialColorsEmpty');
  const hidden    = document.getElementById('pSpecialColors');
  if (!container) return;

  if (!specialColors.length) {
    container.innerHTML = '';
    empty.style.display = '';
    hidden.value = '[]';
    return;
  }
  empty.style.display = 'none';
  hidden.value = JSON.stringify(specialColors);

  container.innerHTML = specialColors.map((c, i) => `
    <div class="d-flex gap-2 align-items-center mb-2" id="colorRow_${i}">
      <input type="text" class="form-control form-control-sm" style="flex:2"
        placeholder="Tên màu (VD: Đỏ đậm, Vàng kim...)"
        value="${esc(c.name)}"
        oninput="updateColor(${i},'name',this.value)">
      <input type="text" class="form-control form-control-sm" style="flex:1;font-family:monospace"
        placeholder="Mã (tuỳ chọn)"
        value="${esc(c.code||'')}"
        oninput="updateColor(${i},'code',this.value)">
      <div class="input-group input-group-sm" style="width:160px;flex-shrink:0">
        <span class="input-group-text" style="font-size:11px;white-space:nowrap">+ Phụ thu</span>
        <input type="number" class="form-control" style="text-align:right"
          min="0" step="1000" value="${c.surcharge||0}"
          onfocus="this.select()"
          oninput="updateColor(${i},'surcharge',this.value)">
        <span class="input-group-text" style="font-size:11px">₫</span>
      </div>
      <button type="button" class="btn btn-sm btn-outline-danger" style="flex-shrink:0"
        onclick="removeColor(${i})"><i class="bi bi-x"></i></button>
    </div>`).join('');
}

function addColorRow() {
  specialColors.push({ name: '', code: '', surcharge: 0 });
  renderColors();
  // Focus vào ô tên màu vừa thêm
  const rows = document.querySelectorAll('#specialColorsContainer .d-flex');
  if (rows.length) rows[rows.length-1].querySelector('input')?.focus();
}

function updateColor(idx, field, val) {
  if (!specialColors[idx]) return;
  specialColors[idx][field] = field === 'surcharge' ? (parseFloat(val)||0) : val;
  document.getElementById('pSpecialColors').value = JSON.stringify(specialColors);
}

function removeColor(idx) {
  specialColors.splice(idx, 1);
  renderColors();
}

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Modal thêm ────────────────────────────────────────────────
function openAddModal() {
  document.getElementById('modalTitle').textContent   = 'Thêm Sản Phẩm Mới';
  document.getElementById('modalBtnText').textContent = 'Thêm sản phẩm';
  document.getElementById('pId').value       = '';
  document.getElementById('pCode').value     = '';
  document.getElementById('pName').value     = '';
  document.getElementById('pPriceIn').value  = '0';
  document.getElementById('pPriceOut').value = '0';
  document.getElementById('pStock').value    = '0';
  document.getElementById('pMinStock').value = '5';
  document.getElementById('pCategory').value = '';
  document.getElementById('pUnit').value     = '<?= UNITS[0] ?>';
  document.getElementById('pStock').removeAttribute('readonly');
  specialColors = [];
  renderColors();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('productModal')).show();
}

// ── Modal sửa ────────────────────────────────────────────────
function openEditModal(p) {
  document.getElementById('modalTitle').textContent   = 'Sửa Thông Tin Sản Phẩm';
  document.getElementById('modalBtnText').textContent = 'Lưu thay đổi';
  document.getElementById('pId').value       = p.id       || '';
  document.getElementById('pCode').value     = p.code     || '';
  document.getElementById('pName').value     = p.name     || '';
  document.getElementById('pPriceIn').value  = p.price_in  || 0;
  document.getElementById('pPriceOut').value = p.price_out || 0;
  document.getElementById('pStock').value    = p.stock     || 0;
  document.getElementById('pMinStock').value = p.min_stock || 5;
  const catSel = document.getElementById('pCategory');
  if (p.category_key) catSel.value = p.category_key;
  const unitSel = document.getElementById('pUnit');
  if (p.unit) unitSel.value = p.unit;
  document.getElementById('pStock').setAttribute('readonly', true);
  // Load màu đặc biệt
  specialColors = Array.isArray(p.special_colors) ? p.special_colors : [];
  renderColors();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('productModal')).show();
}
</script>
<?php endif; ?>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
