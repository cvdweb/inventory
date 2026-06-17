<?php
$reqBranch  = $_GET['branch'] ?? array_key_first(BRANCHES);
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }
$branchInfo = BRANCHES[$reqBranch];
$yearMonth  = $_GET['ym'] ?? date('Y_m');
$invoices   = getInvoices($reqBranch, $yearMonth);
$pageTitle  = 'Danh Sách Hóa Đơn — ' . $branchInfo['name'];
include BASE_PATH . '/views/layouts/header.php';
$totalRevenue = array_sum(array_column($invoices, 'total'));

// Đếm theo trạng thái giao hàng
$cntPending  = count(array_filter($invoices, fn($i) => ($i['delivery_status']??'') === 'pending'));
$cntOverdue  = 0;
foreach ($invoices as $i) {
    if (($i['delivery_status']??'') === 'pending' && !empty($i['delivery_date']) && $i['delivery_date'] < date('Y-m-d')) {
        $cntOverdue++;
    }
}

function deliveryBadge(array $inv): string {
    $status = $inv['delivery_status'] ?? 'self_pickup';
    $date   = $inv['delivery_date']   ?? '';
    $today  = date('Y-m-d');
    $overdue= $status === 'pending' && $date && $date < $today;

    return match(true) {
        $status === 'delivered'  => '<span class="badge" style="background:#10b981;font-size:11px"><i class="bi bi-check2-circle me-1"></i>Đã giao</span>',
        $overdue                 => '<span class="badge" style="background:#ef4444;font-size:11px"><i class="bi bi-exclamation-triangle me-1"></i>Quá hạn '.date('d/m',strtotime($date)).'</span>',
        $status === 'pending'    => '<span class="badge" style="background:#f59e0b;font-size:11px"><i class="bi bi-truck me-1"></i>Giao '.date('d/m',strtotime($date)).'</span>',
        default                  => '<span class="badge" style="background:#6b7280;font-size:11px">Tại quầy</span>',
    };
}
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-journal-text me-2 text-<?= $branchInfo['color'] ?>"></i>Hóa Đơn Bán Hàng</h2>
    <p><?= htmlspecialchars($branchInfo['name']) ?> — <?= str_replace('_','/',$yearMonth) ?></p>
  </div>
  <div class="d-flex gap-2">
    <form class="d-flex gap-2" method="GET">
      <input type="hidden" name="page" value="invoices">
      <input type="hidden" name="branch" value="<?= $reqBranch ?>">
      <input type="month" name="ym" class="form-control form-control-sm"
        value="<?= str_replace('_','-',$yearMonth) ?>"
        onchange="this.value=this.value.replace('-','_');this.form.submit()">
    </form>
    <a href="index.php?page=invoice&branch=<?= $reqBranch ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Tạo hóa đơn
    </a>
  </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-card stat-amber">
      <div class="stat-icon"><i class="bi bi-receipt"></i></div>
      <div class="stat-value"><?= count($invoices) ?></div>
      <div class="stat-label">Tổng hóa đơn</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-value" style="font-size:16px"><?= formatMoney($totalRevenue) ?></div>
      <div class="stat-label">Tổng doanh thu</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:#fffbeb;border:1px solid #fde68a">
      <div class="stat-icon" style="background:rgba(245,158,11,.15);color:#f59e0b"><i class="bi bi-truck"></i></div>
      <div class="stat-value" style="color:#f59e0b"><?= $cntPending ?></div>
      <div class="stat-label">Chờ giao hàng</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:<?= $cntOverdue > 0 ? '#fef2f2' : '#fff' ?>;border:1px solid <?= $cntOverdue > 0 ? '#fecaca' : '#e5e7eb' ?>">
      <div class="stat-icon" style="background:rgba(239,68,68,.12);color:#ef4444"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="stat-value" style="color:<?= $cntOverdue > 0 ? '#ef4444' : '#9ca3af' ?>"><?= $cntOverdue ?></div>
      <div class="stat-label">Quá hạn giao</div>
    </div>
  </div>
