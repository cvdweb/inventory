<?php
$reqBranch  = $_GET['branch'] ?? array_key_first(BRANCHES);
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }
$branchInfo  = BRANCHES[$reqBranch];
$pageTitle   = 'Lập Hóa Đơn — ' . $branchInfo['name'];
$allProducts = getAllProducts($reqBranch);
$catList     = getCategories($reqBranch, true);
include BASE_PATH . '/views/layouts/header.php';
?>

<style>
/* ── Layout POS sticky ─────────────────────────────────────── */
.pos-wrap {
  display: flex;
  flex-direction: column;
  gap: 12px;
  height: calc(100vh - 130px); /* trừ topbar */
}

/* Accordion thông tin khách + giao hàng */
.pos-info-bar {
  flex-shrink: 0;
}

/* Vùng làm việc chính: tìm SP trái + DS hóa đơn phải */
.pos-main {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 12px;
  flex: 1;
  min-height: 0; /* quan trọng để overflow hoạt động */
}

/* Cột trái: tìm sản phẩm — sticky, scroll độc lập */
.pos-left {
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  min-height: 0;
}
.pos-left-search {
  flex-shrink: 0;
  padding: 12px;
  border-bottom: 1px solid var(--border);
  background: #fff;
}
.pos-left-cats {
  flex: 1;
  overflow-y: auto;
  padding: 10px;
}

/* Cột phải: DS hóa đơn — scroll độc lập */
.pos-right {
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  min-height: 0;
}
.pos-right-header {
  flex-shrink: 0;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 14px;
}
.pos-right-items {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}
.pos-right-footer {
  flex-shrink: 0;
  border-top: 2px solid var(--border);
  background: var(--bg-main);
}

