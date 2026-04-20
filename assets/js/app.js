// ============================================================
// APP.JS — Quản Lý Nhập Xuất Hàng Hóa
// ============================================================

// Clock
function updateClock() {
  const el = document.getElementById('clock');
  if (!el) return;
  const now = new Date();
  const pad = n => String(n).padStart(2,'0');
  el.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())} — ${now.toLocaleDateString('vi-VN',{weekday:'short',day:'2-digit',month:'2-digit',year:'numeric'})}`;
}
setInterval(updateClock, 1000);
updateClock();

// Sidebar toggle
const sidebar        = document.getElementById('sidebar');
const sidebarToggle  = document.getElementById('sidebarToggle');
const mainContent    = document.getElementById('mainContent');

// Create overlay
const overlay = document.createElement('div');
overlay.className = 'sidebar-overlay';
document.body.appendChild(overlay);

if (sidebarToggle) {
  sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
  });
}
overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('open');
});

// Format money VND
function formatMoney(amount) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

// Format number
function formatNum(n) {
  return new Intl.NumberFormat('vi-VN').format(n);
}

// ============================================================
// INVOICE BUILDER
// ============================================================
let invoiceItems = [];
let currentBranch = '';

function initInvoiceBuilder(branch) {
  currentBranch = branch;
  renderInvoiceItems();
}

function addInvoiceItem(product) {
  const existing = invoiceItems.find(i => i.code === product.code);
  if (existing) {
    existing.qty += 1;
    existing.line_total = existing.qty * existing.price_out;
  } else {
    invoiceItems.push({
      code:       product.code,
      name:       product.name,
      unit:       product.unit,
      qty:        1,
      price_out:  parseFloat(product.price_out) || 0,
      line_total: parseFloat(product.price_out) || 0,
      stock:      parseFloat(product.stock) || 0,
    });
  }
  renderInvoiceItems();
  updateProductSearch('');
}

function removeInvoiceItem(code) {
  invoiceItems = invoiceItems.filter(i => i.code !== code);
  renderInvoiceItems();
}

function updateQty(code, qty) {
  const item = invoiceItems.find(i => i.code === code);
  if (!item) return;
  const n = parseFloat(qty) || 0;
  if (n > item.stock) {
    alert(`Tồn kho chỉ còn ${formatNum(item.stock)} ${item.unit}`);
    return;
  }
  item.qty = n;
  item.line_total = item.qty * item.price_out;
  renderInvoiceItems();
}

function updatePrice(code, price) {
  const item = invoiceItems.find(i => i.code === code);
  if (!item) return;
  item.price_out = parseFloat(price) || 0;
  item.line_total = item.qty * item.price_out;
  renderInvoiceItems();
}

function renderInvoiceItems() {
  const container = document.getElementById('invoiceItems');
  const totalEl   = document.getElementById('invoiceTotal');
  const itemsJson = document.getElementById('invoiceItemsJson');
  if (!container) return;

  let total = 0;
  container.innerHTML = '';

  if (invoiceItems.length === 0) {
    container.innerHTML = '<div class="empty-state"><i class="bi bi-cart-x"></i><p>Chưa có sản phẩm. Tìm và thêm sản phẩm bên trên.</p></div>';
  } else {
    invoiceItems.forEach(item => {
      total += item.line_total;
      const row = document.createElement('div');
      row.className = 'invoice-item-row';
      row.innerHTML = `
        <div>
          <div class="fw-600 text-dark" style="font-size:13.5px">${escHtml(item.name)}</div>
          <div class="product-code">${escHtml(item.code)} · Tồn: <b>${formatNum(item.stock)}</b> ${escHtml(item.unit)}</div>
        </div>
        <div style="min-width:80px">
          <label class="form-label" style="font-size:11px">SL (${escHtml(item.unit)})</label>
          <input type="number" class="form-control form-control-sm" min="0.01" step="0.01" value="${item.qty}"
            onchange="updateQty('${item.code}', this.value)">
        </div>
        <div>
          <label class="form-label" style="font-size:11px">Đơn giá (₫)</label>
          <input type="number" class="form-control form-control-sm" min="0" value="${item.price_out}"
            onchange="updatePrice('${item.code}', this.value)">
        </div>
        <div>
          <label class="form-label" style="font-size:11px">Thành tiền</label>
          <div class="money text-amber fw-700" style="font-size:14px;padding-top:8px">${formatMoney(item.line_total)}</div>
        </div>
        <div style="padding-top:20px">
          <button class="btn btn-sm btn-outline-danger" onclick="removeInvoiceItem('${item.code}')"><i class="bi bi-x"></i></button>
        </div>
      `;
      container.appendChild(row);
    });
  }

  if (totalEl) totalEl.textContent = formatMoney(total);
  if (itemsJson) itemsJson.value = JSON.stringify(invoiceItems);
}

// Product search in invoice
let searchTimeout;
function productSearchInput(val) {
  clearTimeout(searchTimeout);
  if (!val || val.length < 1) { document.getElementById('productDropdown').innerHTML = ''; return; }
  searchTimeout = setTimeout(() => {
    fetch(`index.php?ajax=search_products&branch=${encodeURIComponent(currentBranch)}&q=${encodeURIComponent(val)}`)
      .then(r => r.json())
      .then(data => updateProductSearch(val, data))
      .catch(() => {});
  }, 220);
}

function updateProductSearch(val, results) {
  const dd = document.getElementById('productDropdown');
  if (!dd) return;
  if (!results || results.length === 0) { dd.innerHTML = val ? '<div class="product-dropdown-item text-muted">Không tìm thấy sản phẩm</div>' : ''; return; }
  dd.innerHTML = results.slice(0, 10).map(p => `
    <div class="product-dropdown-item" onclick='addInvoiceItem(${JSON.stringify(p)})'>
      <div class="fw-600">${escHtml(p.name)}</div>
      <div class="d-flex gap-3 mt-1">
        <span class="product-code">${escHtml(p.code)}</span>
        <span class="text-muted" style="font-size:12px">${escHtml(p.category_name || '')}</span>
        <span class="text-muted" style="font-size:12px">Tồn: <b>${formatNum(p.stock)}</b> ${escHtml(p.unit)}</span>
        <span class="text-amber fw-700" style="font-size:12px">${formatMoney(p.price_out)}</span>
      </div>
    </div>
  `).join('');
}

// Close dropdown on outside click
document.addEventListener('click', e => {
  const dd = document.getElementById('productDropdown');
  if (dd && !dd.contains(e.target) && e.target.id !== 'productSearch') dd.innerHTML = '';
});

// Submit invoice
function submitInvoice(e) {
  if (invoiceItems.length === 0) { e.preventDefault(); alert('Vui lòng thêm sản phẩm vào hóa đơn'); return false; }
}

// Escape HTML
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Confirm delete
function confirmDelete(msg) {
  return confirm(msg || 'Bạn có chắc chắn muốn xóa?');
}

// Auto dismiss alerts
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(a => {
    const bs = bootstrap.Alert.getOrCreateInstance(a);
    if (bs) bs.close();
  });
}, 5000);