</div>

<!-- Bộ lọc trạng thái -->
<?php $filterStatus = $_GET['ds'] ?? ''; ?>
<div class="card mb-3">
  <div class="card-body py-2 d-flex gap-2 flex-wrap align-items-center">
    <span style="font-size:12px;font-weight:700;color:#6b7280">Lọc giao hàng:</span>
    <?php
    $filters = [
      ''          => ['label'=>'Tất cả',      'color'=>'secondary'],
      'pending'   => ['label'=>'Chờ giao',    'color'=>'warning'],
      'overdue'   => ['label'=>'Quá hạn',     'color'=>'danger'],
      'delivered' => ['label'=>'Đã giao',     'color'=>'success'],
      'self_pickup'=> ['label'=>'Tại quầy',   'color'=>'secondary'],
    ];
    foreach ($filters as $fs => $fi): ?>
    <a href="?page=invoices&branch=<?= $reqBranch ?>&ym=<?= $yearMonth ?>&ds=<?= $fs ?>"
       class="btn btn-sm <?= $filterStatus === $fs ? 'btn-'.$fi['color'] : 'btn-outline-'.$fi['color'] ?>">
      <?= $fi['label'] ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Bảng hóa đơn -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>Mã hóa đơn</th>
          <th>Khách hàng</th>
          <th class="text-end">Tổng tiền</th>
          <th>Thanh toán</th>
          <th>Giao hàng</th>
          <th>Ngày lập</th>
          <th class="text-center" style="min-width:130px">Thao tác</th>
        </tr></thead>
        <tbody>
        <?php
        $today = date('Y-m-d');
        $list  = array_reverse($invoices);

        // Lọc theo trạng thái
        if ($filterStatus) {
            $list = array_filter($list, function($inv) use ($filterStatus, $today) {
                $st   = $inv['delivery_status'] ?? 'self_pickup';
                $date = $inv['delivery_date']   ?? '';
                if ($filterStatus === 'overdue')
                    return $st === 'pending' && $date && $date < $today;
                return $st === $filterStatus;
            });
        }

        if (empty($list)): ?>
        <tr><td colspan="7">
          <div class="empty-state"><i class="bi bi-receipt"></i>
            <p><?= $filterStatus ? 'Không có hóa đơn nào với bộ lọc này' : 'Chưa có hóa đơn trong tháng này' ?></p>
          </div>
        </td></tr>
        <?php else: foreach ($list as $inv):
          $st     = $inv['delivery_status'] ?? 'self_pickup';
          $date   = $inv['delivery_date']   ?? '';
          $overdue= $st === 'pending' && $date && $date < $today;
          $rowBg  = $overdue ? 'style="background:#fff5f5"' : '';
        ?>
        <tr <?= $rowBg ?>>
          <td><code style="font-size:11px"><?= htmlspecialchars(substr($inv['id']??'',0,20)) ?></code></td>
          <td>
            <div class="fw-600" style="font-size:13.5px"><?= htmlspecialchars($inv['customer']??'Khách lẻ') ?></div>
            <?php if (!empty($inv['phone'])): ?>
            <div style="font-size:12px;color:#9ca3af"><?= htmlspecialchars($inv['phone']) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-end money fw-700 text-success"><?= formatMoney($inv['total']??0) ?></td>
          <td>
            <?php $pm = $inv['payment'] ?? 'cash'; ?>
            <span class="badge <?= match($pm){'cash'=>'bg-success','transfer'=>'bg-info','credit'=>'bg-warning text-dark',default=>'bg-secondary'} ?>">
              <?= match($pm){'cash'=>'Tiền mặt','transfer'=>'CK','cod'=>'COD','credit'=>'Công nợ',default=>$pm} ?>
            </span>
          </td>
          <td><?= deliveryBadge($inv) ?></td>
          <td style="font-size:12px;color:#9ca3af"><?= htmlspecialchars(substr($inv['created_at']??'',0,16)) ?></td>
          <td class="text-center">
            <div class="d-flex gap-1 justify-content-center">
              <!-- Xem chi tiết -->
              <button class="btn btn-sm btn-outline-secondary" title="Xem hóa đơn"
                onclick='viewInvoice(<?= json_encode($inv, JSON_HEX_APOS|JSON_UNESCAPED_UNICODE) ?>)'>
                <i class="bi bi-eye"></i>
              </button>

              <!-- Sửa hóa đơn (chỉ admin/superadmin + chưa giao) -->
              <?php if (in_array(currentUser()['role'],['superadmin','admin']) && $st !== 'delivered'): ?>
              <a href="index.php?page=edit_invoice&branch=<?= $reqBranch ?>&id=<?= urlencode($inv['id']??'') ?>&ym=<?= $yearMonth ?>"
                 class="btn btn-sm btn-outline-warning" title="Sửa hóa đơn">
                <i class="bi bi-pencil"></i>
              </a>
              <?php endif; ?>

              <!-- In phiếu giao hàng (chỉ khi có giao hàng) -->
              <?php if (in_array($st, ['pending','delivered'])): ?>
              <button class="btn btn-sm btn-outline-warning" title="In phiếu giao hàng"
                onclick='printDelivery(<?= json_encode($inv, JSON_HEX_APOS|JSON_UNESCAPED_UNICODE) ?>)'>
                <i class="bi bi-truck"></i>
              </button>
              <?php endif; ?>

              <!-- Đánh dấu đã giao -->
              <?php if ($st === 'pending'): ?>
              <form method="POST" action="index.php?page=invoices&branch=<?= $reqBranch ?>&action=delivered&id=<?= urlencode($inv['id']??'') ?>&ym=<?= $yearMonth ?>" class="d-inline"
                 onsubmit="return confirm('Xác nhận đã giao hàng cho đơn này?')">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-sm btn-outline-success" title="Đánh dấu đã giao">
                  <i class="bi bi-check2-circle"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($invoices)): ?>
        <tfoot>
          <tr class="fw-700">
            <td colspan="2" class="text-end">Tổng cộng:</td>
            <td class="text-end money text-success"><?= formatMoney($totalRevenue) ?></td>
            <td colspan="4"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<!-- Modal xem chi tiết hóa đơn -->
