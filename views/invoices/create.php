<?php
$reqBranch  = $_GET['branch'] ?? firstAccessibleBranchId();
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }
$branchInfo  = getBranchInfo($reqBranch);
$pageTitle   = 'Lập Hóa Đơn — ' . $branchInfo['name'];
$allProducts = getAllProducts($reqBranch);
$catList     = getCategories($reqBranch, true);
include BASE_PATH . '/views/layouts/header.php';
?>

<style>
/* â”€â”€ Layout POS sticky â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.pos-wrap {
  display: flex;
  flex-direction: column;
  gap: 12px;
  height: calc(100vh - 112px); /* trừ topbar + padding */
}

/* Accordion thông tin khách + giao hàng */
.pos-info-bar {
  flex-shrink: 0;
}

/* Vùng làm việc chính: tìm SP trái + DS hóa đơn phải */
.pos-main {
  display: grid;
  grid-template-columns: clamp(400px, 34vw, 560px) 1fr;
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
  padding: 14px;
  border-bottom: 1px solid var(--border);
  background: #fff;
}
.pos-left-search .form-control {
  height: 46px;
  font-size: 15px;
  border-radius: 10px;
}
.pos-left-title {
  font-weight: 800;
  font-size: 14px;
  margin-bottom: 10px;
  color: #111827;
}
.pos-left-title-count {
  font-size: 11px;
  font-weight: 500;
  color: #9ca3af;
  float: right;
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

/* Item trong DS hóa đơn — thẻ 2 dòng: SP trên, điều khiển dưới */
.inv-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  grid-template-areas:
    "product remove"
    "controls controls";
  gap: 4px 12px;
  align-items: center;
  padding: 12px 14px;
  border-bottom: 1px solid #f3f4f6;
  transition: background .1s;
}
.inv-row:hover { background: #fafafa; }
.inv-product { grid-area: product; min-width: 0; }
.inv-remove { grid-area: remove; }
.inv-controls { grid-area: controls; }

.inv-name {
  font-weight: 700;
  font-size: 14.5px;
  color: #111827;
  line-height: 1.3;
  word-break: break-word;
}
.inv-color {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 7px;
  border-radius: 4px;
  background: #f3e8ff;
  color: #7c3aed;
  font-size: 11px;
  font-weight: 700;
  vertical-align: 1px;
}
.inv-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 3px 14px;
  align-items: center;
  margin-top: 4px;
  font-size: 11px;
}
.inv-code { font-family: 'JetBrains Mono', monospace; color: #9ca3af; }
.inv-stock { font-weight: 700; color: #10b981; }
.inv-stock.low { color: #ef4444; }

.inv-controls {
  display: flex;
  flex-wrap: nowrap;
  align-items: flex-end;
  gap: 10px 14px;
  margin-top: 9px;
}
.inv-qty { flex: 0 0 auto; }
.inv-price { flex: 1 1 200px; min-width: 0; max-width: 360px; }
.inv-qty, .inv-price {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  min-width: 0;
}
.inv-label {
  display: block;
  margin-bottom: 5px;
  font-size: 10.5px;
  color: #6b7280;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .4px;
}

.inv-remove {
  width: 36px;
  height: 36px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e5e7eb;
  border-radius: 9px;
  background: #fff;
  color: #9ca3af;
  transition: background .15s, border-color .15s, color .15s;
}
.inv-remove:hover { background: #fef2f2; border-color: #fecaca; color: #ef4444; }
.inv-remove:focus-visible { box-shadow: 0 0 0 2px rgba(239,68,68,.25); }

.qty-control {
  display: grid;
  grid-template-columns: 36px minmax(56px, 1fr) 36px;
  gap: 4px;
  align-items: center;
}
.qty-step {
  min-width: 36px;
  height: 38px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
}
.inv-row .form-control {
  min-height: 38px;
}

/* Flash khi thêm sản phẩm mới */
@keyframes flashRow {
  0%   { background: #fef3c7; }
  100% { background: transparent; }
}
.inv-row.flash { animation: flashRow .5s ease; }

/* Product item trong danh sách nhóm */
.cat-item {
  padding: 14px 14px;
  cursor: pointer;
  border: 1px solid #edf0f3;
  border-radius: 10px;
  margin-bottom: 8px;
  transition: background .1s, border-color .1s;
  background: #fff;
}
.cat-item:hover { background: #fffbeb; border-color: #fcd34d; }
.cat-item:last-child { margin-bottom: 0; }
.cat-toggle {
  min-height: 46px;
}
.cat-toggle:hover { border-color: #fcd34d !important; }
.cat-toggle:focus-visible { box-shadow: 0 0 0 2px rgba(245,158,11,.25); }
.cat-toggle-icon { color: #8b5cf6; }
.cat-item-name {
  font-weight: 700;
  font-size: 15px;
  color: #111827;
  line-height: 1.35;
}
.cat-item-stock {
  font-size: 12.5px;
  font-weight: 800;
}
.cat-item-price {
  font-size: 13px;
  font-weight: 800;
  color: #f59e0b;
}

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
.invoice-info-head {
  display: grid;
  grid-template-columns: minmax(180px, 240px) 1fr auto;
  gap: 14px;
  align-items: start;
  width: 100%;
}
.invoice-title-block {
  min-width: 0;
}
.invoice-title-main {
  font-size: 14px;
  font-weight: 800;
  color: #111827;
  line-height: 1.2;
}
.invoice-title-sub {
  font-size: 11.5px;
  color: #6b7280;
  margin-top: 2px;
}
.invoice-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(120px, 1fr));
  gap: 8px 12px;
  min-width: 0;
}
.invoice-chip {
  display: grid;
  grid-template-columns: 18px 1fr;
  gap: 8px;
  align-items: start;
  min-width: 0;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: #111827;
}
.invoice-chip i {
  color: #f59e0b;
  font-size: 14px;
  flex-shrink: 0;
}
.invoice-chip-label {
  font-size: 10.5px;
  line-height: 1.1;
  color: #6b7280;
  font-weight: 800;
  text-transform: uppercase;
}
.invoice-chip-value {
  font-size: 12.5px;
  line-height: 1.2;
  font-weight: 800;
  color: #111827;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.invoice-info-notice {
  grid-column: 1 / -1;
  display: none;
  align-items: center;
  gap: 6px;
  padding: 7px 9px;
  border-radius: 8px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
  font-size: 12px;
  font-weight: 700;
}
.invoice-edit-hint {
  min-height: 36px;
  white-space: nowrap;
}
.invoice-info-actions { display:flex; gap:6px; }

/* Responsive */
@media (max-width: 1200px) {
  .pos-main { grid-template-columns: 1fr; }
  .pos-left  { max-height: 44vh; }
  .pos-wrap  { height: auto; }
}

@media (max-width: 768px) {
  body { background: #f6f7f9; }
  .content-body {
    padding: 8px;
    padding-bottom: calc(118px + env(safe-area-inset-bottom));
  }
  .pos-wrap {
    height: auto;
    gap: 8px;
  }
  .pos-info-bar .card {
    border-radius: 8px;
  }
  .pos-info-bar .card-header {
    min-height: 46px;
    padding: 8px 10px !important;
  }
  .invoice-info-head {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .invoice-title-block {
    grid-column: auto;
  }
  .invoice-summary {
    grid-column: auto;
    grid-template-columns: 1fr;
    gap: 6px;
    padding: 8px 0 2px;
    border-top: 1px solid #f3f4f6;
  }
  .invoice-chip {
    grid-template-columns: 20px 1fr;
    padding: 2px 0;
  }
  .invoice-chip-value {
    max-width: 100%;
    font-size: 12px;
  }
  .invoice-info-notice {
    font-size: 11.5px;
  }
  .invoice-edit-hint {
    grid-column: auto;
    grid-row: auto;
    width: 100%;
    min-height: 40px;
    padding: 0 10px !important;
  }
  .invoice-info-actions { grid-column:auto; width:100%; }
  .invoice-info-actions .invoice-edit-hint { flex:1; width:auto; }
  #accBody .row > [class*="col-"] {
    width: 100%;
  }
  #accBody .form-control,
  #accBody .form-select {
    min-height: 42px;
    font-size: 16px;
  }

  .pos-main {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .pos-left {
    position: sticky;
    top: calc(56px + env(safe-area-inset-top));
    z-index: 30;
    border-radius: 8px;
    max-height: none;
    box-shadow: 0 8px 18px rgba(17,24,39,.08);
  }
  .pos-left-search {
    padding: 10px;
  }
  .pos-left-search .form-control {
    height: 46px;
    font-size: 16px !important;
    border-radius: 10px;
  }
  #productDropdown {
    max-height: min(52vh, 430px) !important;
    border-radius: 10px !important;
  }
  .pos-left-cats {
    max-height: 30vh;
    padding: 8px;
  }
  .pos-left-cats button {
    min-height: 46px;
  }
  .cat-item {
    margin-bottom: 6px;
    background: #fff;
    border: 1px solid #edf0f3;
  }

  .pos-right {
    border: 0;
    background: transparent;
    overflow: visible;
  }
  .pos-right-header {
    background: transparent;
    border: 0;
    padding: 8px 2px;
  }
  .pos-table-head {
    display: none !important;
  }
  .pos-right-items {
    overflow: visible;
  }
  .inv-row {
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    padding: 13px 14px;
    margin-bottom: 8px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(17,24,39,.04);
  }
  .inv-product {
    grid-column: 1 / -1;
  }
  .inv-name { font-size: 15px; }
  .inv-qty,
  .inv-price {
    min-width: 0;
  }
  .inv-remove {
    width: 40px;
    height: 40px;
  }
  .inv-controls {
    flex-wrap: nowrap;
    gap: 10px;
    margin-top: 10px;
  }
  .inv-qty { flex: 1 1 170px; }
  .inv-price { flex: 1 1 140px; }
  .qty-control {
    grid-template-columns: 42px minmax(0, 1fr) 42px;
  }
  .qty-step {
    height: 44px;
    border-radius: 10px;
    font-size: 18px;
  }
  .inv-row .form-control {
    min-height: 44px;
    font-size: 16px;
    border-radius: 10px;
  }

  .pos-right-footer {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 60;
    border-top: 1px solid #d1d5db;
    background: rgba(255,255,255,.98);
    box-shadow: 0 -10px 25px rgba(17,24,39,.12);
    padding-bottom: env(safe-area-inset-bottom);
  }
  .pos-footer-inner {
    padding: 10px 12px !important;
    gap: 8px !important;
  }
  .pos-footer-count {
    display: none;
  }
  .pos-footer-actions {
    width: 100%;
    display: grid !important;
    grid-template-columns: 1fr 156px;
    gap: 10px !important;
  }
  .pos-total-box {
    text-align: left !important;
    line-height: 1.25 !important;
  }
  #invoiceTotal {
    font-size: 20px !important;
  }
  .submit-invoice {
    width: 100%;
    min-height: 52px;
    padding: 0 12px !important;
    border-radius: 12px;
    font-size: 14px;
  }
}

@media (max-width: 390px) {
  .pos-footer-actions {
    grid-template-columns: 1fr 140px;
  }
  #invoiceTotal {
    font-size: 18px !important;
  }
  .submit-invoice {
    font-size: 13px;
  }
}
/* Mobile POS: uu tien chon hang, xem don o mot man hinh rieng. */
#accBody .form-label {
  display: flex;
  align-items: flex-end;
  min-height: 18px;
  margin-bottom: 5px;
}
#accBody .form-control,
#accBody .form-select {
  min-height: 34px;
}
.mobile-cart-bar,
.mobile-order-back,
.mobile-invoice-info-toggle { display: none; }

@media (max-width: 768px) {
  body.mobile-order-open { overflow: hidden; }
  .content-body { padding-bottom: calc(88px + var(--pwa-browser-nav-height, 0px)); }
  html.pwa-standalone .main-content { padding-bottom: 0; }
  .pos-wrap, .pos-main { gap: 0; }
  .pos-info-bar { display: none; }
  .pos-left {
    position: static;
    overflow: visible;
    border: 0;
    box-shadow: none;
    background: transparent;
  }
  .pos-left-search {
    position: sticky;
    top: calc(56px + env(safe-area-inset-top));
    z-index: 35;
    margin: 0 -8px 8px;
    padding: 10px 12px;
    border: 0;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 5px 14px rgba(17,24,39,.08);
  }
  .pos-left-cats { max-height: none; overflow: visible; padding: 0; }
  .pos-left-cats .cat-toggle { min-height: 52px; border-radius: 10px; font-size: 14.5px; }
  .cat-item { min-height: 72px; padding: 15px 14px; }
  .cat-item-name { font-size: 15.5px; }
  .cat-item-stock { font-size: 13px; }
  .cat-item-price { font-size: 13.5px; }
  .pos-left-cats { padding: 8px 0 0; }
  .pos-left-cats .mb-2 { margin-bottom: 10px; }
  .pos-right {
    display: none;
    position: fixed;
    inset: 0 0 var(--pwa-browser-nav-height, 0px);
    z-index: 1080;
    min-height: 0;
    height: auto;
    border: 0;
    border-radius: 0;
    background: #f6f7f9;
    overflow: hidden;
  }
  body.mobile-order-open .pos-right { display: flex; }
  .pos-right-header {
    min-height: 58px;
    padding: 8px 12px;
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
    font-size: 16px;
  }
  .mobile-order-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    padding: 0;
    margin-right: 2px;
    border: 0;
    border-radius: 8px;
    background: #f3f4f6;
    color: #111827;
  }
  .mobile-invoice-info-toggle {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr) 24px;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 62px;
    padding: 9px 14px;
    border: 0;
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
    color: #111827;
    text-align: left;
  }
  .mobile-info-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: #fffbeb;
    color: #f59e0b;
    font-size: 18px;
  }
  .mobile-info-title { display: block; font-size: 13px; font-weight: 800; }
  .mobile-info-summary {
    display: block;
    margin-top: 2px;
    overflow: hidden;
    color: #6b7280;
    font-size: 11.5px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .mobile-info-chevron { transition: transform .2s; }
  .mobile-invoice-info-toggle[aria-expanded="true"] .mobile-info-chevron { transform: rotate(180deg); }
  .mobile-invoice-info-slot {
    flex-shrink: 0;
    max-height: 52dvh;
    overflow-y: auto;
    background: #fff;
  }
  .mobile-invoice-info-slot .pos-info-bar { display: block; }
  .mobile-invoice-info-slot .pos-info-bar .card {
    border: 0;
    border-bottom: 1px solid #e5e7eb;
    border-radius: 0;
    box-shadow: none;
  }
  .mobile-invoice-info-slot .pos-info-bar .card-header { display: none; }
  .mobile-invoice-info-slot #accBody { padding: 12px !important; }
  .mobile-invoice-info-slot #accBody .row { margin: 0; }
  .mobile-invoice-info-slot #accBody .row > [class*="col-"] { padding-right: 0; padding-left: 0; }
  .pos-right-items { flex: 1; padding: 10px; overflow-y: auto; }
  .inv-row { grid-template-columns: minmax(0, 1fr) auto; }
  .inv-qty .qty-control { grid-template-columns: 42px minmax(0, 1fr) 42px; }
  .pos-right-footer {
    display: block;
    position: static;
    z-index: auto;
    background: #fff;
    box-shadow: 0 -8px 20px rgba(17,24,39,.1);
  }
  .mobile-cart-bar {
    display: grid;
    position: fixed;
    right: 0;
    bottom: var(--pwa-browser-nav-height, 0px);
    left: 0;
    z-index: 70;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    min-height: 72px;
    padding: 9px 12px;
    border-top: 1px solid #e5e7eb;
    background: rgba(255,255,255,.98);
    box-shadow: 0 -8px 22px rgba(17,24,39,.12);
  }
  body.mobile-order-open .mobile-cart-bar { display: none; }
  .mobile-cart-label { display: block; color: #6b7280; font-size: 11.5px; font-weight: 700; }
  .mobile-cart-total {
    display: block;
    margin-top: 1px;
    color: #f59e0b;
    font-family: 'JetBrains Mono', monospace;
    font-size: 18px;
    font-weight: 800;
  }
  .mobile-cart-open { min-width: 132px; min-height: 48px; border-radius: 8px; font-weight: 800; }
  .mobile-cart-open:disabled {
    border-color: #d1d5db;
    background: #e5e7eb;
    color: #9ca3af;
    opacity: 1;
  }
}
</style>

<form method="POST" action="index.php?page=invoice&branch=<?= $reqBranch ?>"
      onsubmit="return invoiceSubmit(event)" id="invoiceForm">
  <?= csrfField() ?>
  <input type="hidden" name="action"  value="create_invoice">
  <input type="hidden" name="branch"  value="<?= $reqBranch ?>">
  <input type="hidden" name="items"   id="invoiceItemsJson">

<div class="pos-wrap">

  <div id="invoiceInfoDesktopSlot"></div>

  <!-- ══ ACCORDION: Thông tin khách + giao hàng ══════════════ -->
  <div class="pos-info-bar">
    <div class="card">
      <!-- Tiêu đề accordion -->
      <div class="card-header acc-toggle py-2" onclick="toggleAccordion()" id="accToggle">
        <div class="invoice-info-head">
          <div class="invoice-title-block">
            <div class="invoice-title-main">
              <i class="bi bi-receipt me-1 text-<?= $branchInfo['color'] ?>"></i>
              Thông tin hóa đơn
            </div>
            <div class="invoice-title-sub">Bấm “Sửa thông tin” khi cần nhập khách, thanh toán, giao hàng.</div>
          </div>

          <div id="accSummary" class="invoice-summary">
            <div class="invoice-chip">
              <i class="bi bi-person"></i>
              <div>
                <div class="invoice-chip-label">Khách hàng</div>
                <div class="invoice-chip-value" id="summCustomer">Khách lẻ</div>
              </div>
            </div>
            <div class="invoice-chip">
              <i class="bi bi-credit-card"></i>
              <div>
                <div class="invoice-chip-label">Thanh toán</div>
                <div class="invoice-chip-value" id="summPayment">Tiền mặt</div>
              </div>
            </div>
            <div class="invoice-chip">
              <i class="bi bi-truck"></i>
              <div>
                <div class="invoice-chip-label">Giao hàng</div>
                <div class="invoice-chip-value" id="summDeliveryDate">Tại quầy</div>
              </div>
            </div>
            <div class="invoice-chip" id="summShipping" style="display:none">
              <i class="bi bi-cash-coin"></i>
              <div>
                <div class="invoice-chip-label">Vận chuyển</div>
                <div class="invoice-chip-value" id="summShippingAmt">0 ₫</div>
              </div>
            </div>
            <div class="invoice-info-notice" id="invoiceInfoNotice">
              <i class="bi bi-info-circle"></i>
              <span id="invoiceInfoNoticeText"></span>
            </div>
          </div>

          <div class="invoice-info-actions">
            <a href="index.php?page=help&topic=invoices" class="btn btn-sm btn-outline-secondary context-help-btn" title="Hướng dẫn lập hóa đơn" onclick="event.stopPropagation()"><i class="bi bi-question-circle"></i><span class="context-help-label">Hướng dẫn</span></a>
            <button type="button" class="invoice-edit-hint btn btn-sm btn-outline-secondary" onclick="event.stopPropagation();toggleAccordion()"><i class="bi bi-pencil-square me-1"></i><span id="accActionText">Sửa thông tin</span></button>
          </div>
        </div>
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
            <input type="tel" name="phone" class="form-control form-control-sm" placeholder="0xxx..." oninput="updateInvoiceInfoNotice()">
          </div>
          <div class="col-md-2">
            <label class="form-label" style="font-size:11.5px">Thanh toán</label>
            <select name="payment" id="inpPayment" class="form-select form-select-sm" onchange="updateSummary()">
              <option value="cash">Tiền mặt</option>
              <option value="transfer">Chuyển khoản</option>
              <option value="cod">COD</option>
              <?php if(featureEnabled('receivables')): ?><option value="credit">Công nợ</option><?php endif; ?>
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
            <input type="hidden" name="delivery_date" id="inpDeliveryDate" value="" onchange="onDeliveryDateChange(this.value)">
            <input type="text" class="form-control form-control-sm" data-vn-date-target="inpDeliveryDate"
              placeholder="dd/mm/yyyy">
          </div>
          <div class="col-md-5">
            <label class="form-label" style="font-size:11.5px">Địa chỉ giao hàng</label>
            <input type="text" name="address" class="form-control form-control-sm"
              placeholder="Để trống = lấy tại quầy">
          </div>
          <div class="col-md-3">
            <label class="form-label" style="font-size:11.5px">Ghi chú cho tài xế</label>
            <input type="text" name="delivery_note" class="form-control form-control-sm"
              placeholder="VD: Gọi trước 30 phút...">
          </div>
          <!-- Phí vận chuyển — hiện khi có ngày giao -->
          <div class="col-md-2" id="shippingWrap" style="display:none">
            <label class="form-label" style="font-size:11.5px">
              <i class="bi bi-cash me-1 text-success"></i>Phí vận chuyển (₫)
            </label>
            <input type="number" name="shipping_fee" id="inpShippingFee"
              class="form-control form-control-sm" min="0" step="10" value="0"
              onfocus="this.select()" oninput="updateTotals()">
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
        <div class="pos-left-title">
          <i class="bi bi-search me-2 text-warning"></i>Tìm Sản Phẩm
          <span class="pos-left-title-count"><?= count($allProducts) ?> SP</span>
        </div>
        <div style="position:relative">
          <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;font-size:15px">
            <i class="bi bi-search"></i>
          </span>
          <input type="text" id="productSearch" class="form-control form-control-sm"
            style="padding-left:36px;font-size:15px"
            placeholder="Nhập mã hoặc tên..."
            autocomplete="off"
            oninput="doSearch(this.value)"
            onfocus="doSearch(this.value)">
          <!-- Dropdown -->
          <div id="productDropdown" style="
            display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;
            background:#fff;border:1.5px solid #e5e7eb;border-radius:10px;
            box-shadow:0 8px 24px rgba(0,0,0,.14);z-index:9999;
            max-height:min(60vh,420px);overflow-y:auto"></div>
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
            class="cat-toggle btn btn-sm w-100 text-start d-flex justify-content-between align-items-center"
            style="background:#f9fafb;border:1px solid #e5e7eb;font-weight:800;font-size:14px;padding:11px 14px"
            onclick="toggleCat('cat_<?= $catKey ?>', this)">
            <span><i class="bi <?= htmlspecialchars($catInfo['icon'] ?? 'bi-box') ?> me-2 cat-toggle-icon"></i><?= htmlspecialchars($catInfo['name']) ?></span>
            <span class="d-flex align-items-center gap-1">
              <span class="badge bg-secondary" style="font-size:11px"><?= count($catProds) ?></span>
              <i class="bi bi-chevron-down" style="font-size:11px;color:#9ca3af;transition:transform .2s" id="chev_<?= $catKey ?>"></i>
            </span>
          </button>
          <div id="cat_<?= $catKey ?>" style="display:none">
            <?php foreach ($catProds as $p):
              $low    = ($p['stock'] ?? 0) < ($p['min_stock'] ?? 5);
              $pJson  = json_encode([
                'code'           => $p['code'],
                'name'           => $p['name'],
                'unit'           => $p['unit'],
                'price_out'      => (float)($p['price_out'] ?? 0),
                'stock'          => (float)($p['stock'] ?? 0),
                'special_colors' => $p['special_colors'] ?? [],
              ], JSON_UNESCAPED_UNICODE);
              $hasColors = !empty($p['special_colors']);
            ?>
            <div class="cat-item" onclick='addItem(<?= $pJson ?>)'>
              <div class="cat-item-name">
                <?= htmlspecialchars($p['name']) ?>
                <?php if ($hasColors): ?>
                <span style="background:#f3e8ff;color:#7c3aed;border-radius:4px;padding:1px 6px;font-size:10px;font-weight:700;margin-left:4px">
                  <i class="bi bi-palette"></i> <?= count($p['special_colors']) ?> màu ĐB
                </span>
                <?php endif; ?>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-1">
                <span style="font-family:'JetBrains Mono',monospace;font-size:11px;color:#9ca3af"><?= htmlspecialchars($p['code']) ?></span>
                <span class="cat-item-stock" style="color:<?= $low ? '#ef4444' : '#10b981' ?>">
                  <?= number_format($p['stock'] ?? 0, 2, ',', '.') ?> <?= htmlspecialchars($p['unit']) ?>
                  <?= $low ? 'âš ' : '' ?>
                </span>
                <span class="cat-item-price"><?= number_format($p['price_out'] ?? 0, 0, ',', '.') ?> &#8363;</span>
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
        <button type="button" class="mobile-order-back" onclick="closeMobileOrder()" aria-label="Quay lại chọn hàng">
          <i class="bi bi-arrow-left"></i>
        </button>
        <i class="bi bi-list-ul" style="color:#f59e0b"></i>
        Danh Sách Hàng Hóa
        <span id="itemCount" class="badge bg-secondary" style="font-weight:600;font-size:11px">0</span>
        <div class="ms-auto d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-danger"
            onclick="if(invoiceItems.length) new bootstrap.Modal(document.getElementById('confirmClearModal')).show()">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>

      <!-- Header cột (cố định) -->
      <button type="button" class="mobile-invoice-info-toggle" id="mobileInvoiceInfoToggle"
        onclick="toggleAccordion()" aria-expanded="false" aria-controls="accBody">
        <span class="mobile-info-icon"><i class="bi bi-receipt"></i></span>
        <span>
          <span class="mobile-info-title">Thông tin đơn</span>
          <span class="mobile-info-summary" id="mobileInfoSummary">Khách lẻ · Tiền mặt · Tại quầy</span>
        </span>
        <i class="bi bi-chevron-down mobile-info-chevron"></i>
      </button>
      <div id="mobileInvoiceInfoSlot" class="mobile-invoice-info-slot"></div>

      <!-- Danh sách items — scroll độc lập -->
      <div class="pos-right-items" id="invoiceItems">
        <div class="empty-state" style="padding:40px 20px">
          <i class="bi bi-cart-x" style="font-size:40px;opacity:.25;display:block;margin-bottom:10px"></i>
          <p style="color:#9ca3af;font-size:13px">Chưa có sản phẩm.<br>Tìm hoặc chọn nhóm hàng bên trái.</p>
        </div>
      </div>

      <!-- Footer: tổng + nút xuất -->
      <div class="pos-right-footer">
        <div class="pos-footer-inner" style="padding:10px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px">
          <div class="pos-footer-count" style="font-size:12.5px;color:#6b7280">
            <span id="itemCountFt">0</span> sản phẩm
          </div>
          <div class="pos-footer-actions d-flex align-items-center gap-3 ms-auto">
            <!-- Breakdown -->
            <div class="pos-total-box text-end" style="line-height:1.6">
              <div style="font-size:12px;color:#9ca3af">
                Hàng hóa: <span id="subtotalDisplay" style="color:#374151;font-weight:600">0 ₫</span>
              </div>
              <div id="shippingRow" style="font-size:12px;color:#9ca3af;display:none">
                Phí vận chuyển: <span id="shippingDisplay" style="color:#10b981;font-weight:600">0 ₫</span>
              </div>
              <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-top:2px">Tổng cộng</div>
              <div id="invoiceTotal" style="font-family:'JetBrains Mono',monospace;font-size:22px;font-weight:800;color:#f59e0b">0 &#8363;</div>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-lg px-3" onclick="printQuote()" style="white-space:nowrap">
              <i class="bi bi-printer me-1"></i>Báo giá
            </button>
            <button type="submit" class="submit-invoice btn btn-primary btn-lg px-4" style="white-space:nowrap">
              <i class="bi bi-check2-circle me-2"></i>Xuất Hóa Đơn
            </button>
          </div>
        </div>
      </div>

    </div>
  </div><!-- .pos-main -->
  <div class="mobile-cart-bar" id="mobileCartBar">
    <div>
      <span class="mobile-cart-label" id="mobileCartCount">Chưa chọn sản phẩm</span>
      <strong class="mobile-cart-total" id="mobileCartTotal">0 ₫</strong>
    </div>
    <button type="button" class="btn btn-primary mobile-cart-open" id="mobileCartOpen"
      onclick="openMobileOrder()" disabled>
      Xem đơn <i class="bi bi-arrow-right ms-1"></i>
    </button>
  </div>
</div><!-- .pos-wrap -->
</form>

<script>
const PRODUCTS = <?= json_encode(array_values($allProducts), JSON_UNESCAPED_UNICODE) ?>;
let invoiceItems = [];
let accOpen = false;

// â”€â”€ Accordion â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function toggleAccordion() {
  const body = document.getElementById('accBody');
  const tog  = document.getElementById('accToggle');
  accOpen = !accOpen;
  body.style.display = accOpen ? 'block' : 'none';
  tog.classList.toggle('collapsed', !accOpen);
  const actionText = document.getElementById('accActionText');
  if (actionText) actionText.textContent = accOpen ? 'Thu gọn' : 'Sửa thông tin';
  const mobileToggle = document.getElementById('mobileInvoiceInfoToggle');
  if (mobileToggle) mobileToggle.setAttribute('aria-expanded', accOpen ? 'true' : 'false');
}