/* Item row trong DS hóa đơn */
.inv-row {
  display: grid;
  grid-template-columns: 1fr 100px 130px 120px 36px;
  gap: 8px;
  align-items: center;
  padding: 9px 14px;
  border-bottom: 1px solid #f3f4f6;
  transition: background .1s;
}
.inv-row:hover { background: #fafafa; }

/* Flash khi thêm sản phẩm mới */
@keyframes flashRow {
  0%   { background: #fef3c7; }
  100% { background: transparent; }
}
.inv-row.flash { animation: flashRow .5s ease; }

/* Product item trong danh sách nhóm */
.cat-item {
  padding: 9px 10px;
  cursor: pointer;
  border-bottom: 1px solid #f3f4f6;
  border-radius: 6px;
  margin-bottom: 2px;
  transition: background .1s;
}
.cat-item:hover { background: #fffbeb; }
.cat-item:last-child { border-bottom: none; }

/* Accordion */
.acc-toggle {
  cursor: pointer;
  user-select: none;
}
.acc-toggle .acc-icon {
  transition: transform .2s;
}
.acc-toggle.collapsed .acc-icon {
  transform: rotate(-90deg);
}

/* Responsive */
@media (max-width: 900px) {
  .pos-main { grid-template-columns: 1fr; }
  .pos-left  { max-height: 40vh; }
  .pos-wrap  { height: auto; }
}
</style>

<form method="POST" action="index.php?page=invoice&branch=<?= $reqBranch ?>"
      onsubmit="return invoiceSubmit(event)" id="invoiceForm">
  <input type="hidden" name="action"  value="create_invoice">
  <input type="hidden" name="branch"  value="<?= $reqBranch ?>">
  <input type="hidden" name="items"   id="invoiceItemsJson">

<div class="pos-wrap">

  <!-- ══ ACCORDION: Thông tin khách + giao hàng ══════════════ -->
  <div class="pos-info-bar">
    <div class="card">
      <!-- Tiêu đề accordion -->
      <div class="card-header acc-toggle d-flex align-items-center gap-2 py-2"
           onclick="toggleAccordion()" id="accToggle">
        <i class="bi bi-receipt me-1 text-<?= $branchInfo['color'] ?>"></i>
        <span class="fw-700" style="font-size:13.5px">
          Lập Hóa Đơn — <?= htmlspecialchars($branchInfo['name']) ?>
        </span>
        <!-- Summary hiện khi đóng -->
        <div id="accSummary" class="d-flex gap-3 ms-3" style="font-size:12.5px;color:#6b7280">
          <span id="summCustomer"><i class="bi bi-person me-1"></i>Khách lẻ</span>
          <span id="summPayment"><i class="bi bi-cash me-1"></i>Tiền mặt</span>
          <span id="summDelivery" style="display:none"><i class="bi bi-truck me-1 text-warning"></i><span id="summDeliveryDate"></span></span>
        </div>
        <i class="bi bi-chevron-down acc-icon ms-auto" style="color:#9ca3af"></i>
      </div>

      <!-- Nội dung accordion -->
      <div id="accBody" class="card-body py-2" style="display:none">
        <div class="row g-2">
          <!-- Khách hàng -->
          <div class="col-md-3">
            <label class="form-label" style="font-size:11.5px">Tên khách hàng</label>
            <input type="text" name="customer" id="inpCustomer" class="form-control form-control-sm"
              value="Khách lẻ" oninput="updateSummary()">
          </div>
          <div class="col-md-2">
            <label class="form-label" style="font-size:11.5px">Số điện thoại</label>
            <input type="tel" name="phone" class="form-control form-control-sm" placeholder="0xxx...">
          </div>
          <div class="col-md-2">
            <label class="form-label" style="font-size:11.5px">Thanh toán</label>
            <select name="payment" id="inpPayment" class="form-select form-select-sm" onchange="updateSummary()">
              <option value="cash">Tiền mặt</option>
              <option value="transfer">Chuyển khoản</option>
              <option value="cod">COD</option>
              <option value="credit">Công nợ</option>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label" style="font-size:11.5px">Ghi chú hóa đơn</label>
            <input type="text" name="note" class="form-control form-control-sm" placeholder="Ghi chú...">
          </div>

          <!-- Giao hàng -->
          <div class="col-md-2">
            <label class="form-label" style="font-size:11.5px">
              <i class="bi bi-truck me-1 text-warning"></i>Ngày giao hàng
            </label>
            <input type="date" name="delivery_date" id="inpDeliveryDate"
              class="form-control form-control-sm" min="<?= date('Y-m-d') ?>"
              onchange="onDeliveryDateChange(this.value)">
          </div>
          <div class="col-md-3">
            <label class="form-label" style="font-size:11.5px">Địa chỉ giao hàng</label>
            <input type="text" name="address" class="form-control form-control-sm"
              placeholder="Để trống = lấy tại quầy">
          </div>
          <div class="col-md-4">
            <label class="form-label" style="font-size:11.5px">Ghi chú cho tài xế</label>
            <input type="text" name="delivery_note" class="form-control form-control-sm"
              placeholder="VD: Gọi trước 30 phút, giao tầng 2...">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-outline-secondary w-100"
              onclick="toggleAccordion()">
              <i class="bi bi-chevron-up me-1"></i>Thu gọn
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ VÙNG CHÍNH: Tìm SP (trái) + DS Hóa đơn (phải) ══════ -->
  <div class="pos-main">

    <!-- ── Cột trái: Tìm sản phẩm ──────────────────────────── -->
    <div class="pos-left">

      <!-- Ô tìm kiếm — sticky top -->
      <div class="pos-left-search">
        <div style="font-weight:700;font-size:13px;margin-bottom:8px;color:#374151">
          <i class="bi bi-search me-2 text-warning"></i>Tìm Sản Phẩm
          <span style="font-size:11px;font-weight:400;color:#9ca3af;float:right"><?= count($allProducts) ?> SP</span>
        </div>
        <div style="position:relative">
          <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;font-size:14px">
            <i class="bi bi-search"></i>
          </span>
          <input type="text" id="productSearch" class="form-control form-control-sm"
            style="padding-left:32px;font-size:13px"
            placeholder="Nhập mã hoặc tên..."
            autocomplete="off"
            oninput="doSearch(this.value)"
            onfocus="doSearch(this.value)">
          <!-- Dropdown -->
          <div id="productDropdown" style="
            display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;
            background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;
            box-shadow:0 8px 24px rgba(0,0,0,.14);z-index:9999;
            max-height:260px;overflow-y:auto"></div>
        </div>
      </div>

      <!-- Danh sách nhóm hàng — scroll -->
      <div class="pos-left-cats">
        <?php foreach ($catList as $catInfo):
          $catKey   = $catInfo['key'];
          $catProds = array_values(array_filter($allProducts, fn($p) => ($p['category_key'] ?? '') === $catKey));
          if (empty($catProds)) continue;
        ?>
        <div class="mb-2">
          <button type="button"
            class="btn btn-sm w-100 text-start d-flex justify-content-between align-items-center"
            style="background:#f9fafb;border:1px solid #e5e7eb;font-weight:700;font-size:12.5px;padding:6px 10px"
            onclick="toggleCat('cat_<?= $catKey ?>', this)">
            <span><i class="bi <?= htmlspecialchars($catInfo['icon'] ?? 'bi-box') ?> me-2" style="color:#8b5cf6"></i><?= htmlspecialchars($catInfo['name']) ?></span>
            <span class="d-flex align-items-center gap-1">
              <span class="badge bg-secondary" style="font-size:10px"><?= count($catProds) ?></span>
              <i class="bi bi-chevron-down" style="font-size:11px;color:#9ca3af;transition:transform .2s" id="chev_<?= $catKey ?>"></i>
            </span>
          </button>
          <div id="cat_<?= $catKey ?>" style="display:none">
            <?php foreach ($catProds as $p):
              $low = ($p['stock'] ?? 0) < ($p['min_stock'] ?? 5);
              $pJson = json_encode([
                'code'      => $p['code'],
                'name'      => $p['name'],
                'unit'      => $p['unit'],
                'price_out' => (float)($p['price_out'] ?? 0),
                'stock'     => (float)($p['stock'] ?? 0),
              ], JSON_UNESCAPED_UNICODE);
            ?>
            <div class="cat-item" onclick='addItem(<?= $pJson ?>)'>
              <div style="font-weight:600;font-size:13px;color:#111827"><?= htmlspecialchars($p['name']) ?></div>
              <div class="d-flex justify-content-between mt-1">
                <span style="font-family:'JetBrains Mono',monospace;font-size:10.5px;color:#9ca3af"><?= htmlspecialchars($p['code']) ?></span>
                <span style="font-size:11px;font-weight:700;color:<?= $low ? '#ef4444' : '#10b981' ?>">
                  <?= number_format($p['stock'] ?? 0, 2, ',', '.') ?> <?= htmlspecialchars($p['unit']) ?>
                  <?= $low ? '⚠' : '' ?>
                </span>
                <span style="font-size:11px;font-weight:700;color:#f59e0b"><?= number_format($p['price_out'] ?? 0, 0, ',', '.') ?>₫</span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Cột phải: Danh sách hóa đơn ────────────────────── -->
    <div class="pos-right">

      <!-- Header DS -->
      <div class="pos-right-header">
        <i class="bi bi-list-ul" style="color:#f59e0b"></i>
        Danh Sách Hàng Hóa
        <span id="itemCount" class="badge bg-secondary" style="font-weight:600;font-size:11px">0</span>
        <div class="ms-auto d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-danger"
            onclick="if(invoiceItems.length&&confirm('Xóa toàn bộ hóa đơn?')){invoiceItems=[];renderItems()}">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>

      <!-- Header cột (cố định) -->
      <div style="display:grid;grid-template-columns:1fr 100px 130px 120px 36px;gap:8px;
        padding:6px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;flex-shrink:0">
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Sản phẩm</div>
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Số lượng</div>
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Đơn giá (₫)</div>
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Thành tiền</div>
        <div></div>
      </div>

      <!-- Danh sách items — scroll độc lập -->
      <div class="pos-right-items" id="invoiceItems">
        <div class="empty-state" style="padding:40px 20px">
          <i class="bi bi-cart-x" style="font-size:40px;opacity:.25;display:block;margin-bottom:10px"></i>
          <p style="color:#9ca3af;font-size:13px">Chưa có sản phẩm.<br>Tìm hoặc chọn nhóm hàng bên trái.</p>
        </div>
      </div>

      <!-- Footer: tổng + nút xuất -->
      <div class="pos-right-footer">
        <div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center">
          <div style="font-size:12.5px;color:#6b7280">
            <span id="itemCountFt">0</span> sản phẩm
          </div>
          <div class="d-flex align-items-center gap-3">
            <div class="text-end">
              <div style="font-size:11px;color:#9ca3af">TỔNG CỘNG</div>
              <div id="invoiceTotal" style="font-family:'JetBrains Mono',monospace;font-size:22px;font-weight:800;color:#f59e0b">0 ₫</div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg px-4" style="white-space:nowrap">
              <i class="bi bi-check2-circle me-2"></i>Xuất Hóa Đơn
            </button>
          </div>
        </div>
      </div>

    </div>
  </div><!-- .pos-main -->
</div><!-- .pos-wrap -->
</form>

<script>
const PRODUCTS = <?= json_encode(array_values($allProducts), JSON_UNESCAPED_UNICODE) ?>;
let invoiceItems = [];
let accOpen = false;

// ── Accordion ─────────────────────────────────────────────────
function toggleAccordion() {
  const body = document.getElementById('accBody');
  const tog  = document.getElementById('accToggle');
  accOpen = !accOpen;
  body.style.display = accOpen ? 'block' : 'none';
  tog.classList.toggle('collapsed', !accOpen);
  document.getElementById('accSummary').style.display = accOpen ? 'none' : '';
}

function updateSummary() {
  const c = document.getElementById('inpCustomer')?.value || 'Khách lẻ';
  const p = document.getElementById('inpPayment')?.value || 'cash';
  const payLabels = {cash:'Tiền mặt',transfer:'Chuyển khoản',cod:'COD',credit:'Công nợ'};
  document.getElementById('summCustomer').innerHTML = `<i class="bi bi-person me-1"></i>${esc(c)}`;
  document.getElementById('summPayment').innerHTML  = `<i class="bi bi-cash me-1"></i>${payLabels[p]||p}`;
}

function onDeliveryDateChange(val) {
  const el  = document.getElementById('summDelivery');
  const txt = document.getElementById('summDeliveryDate');
  if (val) {
    const d = new Date(val + 'T00:00:00');
    txt.textContent = 'Giao ' + d.toLocaleDateString('vi-VN',{day:'2-digit',month:'2-digit'});
    el.style.display = '';
  } else {
    el.style.display = 'none';
  }
}

// ── Toggle nhóm hàng ──────────────────────────────────────────
function toggleCat(id, btn) {
  const el   = document.getElementById(id);
  const chev = document.getElementById('chev_' + id.replace('cat_',''));
  if (!el) return;
  const open = el.style.display === 'none';
  el.style.display = open ? 'block' : 'none';
  if (chev) chev.style.transform = open ? 'rotate(180deg)' : 'rotate(0)';
}

// ── Tìm kiếm ─────────────────────────────────────────────────
function doSearch(val) {
  const dd = document.getElementById('productDropdown');
  val = val.trim();
  if (!val) { dd.style.display = 'none'; return; }
  const kw = rmv(val.toLowerCase());
  const results = PRODUCTS.filter(p =>
    rmv((p.code||'').toLowerCase()).includes(kw) ||
    rmv((p.name||'').toLowerCase()).includes(kw)
  ).slice(0,12);
  if (!results.length) {
    dd.innerHTML = '<div style="padding:12px 14px;font-size:13px;color:#9ca3af">Không tìm thấy sản phẩm</div>';
    dd.style.display = 'block'; return;
  }
  dd.innerHTML = results.map(p => {
    const low = p.stock < (p.min_stock||5);
    return `<div onclick='addItem(${JSON.stringify(p)})'
      style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f3f4f6;transition:background .1s"
      onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background=''">
      <div style="font-weight:600;font-size:13.5px;color:#111">${esc(p.name)}</div>
      <div style="display:flex;gap:10px;margin-top:4px;flex-wrap:wrap;align-items:center">
        <span style="font-family:'JetBrains Mono',monospace;font-size:11px;color:#9ca3af">${esc(p.code)}</span>
        <span style="font-size:11px;font-weight:700;color:${low?'#ef4444':'#10b981'}">
          Tồn: ${fmt(p.stock)} ${esc(p.unit)}${low?' ⚠️':''}
        </span>
        <span style="font-size:12px;font-weight:800;color:#f59e0b;margin-left:auto">${fmtM(p.price_out)}</span>
      </div>
    </div>`;
  }).join('');
  dd.style.display = 'block';
}
document.addEventListener('click', e => {
  const dd = document.getElementById('productDropdown');
  if (!dd.contains(e.target) && e.target.id !== 'productSearch') dd.style.display = 'none';
});

// ── Thêm sản phẩm ─────────────────────────────────────────────
function addItem(p) {
  document.getElementById('productDropdown').style.display = 'none';
  document.getElementById('productSearch').value = '';

  const ex = invoiceItems.find(i => i.code === p.code);
  if (ex) {
    ex.qty += 1;
    ex.line_total = ex.qty * ex.price_out;
    renderItems();
    flashRow(p.code);
  } else {
    invoiceItems.push({
      code: p.code, name: p.name, unit: p.unit,
      qty: 1,
      price_out: parseFloat(p.price_out)||0,
      line_total: parseFloat(p.price_out)||0,
      stock: parseFloat(p.stock)||0,
    });
    renderItems();
    flashRow(p.code);
  }
}

function flashRow(code) {
  // Scroll xuống cuối DS để thấy item vừa thêm
  const container = document.getElementById('invoiceItems');
  if (container) container.scrollTop = container.scrollHeight;
  // Flash highlight
  setTimeout(() => {
    const row = document.getElementById('row_' + code);
    if (row) { row.classList.add('flash'); setTimeout(()=>row.classList.remove('flash'),600); }
  }, 30);
}

// ── Xóa item ─────────────────────────────────────────────────
function removeItem(code) {
  invoiceItems = invoiceItems.filter(i => i.code !== code);
  renderItems();
}

// ── Cập nhật số lượng ─────────────────────────────────────────
function setQty(code, val) {
  const item = invoiceItems.find(i => i.code === code);
  if (!item) return;
  const n = parseFloat(val)||0;
  if (n <= 0) { removeItem(code); return; }
  if (n > item.stock) {
    alert(`Tồn kho chỉ còn ${fmt(item.stock)} ${item.unit}`);
    const inp = document.querySelector(`[data-qty="${code}"]`);
    if (inp) inp.value = item.qty; return;
  }
  item.qty = n; item.line_total = item.qty * item.price_out;
  // Cập nhật thành tiền không re-render toàn bộ
  const lt = document.getElementById('lt_'+code);
  if (lt) lt.textContent = fmtM(item.line_total);
  updateTotals(); syncJson();
}

// ── Cập nhật đơn giá ─────────────────────────────────────────
function setPrice(code, val) {
  const item = invoiceItems.find(i => i.code === code);
  if (!item) return;
  item.price_out  = parseFloat(val)||0;
  item.line_total = item.qty * item.price_out;
  const lt = document.getElementById('lt_'+code);
  if (lt) lt.textContent = fmtM(item.line_total);
  updateTotals(); syncJson();
}

// ── Render toàn bộ DS ─────────────────────────────────────────
function renderItems() {
  const container = document.getElementById('invoiceItems');
  if (!container) return;
  const cnt = invoiceItems.length;
  document.getElementById('itemCount').textContent   = cnt;
  document.getElementById('itemCountFt').textContent = cnt;

  if (!cnt) {
    container.innerHTML = `<div class="empty-state" style="padding:40px 20px">
      <i class="bi bi-cart-x" style="font-size:40px;opacity:.25;display:block;margin-bottom:10px"></i>
      <p style="color:#9ca3af;font-size:13px">Chưa có sản phẩm.<br>Tìm hoặc chọn nhóm hàng bên trái.</p>
    </div>`;
    updateTotals(); return;
  }

  container.innerHTML = invoiceItems.map(item => `
    <div class="inv-row" id="row_${esc(item.code)}">
      <div>
        <div style="font-weight:600;font-size:13.5px;color:#111;line-height:1.3">${esc(item.name)}</div>
        <div style="font-family:'JetBrains Mono',monospace;font-size:10.5px;color:#9ca3af">
          ${esc(item.code)}
          <span style="margin-left:6px;color:${item.stock<(item.min_stock||5)?'#ef4444':'#10b981'};font-weight:700">
            Tồn: ${fmt(item.stock)} ${esc(item.unit)}
          </span>
        </div>
      </div>
      <div>
        <input type="number" data-qty="${esc(item.code)}"
          class="form-control form-control-sm" style="text-align:right;font-weight:700"
          min="0.01" step="1" value="${item.qty}"
          onchange="setQty('${esc(item.code)}',this.value)">
      </div>
      <div>
        <input type="number" class="form-control form-control-sm" style="text-align:right"
          min="0" value="${item.price_out}"
          onchange="setPrice('${esc(item.code)}',this.value)">
      </div>
      <div id="lt_${esc(item.code)}"
        style="font-family:'JetBrains Mono',monospace;font-weight:800;font-size:14px;color:#f59e0b;text-align:right">
        ${fmtM(item.line_total)}
      </div>
      <div style="text-align:center">
        <button type="button" class="btn btn-sm btn-outline-danger"
          onclick="removeItem('${esc(item.code)}')">
          <i class="bi bi-x"></i>
        </button>
      </div>
    </div>`).join('');

  updateTotals(); syncJson();
}

function updateTotals() {
  const total = invoiceItems.reduce((s,i) => s + i.line_total, 0);
  const el = document.getElementById('invoiceTotal');
  if (el) el.textContent = fmtM(total);
  syncJson();
}
function syncJson() {
  const el = document.getElementById('invoiceItemsJson');
  if (el) el.value = JSON.stringify(invoiceItems);
}
function invoiceSubmit(e) {
  if (!invoiceItems.length) {
    e.preventDefault();
    alert('⚠️ Vui lòng thêm ít nhất 1 sản phẩm vào hóa đơn!');
    return false;
  }
  syncJson(); return true;
}

// ── Tiện ích ──────────────────────────────────────────────────
function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function fmt(n) { return new Intl.NumberFormat('vi-VN').format(n||0); }
function fmtM(n) { return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(n||0); }
function rmv(s) { return s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d'); }

// Init
renderItems();
updateSummary();
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