<div class="modal fade" id="invoiceDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Chi Tiết Hóa Đơn</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="invoiceDetailBody"></div>
      <div class="modal-footer">
        <button class="btn btn-outline-warning" id="btnPrintDelivery" style="display:none"
          onclick="printDeliveryFromModal()">
          <i class="bi bi-truck me-1"></i>In Phiếu Giao Hàng
        </button>
        <button class="btn btn-outline-secondary" onclick="printInvoice()">
          <i class="bi bi-printer me-1"></i>In Hóa Đơn
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>

<script>
let _currentInv = null;

function _esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function _money(n) { return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(Number(n)||0); }
function _payLabel(p) { return {cash:'Tiền mặt',transfer:'Chuyển khoản',cod:'COD',credit:'Công nợ'}[p]||p||''; }
function _formatDateVN(value) {
  if (!value) return '';
  const date = new Date(value);
  if (isNaN(date.getTime())) return _esc(value);
  const d = String(date.getDate()).padStart(2, '0');
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const y = date.getFullYear();
  return `Ngày ${d} tháng ${m} năm ${y}`;
}
function _deliveryLabel(inv) {
  const st = inv.delivery_status||'self_pickup';
  const dt = inv.delivery_date||'';
  const today = new Date().toISOString().slice(0,10);
  if (st==='delivered')   return '<span style="color:#10b981;font-weight:700">✓ Đã giao '+(inv.delivered_at||'').slice(0,10)+'</span>';
  if (st==='pending' && dt < today) return '<span style="color:#ef4444;font-weight:700">⚠ Quá hạn — '+dt+'</span>';
  if (st==='pending')     return '<span style="color:#f59e0b;font-weight:700">🚚 Giao ngày '+dt+'</span>';
  return '<span style="color:#6b7280">Khách lấy tại quầy</span>';
}

