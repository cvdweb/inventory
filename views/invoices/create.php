<?php
$reqBranch  = $_GET['branch'] ?? array_key_first(BRANCHES);
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }
$branchInfo  = BRANCHES[$reqBranch];
$pageTitle   = 'Lập Hóa Đơn — ' . $branchInfo['name'];
$allProducts = getAllProducts($reqBranch);
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex align-items-center gap-2">
  <div>
    <h2><i class="bi bi-receipt me-2 text-<?= $branchInfo['color'] ?>"></i>Lập Hóa Đơn Bán Hàng</h2>
    <p><?= htmlspecialchars($branchInfo['name']) ?> — <?= date('d/m/Y') ?></p>
  </div>
</div>

<form method="POST" action="index.php?page=invoice&branch=<?= $reqBranch ?>" onsubmit="return invoiceSubmit(event)">
  <input type="hidden" name="action" value="create_invoice">
  <input type="hidden" name="branch" value="<?= $reqBranch ?>">
  <input type="hidden" name="items" id="invoiceItemsJson">

<div class="row g-3">
  <!-- Left -->
  <div class="col-lg-4">

    <!-- Thông tin khách -->
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-person me-2"></i>Thông Tin Khách Hàng</div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label">Tên khách hàng</label>
          <input type="text" name="customer" class="form-control" value="Khách lẻ">
        </div>
        <div class="mb-2">
          <label class="form-label">Số điện thoại</label>
          <input type="tel" name="phone" class="form-control" placeholder="0xxx xxx xxx">
        </div>
        <div class="mb-2">
          <label class="form-label">Hình thức thanh toán</label>
          <select name="payment" class="form-select">
            <option value="cash">Tiền mặt</option>
            <option value="transfer">Chuyển khoản</option>
            <option value="cod">COD</option>
            <option value="credit">Công nợ</option>
          </select>
        </div>
        <div class="mb-0">
          <label class="form-label">Ghi chú hóa đơn</label>
          <textarea name="note" class="form-control" rows="2" placeholder="Ghi chú..."></textarea>
        </div>
      </div>
    </div>

    <!-- Thông tin giao hàng -->
    <div class="card mb-3" style="border-left:3px solid #f59e0b">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-truck" style="color:#f59e0b"></i>
        <span>Thông Tin Giao Hàng</span>
        <span style="font-size:11px;color:#9ca3af;margin-left:auto">Để trống = lấy tại quầy</span>
      </div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label">Ngày giao hàng</label>
          <input type="date" name="delivery_date" id="deliveryDate" class="form-control"
            min="<?= date('Y-m-d') ?>" onchange="onDeliveryDateChange(this.value)">
          <div class="form-text">Để trống nếu khách lấy hàng trực tiếp tại quầy</div>
        </div>
        <div class="mb-2">
          <label class="form-label">Địa chỉ giao hàng</label>
          <input type="text" name="address" id="deliveryAddress" class="form-control"
            placeholder="Số nhà, đường, phường/xã...">
        </div>
        <div class="mb-0">
          <label class="form-label">Ghi chú cho tài xế</label>
          <textarea name="delivery_note" class="form-control" rows="2"
            placeholder="VD: Gọi trước 30 phút, giao tầng 2..."></textarea>
        </div>

        <!-- Preview badge -->
        <div id="deliveryBadge" class="mt-2" style="display:none">
          <span class="badge" id="deliveryBadgeInner"
            style="background:#f59e0b;font-size:12px;padding:6px 12px">
            <i class="bi bi-truck me-1"></i><span id="deliveryBadgeText"></span>
          </span>
        </div>
      </div>
    </div>

    <!-- Tìm sản phẩm -->
    <div class="card">
      <div class="card-header"><i class="bi bi-search me-2"></i>Tìm Sản Phẩm</div>
      <div class="card-body pb-2">
        <div style="position:relative" class="mb-2">
          <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none">
            <i class="bi bi-search"></i>
          </span>
          <input type="text" id="productSearch" class="form-control" style="padding-left:36px"
            placeholder="Nhập mã hoặc tên sản phẩm..."
            autocomplete="off" oninput="doSearch(this.value)" onfocus="doSearch(this.value)">
          <div id="productDropdown" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;
            background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;
            box-shadow:0 4px 12px rgba(0,0,0,.12);z-index:9999;max-height:320px;overflow-y:auto"></div>
        </div>
        <div style="font-size:12px;color:#9ca3af">
          <i class="bi bi-info-circle me-1"></i>Gõ để tìm — click để thêm vào hóa đơn
          <span class="float-end fw-600"><?= count($allProducts) ?> sản phẩm</span>
        </div>
      </div>
      <!-- Chọn theo nhóm -->
      <div class="card-body pt-0">
        <div class="fw-700 mb-2" style="font-size:12px;color:#6b7280">HOẶC CHỌN THEO NHÓM</div>
        <?php
        $catList = getCategories($reqBranch, true);
        foreach ($catList as $catInfo):
          $catKey   = $catInfo['key'];
          $catProds = array_filter($allProducts, fn($p) => ($p['category_key'] ?? '') === $catKey);
        ?>
        <div class="mb-2">
          <button type="button"
            class="btn btn-sm btn-outline-secondary w-100 text-start d-flex justify-content-between align-items-center"
            onclick="toggleCatList('cat_<?= $catKey ?>')">
            <span><?= htmlspecialchars($catInfo['name']) ?></span>
            <span class="badge bg-secondary"><?= count($catProds) ?></span>
          </button>
          <div id="cat_<?= $catKey ?>" style="display:none;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 6px 6px;overflow:hidden">
            <?php foreach ($catProds as $p): $low = ($p['stock']??0) < ($p['min_stock']??5); ?>
            <div onclick='addItemToInvoice(<?= json_encode([
                'code'      => $p['code'],
                'name'      => $p['name'],
                'unit'      => $p['unit'],
                'price_out' => (float)($p['price_out'] ?? 0),
                'stock'     => (float)($p['stock'] ?? 0),
                'category_name' => $p['category_name'] ?? '',
            ], JSON_UNESCAPED_UNICODE) ?>)'
              style="padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f3f4f6;background:#fff"
              onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background='#fff'">
              <div class="fw-600 text-dark"><?= htmlspecialchars($p['name']) ?></div>
              <div class="d-flex justify-content-between mt-1">
                <span style="font-family:'JetBrains Mono',monospace;font-size:11px;color:#9ca3af"><?= htmlspecialchars($p['code']) ?></span>
                <span style="font-size:11px;color:<?= $low ? '#ef4444' : '#10b981' ?>;font-weight:700">
                  Tồn: <?= number_format($p['stock']??0,2,',','.') ?> <?= htmlspecialchars($p['unit']) ?>
                </span>
                <span style="font-size:11px;font-weight:700;color:#f59e0b"><?= number_format($p['price_out']??0,0,',','.') ?>₫</span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Right: Hàng hóa -->
  <div class="col-lg-8">
    <div class="invoice-builder">
      <div class="card-header border-bottom" style="padding:14px 20px;font-weight:700;font-size:14px">
        <i class="bi bi-list-ul me-2"></i>Danh Sách Hàng Hóa
        <span id="itemCount" class="badge bg-secondary ms-2">0</span>
      </div>
      <div id="invoiceItems">
        <div class="empty-state">
          <i class="bi bi-cart-x"></i>
          <p>Chưa có sản phẩm.<br>Tìm hoặc chọn nhóm hàng bên trái để thêm.</p>
        </div>
      </div>
      <div class="invoice-total-bar">
        <div class="text-end">
          <div class="total-label">Tổng cộng</div>
          <div class="total-value" id="invoiceTotal">0 ₫</div>
        </div>
      </div>
    </div>

    <div class="mt-3 d-flex gap-2 justify-content-end">
      <button type="button" class="btn btn-outline-secondary"
        onclick="if(confirm('Xóa toàn bộ hóa đơn?')){invoiceItems=[];renderItems()}">
        <i class="bi bi-trash me-1"></i>Xóa tất cả
      </button>
      <button type="submit" class="btn btn-primary btn-lg px-4">
        <i class="bi bi-check2-circle me-2"></i>Xuất Hóa Đơn
      </button>
    </div>
  </div>
