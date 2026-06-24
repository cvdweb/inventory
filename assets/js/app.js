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
    document.body.classList.toggle('sidebar-open', sidebar.classList.contains('open'));
  });
}
overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('open');
  document.body.classList.remove('sidebar-open');
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
let legacyInvoiceItems = [];
let currentBranch = '';

function initInvoiceBuilder(branch) {
  currentBranch = branch;
  renderInvoiceItems();
}

function addInvoiceItem(product) {
  const existing = legacyInvoiceItems.find(i => i.code === product.code);
  if (existing) {
    existing.qty += 1;
    existing.line_total = existing.qty * existing.price_out;
  } else {
    legacyInvoiceItems.push({
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
  legacyInvoiceItems = legacyInvoiceItems.filter(i => i.code !== code);
  renderInvoiceItems();
}

function updateQty(code, qty) {
  const item = legacyInvoiceItems.find(i => i.code === code);
  if (!item) return;
  const n = parseFloat(qty) || 0;
  if (n > item.stock) {
    showToast(`Tồn kho chỉ còn ${formatNum(item.stock)} ${item.unit}`, 'warning');
    return;
  }
  item.qty = n;
  item.line_total = item.qty * item.price_out;
  renderInvoiceItems();
}

function updatePrice(code, price) {
  const item = legacyInvoiceItems.find(i => i.code === code);
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

  if (legacyInvoiceItems.length === 0) {
    container.innerHTML = '<div class="empty-state"><i class="bi bi-cart-x"></i><p>Chưa có sản phẩm. Tìm và thêm sản phẩm bên trên.</p></div>';
  } else {
    legacyInvoiceItems.forEach(item => {
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
          <input type="number" class="form-control form-control-sm" min="1" step="1" value="${item.qty}"
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
  if (itemsJson) itemsJson.value = JSON.stringify(legacyInvoiceItems);
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
  if (legacyInvoiceItems.length === 0) { e.preventDefault(); showToast('Vui lòng thêm sản phẩm vào hóa đơn', 'warning'); return false; }
}

// Escape HTML
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Vietnamese date inputs: visible dd/mm/yyyy, submitted yyyy-mm-dd.
function isoToVnDate(value) {
  const m = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
}

function vnToIsoDate(value) {
  const m = String(value || '').trim().match(/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/);
  if (!m) return '';
  const d = String(parseInt(m[1], 10)).padStart(2, '0');
  const mo = String(parseInt(m[2], 10)).padStart(2, '0');
  const y = m[3];
  const dt = new Date(`${y}-${mo}-${d}T00:00:00`);
  if (Number.isNaN(dt.getTime())) return '';
  if (dt.getFullYear() !== Number(y) || dt.getMonth() + 1 !== Number(mo) || dt.getDate() !== Number(d)) return '';
  return `${y}-${mo}-${d}`;
}

function initVnDateInputs() {
  document.querySelectorAll('[data-vn-date-target]').forEach(display => {
    const target = document.getElementById(display.dataset.vnDateTarget);
    if (!target) return;
    display.value = isoToVnDate(target.value);
    display.placeholder = 'dd/mm/yyyy';
    display.inputMode = 'numeric';
    display.autocomplete = 'off';

    const sync = () => {
      const iso = vnToIsoDate(display.value);
      target.value = iso;
      display.setCustomValidity(display.value && !iso ? 'Vui lòng nhập ngày theo dạng dd/mm/yyyy' : '');
      target.dispatchEvent(new Event('change', { bubbles: true }));
    };
    display.addEventListener('input', sync);
    display.addEventListener('change', sync);
  });
}

initVnDateInputs();

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

// ============================================================
// PWA INSTALL + SERVICE WORKER
// ============================================================
let deferredInstallPrompt = null;
const installBtn = document.getElementById('pwaInstallBtn');

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('./sw.js').catch(() => {});
  });
}

window.addEventListener('beforeinstallprompt', event => {
  event.preventDefault();
  deferredInstallPrompt = event;
  if (installBtn) installBtn.style.display = '';
});

if (installBtn) {
  installBtn.addEventListener('click', async () => {
    if (!deferredInstallPrompt) {
      showToast('Nếu dùng iPhone/iPad: mở nút Chia sẻ trong Safari rồi chọn "Thêm vào Màn hình chính".', 'info', { duration: 8000, title: 'Cài đặt ứng dụng' });
      return;
    }

    deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice.catch(() => null);
    deferredInstallPrompt = null;
    installBtn.style.display = 'none';
  });
}

window.addEventListener('appinstalled', () => {
  deferredInstallPrompt = null;
  if (installBtn) installBtn.style.display = 'none';
});

// ============================================================
// PWA BROWSER NAVIGATION
// ============================================================
function isPwaStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true;
}

function formFingerprint(form) {
  const values = [];
  new FormData(form).forEach((value, key) => {
    const isFile = typeof File !== 'undefined' && value instanceof File;
    values.push([key, isFile ? `${value.name}:${value.size}` : String(value)]);
  });
  return JSON.stringify(values);
}

function initPwaBrowserNavigation() {
  if (!isPwaStandalone()) return;

  document.documentElement.classList.add('pwa-standalone');

  const backBtn = document.getElementById('pwaNavBack');
  const forwardBtn = document.getElementById('pwaNavForward');
  const reloadBtn = document.getElementById('pwaNavReload');
  const nav = document.getElementById('pwaBrowserNav');
  const desktopSlot = document.getElementById('pwaDesktopNavSlot');
  const desktopMedia = window.matchMedia('(min-width: 769px)');
  const guardedForms = Array.from(document.querySelectorAll('form')).filter(form =>
    String(form.method || '').toLowerCase() === 'post'
  );
  const initialForms = new WeakMap();
  let formSubmitting = false;

  const syncNavigationPosition = () => {
    if (!nav || !desktopSlot) return;
    if (desktopMedia.matches) {
      if (nav.parentElement !== desktopSlot) desktopSlot.appendChild(nav);
    } else if (nav.parentElement !== document.body) {
      document.body.appendChild(nav);
    }
  };

  if (typeof desktopMedia.addEventListener === 'function') {
    desktopMedia.addEventListener('change', syncNavigationPosition);
  } else {
    desktopMedia.addListener(syncNavigationPosition);
  }
  syncNavigationPosition();

  guardedForms.forEach(form => {
    initialForms.set(form, formFingerprint(form));
    form.addEventListener('submit', () => { formSubmitting = true; });
  });

  const hasUnsavedChanges = () => !formSubmitting && guardedForms.some(form =>
    initialForms.get(form) !== formFingerprint(form)
  );

  const allowNavigation = () => !hasUnsavedChanges() || confirm(
    'Bạn có thay đổi chưa lưu. Tiếp tục sẽ làm mất các thay đổi này.'
  );

  backBtn?.addEventListener('click', () => {
    if (!allowNavigation()) return;
    if (window.history.length > 1) {
      window.history.back();
    } else {
      window.location.assign('index.php');
    }
  });

  forwardBtn?.addEventListener('click', () => {
    if (!allowNavigation()) return;
    window.history.forward();
  });

  reloadBtn?.addEventListener('click', () => {
    if (!allowNavigation()) return;
    window.location.reload();
  });
}

initPwaBrowserNavigation();

// ============================================================
// PROGRESSIVE LISTS
// ============================================================
function initProgressiveLists(root = document) {
  root.querySelectorAll('[data-progressive-list]').forEach(list => {
    if (list.dataset.progressiveReady === '1') return;
    list.dataset.progressiveReady = '1';

    const initial = Math.max(1, parseInt(list.dataset.progressiveInitial || '20', 10));
    const batch = Math.max(1, parseInt(list.dataset.progressiveBatch || String(initial), 10));
    const autoLoad = list.dataset.progressiveAuto === 'true';
    const itemLabel = (list.dataset.progressiveLabel || '').trim();
    const items = Array.from(list.children).filter(item => item.hasAttribute('data-progressive-item'));
    const total = items.length;
    if (total <= initial) return;

    const controls = document.getElementById(list.dataset.progressiveControls || '');
    if (!controls) return;
    const pending = items.slice(initial);
    pending.forEach(item => item.remove());

    controls.classList.add('progressive-list-controls');

    const status = document.createElement('span');
    status.className = 'progressive-list-status';
    status.setAttribute('aria-live', 'polite');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-outline-primary progressive-list-button';
    button.innerHTML = '<i class="bi bi-chevron-down me-1"></i>Tải thêm';
    controls.append(status, button);

    let observer = null;
    let loading = false;

    const itemSuffix = itemLabel ? ' ' + itemLabel : '';

    const renderStatus = () => {
      const shown = total - pending.length;
      if (!pending.length && autoLoad) {
        status.innerHTML = '<i class="bi bi-check-circle me-1 text-success"></i>Đã hiển thị toàn bộ ' + total + itemSuffix;
        controls.classList.add('progressive-list-complete');
        button.remove();
        observer?.disconnect();
        return;
      }
      status.textContent = 'Đang hiển thị ' + shown + '/' + total + itemSuffix;
      if (!pending.length) controls.remove();
    };

    const loadNext = () => {
      if (loading || !pending.length) return;
      loading = true;
      if (autoLoad) status.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Đang tải thêm' + itemSuffix + '...';
      window.requestAnimationFrame(() => {
        try {
          const nextItems = pending.splice(0, batch);
          const fragment = document.createDocumentFragment();
          nextItems.forEach(item => fragment.appendChild(item));
          list.appendChild(fragment);
          renderStatus();
        } catch (error) {
          button.hidden = false;
          status.textContent = 'Không thể tự tải thêm. Vui lòng bấm Tải thêm.';
          observer?.disconnect();
        } finally {
          loading = false;
        }
      });
    };

    button.addEventListener('click', loadNext);

    if (autoLoad && 'IntersectionObserver' in window) {
      button.hidden = true;
      controls.classList.add('progressive-list-auto');
      observer = new IntersectionObserver(entries => {
        if (entries.some(entry => entry.isIntersecting)) loadNext();
      }, { root: null, rootMargin: '320px 0px', threshold: 0 });
      observer.observe(controls);
    }

    renderStatus();
  });
}

initProgressiveLists();