function viewInvoice(inv) {
  _currentInv = inv;
  const hasDelivery = inv.delivery_status === 'pending' || inv.delivery_status === 'delivered';
  document.getElementById('btnPrintDelivery').style.display = hasDelivery ? '' : 'none';

  const rows = (inv.items||[]).map(i=>`
    <tr>
      <td><code style="font-size:12px">${_esc(i.product_code)}</code></td>
      <td>${_esc(i.product_name)}</td>
      <td class="text-center">${Number(i.qty).toLocaleString('vi-VN')} ${_esc(i.unit)}</td>
      <td class="text-end">${_money(i.price_out)}</td>
      <td class="text-end fw-700 text-success">${_money(i.line_total)}</td>
    </tr>`).join('');

  document.getElementById('invoiceDetailBody').innerHTML = `
    <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom">
      <div>
        <div class="fw-800" style="font-size:16px"><?= htmlspecialchars(BUSINESS['name']) ?></div>
        <div style="font-size:12px;color:#6b7280;margin-top:2px">📍 <?= htmlspecialchars(BUSINESS['address']) ?></div>
        <div style="font-size:12px;color:#6b7280">📞 <?= htmlspecialchars(BUSINESS['phone']) ?><?= !empty(BUSINESS['tax_code']) ? ' &nbsp;|&nbsp; MST: ' . htmlspecialchars(BUSINESS['tax_code']) : '' ?></div>
      </div>
      <div class="text-end">
        <div style="font-size:12px;color:#6b7280">Mã hóa đơn</div>
        <code style="font-size:13px;font-weight:700">${_esc(inv.id)}</code>
        <div class="text-muted" style="font-size:12px;margin-top:2px">${_esc(inv.created_at||'')}</div>
      </div>
    </div>
    <div class="row g-2 mb-3" style="font-size:13.5px">
      <div class="col-6"><span class="text-muted">Khách hàng:</span> <b>${_esc(inv.customer||'Khách lẻ')}</b></div>
      <div class="col-6"><span class="text-muted">SĐT:</span> ${_esc(inv.phone||'—')}</div>
      <div class="col-6"><span class="text-muted">Thanh toán:</span> <b>${_payLabel(inv.payment)}</b></div>
      <div class="col-6"><span class="text-muted">Người lập:</span> ${_esc(inv.created_by||'')}</div>
      <div class="col-12"><span class="text-muted">Giao hàng:</span> ${_deliveryLabel(inv)}
        ${inv.address ? '<br><span class="text-muted">Địa chỉ:</span> <b>'+_esc(inv.address)+'</b>' : ''}
        ${inv.delivery_note ? '<br><span class="text-muted">GC tài xế:</span> '+_esc(inv.delivery_note) : ''}
      </div>
    </div>
    <table class="table table-bordered table-sm mb-2">
      <thead class="table-light">
        <tr><th>Mã SP</th><th>Tên hàng hóa</th><th class="text-center">Số lượng</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th></tr>
      </thead>
      <tbody>${rows}</tbody>
      <tfoot>
        <tr><td colspan="4" class="text-end fw-700">Tổng hàng hóa:</td>
          <td class="text-end fw-800 text-success" style="font-size:15px">${_money((inv.total||0)-(inv.shipping_fee||0))}</td></tr>
        ${(inv.shipping_fee||0)>0?`<tr><td colspan="4" class="text-end fw-700">Giá vận chuyển:</td>
          <td class="text-end fw-800 text-warning" style="font-size:15px">${_money(inv.shipping_fee||0)}</td></tr>`:''}
        <tr style="background:#f9fafb"><td colspan="4" class="text-end fw-700">TỔNG CỘNG:</td>
          <td class="text-end fw-800 text-success" style="font-size:15px">${_money(inv.total||0)}</td></tr>
      </tfoot>
    </table>
    ${inv.note ? `<div class="text-muted" style="font-size:13px"><b>Ghi chú:</b> ${_esc(inv.note)}</div>` : ''}
  `;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('invoiceDetailModal')).show();
}

