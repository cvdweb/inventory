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
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary" onclick="printPriceList()">
      <i class="bi bi-printer me-1"></i>In Bảng Giá
    </button>
    <?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="openAddModal()">
      <i class="bi bi-plus-lg me-1"></i>Thêm Sản Phẩm
    </button>
    <?php endif; ?>
  </div>
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
                value="0" min="0" step="10">
            </div>
            <div class="col-md-4">
              <label class="form-label">Giá bán (₫) *</label>
              <input type="number" name="price_out" id="pPriceOut" class="form-control" onfocus="this.select()"
                value="0" min="0" step="10" required>
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
// ── In Bảng Giá ───────────────────────────────────────────────
function printPriceList() {
  const BIZ = <?= json_encode([
    'name'       => BUSINESS['name'],
    'address'    => BUSINESS['address'],
    'phone'      => BUSINESS['phone'],
    'slogan'     => BUSINESS['slogan'] ?? '',
    'branch'     => $branchInfo['name'],
    'date'       => date('d/m/Y'),
  ], JSON_UNESCAPED_UNICODE) ?>;

  // Dữ liệu sản phẩm nhóm theo category
  const GROUPS = <?= (function() use ($categoriesRaw, $reqBranch) {
    $groups = [];
    foreach ($categoriesRaw as $cat) {
        $file  = DATA_PATH . "/{$reqBranch}/" . $cat['file'];
        $prods = readJson($file);
        if (empty($prods)) continue;
        $groups[] = [
            'name'     => $cat['name'],
            'products' => array_values($prods),
        ];
    }
    return json_encode($groups, JSON_UNESCAPED_UNICODE);
  })() ?>;

  // Sinh HTML bảng từng nhóm
  let tablesHtml = '';
  GROUPS.forEach(group => {
    if (!group.products.length) return;
    const rows = group.products.map((p, idx) => {
      const hasSpecial = p.special_colors && p.special_colors.length > 0;
      // Dòng sản phẩm gốc
      let html = `<tr>
        <td style="text-align:center;color:#777">${idx+1}</td>
        <td style="font-family:'Courier New',monospace;font-size:10.5pt">${_esc(p.code||'')}</td>
        <td style="font-weight:bold">${_esc(p.name||'')}${hasSpecial?'<span style="font-size:9.5pt;color:#7c3aed;font-weight:normal;margin-left:6px">(màu thường)</span>':''}</td>
        <td style="text-align:center">${_esc(p.unit||'')}</td>
        <td style="text-align:right;font-weight:bold;font-size:13pt">${_fmtPrice(p.price_out)}</td>
        <td></td>
      </tr>`;
      // Dòng màu đặc biệt
      if (hasSpecial) {
        p.special_colors.forEach(sc => {
          const finalPrice = (parseFloat(p.price_out)||0) + (parseFloat(sc.surcharge)||0);
          html += `<tr style="background:#faf5ff">
            <td></td>
            <td style="font-family:'Courier New',monospace;font-size:10pt;color:#7c3aed;padding-left:12pt">
              ${sc.code ? _esc(sc.code) : ''}
            </td>
            <td style="padding-left:20pt;color:#5b21b6">
              <span style="margin-right:4pt">↳</span>${_esc(sc.name)}
              <span style="font-size:9.5pt;color:#9ca3af;margin-left:6px">+ ${_fmtPrice(sc.surcharge)}</span>
            </td>
            <td style="text-align:center;color:#777">${_esc(p.unit||'')}</td>
            <td style="text-align:right;font-weight:bold;font-size:13pt;color:#7c3aed">${_fmtPrice(finalPrice)}</td>
            <td></td>
          </tr>`;
        });
      }
      return html;
    }).join('');

    tablesHtml += `
      <div class="group-block">
        <div class="group-title">${_esc(group.name)}</div>
        <table>
          <thead><tr>
            <th style="width:32px;text-align:center">STT</th>
            <th style="width:90px">Mã SP</th>
            <th>Tên sản phẩm</th>
            <th style="width:55px;text-align:center">ĐVT</th>
            <th style="width:120px;text-align:right">Giá bán</th>
            <th style="width:80px;text-align:center">Ghi chú</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  });

  const win = window.open('', '_blank', 'width=900,height=750');
  win.document.write(`<!DOCTYPE html>
<html lang="vi"><head>
<meta charset="UTF-8">
<title>Bảng Giá — ${_esc(BIZ.branch)}</title>
<style>
  @page { size: A4; margin: 12mm 16mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Times New Roman', serif; font-size: 12.5pt; color: #000; }

  /* Header */
  .biz-header { text-align: center; padding-bottom: 4mm; border-bottom: 2.5px solid #000; margin-bottom: 4mm; }
  .biz-name   { font-size: 17pt; font-weight: bold; letter-spacing: .5px; }
  .biz-branch { font-size: 11pt; color: #555; margin-top: 1.5mm; }
  .biz-contact{ font-size: 12pt; color: #222; margin-top: 1.5mm; font-weight: bold; }
  .biz-slogan { font-size: 10pt; color: #777; font-style: italic; margin-top: 1mm; }

  /* Tiêu đề bảng giá */
  .doc-title  { text-align: center; font-size: 18pt; font-weight: bold;
    letter-spacing: 3px; text-transform: uppercase; margin: 4mm 0 1mm; }
  .doc-date   { text-align: center; font-size: 10pt; color: #666; margin-bottom: 5mm; }

  /* Nhóm hàng */
  .group-block { margin-bottom: 6mm; break-inside: avoid; }
  .group-title {
    font-size: 13pt; font-weight: bold;
    background: #1e293b; color: #fff;
    padding: 2.5mm 4mm; border-radius: 1.5mm;
    margin-bottom: 0;
    letter-spacing: .5px;
  }

  /* Bảng */
  table { width: 100%; border-collapse: collapse; font-size: 12pt; }
  thead tr { background: #f1f5f9; }
  th { border: 1px solid #94a3b8; padding: 2mm 3mm; font-weight: bold; font-size: 11pt; }
  td { border: 1px solid #cbd5e1; padding: 2mm 3mm; vertical-align: middle; }
  tr:nth-child(even):not([style]) { background: #f8fafc; }

  /* Footer */
  .footer {
    margin-top: 6mm;
    padding-top: 3mm;
    border-top: 1px dashed #aaa;
    font-size: 10pt;
    color: #666;
    display: flex;
    justify-content: space-between;
  }
  .note-box {
    margin-top: 4mm;
    padding: 3mm 4mm;
    border: 1px solid #e5e7eb;
    border-radius: 2mm;
    font-size: 10.5pt;
    color: #555;
    background: #fafafa;
  }
</style>
</head><body>

<!-- Header doanh nghiệp -->
<div class="biz-header">
  <div class="biz-name">${_esc(BIZ.name)}</div>
  <div class="biz-branch">${_esc(BIZ.branch)}</div>
  <div class="biz-contact">📍 ${_esc(BIZ.address)} &nbsp;|&nbsp; 📞 ${_esc(BIZ.phone)}</div>
  ${BIZ.slogan ? `<div class="biz-slogan">"${_esc(BIZ.slogan)}"</div>` : ''}
</div>

<!-- Tiêu đề -->
<div class="doc-title">Bảng Giá Sản Phẩm</div>
<div class="doc-date">Áp dụng từ ngày ${_esc(BIZ.date)} &nbsp;·&nbsp; Giá có thể thay đổi, vui lòng liên hệ để xác nhận</div>

<!-- Bảng giá từng nhóm -->
${tablesHtml}

<!-- Ghi chú -->
<div class="note-box">
  <b>Ghi chú:</b>
  Giá trên là giá bán lẻ, chưa bao gồm VAT.
  Màu đặc biệt (nền tím) có phụ thu thêm theo từng loại màu.
  Liên hệ cửa hàng để biết thêm chi tiết và giá sỉ.
</div>

<div class="footer">
  <span>In lúc: ${new Date().toLocaleString('vi-VN')}</span>
  <span>${_esc(BIZ.name)} — ${_esc(BIZ.phone)}</span>
</div>

<script>window.onload = function(){ window.print(); window.close(); }<\/script>
</body></html>`);
  win.document.close();
}

function _esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function _fmtPrice(n) { return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(Number(n)||0); }
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
          min="0" step="50" value="${c.surcharge||0}"
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