function updateSummary() {
  const c = document.getElementById('inpCustomer')?.value || 'Khách lẻ';
  const p = document.getElementById('inpPayment')?.value || 'cash';
  const payLabels = {cash:'Tiền mặt',transfer:'Chuyển khoản',cod:'COD',credit:'Công nợ'};
  document.getElementById('summCustomer').textContent = c.trim() || 'Khách lẻ';
  document.getElementById('summPayment').textContent  = payLabels[p] || p;
  updateInvoiceInfoNotice();
}

function onDeliveryDateChange(val) {
  const txt      = document.getElementById('summDeliveryDate');
  const shipWrap = document.getElementById('shippingWrap');
  if (val) {
    const d = new Date(val + 'T00:00:00');
    txt.textContent = 'Giao ' + d.toLocaleDateString('vi-VN',{day:'2-digit',month:'2-digit'});
    shipWrap.style.display = '';   // Hiện ô phí vận chuyển
  } else {
    txt.textContent = 'Tại quầy';
    shipWrap.style.display = 'none';
    document.getElementById('inpShippingFee').value = '0';
    updateTotals();
  }
  updateInvoiceInfoNotice();
}

function updateInvoiceInfoNotice() {
  const notice = document.getElementById('invoiceInfoNotice');
  const text = document.getElementById('invoiceInfoNoticeText');
  if (!notice || !text) return;

  const payment = document.getElementById('inpPayment')?.value || 'cash';
  const customer = (document.getElementById('inpCustomer')?.value || '').trim();
  const phone = (document.querySelector('input[name="phone"]')?.value || '').trim();
  const deliveryDate = document.getElementById('inpDeliveryDate')?.value || '';
  const messages = [];

  if (payment === 'credit' && (!customer || customer === 'Khách lẻ') && !phone) {
    messages.push('Công nợ nên có tên khách hoặc SĐT để theo dõi chính xác.');
  } else if (payment === 'credit') {
    messages.push('Hóa đơn này sẽ được ghi vào công nợ khách hàng.');
  }
  if (deliveryDate) {
    messages.push('Có giao hàng, hãy kiểm tra địa chỉ và phí vận chuyển trước khi lưu.');
  }

  if (messages.length) {
    text.textContent = messages.join(' ');
    notice.style.display = 'flex';
  } else {
    notice.style.display = 'none';
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
    const hasColor = p.special_colors && p.special_colors.length > 0;
    return `<div onclick='addItem(${JSON.stringify(p)})'
      style="padding:13px 16px;cursor:pointer;border-bottom:1px solid #f3f4f6;transition:background .1s"
      onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background=''">
      <div style="font-weight:700;font-size:14.5px;color:#111">
        ${esc(p.name)}
        ${hasColor ? `<span style="background:#f3e8ff;color:#7c3aed;border-radius:4px;padding:1px 6px;font-size:10px;font-weight:700;margin-left:6px">
          <i class="bi bi-palette"></i> ${p.special_colors.length} màu ĐB</span>` : ''}
      </div>
      <div style="display:flex;gap:10px;margin-top:5px;flex-wrap:wrap;align-items:center">
        <span style="font-family:'JetBrains Mono',monospace;font-size:11.5px;color:#9ca3af">${esc(p.code)}</span>
        <span style="font-size:12px;font-weight:700;color:${low?'#ef4444':'#10b981'}">
          Tồn: ${fmt(p.stock)} ${esc(p.unit)}${low?' ⚠️':''}
        </span>
        <span style="font-size:13px;font-weight:800;color:#f59e0b;margin-left:auto">${fmtM(p.price_out)}</span>
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

  // Nếu có màu đặc biệt → hiện modal chọn màu
  if (p.special_colors && p.special_colors.length > 0) {
    showColorPicker(p);
    return;
  }
  _doAddItem(p, '', 0);
}

// Thêm thực sự vào giỏ (sau khi chọn màu hoặc không có màu)
function _doAddItem(p, colorName, surcharge) {
  // Key phân biệt: cùng SP nhưng khác màu = dòng riêng
  const key = colorName ? (p.code + '__' + colorName.replace(/\s+/g,'_')) : p.code;
  const basePrice  = parseFloat(p.price_out) || 0;
  const finalPrice = basePrice + (parseFloat(surcharge) || 0);
  const displayName = colorName ? `${p.name} — ${colorName}` : p.name;
  const stock = parseFloat(p.stock) || 0;

  const ex = invoiceItems.find(i => i.code === key);
  if (ex) {
    if (ex.qty + 1 > stock) {
      if (typeof showToast === 'function') showToast(`Tồn kho chỉ còn ${fmt(stock)} ${p.unit}`, 'warning');
      return;
    }
    ex.qty += 1;
    ex.line_total = ex.qty * ex.price_out;
  } else {
    if (1 > stock) {
      if (typeof showToast === 'function') showToast(`Sản phẩm đã hết hàng trong kho.`, 'warning');
      return;
    }
    invoiceItems.push({
      code:         key,
      product_code: p.code,          // mã SP gốc để trừ tồn kho
      name:         displayName,
      unit:         p.unit,
      qty:          1,
      price_out:    finalPrice,
      line_total:   finalPrice,
      stock:        stock,
      color_name:   colorName || '',
      surcharge:    parseFloat(surcharge) || 0,
    });
  }
  renderItems();
  flashRow(key);
  if (!window.matchMedia('(max-width: 768px)').matches) {
    setTimeout(() => document.getElementById('productSearch')?.focus(), 80);
  }
}

// ── Modal chọn màu đặc biệt ───────────────────────────────────
let _colorPickerProduct = null;

function showColorPicker(p) {
  _colorPickerProduct = p;
  const basePrice = parseFloat(p.price_out) || 0;

  // Tạo modal một lần
  if (!document.getElementById('colorPickerModal')) {
    const el = document.createElement('div');
    el.id = 'colorPickerModal';
    el.className = 'modal fade';
    el.setAttribute('tabindex', '-1');
    el.innerHTML = `
      <div class="modal-dialog modal-sm">
        <div class="modal-content">
          <div class="modal-header py-2">
            <h6 class="modal-title fw-700">
              <i class="bi bi-palette me-2" style="color:#8b5cf6"></i>Chọn Màu
            </h6>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-2" id="colorPickerBody"></div>
        </div>
      </div>`;
    document.body.appendChild(el);
    // Gán sự kiện click bằng delegation — tránh inline onclick
    document.getElementById('colorPickerBody').addEventListener('click', function(e) {
      const item = e.target.closest('[data-color-idx]');
      if (!item) return;
      const idx = parseInt(item.dataset.colorIdx);
      const prod = _colorPickerProduct;
      if (!prod) return;
      bootstrap.Modal.getInstance(document.getElementById('colorPickerModal')).hide();
      if (idx === -1) {
        // Màu thường
        _doAddItem(prod, '', 0);
      } else {
        const sc = prod.special_colors[idx];
        if (!sc) return;
        const label = sc.name + (sc.code ? ' (' + sc.code + ')' : '');
        _doAddItem(prod, label, sc.surcharge);
      }
    });
  }

  // Render nội dung
  let html = `
    <div data-color-idx="-1"
      style="padding:10px 12px;cursor:pointer;border-radius:6px;border:1.5px solid #e5e7eb;
             margin-bottom:6px;transition:all .15s"
      onmouseover="this.style.borderColor='#f59e0b';this.style.background='#fffbeb'"
      onmouseout="this.style.borderColor='#e5e7eb';this.style.background=''">
      <div style="font-weight:700;font-size:13.5px">Màu thường</div>
      <div style="font-size:12px;color:#6b7280;margin-top:2px">
        Giá: <b style="color:#f59e0b">${fmtM(basePrice)}</b>
      </div>
    </div>
    <div style="font-size:11px;font-weight:700;color:#7c3aed;
                margin:10px 0 6px;padding:0 2px;border-top:1px dashed #e9d5ff;padding-top:8px">
      <i class="bi bi-stars me-1"></i>MÀU ĐẶC BIỆT
    </div>`;

  p.special_colors.forEach((sc, idx) => {
    const finalPrice = basePrice + (parseFloat(sc.surcharge) || 0);
    html += `
      <div data-color-idx="${idx}"
        style="padding:10px 12px;cursor:pointer;border-radius:6px;border:1.5px solid #e9d5ff;
               margin-bottom:6px;background:#faf5ff;transition:all .15s"
        onmouseover="this.style.borderColor='#8b5cf6';this.style.background='#f3e8ff'"
        onmouseout="this.style.borderColor='#e9d5ff';this.style.background='#faf5ff'">
        <div style="font-weight:700;font-size:13.5px;color:#5b21b6">
          ${esc(sc.name)}
          ${sc.code ? `<span style="font-family:monospace;font-size:11px;color:#9ca3af;
            font-weight:400;margin-left:6px">${esc(sc.code)}</span>` : ''}
        </div>
        <div style="font-size:12px;color:#6b7280;margin-top:2px">
          Phụ thu: <span style="color:#7c3aed;font-weight:700">+${fmtM(sc.surcharge)}</span>
          &nbsp;â†’&nbsp;
          <b style="color:#7c3aed;font-size:13px">${fmtM(finalPrice)}</b>
        </div>
      </div>`;
  });

  document.getElementById('colorPickerBody').innerHTML = html;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('colorPickerModal')).show();
}

function flashRow(code) {
  const container = document.getElementById('invoiceItems');
  if (container) container.scrollTop = container.scrollHeight;
  setTimeout(() => {
    const row = document.getElementById('row_' + code);
    if (row) { row.classList.add('flash'); setTimeout(()=>row.classList.remove('flash'),600); }
  }, 30);
}

function syncJson() {
  const el = document.getElementById('invoiceItemsJson');
  if (el) {
    // Gửi product_code gốc (không phải key có màu) để controller trừ tồn kho đúng
    const data = invoiceItems.map(i => ({
      code:      i.product_code || i.code,  // mã SP gốc
      name:      i.name,
      unit:      i.unit,
      qty:       i.qty,
      price_out: i.price_out,
      line_total:i.line_total,
      color_name:i.color_name || '',
    }));
    el.value = JSON.stringify(data);
  }
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
    showToast(`Tồn kho chỉ còn ${fmt(item.stock)} ${item.unit}`, 'warning');
    const inp = document.querySelector(`[data-qty="${code}"]`);
    if (inp) inp.value = item.qty; return;
  }
  item.qty = n; item.line_total = item.qty * item.price_out;
  updateTotals(); syncJson();
}

function stepQty(code, delta) {
  const item = invoiceItems.find(i => i.code === code);
  if (!item) return;
  const next = Math.max(0, (parseFloat(item.qty) || 0) + delta);
  if (next <= 0) {
    removeItem(code);
    return;
  }
  setQty(code, next);
  const inp = document.querySelector(`[data-qty="${code}"]`);
  if (inp) inp.value = item.qty;
}

// ── Cập nhật đơn giá ─────────────────────────────────────────
function setPrice(code, val) {
  const item = invoiceItems.find(i => i.code === code);
  if (!item) return;
  item.price_out  = parseFloat(val)||0;
  item.line_total = item.qty * item.price_out;
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
      <div class="inv-product">
        <div class="inv-name">
          ${item.color_name
            ? `<span>${esc(item.name.split(' — ')[0])}</span>
               <span class="inv-color"><i class="bi bi-palette" style="font-size:10px"></i> ${esc(item.color_name)}</span>`
            : esc(item.name)
          }
        </div>
        <div class="inv-meta">
          <span class="inv-code">${esc(item.product_code || item.code)}</span>
          <span class="inv-stock ${item.stock < (item.min_stock || 5) ? 'low' : ''}">
            <i class="bi bi-box-seam" style="font-size:10px"></i> Tồn: ${fmt(item.stock)} ${esc(item.unit)}
          </span>
        </div>
      </div>
      <button type="button" class="inv-remove" onclick="removeItem('${esc(item.code)}')" aria-label="Xóa sản phẩm">
        <i class="bi bi-x-lg"></i>
      </button>
      <div class="inv-controls">
        <div class="inv-qty">
          <span class="inv-label">Số lượng</span>
          <div class="qty-control">
            <button type="button" class="qty-step btn btn-outline-secondary" onclick="stepQty('${esc(item.code)}',-1)" aria-label="Giảm số lượng">
              <i class="bi bi-dash"></i>
            </button>
            <input type="number" data-qty="${esc(item.code)}"
              class="form-control form-control-sm" style="text-align:center;font-weight:800"
              min="0.01" step="0.01" value="${item.qty}"
              onfocus="this.select()"
              onchange="setQty('${esc(item.code)}',this.value)">
            <button type="button" class="qty-step btn btn-outline-secondary" onclick="stepQty('${esc(item.code)}',1)" aria-label="Tăng số lượng">
              <i class="bi bi-plus"></i>
            </button>
          </div>
        </div>
        <div class="inv-price">
          <span class="inv-label">Đơn giá</span>
          <input type="number" class="form-control form-control-sm" style="text-align:right"
            min="0" step="1" value="${item.price_out}"
            onfocus="this.select()"
            onchange="setPrice('${esc(item.code)}',this.value)">
        </div>
      </div>
    </div>`).join('');

  updateTotals(); syncJson();
}

