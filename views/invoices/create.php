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
  grid-template-columns: 1fr 136px 130px 120px 36px;
  gap: 8px;
  align-items: center;
  padding: 9px 14px;
  border-bottom: 1px solid #f3f4f6;
  transition: background .1s;
}
.inv-row:hover { background: #fafafa; }
.mobile-label { display: none; }
.qty-control {
  display: grid;
  grid-template-columns: 34px minmax(54px, 1fr) 34px;
  gap: 4px;
  align-items: center;
}
.qty-step {
  min-width: 34px;
  height: 32px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.inv-row .form-control {
  min-height: 34px;
}

/* Flash khi thêm sản phẩm mới */
@keyframes flashRow {
  0%   { background: #fef3c7; }
  100% { background: transparent; }
}
.inv-row.flash { animation: flashRow .5s ease; }

/* Product item trong danh sách nhóm */
.cat-item {
  padding: 12px 12px;
  cursor: pointer;
  border: 1px solid #edf0f3;
  border-radius: 8px;
  margin-bottom: 6px;
  transition: background .1s;
  background: #fff;
}
.cat-item:hover { background: #fffbeb; border-color: #fcd34d; }
.cat-item:last-child { margin-bottom: 0; }
.cat-toggle {
  min-height: 42px;
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
@media (max-width: 900px) {
  .pos-main { grid-template-columns: 1fr; }
  .pos-left  { max-height: 40vh; }
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
    min-height: 42px;
  }
  .cat-item {
    padding: 12px 10px;
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
    grid-template-columns: 1fr auto;
    gap: 10px;
    padding: 12px;
    margin-bottom: 8px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(17,24,39,.04);
  }
  .inv-product {
    grid-column: 1 / -1;
  }
  .inv-qty,
  .inv-price {
    min-width: 0;
  }
  .inv-total {
    grid-column: 1 / 2;
    align-self: center;
    text-align: left !important;
    font-size: 16px !important;
  }
  .inv-remove {
    grid-column: 2 / 3;
    align-self: center;
  }
  .inv-remove .btn {
    width: 42px;
    height: 42px;
    padding: 0;
  }
  .mobile-label {
    display: block;
    margin-bottom: 4px;
    font-size: 11px;
    color: #6b7280;
    font-weight: 800;
    text-transform: uppercase;
  }
  .qty-control {
    grid-template-columns: 42px minmax(68px, 1fr) 42px;
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
  .cat-toggle { min-height: 48px; border-radius: 8px; }
  .cat-item { min-height: 62px; padding: 13px 12px; }
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
  .inv-row { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
  .inv-qty { grid-column: 1 / 2; min-width: 0; }
  .inv-price { grid-column: 2 / 3; width: auto; min-width: 0; }
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
            class="cat-toggle btn btn-sm w-100 text-start d-flex justify-content-between align-items-center"
            style="background:#f9fafb;border:1px solid #e5e7eb;font-weight:800;font-size:13.5px;padding:9px 12px"
            onclick="toggleCat('cat_<?= $catKey ?>', this)">
            <span><i class="bi <?= htmlspecialchars($catInfo['icon'] ?? 'bi-box') ?> me-2" style="color:#8b5cf6"></i><?= htmlspecialchars($catInfo['name']) ?></span>
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
              <div style="font-weight:700;font-size:14px;color:#111827;line-height:1.35">
                <?= htmlspecialchars($p['name']) ?>
                <?php if ($hasColors): ?>
                <span style="background:#f3e8ff;color:#7c3aed;border-radius:4px;padding:1px 6px;font-size:10px;font-weight:700;margin-left:4px">
                  <i class="bi bi-palette"></i> <?= count($p['special_colors']) ?> màu ĐB
                </span>
                <?php endif; ?>
              </div>
              <div class="d-flex justify-content-between mt-1">
                <span style="font-family:'JetBrains Mono',monospace;font-size:11px;color:#9ca3af"><?= htmlspecialchars($p['code']) ?></span>
                <span style="font-size:11.5px;font-weight:800;color:<?= $low ? '#ef4444' : '#10b981' ?>">
                  <?= number_format($p['stock'] ?? 0, 2, ',', '.') ?> <?= htmlspecialchars($p['unit']) ?>
                  <?= $low ? 'âš ' : '' ?>
                </span>
                <span style="font-size:12px;font-weight:800;color:#f59e0b"><?= number_format($p['price_out'] ?? 0, 0, ',', '.') ?> &#8363;</span>
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
            onclick="if(invoiceItems.length&&confirm('Xóa toàn bộ hóa đơn?')){invoiceItems=[];renderItems()}">
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

      <div class="pos-table-head" style="display:grid;grid-template-columns:1fr 136px 130px 120px 36px;gap:8px;
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
      style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f3f4f6;transition:background .1s"
      onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background=''">
      <div style="font-weight:600;font-size:13.5px;color:#111">
        ${esc(p.name)}
        ${hasColor ? `<span style="background:#f3e8ff;color:#7c3aed;border-radius:4px;padding:1px 6px;font-size:10px;font-weight:700;margin-left:6px">
          <i class="bi bi-palette"></i> ${p.special_colors.length} màu ĐB</span>` : ''}
      </div>
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

  const ex = invoiceItems.find(i => i.code === key);
  if (ex) {
    ex.qty += 1;
    ex.line_total = ex.qty * ex.price_out;
  } else {
    invoiceItems.push({
      code:         key,
      product_code: p.code,          // mã SP gốc để trừ tồn kho
      name:         displayName,
      unit:         p.unit,
      qty:          1,
      price_out:    finalPrice,
      line_total:   finalPrice,
      stock:        parseFloat(p.stock) || 0,
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
  // Cập nhật thành tiền không re-render toàn bộ
  const lt = document.getElementById('lt_'+code);
  if (lt) lt.textContent = fmtM(item.line_total);
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
      <div class="inv-product">
        <div style="font-weight:600;font-size:13.5px;color:#111;line-height:1.3">
          ${item.color_name
            ? `<span>${esc(item.name.split(' — ')[0])}</span>
               <span style="display:inline-block;margin-left:6px;background:#f3e8ff;color:#7c3aed;border-radius:4px;padding:1px 7px;font-size:11px;font-weight:700">
                 <i class="bi bi-palette" style="font-size:10px"></i> ${esc(item.color_name)}
               </span>`
            : esc(item.name)
          }
        </div>
        <div style="font-family:'JetBrains Mono',monospace;font-size:10.5px;color:#9ca3af">
          ${esc(item.product_code || item.code)}
          <span style="margin-left:6px;color:${item.stock<(item.min_stock||5)?'#ef4444':'#10b981'};font-weight:700">
            Tồn: ${fmt(item.stock)} ${esc(item.unit)}
          </span>
        </div>
      </div>
      <div class="inv-qty">
        <span class="mobile-label">Số lượng</span>
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
        <span class="mobile-label">Đơn giá</span>
        <input type="number" class="form-control form-control-sm" style="text-align:right"
          min="0" step="1" value="${item.price_out}"
          onfocus="this.select()"
          onchange="setPrice('${esc(item.code)}',this.value)">
      </div>
      <div class="inv-total" id="lt_${esc(item.code)}"
        style="font-family:'JetBrains Mono',monospace;font-weight:800;font-size:14px;color:#f59e0b;text-align:right">
        ${fmtM(item.line_total)}
      </div>
      <div class="inv-remove" style="text-align:center">
        <button type="button" class="btn btn-sm btn-outline-danger"
          onclick="removeItem('${esc(item.code)}')">
          <i class="bi bi-x"></i>
        </button>
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
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