</div>
</form>

<script>
const PRODUCTS = <?= json_encode(array_values($allProducts), JSON_UNESCAPED_UNICODE) ?>;
let invoiceItems = [];

// ── Ngày giao hàng preview ────────────────────────────────────
function onDeliveryDateChange(val) {
  const badge    = document.getElementById('deliveryBadge');
  const badgeTxt = document.getElementById('deliveryBadgeText');
  if (val) {
    const d = new Date(val);
    badgeTxt.textContent = 'Giao ' + d.toLocaleDateString('vi-VN', {weekday:'short',day:'2-digit',month:'2-digit'});
    badge.style.display = 'block';
  } else {
    badge.style.display = 'none';
  }
}

// ── Tìm kiếm ─────────────────────────────────────────────────
function doSearch(val) {
  const dd = document.getElementById('productDropdown');
  val = val.trim();
  if (!val) { dd.style.display = 'none'; return; }
  const kw = removeDiacritics(val.toLowerCase());
  const results = PRODUCTS.filter(p =>
    removeDiacritics((p.code||'').toLowerCase()).includes(kw) ||
    removeDiacritics((p.name||'').toLowerCase()).includes(kw)
  ).slice(0, 12);
  if (!results.length) {
    dd.innerHTML = '<div style="padding:12px 14px;font-size:13px;color:#9ca3af">Không tìm thấy</div>';
    dd.style.display = 'block'; return;
  }
  dd.innerHTML = results.map(p => {
    const low = p.stock < (p.min_stock || 5);
    return `<div onclick='addItemToInvoice(${JSON.stringify(p)})'
      style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f3f4f6"
      onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background=''">
      <div style="font-weight:600;font-size:13.5px">${esc(p.name)}</div>
      <div style="display:flex;gap:12px;margin-top:4px;flex-wrap:wrap">
        <span style="font-family:'JetBrains Mono',monospace;font-size:11px;color:#9ca3af">${esc(p.code)}</span>
        <span style="font-size:11px;color:${low?'#ef4444':'#10b981'};font-weight:700">
          Tồn: ${fmt(p.stock)} ${esc(p.unit)}${low?' ⚠️':''}
        </span>
        <span style="font-size:11px;font-weight:700;color:#f59e0b">${fmtMoney(p.price_out)}</span>
      </div>
    </div>`;
  }).join('');
  dd.style.display = 'block';
}
document.addEventListener('click', e => {
  const dd = document.getElementById('productDropdown');
  const inp = document.getElementById('productSearch');
  if (!dd.contains(e.target) && e.target !== inp) dd.style.display = 'none';
});