function updateTotals() {
  const subtotal = invoiceItems.reduce((s,i) => s + i.line_total, 0);
  const shipping = parseFloat(document.getElementById('inpShippingFee')?.value || 0) || 0;
  const grand    = subtotal + shipping;

  // Subtotal
  const subEl = document.getElementById('subtotalDisplay');
  if (subEl) subEl.textContent = fmtM(subtotal);

  // Shipping row
  const shipRow = document.getElementById('shippingRow');
  const shipEl  = document.getElementById('shippingDisplay');
  if (shipRow) shipRow.style.display = shipping > 0 ? '' : 'none';
  if (shipEl)  shipEl.textContent = fmtM(shipping);

  // Grand total
  const el = document.getElementById('invoiceTotal');
  if (el) el.textContent = fmtM(grand);

  // Summary badge
  const summShip = document.getElementById('summShipping');
  const summAmt  = document.getElementById('summShippingAmt');
  if (summShip && summAmt) {
    if (shipping > 0) {
      summAmt.textContent = fmtM(shipping);
      summShip.style.display = '';
    } else {
      summShip.style.display = 'none';
    }
  }

  updateMobileCart();
  syncJson();
}
function invoiceSubmit(e) {
  if (!invoiceItems.length) {
    e.preventDefault();
    showToast('Vui lòng thêm ít nhất 1 sản phẩm vào hóa đơn.', 'warning');
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

function updateMobileCart() {
  const countEl = document.getElementById('mobileCartCount');
  const totalEl = document.getElementById('mobileCartTotal');
  const openBtn = document.getElementById('mobileCartOpen');
  const count = invoiceItems.length;
  const subtotal = invoiceItems.reduce((sum, item) => sum + item.line_total, 0);
  const shipping = parseFloat(document.getElementById('inpShippingFee')?.value || 0) || 0;

  if (countEl) {
    countEl.textContent = count ? 'Đã chọn ' + count + ' mặt hàng' : 'Chưa chọn sản phẩm';
  }
  if (totalEl) totalEl.textContent = fmtM(subtotal + shipping);
  if (openBtn) openBtn.disabled = count === 0;
  if (count === 0 && document.body.classList.contains('mobile-order-open')) closeMobileOrder();
}

function updateMobileInfoSummary() {
  const summary = document.getElementById('mobileInfoSummary');
  if (!summary) return;
  const customer = document.getElementById('summCustomer')?.textContent?.trim() || 'Khách lẻ';
  const payment = document.getElementById('summPayment')?.textContent?.trim() || 'Tiền mặt';
  const delivery = document.getElementById('summDeliveryDate')?.textContent?.trim() || 'Tại quầy';
  summary.textContent = [customer, payment, delivery].join(' · ');
}

function openMobileOrder() {
  if (!window.matchMedia('(max-width: 768px)').matches || !invoiceItems.length) return;
  document.body.classList.add('mobile-order-open');
  document.getElementById('invoiceItems')?.scrollTo({top: 0, behavior: 'auto'});
}

function closeMobileOrder() {
  document.body.classList.remove('mobile-order-open');
  if (accOpen && window.matchMedia('(max-width: 768px)').matches) toggleAccordion();
}

const mobileInvoiceMedia = window.matchMedia('(max-width: 768px)');

function syncMobileInvoiceLayout() {
  const infoBar = document.querySelector('.pos-info-bar');
  const desktopSlot = document.getElementById('invoiceInfoDesktopSlot');
  const mobileSlot = document.getElementById('mobileInvoiceInfoSlot');
  if (!infoBar || !desktopSlot || !mobileSlot) return;

  if (mobileInvoiceMedia.matches) {
    if (infoBar.parentElement !== mobileSlot) mobileSlot.appendChild(infoBar);
  } else {
    if (infoBar.previousElementSibling !== desktopSlot) {
      desktopSlot.insertAdjacentElement('afterend', infoBar);
    }
    document.body.classList.remove('mobile-order-open');
  }
}

if (typeof mobileInvoiceMedia.addEventListener === 'function') {
  mobileInvoiceMedia.addEventListener('change', syncMobileInvoiceLayout);
} else {
  mobileInvoiceMedia.addListener(syncMobileInvoiceLayout);
}
document.getElementById('invoiceForm')?.addEventListener('input', updateMobileInfoSummary);
document.getElementById('invoiceForm')?.addEventListener('change', updateMobileInfoSummary);

document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && document.body.classList.contains('mobile-order-open')) {
    closeMobileOrder();
  }
});
// Init
syncMobileInvoiceLayout();
renderItems();
updateSummary();
updateMobileCart();