// ── In hóa đơn thường ─────────────────────────────────────────
function printInvoice() {
  const inv = _currentInv;
  if (!inv) return;

  const BIZ = <?= json_encode([
    'name'        => BUSINESS['name'],
    'address'     => BUSINESS['address'],
    'phone'       => BUSINESS['phone'],
    'slogan'      => BUSINESS['slogan'] ?? '',
    'branch_vlxd' => BRANCHES['branch_1_vlxd']['name'],
    'branch_mt'   => BRANCHES['branch_2_maiton']['name'],
  ], JSON_UNESCAPED_UNICODE) ?>;

  const branchName = inv.branch === 'branch_1_vlxd' ? BIZ.branch_vlxd
                   : inv.branch === 'branch_2_maiton' ? BIZ.branch_mt : '';
  const payLabel = {cash:'Tiền mặt',transfer:'Chuyển khoản',cod:'COD',credit:'Công nợ'};

  const rows = (inv.items||[]).map((item, idx) => `
    <tr>
      <td style="text-align:center">${idx+1}</td>
      <td>
        <div style="font-weight:bold">${_esc(item.product_name)}</div>
        <div style="font-size:11pt;color:#666">${_esc(item.product_code)}</div>
      </td>
      <td style="text-align:center;font-weight:bold;font-size:15pt">${Number(item.qty).toLocaleString('vi-VN')}</td>
      <td style="text-align:center">${_esc(item.unit)}</td>
      <td style="text-align:right">${_money(item.price_out)}</td>
      <td style="text-align:right;font-weight:bold">${_money(item.line_total)}</td>
    </tr>`).join('');

  const win = window.open('', '_blank', 'width=900,height=750');
  win.document.write(`<!DOCTYPE html>
<html lang="vi"><head>
<meta charset="UTF-8">
<title>Hóa Đơn</title>
<style>
  @page { size: A4; margin: 12mm 16mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Times New Roman', serif; font-size: 12pt; color: #000; line-height:1.05 }

  /* Header doanh nghiệp — chỉ thông tin công ty, căn giữa. spacing reduced */
  .biz-header {
    text-align: center;
    padding-bottom: 3mm;
    margin-bottom: 4mm;
  }
  .biz-name    { font-size: 16pt; font-weight: bold; letter-spacing: .5px; }
  .biz-contact { font-size: 12.5pt; color: #222; margin-top: 1mm; font-weight: bold; }
  .biz-slogan  { font-size: 10pt; color: #777; font-style: italic; margin-top: 1mm; }

  /* Tiêu đề */
  .inv-title {
    text-align: center;
    font-size: 16pt;
    font-weight: bold;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin: 4mm 0 4mm;
  }

  /* Thông tin khách: label và giá trị trên cùng 1 hàng để tiết kiệm không gian */
  .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5mm 6mm;
    font-size: 12.5pt;
    margin-bottom: 4mm;
    padding: 2.5mm 4mm;
    border: 1px solid #bbb;
    border-radius: 2mm;
    background: #fafafa;
  }
  .info-grid > div { display:flex; align-items:center; gap:6mm; }
  .info-label { color: #666; font-size: 10.5pt; width:110px; flex-shrink:0; }
  .info-val   { font-weight: bold; font-size: 13pt; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .info-grid > div:first-child .info-val { font-size: 17pt; }

  /* Bảng hàng hóa */
  table { width: 100%; border-collapse: collapse; margin-bottom: 5mm; font-size: 13pt; }
  thead tr { background: #e0e0e0; }
  th { border: 1px solid #888; padding: 2mm 3mm; font-weight: bold; font-size: 12.5pt; }
  td { border: 1px solid #bbb; padding: 2mm 3mm; vertical-align: middle; }
  tfoot td { background: #efefef; font-weight: bold; font-size: 14pt; }

  .inv-note { font-size: 11.5pt; color: #444; margin-bottom: 3mm;
    padding: 1.5mm 0; border-top: 1px dashed #ccc; }
  .inv-signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; margin-top: 2mm; }
  .sign-box { display:flex; flex-direction:column; align-items:center; justify-content:flex-start; gap:0.5mm; text-align: center; font-size: 12.5pt; }
  .sign-date { font-size: 10.8pt; color: #444; margin-bottom: 0; }
  .sign-title { font-weight: bold; margin-bottom: 0; }
  .sign-line { min-height: 8pt; margin-bottom: 0; width: 90%; }
  .sign-hint, .sign-name { font-size: 10.5pt; color: #444; margin-top:0; line-height:1.1; }
  .sign-name { font-weight: 700; }
</style>
</head><body>

<!-- Header: chỉ thông tin doanh nghiệp, căn giữa -->
<div class="biz-header">
  <div class="biz-name">${_esc(BIZ.name)}</div>
  <div class="biz-contact">📍 ${_esc(BIZ.address)}</div>
  <div class="biz-contact">📞 ${_esc(BIZ.phone)}</div>
  ${BIZ.slogan ? `<div class="biz-slogan">"${_esc(BIZ.slogan)}"</div>` : ''}
</div>

<!-- Tiêu đề -->
<div class="inv-title">Hóa Đơn Bán Hàng</div>

<!-- Thông tin khách -->
<div class="info-grid">
  <div><div class="info-label">Khách hàng</div><div class="info-val">${_esc(inv.customer||'Khách lẻ')}</div></div>
  <div><div class="info-label">Số điện thoại</div><div class="info-val">${_esc(inv.phone||'—')}</div></div>
  <div><div class="info-label">Hình thức thanh toán</div><div class="info-val">${payLabel[inv.payment]||inv.payment||''}</div></div>
  <div><div class="info-label">Người lập hóa đơn</div><div class="info-val">${_esc(inv.created_by||'')}</div></div>
  ${inv.address ? `<div style="grid-column:1/-1"><div class="info-label">Địa chỉ giao hàng</div><div class="info-val">${_esc(inv.address)}</div></div>` : ''}
</div>

<!-- Bảng hàng hóa -->
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
    <tr>
      <td colspan="5" style="text-align:right">Tổng hàng hóa:</td>
      <td style="text-align:right;font-size:16pt">${_money((inv.total||0)-(inv.shipping_fee||0))}</td>
    </tr>
    ${(inv.shipping_fee||0)>0?`<tr>
      <td colspan="5" style="text-align:right">Giá vận chuyển:</td>
      <td style="text-align:right;font-size:16pt">${_money(inv.shipping_fee||0)}</td>
    </tr>`:''}
    <tr style="background:#efefef">
      <td colspan="5" style="text-align:right;font-weight:bold">TỔNG CỘNG:</td>
      <td style="text-align:right;font-weight:bold;font-size:18pt">${_money(inv.total||0)}</td>
    </tr>
  </tfoot>
</table>

${inv.note ? `<div class="inv-note"><b>Ghi chú:</b> ${_esc(inv.note)}</div>` : ''}

<div class="inv-note"><b>Lưu ý:</b> Kiểm tra hàng hóa và toa đầy đủ trước khi thanh toán. Khi thanh toán, vui lòng ký và ghi họ tên.</div>

<div class="inv-signatures">
  <div class="sign-box">
    <div class="sign-title">Người nhận hàng</div>
    <div class="sign-line"></div>
    <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
  </div>
  <div class="sign-box">
    <div class="sign-date">${_formatDateVN(inv.created_at || inv.createdAt || new Date().toISOString())}</div>
    <div class="sign-title">Người lập hóa đơn</div>
    <div class="sign-line"></div>
    <div class="sign-name">${_esc(inv.created_by||'')}</div>
  </div>
</div>

<script>window.onload = function(){ window.print(); window.close(); }<\/script>
</body></html>`);
  win.document.close();
}

