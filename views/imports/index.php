<?php
$reqBranch  = $_GET['branch'] ?? array_key_first(BRANCHES);
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }
$branchInfo = BRANCHES[$reqBranch];
$categoriesRaw = getCategories($reqBranch, true);
$categories = [];
foreach ($categoriesRaw as $c2) { $categories[$c2['key']] = $c2; }
$products   = getAllProducts($reqBranch);
$imports    = getImports($reqBranch);
$pageTitle  = 'Nhập Hàng — ' . $branchInfo['name'];
$preCode    = $_GET['product_code'] ?? '';
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex align-items-center gap-2">
  <div>
    <h2><i class="bi bi-download me-2 text-<?= $branchInfo['color'] ?>"></i>Nhập Hàng</h2>
    <p><?= htmlspecialchars($branchInfo['name']) ?> — Tháng <?= date('m/Y') ?></p>
  </div>
</div>

<div class="row g-3">
<!-- Phiếu nhập -->
<div class="col-lg-5">
  <div class="card">
    <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Tạo Phiếu Nhập</div>
    <div class="card-body">
      <form method="POST" action="index.php?page=imports&branch=<?= $reqBranch ?>">
        <input type="hidden" name="action" value="import">
        <input type="hidden" name="branch" value="<?= $reqBranch ?>">

        <div class="mb-3">
          <label class="form-label">Nhóm hàng *</label>
          <select name="category" id="importCategory" class="form-select" onchange="filterProductsByCategory(this.value)" required>
            <option value="">-- Chọn nhóm --</option>
            <?php foreach ($categories as $cKey => $cInfo): ?>
            <option value="<?= $cKey ?>"><?= htmlspecialchars($cInfo['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Sản phẩm *</label>
          <select name="product_code" id="importProduct" class="form-select" onchange="fillProductInfo(this)" required>
            <option value="">-- Chọn nhóm trước --</option>
          </select>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Số lượng *</label>
            <div class="input-group">
              <input type="number" name="qty" id="importQty" class="form-control" min="0.01" step="0.01" required placeholder="0">
              <span class="input-group-text" id="importUnit">đvt</span>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label">Giá nhập (₫)</label>
            <input type="number" name="price_in" id="importPrice" class="form-control" min="0" placeholder="0">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Ngày nhập</label>
          <input type="date" name="import_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Nhà cung cấp</label>
          <input type="text" name="supplier" class="form-control" placeholder="Tên nhà cung cấp">
        </div>

        <div class="mb-3">
          <label class="form-label">Ghi chú</label>
          <textarea name="note" class="form-control" rows="2" placeholder="Ghi chú thêm..."></textarea>
        </div>

        <div id="importSummary" class="alert alert-info d-none py-2" style="font-size:13px">
          <i class="bi bi-info-circle me-2"></i>
          <span id="importSummaryText"></span>
        </div>

        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-check2-circle me-2"></i>Xác Nhận Nhập Hàng
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Lịch sử nhập -->
<div class="col-lg-7">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-clock-history me-2"></i>Lịch Sử Nhập Hàng — Tháng <?= date('m/Y') ?></span>
      <span class="badge bg-secondary"><?= count($imports) ?> phiếu</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive" style="max-height:520px;overflow-y:auto">
        <table class="table table-hover mb-0" style="font-size:12.5px">
          <thead style="position:sticky;top:0;background:#fff;z-index:1">
            <tr><th>Phiếu</th><th>Sản phẩm</th><th>SL</th><th>Giá nhập</th><th>Thành tiền</th><th>Ngày</th></tr>
          </thead>
          <tbody>
          <?php if (empty($imports)): ?>
          <tr><td colspan="6"><div class="empty-state" style="padding:32px"><i class="bi bi-inbox"></i><p>Chưa có phiếu nhập</p></div></td></tr>
          <?php else: foreach (array_reverse($imports) as $imp): ?>
          <tr>
            <td><code style="font-size:10px"><?= htmlspecialchars(substr($imp['id'] ?? '', 0, 18)) ?></code></td>
            <td>
              <div class="fw-600"><?= htmlspecialchars($imp['product_name'] ?? '') ?></div>
              <div class="product-code"><?= htmlspecialchars($imp['product_code'] ?? '') ?></div>
            </td>
            <td class="text-end fw-700"><?= number_format($imp['qty'] ?? 0, 2, ',', '.') ?> <?= htmlspecialchars($imp['unit'] ?? '') ?></td>
            <td class="text-end"><?= formatMoney($imp['price_in'] ?? 0) ?></td>
            <td class="text-end money fw-700 text-success"><?= formatMoney($imp['total_amount'] ?? 0) ?></td>
            <td><?= htmlspecialchars(substr($imp['import_date'] ?? $imp['created_at'] ?? '', 0, 10)) ?></td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

<script>
const allProducts = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
const preCode = <?= json_encode($preCode) ?>;

function filterProductsByCategory(cat) {
  const sel = document.getElementById('importProduct');
  sel.innerHTML = '<option value="">-- Chọn sản phẩm --</option>';
  const filtered = allProducts.filter(p => p.category_key === cat);
  filtered.forEach(p => {
    const opt = document.createElement('option');
    opt.value = p.code;
    opt.textContent = `${p.name} (Tồn: ${p.stock} ${p.unit})`;
    opt.dataset.unit     = p.unit;
    opt.dataset.price_in = p.price_in || 0;
    opt.dataset.stock    = p.stock || 0;
    sel.appendChild(opt);
  });
  if (preCode) {
    sel.value = preCode;
    fillProductInfo(sel);
  }
}

function fillProductInfo(sel) {
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) return;
  document.getElementById('importUnit').textContent  = opt.dataset.unit  || 'đvt';
  document.getElementById('importPrice').value       = opt.dataset.price_in || 0;
  updateSummary(opt);
}

function updateSummary(opt) {
  const qty   = parseFloat(document.getElementById('importQty').value) || 0;
  const price = parseFloat(document.getElementById('importPrice').value) || 0;
  const unit  = opt ? opt.dataset.unit : '';
  const stock = opt ? parseFloat(opt.dataset.stock || 0) : 0;
  if (qty > 0 && opt && opt.value) {
    document.getElementById('importSummary').classList.remove('d-none');
    document.getElementById('importSummaryText').innerHTML =
      `Nhập <b>${qty}</b> ${unit}, tồn hiện tại: <b>${stock}</b> ${unit} → sau nhập: <b>${stock + qty}</b> ${unit}` +
      (price > 0 ? `, tổng tiền: <b>${new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(qty*price)}</b>` : '');
  } else {
    document.getElementById('importSummary').classList.add('d-none');
  }
}

document.getElementById('importQty').addEventListener('input', () => {
  const sel = document.getElementById('importProduct');
  fillProductInfo(sel);
});
document.getElementById('importPrice').addEventListener('input', () => {
  const sel = document.getElementById('importProduct');
  fillProductInfo(sel);
});

// Pre-fill if came from dashboard
if (preCode) {
  const product = allProducts.find(p => p.code === preCode);
  if (product) {
    const catSel = document.getElementById('importCategory');
    catSel.value = product.category_key;
    filterProductsByCategory(product.category_key);
  }
}
</script>
<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