function printQuote() {
  if (!invoiceItems.length) {
    if (typeof showToast === 'function') showToast('Vui lòng thêm sản phẩm vào hóa đơn để in báo giá.', 'warning');
    return;
  }

  const BIZ = <?= json_encode([
    'name'        => BUSINESS['name'] ?? '',
    'address'     => BUSINESS['address'] ?? '',
    'phone'       => BUSINESS['phone'] ?? '',
    'tax_code'    => BUSINESS['tax_code'] ?? '',
  ], JSON_UNESCAPED_UNICODE) ?>;

  const payLabel = {cash:'Tiền mặt',transfer:'Chuyển khoản',cod:'COD',credit:'Công nợ'};
  
  const customer = document.getElementById('inpCustomer')?.value || 'Khách lẻ';
  const phone = document.querySelector('input[name="phone"]')?.value || '';
  const address = document.querySelector('input[name="address"]')?.value || '';
  const payment = document.getElementById('inpPayment')?.value || 'cash';
  const note = document.querySelector('input[name="note"]')?.value || '';
  
  const shipping_fee = parseFloat(document.getElementById('inpShippingFee')?.value || 0) || 0;
  const subtotal = invoiceItems.reduce((s,i) => s + i.line_total, 0);
  const total = subtotal + shipping_fee;

  const rows = invoiceItems.map((item, idx) => `
    <tr>
      <td style="text-align:center">${idx+1}</td>
      <td>
        <div style="font-weight:bold">${esc(item.name)}</div>
        <div class="product-code">${esc(item.product_code || item.code)}</div>
      </td>
      <td style="text-align:center;font-weight:bold">${fmt(item.qty)}</td>
      <td style="text-align:center">${esc(item.unit)}</td>
      <td style="text-align:right">${fmtM(item.price_out)}</td>
      <td style="text-align:right;font-weight:bold">${fmtM(item.line_total)}</td>
    </tr>`).join('');

  const win = window.open('', '_blank', 'width=900,height=750');
  const now = new Date();
  const pad = n => String(n).padStart(2,'0');
  const timeStr = `${pad(now.getHours())}:${pad(now.getMinutes())} ${pad(now.getDate())}/${pad(now.getMonth()+1)}/${now.getFullYear()}`;

  win.document.write(`<!DOCTYPE html>
<html lang="vi"><head>
<meta charset="UTF-8">
<title>Bảng Báo Giá</title>
<style>
  @page { size: A4; margin: 12mm 16mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Times New Roman', serif; font-size: 11.5pt; color: #000; line-height:1.18; }
  .biz-header { text-align: center; padding-bottom: 2mm; margin-bottom: 2mm; }
  .biz-name    { font-size: 15pt; font-weight: bold; }
  .biz-contact { font-size: 10.5pt; color: #333; margin-top: .7mm; }
  .inv-title { text-align: center; font-size: 15pt; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; margin: 2.5mm 0 2.5mm; }
  .receipt-meta { display:flex; justify-content:center; flex-wrap:wrap; gap:1mm 8mm; margin-bottom:3mm; font-size:10.5pt; }
  .receipt-meta > div { display:flex; gap:1.5mm; min-width:0; }
  .receipt-meta span { color:#666; }
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1mm 6mm; font-size: 11pt; margin-bottom: 3mm; padding: 2mm 3mm; border: 1px solid #bbb; border-radius: 2mm; background: #fafafa; }
  .info-grid > div { display:flex; align-items:flex-start; gap:3mm; }
  .info-label { color: #666; font-size: 9.5pt; width:82px; flex-shrink:0; }
  .info-val   { font-weight: bold; font-size: 11pt; overflow-wrap:anywhere; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 3mm; font-size: 11.5pt; }
  th { border: 1px solid #888; padding: 1.7mm 2mm; font-weight: bold; font-size: 11pt; background:#e0e0e0; }
  td { border: 1px solid #bbb; padding: 1.7mm 2mm; vertical-align: middle; }
  tfoot td { background: #f2f2f2; font-weight: bold; font-size: 11.5pt; }
  .product-code { margin-top:.5mm; font-size:9pt; color:#666; font-weight:normal; }
  .grand-total td { font-size:15.5pt; }
  .payment-summary { display:flex; justify-content:flex-end; align-items:baseline; gap:4mm; margin:-1mm 0 3mm; font-size:10.5pt; }
  .inv-note { font-size: 11.5pt; color: #444; margin-bottom: 3mm; padding: 1.5mm 0; border-top: 1px dashed #ccc; }
  .inv-signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; margin-top: 3mm; }
  .sign-box { display:flex; flex-direction:column; align-items:center; text-align: center; font-size: 12.5pt; }
  .sign-title { font-weight: bold; }
  .sign-line { min-height: 16mm; width: 90%; }
  .sign-hint { font-size: 10.5pt; color: #444; line-height:1.1; }
  .legal-note { margin-top:2mm; color:#777; text-align:center; font-size:9pt; font-style:italic; }
</style>
</head><body>
<div class="biz-header">
  <div class="biz-name">${esc(BIZ.name)}</div>
  <div class="biz-contact">Địa chỉ: ${esc(BIZ.address)}</div>
  <div class="biz-contact">Điện thoại: ${esc(BIZ.phone)}${BIZ.tax_code ? ` &nbsp;|&nbsp; MST: ${esc(BIZ.tax_code)}` : ''}</div>
</div>
<div class="inv-title">Bảng Báo Giá</div>
<div class="receipt-meta">
  <div><span>Thời gian lập</span><strong>${timeStr}</strong></div>
</div>
<div class="info-grid">
  <div ${phone ? '' : 'style="grid-column:1/-1"'}><div class="info-label">Khách hàng</div><div class="info-val">${esc(customer)}</div></div>
  ${phone ? `<div><div class="info-label">Điện thoại</div><div class="info-val">${esc(phone)}</div></div>` : ''}
  ${address ? `<div style="grid-column:1/-1"><div class="info-label">Địa chỉ</div><div class="info-val">${esc(address)}</div></div>` : ''}
</div>
<table>
  <thead>
    <tr>
      <th style="width:36px;text-align:center">STT</th>
      <th style="text-align:left">Tên hàng hóa</th>
      <th style="width:80px;text-align:center">Số lượng</th>
      <th style="width:56px;text-align:center">ĐVT</th>
      <th style="width:115px;text-align:right">Đơn giá</th>
      <th style="width:125px;text-align:right">Thành tiền</th>
    </tr>
  </thead>
  <tbody>${rows}</tbody>
  <tfoot>
    ${shipping_fee > 0 ? `<tr>
      <td colspan="5" style="text-align:right">Tổng hàng hóa:</td>
      <td style="text-align:right">${fmtM(subtotal)}</td>
    </tr><tr>
      <td colspan="5" style="text-align:right">Phí vận chuyển:</td>
      <td style="text-align:right">${fmtM(shipping_fee)}</td>
    </tr>` : ''}
    <tr class="grand-total">
      <td colspan="5" style="text-align:right;font-weight:bold">TỔNG CỘNG:</td>
      <td style="text-align:right;font-weight:bold">${fmtM(total)}</td>
    </tr>
  </tfoot>
</table>
<div class="payment-summary"><span>Dự kiến thanh toán</span><strong>${payLabel[payment]||payment||'Chưa xác định'}</strong></div>
${note ? `<div class="inv-note"><b>Ghi chú:</b> ${esc(note)}</div>` : ''}
<div class="inv-signatures">
  <div class="sign-box">
    <div class="sign-title">Khách hàng</div>
    <div class="sign-line"></div>
    <div class="sign-hint">(Xem xét và xác nhận)</div>
  </div>
  <div class="sign-box">
    <div class="sign-title">Người báo giá</div>
    <div class="sign-line"></div>
    <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
  </div>
</div>
<div class="legal-note">Đây là bảng báo giá tham khảo, không có giá trị thanh toán hay xuất kho. Giá cả có thể thay đổi tùy thời điểm.</div>
<script>window.onload = function(){ window.print(); window.close(); }<\/script>
</body></html>`);
  win.document.close();
}
</script>
<div class="modal fade" id="confirmClearModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body text-center p-4">
        <i class="bi bi-trash text-danger mb-3" style="font-size: 3rem;"></i>
        <h5 class="mb-3" style="font-weight:700">Xóa hóa đơn?</h5>
        <p class="text-muted mb-4" style="font-size:14px">Bạn có chắc muốn xóa toàn bộ sản phẩm khỏi hóa đơn này?</p>
        <div class="d-flex justify-content-center gap-2">
          <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Hủy</button>
          <button type="button" class="btn btn-danger px-4" onclick="clearInvoice()">Xóa</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
function clearInvoice() {
  invoiceItems = [];
  renderItems();
  const modalEl = document.getElementById('confirmClearModal');
  const modal = bootstrap.Modal.getInstance(modalEl);
  if(modal) modal.hide();
}
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
