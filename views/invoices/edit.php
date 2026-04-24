<?php
$reqBranch  = $_GET['branch'] ?? '';
$invoiceId  = $_GET['id']     ?? '';
$ym         = $_GET['ym']     ?? date('Y_m');

if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }

$invoice = getInvoiceById($reqBranch, $invoiceId);
if (!$invoice) {
    $_SESSION['flash'] = ['type'=>'danger','message'=>'Không tìm thấy hóa đơn'];
    header("Location: index.php?page=invoices&branch={$reqBranch}"); exit;
}
if (($invoice['delivery_status']??'') === 'delivered') {
    $_SESSION['flash'] = ['type'=>'warning','message'=>'Hóa đơn đã giao — không thể chỉnh sửa'];
    header("Location: index.php?page=invoices&branch={$reqBranch}&ym={$ym}"); exit;
}

$branchInfo  = BRANCHES[$reqBranch];
$allProducts = getAllProducts($reqBranch);
$catList     = getCategories($reqBranch, true);
$pageTitle   = 'Sửa Hóa Đơn — ' . $invoice['id'];
include BASE_PATH . '/views/layouts/header.php';
?>

<style>
.pos-wrap{display:flex;flex-direction:column;gap:12px;height:calc(100vh - 130px)}
.pos-main{display:grid;grid-template-columns:360px 1fr;gap:12px;flex:1;min-height:0}
.pos-left{display:flex;flex-direction:column;background:#fff;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;min-height:0}
.pos-left-search{flex-shrink:0;padding:12px;border-bottom:1px solid var(--border)}
.pos-left-cats{flex:1;overflow-y:auto;padding:10px}
.pos-right{display:flex;flex-direction:column;background:#fff;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;min-height:0}
.pos-right-header{flex-shrink:0;padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;font-weight:700;font-size:14px}
.pos-right-items{flex:1;overflow-y:auto;min-height:0}
.pos-right-footer{flex-shrink:0;border-top:2px solid var(--border);background:var(--bg-main)}
.inv-row{display:grid;grid-template-columns:1fr 100px 130px 120px 36px;gap:8px;align-items:center;padding:9px 14px;border-bottom:1px solid #f3f4f6;transition:background .1s}
.inv-row:hover{background:#fafafa}
.cat-item{padding:9px 10px;cursor:pointer;border-bottom:1px solid #f3f4f6;border-radius:6px;margin-bottom:2px;transition:background .1s}
.cat-item:hover{background:#fffbeb}
@keyframes flashRow{0%{background:#fef3c7}100%{background:transparent}}
.inv-row.flash{animation:flashRow .5s ease}
@media(max-width:900px){.pos-main{grid-template-columns:1fr}.pos-left{max-height:40vh}.pos-wrap{height:auto}}
</style>

<!-- Cảnh báo đang sửa -->
<div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3" style="font-size:13px">
  <i class="bi bi-pencil-square fs-5"></i>
  <div>
    <strong>Đang sửa hóa đơn</strong> <code><?= htmlspecialchars($invoice['id']) ?></code>
    — Lập bởi <strong><?= htmlspecialchars($invoice['created_by']??'') ?></strong>
    lúc <?= htmlspecialchars(substr($invoice['created_at']??'',0,16)) ?>
    <?php if (!empty($invoice['edit_log'])): ?>
    · <span class="text-muted">Đã sửa <?= count($invoice['edit_log']) ?> lần</span>
    <?php endif; ?>
  </div>
  <a href="index.php?page=invoices&branch=<?= $reqBranch ?>&ym=<?= $ym ?>"
     class="btn btn-sm btn-outline-secondary ms-auto">
    <i class="bi bi-x me-1"></i>Hủy
  </a>
</div>

<form method="POST"
      action="index.php?page=invoices&branch=<?= $reqBranch ?>&action=update&id=<?= urlencode($invoiceId) ?>&ym=<?= $ym ?>"
      onsubmit="return invoiceSubmit(event)" id="invoiceForm">
  <input type="hidden" name="items" id="invoiceItemsJson">

<div class="pos-wrap">

  <!-- Accordion thông tin -->
  <div style="flex-shrink:0">
    <div class="card">
      <div class="card-header d-flex align-items-center gap-2 py-2" style="cursor:pointer"
           onclick="toggleAcc()">
        <i class="bi bi-pencil-fill text-warning"></i>
        <span class="fw-700" style="font-size:13.5px">Thông Tin Hóa Đơn</span>
        <div class="d-flex gap-3 ms-3" id="accSum" style="font-size:12.5px;color:#6b7280">
          <span id="sumCust"></span><span id="sumPay"></span><span id="sumDel" style="display:none"></span>
        </div>
        <i class="bi bi-chevron-down ms-auto" style="color:#9ca3af;transition:transform .2s" id="accChev"></i>
      </div>
      <div id="accBody" class="card-body py-2" style="display:none">
        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label" style="font-size:11.5px">Tên khách hàng</label>
            <input type="text" name="customer" id="inpCustomer" class="form-control form-control-sm"
              value="<?= htmlspecialchars($invoice['customer']??'') ?>" oninput="updateSum()">
          </div>
          <div class="col-md-2">
            <label class="form-label" style="font-size:11.5px">Số điện thoại</label>
            <input type="tel" name="phone" class="form-control form-control-sm"
              value="<?= htmlspecialchars($invoice['phone']??'') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label" style="font-size:11.5px">Thanh toán</label>
            <select name="payment" id="inpPayment" class="form-select form-select-sm" onchange="updateSum()">
              <?php foreach(['cash'=>'Tiền mặt','transfer'=>'Chuyển khoản','cod'=>'COD','credit'=>'Công nợ'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($invoice['payment']??'')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label" style="font-size:11.5px">Ghi chú</label>
            <input type="text" name="note" class="form-control form-control-sm"
              value="<?= htmlspecialchars($invoice['note']??'') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label" style="font-size:11.5px"><i class="bi bi-truck me-1 text-warning"></i>Ngày giao</label>
            <input type="date" name="delivery_date" id="inpDel"
              class="form-control form-control-sm"
              value="<?= htmlspecialchars($invoice['delivery_date']??'') ?>"
              onchange="updateDelSum(this.value)">
          </div>
          <div class="col-md-2">
            <label class="form-label" style="font-size:11.5px">Giá vận chuyển</label>
            <input type="number" name="shipping_fee" id="inpShippingFee"
              class="form-control form-control-sm" min="0" step="1000"
              value="<?= htmlspecialchars($invoice['shipping_fee']??0) ?>"
              placeholder="0 ₫"
              oninput="updateTotals()">
          </div>
          <div class="col-md-3">
            <label class="form-label" style="font-size:11.5px">Địa chỉ giao</label>
            <input type="text" name="address" class="form-control form-control-sm"
              value="<?= htmlspecialchars($invoice['address']??'') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label" style="font-size:11.5px">Ghi chú tài xế</label>
            <input type="text" name="delivery_note" class="form-control form-control-sm"
              value="<?= htmlspecialchars($invoice['delivery_note']??'') ?>">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="toggleAcc()">
              <i class="bi bi-chevron-up me-1"></i>Thu gọn
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="pos-main">

    <!-- Cột trái: tìm sản phẩm -->
    <div class="pos-left">
      <div class="pos-left-search">
        <div style="font-weight:700;font-size:13px;margin-bottom:8px;color:#374151">
          <i class="bi bi-search me-2 text-warning"></i>Thêm Sản Phẩm
          <span style="font-size:11px;font-weight:400;color:#9ca3af;float:right"><?= count($allProducts) ?> SP</span>
        </div>
        <div style="position:relative">
          <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none"><i class="bi bi-search"></i></span>
          <input type="text" id="productSearch" class="form-control form-control-sm"
            style="padding-left:32px" placeholder="Nhập mã hoặc tên..."
            autocomplete="off" oninput="doSearch(this.value)" onfocus="doSearch(this.value)">
          <div id="productDropdown" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;
            background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;
            box-shadow:0 8px 24px rgba(0,0,0,.14);z-index:9999;max-height:260px;overflow-y:auto"></div>
        </div>
      </div>
      <div class="pos-left-cats">
        <?php foreach ($catList as $catInfo):
          $ck = $catInfo['key'];
          $cp = array_values(array_filter($allProducts, fn($p)=>($p['category_key']??'')===$ck));
          if (!$cp) continue; ?>
        <div class="mb-2">
          <button type="button"
            class="btn btn-sm w-100 text-start d-flex justify-content-between align-items-center"
            style="background:#f9fafb;border:1px solid #e5e7eb;font-weight:700;font-size:12.5px;padding:6px 10px"
            onclick="toggleCat('cat_<?= $ck ?>','chev_<?= $ck ?>')">
            <span><i class="bi <?= htmlspecialchars($catInfo['icon']??'bi-box') ?> me-2" style="color:#8b5cf6"></i><?= htmlspecialchars($catInfo['name']) ?></span>
            <span class="d-flex align-items-center gap-1">
              <span class="badge bg-secondary" style="font-size:10px"><?= count($cp) ?></span>
              <i class="bi bi-chevron-down" id="chev_<?= $ck ?>" style="font-size:11px;color:#9ca3af;transition:transform .2s"></i>
            </span>
          </button>
          <div id="cat_<?= $ck ?>" style="display:none">
            <?php foreach ($cp as $p):
              $low = ($p['stock']??0)<($p['min_stock']??5);
              $pj  = json_encode(['code'=>$p['code'],'name'=>$p['name'],'unit'=>$p['unit'],
                'price_out'=>(float)($p['price_out']??0),'stock'=>(float)($p['stock']??0)],
                JSON_UNESCAPED_UNICODE); ?>
            <div class="cat-item" onclick='addItem(<?= $pj ?>)'>
              <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($p['name']) ?></div>
              <div class="d-flex justify-content-between mt-1">
                <span style="font-family:'JetBrains Mono',monospace;font-size:10.5px;color:#9ca3af"><?= htmlspecialchars($p['code']) ?></span>
                <span style="font-size:11px;font-weight:700;color:<?= $low?'#ef4444':'#10b981' ?>"><?= number_format($p['stock']??0,2,',','.') ?> <?= htmlspecialchars($p['unit']) ?></span>
                <span style="font-size:11px;font-weight:700;color:#f59e0b"><?= number_format($p['price_out']??0,0,',','.') ?>₫</span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Cột phải: DS hóa đơn -->
    <div class="pos-right">
      <div class="pos-right-header">
        <i class="bi bi-list-ul" style="color:#f59e0b"></i>
        Danh Sách Hàng Hóa
        <span id="itemCount" class="badge bg-secondary" style="font-size:11px">0</span>
        <button type="button" class="btn btn-sm btn-outline-danger ms-auto"
          onclick="if(invoiceItems.length&&confirm('Xóa toàn bộ?')){invoiceItems=[];renderItems()}">
          <i class="bi bi-trash"></i>
        </button>
      </div>
      <!-- Header cột -->
      <div style="display:grid;grid-template-columns:1fr 100px 130px 120px 36px;gap:8px;
        padding:6px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;flex-shrink:0">
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Sản phẩm</div>
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Số lượng</div>
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Đơn giá (₫)</div>
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Thành tiền</div>
        <div></div>
      </div>
      <div class="pos-right-items" id="invoiceItems"></div>
      <div class="pos-right-footer">
        <div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center">
          <div style="font-size:12.5px;color:#6b7280"><span id="itemCountFt">0</span> sản phẩm</div>
          <div class="d-flex align-items-center gap-3">
            <div class="text-end">
              <div style="font-size:11px;color:#9ca3af">TỔNG CỘNG</div>
              <div id="invoiceTotal" style="font-family:'JetBrains Mono',monospace;font-size:22px;font-weight:800;color:#f59e0b">0 ₫</div>
            </div>
            <button type="submit" class="btn btn-warning btn-lg px-4" style="white-space:nowrap">
              <i class="bi bi-check2-circle me-2"></i>Lưu Thay Đổi
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</form>

<script>
const PRODUCTS = <?= json_encode(array_values($allProducts), JSON_UNESCAPED_UNICODE) ?>;

// Load dữ liệu hóa đơn cũ
let invoiceItems = <?= json_encode(array_map(fn($i) => [
  'code'       => $i['product_code'],
  'name'       => $i['product_name'],
  'unit'       => $i['unit'],
  'qty'        => $i['qty'],
  'price_out'  => $i['price_out'],
  'line_total' => $i['line_total'],
  'stock'      => (function() use ($allProducts, $i) {
    foreach ($allProducts as $p) {
      if ($p['code'] === $i['product_code']) return (float)($p['stock'] ?? 0);
    }
    return 0;
  })(),
], $invoice['items']), JSON_UNESCAPED_UNICODE) ?>;

let accOpen = false;
function toggleAcc() {
  accOpen = !accOpen;
  document.getElementById('accBody').style.display = accOpen ? 'block' : 'none';
  document.getElementById('accChev').style.transform = accOpen ? 'rotate(180deg)' : '';
  document.getElementById('accSum').style.display = accOpen ? 'none' : '';
}
function updateSum() {
  const c = document.getElementById('inpCustomer')?.value||'';
  const p = document.getElementById('inpPayment')?.value||'cash';
  const pl = {cash:'Tiền mặt',transfer:'Chuyển khoản',cod:'COD',credit:'Công nợ'};
  document.getElementById('sumCust').innerHTML = `<i class="bi bi-person me-1"></i>${esc(c)}`;
  document.getElementById('sumPay').innerHTML  = `<i class="bi bi-cash me-1"></i>${pl[p]||p}`;
}
function updateDelSum(v) {
  const el = document.getElementById('sumDel');
  if (v) { el.innerHTML=`<i class="bi bi-truck me-1 text-warning"></i>Giao ${v}`; el.style.display=''; }
  else el.style.display='none';
}
function toggleCat(id, chevId) {
  const el = document.getElementById(id), ch = document.getElementById(chevId);
  const open = el.style.display === 'none';
  el.style.display = open ? 'block' : 'none';
  if (ch) ch.style.transform = open ? 'rotate(180deg)' : '';
}
function doSearch(val) {
  const dd = document.getElementById('productDropdown');
  val = val.trim();
  if (!val) { dd.style.display='none'; return; }
  const kw = rmv(val.toLowerCase());
  const res = PRODUCTS.filter(p => rmv((p.code||'').toLowerCase()).includes(kw)||rmv((p.name||'').toLowerCase()).includes(kw)).slice(0,12);
  if (!res.length) { dd.innerHTML='<div style="padding:12px 14px;font-size:13px;color:#9ca3af">Không tìm thấy</div>'; dd.style.display='block'; return; }
  dd.innerHTML = res.map(p=>{
    const low=p.stock<(p.min_stock||5);
    return `<div onclick='addItem(${JSON.stringify(p)})' style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f3f4f6" onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background=''">
      <div style="font-weight:600;font-size:13.5px">${esc(p.name)}</div>
      <div style="display:flex;gap:10px;margin-top:4px;flex-wrap:wrap">
        <span style="font-family:'JetBrains Mono',monospace;font-size:11px;color:#9ca3af">${esc(p.code)}</span>
        <span style="font-size:11px;font-weight:700;color:${low?'#ef4444':'#10b981'}">Tồn: ${fmt(p.stock)} ${esc(p.unit)}</span>
        <span style="font-size:12px;font-weight:800;color:#f59e0b;margin-left:auto">${fmtM(p.price_out)}</span>
      </div></div>`;
  }).join('');
  dd.style.display='block';
}
document.addEventListener('click', e => {
  const dd=document.getElementById('productDropdown');
  if (!dd.contains(e.target)&&e.target.id!=='productSearch') dd.style.display='none';
});
function addItem(p) {
  document.getElementById('productDropdown').style.display='none';
  document.getElementById('productSearch').value='';
  const ex = invoiceItems.find(i=>i.code===p.code);
  if (ex) { ex.qty+=1; ex.line_total=ex.qty*ex.price_out; }
  else invoiceItems.push({code:p.code,name:p.name,unit:p.unit,qty:1,price_out:parseFloat(p.price_out)||0,line_total:parseFloat(p.price_out)||0,stock:parseFloat(p.stock)||0});
  renderItems();
  setTimeout(()=>{ const r=document.getElementById('row_'+p.code); if(r){r.classList.add('flash');setTimeout(()=>r.classList.remove('flash'),600);} const c=document.getElementById('invoiceItems'); if(c)c.scrollTop=c.scrollHeight; },30);
}
function removeItem(code) { invoiceItems=invoiceItems.filter(i=>i.code!==code); renderItems(); }
function setQty(code,val) {
  const item=invoiceItems.find(i=>i.code===code); if(!item) return;
  const n=parseFloat(val)||0;
  if(n<=0){removeItem(code);return;}
  if(n>item.stock){alert(`Tồn kho chỉ còn ${fmt(item.stock)} ${item.unit}`);const inp=document.querySelector(`[data-qty="${code}"]`);if(inp)inp.value=item.qty;return;}
  item.qty=n; item.line_total=item.qty*item.price_out;
  const lt=document.getElementById('lt_'+code); if(lt)lt.textContent=fmtM(item.line_total);
  updateTotals(); syncJson();
}
function setPrice(code,val) {
  const item=invoiceItems.find(i=>i.code===code); if(!item) return;
  item.price_out=parseFloat(val)||0; item.line_total=item.qty*item.price_out;
  const lt=document.getElementById('lt_'+code); if(lt)lt.textContent=fmtM(item.line_total);
  updateTotals(); syncJson();
}
function renderItems() {
  const c=document.getElementById('invoiceItems'); if(!c) return;
  const cnt=invoiceItems.length;
  document.getElementById('itemCount').textContent=cnt;
  document.getElementById('itemCountFt').textContent=cnt;
  if(!cnt){c.innerHTML='<div class="empty-state" style="padding:40px 20px"><i class="bi bi-cart-x" style="font-size:40px;opacity:.25;display:block;margin-bottom:10px"></i><p style="color:#9ca3af;font-size:13px">Chưa có sản phẩm</p></div>';updateTotals();return;}
  c.innerHTML=invoiceItems.map(item=>`
    <div class="inv-row" id="row_${esc(item.code)}">
      <div>
        <div style="font-weight:600;font-size:13.5px">${esc(item.name)}</div>
        <div style="font-family:'JetBrains Mono',monospace;font-size:10.5px;color:#9ca3af">${esc(item.code)} <span style="color:${item.stock<5?'#ef4444':'#10b981'};font-weight:700">Tồn: ${fmt(item.stock)} ${esc(item.unit)}</span></div>
      </div>
      <div><input type="number" data-qty="${esc(item.code)}" class="form-control form-control-sm" style="text-align:right;font-weight:700" min="0.01" step="0.01" value="${item.qty}" onfocus="this.select()" onchange="setQty('${esc(item.code)}',this.value)"></div>
      <div><input type="number" class="form-control form-control-sm" style="text-align:right" min="0" step="1000" value="${item.price_out}" onfocus="this.select()" onchange="setPrice('${esc(item.code)}',this.value)"></div>
      <div id="lt_${esc(item.code)}" style="font-family:'JetBrains Mono',monospace;font-weight:800;font-size:14px;color:#f59e0b;text-align:right">${fmtM(item.line_total)}</div>
      <div style="text-align:center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem('${esc(item.code)}')"><i class="bi bi-x"></i></button></div>
    </div>`).join('');
  updateTotals(); syncJson();
}
function updateTotals() {
  const t=invoiceItems.reduce((s,i)=>s+i.line_total,0);
  const shippingFee=parseFloat(document.getElementById('inpShippingFee')?.value||0);
  const finalTotal=t+shippingFee;
  const el=document.getElementById('invoiceTotal'); if(el)el.textContent=fmtM(finalTotal);
  syncJson();
}
function syncJson() { const el=document.getElementById('invoiceItemsJson'); if(el)el.value=JSON.stringify(invoiceItems); }
function invoiceSubmit(e) {
  if(!invoiceItems.length){e.preventDefault();alert('⚠️ Hóa đơn phải có ít nhất 1 sản phẩm!');return false;}
  syncJson(); return true;
}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function fmt(n){return new Intl.NumberFormat('vi-VN').format(n||0);}
function fmtM(n){return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(n||0);}
function rmv(s){return s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d');}

// Init
renderItems();
updateSum();
updateDelSum(document.getElementById('inpDel')?.value||'');
</script>
<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