function toggleCatList(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// ── Giỏ hàng ─────────────────────────────────────────────────
function addItemToInvoice(p) {
  document.getElementById('productDropdown').style.display = 'none';
  document.getElementById('productSearch').value = '';
  const existing = invoiceItems.find(i => i.code === p.code);
  if (existing) {
    existing.qty += 1;
    existing.line_total = existing.qty * existing.price_out;
  } else {
    invoiceItems.push({
      code: p.code, name: p.name, unit: p.unit,
      qty: 1,
      price_out: parseFloat(p.price_out) || 0,
      line_total: parseFloat(p.price_out) || 0,
      stock: parseFloat(p.stock) || 0,
    });
  }
  renderItems();
}
function removeItem(code) {
  invoiceItems = invoiceItems.filter(i => i.code !== code);
  renderItems();
}
function setQty(code, val) {
  const item = invoiceItems.find(i => i.code === code);
  if (!item) return;
  const n = parseFloat(val) || 0;
  if (n <= 0) { removeItem(code); return; }
  if (n > item.stock) {
    alert(`Tồn kho chỉ còn ${fmt(item.stock)} ${item.unit}`);
    document.querySelector(`[data-qty="${code}"]`).value = item.qty; return;
  }
  item.qty = n; item.line_total = item.qty * item.price_out; renderItems();
}
function setPrice(code, val) {
  const item = invoiceItems.find(i => i.code === code);
  if (!item) return;
  item.price_out = parseFloat(val) || 0;
  item.line_total = item.qty * item.price_out;
  updateTotal(); syncJson();
}
function renderItems() {
  const container = document.getElementById('invoiceItems');
  if (!container) return;
  if (!invoiceItems.length) {
    container.innerHTML = `<div class="empty-state"><i class="bi bi-cart-x"></i><p>Chưa có sản phẩm.</p></div>`;
    document.getElementById('itemCount').textContent = '0';
    updateTotal(); return;
  }
  container.innerHTML = invoiceItems.map(item => `
    <div style="display:grid;grid-template-columns:1fr 110px 140px 130px 36px;gap:8px;align-items:end;padding:10px 16px;border-bottom:1px solid #f3f4f6">
      <div>
        <div style="font-weight:600;font-size:13.5px">${esc(item.name)}</div>
        <div style="font-family:'JetBrains Mono',monospace;font-size:11px;color:#9ca3af">
          ${esc(item.code)} · Tồn: <b style="color:${item.stock<5?'#ef4444':'#10b981'}">${fmt(item.stock)}</b> ${esc(item.unit)}
        </div>
      </div>
      <div>
        <div style="font-size:11px;font-weight:700;color:#6b7280;margin-bottom:3px">SL (${esc(item.unit)})</div>
        <input type="number" data-qty="${esc(item.code)}" class="form-control form-control-sm"
          min="0.01" step="0.01" value="${item.qty}" onchange="setQty('${esc(item.code)}',this.value)" style="text-align:right">
      </div>
      <div>
        <div style="font-size:11px;font-weight:700;color:#6b7280;margin-bottom:3px">Đơn giá (₫)</div>
        <input type="number" class="form-control form-control-sm" min="0"
          value="${item.price_out}" onchange="setPrice('${esc(item.code)}',this.value)" style="text-align:right">
      </div>
      <div>
        <div style="font-size:11px;font-weight:700;color:#6b7280;margin-bottom:3px">Thành tiền</div>
        <div id="lt_${esc(item.code)}" style="font-family:'JetBrains Mono',monospace;font-weight:700;font-size:14px;color:#f59e0b;padding-top:7px">
          ${fmtMoney(item.line_total)}
        </div>
      </div>
      <div style="padding-top:20px">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem('${esc(item.code)}')">
          <i class="bi bi-x"></i>
        </button>
      </div>
    </div>`).join('');
  document.getElementById('itemCount').textContent = invoiceItems.length;
  updateTotal(); syncJson();
}
function updateTotal() {
  const total = invoiceItems.reduce((s,i) => s + i.line_total, 0);
  const el = document.getElementById('invoiceTotal');
  if (el) el.textContent = fmtMoney(total);
  invoiceItems.forEach(item => {
    const lt = document.getElementById('lt_'+item.code);
    if (lt) lt.textContent = fmtMoney(item.line_total);
  });
  syncJson();
}
function syncJson() {
  const el = document.getElementById('invoiceItemsJson');
  if (el) el.value = JSON.stringify(invoiceItems);
}
function invoiceSubmit(e) {
  if (!invoiceItems.length) { e.preventDefault(); alert('⚠️ Vui lòng thêm ít nhất 1 sản phẩm!'); return false; }
  syncJson(); return true;
}

// ── Tiện ích ──────────────────────────────────────────────────
function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function fmt(n) { return new Intl.NumberFormat('vi-VN').format(n||0); }
function fmtMoney(n) { return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(n||0); }
function removeDiacritics(s) {
  return s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d').replace(/Đ/g,'D');
}
renderItems();
</script>
<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