// ── In phiếu giao hàng ───────────────────────────────────────
function printDelivery(inv) { _printDeliveryDoc(inv); }
function printDeliveryFromModal() { if (_currentInv) _printDeliveryDoc(_currentInv); }

function _printDeliveryDoc(inv) {
  const BIZ = <?= json_encode([
    'name'        => BUSINESS['name'],
    'address'     => BUSINESS['address'],
    'phone'       => BUSINESS['phone'],
    'slogan'      => BUSINESS['slogan'] ?? '',
    'branch_vlxd' => BRANCHES['branch_1_vlxd']['name'],
    'branch_mt'   => BRANCHES['branch_2_maiton']['name'],
  ], JSON_UNESCAPED_UNICODE) ?>;

  const branchName = inv.branch === 'branch_1_vlxd' ? BIZ.branch_vlxd
                   : inv.branch === 'branch_2_maiton' ? BIZ.branch_mt : '';

  const deliveryDate = inv.delivery_date
    ? new Date(inv.delivery_date + 'T00:00:00').toLocaleDateString('vi-VN',{weekday:'long',day:'2-digit',month:'2-digit',year:'numeric'})
    : '—';

  const rows = (inv.items||[]).map((i, idx) => `
    <tr>
      <td style="text-align:center">${idx+1}</td>
      <td>
        <div style="font-weight:bold;font-size:15pt">${_esc(i.product_name)}</div>
        <div style="font-size:12pt;color:#666">${_esc(i.product_code)}</div>
      </td>
      <td style="text-align:center;font-size:18pt;font-weight:bold">${Number(i.qty).toLocaleString('vi-VN')}</td>
      <td style="text-align:center;font-size:14pt">${_esc(i.unit)}</td>
      <td></td>
    </tr>`).join('');

  const win = window.open('', '_blank', 'width=900,height=750');
  win.document.write(`<!DOCTYPE html>
<html lang="vi"><head>
<meta charset="UTF-8">
<title>Phiếu Giao Hàng</title>
<style>
  @page { size: A4; margin: 12mm 16mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Times New Roman', serif; font-size: 14pt; color: #000; }

  /* Header: chỉ doanh nghiệp, căn giữa */
  .biz-header {
    text-align: center;
    padding-bottom: 5mm;
    margin-bottom: 5mm;
  }
  .biz-name    { font-size: 18pt; font-weight: bold; letter-spacing: .5px; }
  .biz-branch  { font-size: 12pt; color: #555; margin-top: 2mm; }
  .biz-contact { font-size: 14pt; color: #222; margin-top: 2mm; font-weight: bold; }
  .biz-slogan  { font-size: 11pt; color: #777; font-style: italic; margin-top: 2mm; }

  /* Tiêu đề */
  .doc-title {
    text-align: center;
    font-size: 20pt;
    font-weight: bold;
    letter-spacing: 3px;
    text-transform: uppercase;
    margin: 5mm 0 6mm;
  }

  /* Thông tin giao hàng */
  .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2.5mm 8mm;
    font-size: 13pt;
    margin-bottom: 6mm;
    padding: 4mm 5mm;
    border: 1px solid #bbb;
    border-radius: 2mm;
    background: #fafafa;
  }
  .info-label { color: #666; font-size: 11pt; }
  .info-val   { font-weight: bold; font-size: 15pt; }
  .delivery-date {
    font-size: 15pt;
    font-weight: bold;
    background: #fff9c4;
    border: 1px solid #f59e0b;
    border-radius: 2mm;
    padding: 2mm 4mm;
    display: inline-block;
  }

  /* Ghi chú tài xế */
  .driver-note {
    margin-bottom: 5mm;
    padding: 3mm 5mm;
    background: #f0f9ff;
    border-left: 3px solid #3b82f6;
    font-size: 13pt;
  }

  /* Bảng hàng */
  table { width: 100%; border-collapse: collapse; margin-bottom: 7mm; font-size: 14pt; }
  thead tr { background: #e0e0e0; }
  th { border: 1px solid #888; padding: 3mm 4mm; font-weight: bold; font-size: 13pt; }
  td { border: 1px solid #bbb; padding: 3.5mm 4mm; vertical-align: middle; }
  tfoot td { background: #efefef; font-weight: bold; font-size: 15pt; }

  /* Ký tên */
  .sign-row {
    display: flex;
    justify-content: space-around;
    margin-top: 14mm;
    text-align: center;
  }
  .sign-box  { width: 140px; }
  .sign-name { font-weight: bold; font-size: 13pt; }
  .sign-line { border-top: 1px solid #000; margin-top: 20mm; padding-top: 2mm; font-size: 11pt; color: #555; }
</style>
</head><body>

<!-- Header: chỉ doanh nghiệp, căn giữa -->
<div class="biz-header">
  <div class="biz-name">${_esc(BIZ.name)}</div>
  ${branchName ? `<div class="biz-branch"> ${_esc(branchName)}</div>` : ''}
  <div class="biz-contact">📍 ${_esc(BIZ.address)}</div>
  <div class="biz-contact">📞 ${_esc(BIZ.phone)}</div>
  ${BIZ.slogan ? `<div class="biz-slogan">"${_esc(BIZ.slogan)}"</div>` : ''}
</div>

<!-- Tiêu đề -->
<div class="doc-title">Phiếu Giao Hàng</div>

<!-- Thông tin giao hàng -->
<div class="info-grid">
  <div>
    <div class="info-label">Khách hàng</div>
    <div class="info-val">${_esc(inv.customer||'')}</div>
  </div>
  <div>
    <div class="info-label">Số điện thoại</div>
    <div class="info-val">${_esc(inv.phone||'—')}</div>
  </div>
  <div>
    <div class="info-label">Ngày giao hàng</div>
    <div class="delivery-date">${deliveryDate}</div>
  </div>
  <div>
    <div class="info-label">Địa chỉ giao hàng</div>
    <div class="info-val" style="font-size:13pt">${_esc(inv.address||'—')}</div>
  </div>
</div>

${inv.delivery_note ? `
<div class="driver-note">
  <b>Lưu ý:</b> ${_esc(inv.delivery_note)}
</div>` : ''}

<!-- Bảng hàng hóa -->
<table>
  <thead>
    <tr>
      <th style="width:40px;text-align:center">STT</th>
      <th style="text-align:left">Tên hàng hóa</th>
      <th style="width:90px;text-align:center">Số lượng</th>
      <th style="width:65px;text-align:center">ĐVT</th>
      <th style="width:110px">Ghi chú</th>
    </tr>
  </thead>
  <tbody>${rows}</tbody>
  <tfoot>
    <tr>
      <td colspan="2" style="text-align:right;font-size:13pt">Tổng số loại hàng:</td>
      <td style="text-align:center">${(inv.items||[]).length}</td>
      <td colspan="2"></td>
    </tr>
  </tfoot>
</table>

${inv.note ? `<div style="margin-bottom:6mm;font-size:13pt;color:#444"><b>Ghi chú:</b> ${_esc(inv.note)}</div>` : ''}

<!-- Ký tên -->
<div class="sign-row">
  <div class="sign-box">
    <div class="sign-name">Người giao hàng</div>
    <div class="sign-line">(Ký, ghi rõ họ tên)</div>
  </div>
  <div class="sign-box">
    <div class="sign-name">Người nhận hàng</div>
    <div class="sign-line">(Ký, ghi rõ họ tên)</div>
  </div>
  <div class="sign-box">
    <div class="sign-name">Xác nhận cửa hàng</div>
    <div class="sign-line">(Ký, đóng dấu)</div>
  </div>
</div>

<script>window.onload = function(){ window.print(); window.close(); }<\/script>
</body></html>`);
  win.document.close();
}
</script>
<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
