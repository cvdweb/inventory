<?php
$reqBranch = $_GET['branch'] ?? firstAccessibleBranchId();
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }
$branchInfo = getBranchInfo($reqBranch);
$returns = getSalesReturns($reqBranch);
$isAdmin = in_array(currentUser()['role'] ?? '', ['superadmin', 'admin'], true);
$invoiceOptions = [];
foreach (getAllInvoiceFiles($reqBranch) as $invoiceFile) {
    foreach (readJson($invoiceFile) as $invoice) {
        if (invoiceIsCancelled($invoice) || !in_array($invoice['delivery_status'] ?? 'self_pickup', ['delivered','self_pickup'], true)) continue;
        $used = salesReturnQuantitiesForInvoice($reqBranch, $invoice['id'] ?? '', true);
        $availableItems = [];
        foreach ($invoice['items'] ?? [] as $lineIndex => $item) {
            $code = (string)($item['product_code'] ?? '');
            $lineKey = salesReturnInvoiceLineKey($item, $lineIndex);
            $remaining = max(0, (float)($item['qty'] ?? 0) - (float)($used['items'][$lineKey] ?? $used['items'][$code] ?? 0));
            if ($remaining <= 0) continue;
            $availableItems[] = [
                'code'=>$code, 'line'=>$lineIndex, 'line_key'=>$lineKey, 'name'=>$item['product_name'] ?? $code, 'unit'=>$item['unit'] ?? '',
                'sold_qty'=>(float)($item['qty'] ?? 0), 'remaining'=>$remaining,
                'price'=>(float)($item['price_out'] ?? 0),
            ];
        }
        $shippingRemaining = max(0, (float)($invoice['shipping_fee'] ?? 0) - (float)$used['shipping_refund']);
        if (!$availableItems) continue;
        $invoiceOptions[$invoice['id']] = [
            'id'=>$invoice['id'], 'customer'=>$invoice['customer'] ?? 'Khách lẻ', 'phone'=>$invoice['phone'] ?? '',
            'payment'=>$invoice['payment'] ?? 'cash', 'total'=>(float)($invoice['total'] ?? 0),
            'created_at'=>$invoice['created_at'] ?? '', 'items'=>$availableItems,
            'shipping_remaining'=>$shippingRemaining,
        ];
    }
}
uasort($invoiceOptions, fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
$selectedInvoice = (string)($_GET['invoice'] ?? '');
$statusInfo = [
    'draft'=>['label'=>'Chờ duyệt','class'=>'warning'], 'approved'=>['label'=>'Đã duyệt','class'=>'success'],
    'cancelled'=>['label'=>'Đã hủy','class'=>'secondary'], 'reversed'=>['label'=>'Đã hoàn tác','class'=>'danger'],
];
$refundMethods = ['none'=>'Không chi tiền','cash'=>'Tiền mặt','transfer'=>'Chuyển khoản','account_credit'=>'Giảm công nợ'];
$draftCount = count(array_filter($returns, fn($row)=>($row['status']??'')==='draft'));
$monthApproved = array_filter($returns, fn($row)=>($row['status']??'')==='approved' && str_starts_with($row['approved_at']??'',date('Y-m')));
$monthTotal = array_sum(array_map(fn($row)=>(float)($row['refund_total']??0),$monthApproved));
$pageTitle = 'Trả Hàng — ' . $branchInfo['name'];
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div><h2><i class="bi bi-arrow-return-left me-2 text-primary"></i>Trả Hàng Bán</h2><p><?= htmlspecialchars($branchInfo['name']) ?> · Trả theo hóa đơn gốc, có duyệt và lưu lịch sử</p></div>
  <div class="d-flex gap-2"><a href="index.php?page=help&topic=returns" class="btn btn-outline-secondary context-help-btn" title="Hướng dẫn trả hàng"><i class="bi bi-question-circle"></i><span class="context-help-label">Hướng dẫn</span></a><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#returnModal" <?= !$invoiceOptions?'disabled':'' ?>><i class="bi bi-plus-lg me-1"></i>Lập Phiếu Trả</button></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="stat-card stat-amber"><div class="stat-icon"><i class="bi bi-clock-history"></i></div><div class="stat-value"><?= $draftCount ?></div><div class="stat-label">Chờ duyệt</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card stat-green"><div class="stat-icon"><i class="bi bi-check2-circle"></i></div><div class="stat-value"><?= count($monthApproved) ?></div><div class="stat-label">Đã duyệt tháng này</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-cash-coin"></i></div><div class="stat-value return-stat-money"><?= formatMoney($monthTotal) ?></div><div class="stat-label">Giá trị trả tháng này</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card stat-blue"><div class="stat-icon"><i class="bi bi-receipt"></i></div><div class="stat-value"><?= count($invoiceOptions) ?></div><div class="stat-label">Hóa đơn còn có thể trả</div></div></div>
</div>

<div class="card return-history">
  <div class="card-header"><span><i class="bi bi-clock-history me-2"></i>Lịch Sử Trả Hàng</span></div>
  <div class="card-body p-0">
  <?php if(!$returns): ?><div class="empty-state"><i class="bi bi-arrow-return-left"></i><p>Chưa có phiếu trả hàng</p></div>
  <?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0 return-table"><thead><tr><th>Mã phiếu</th><th>Hóa đơn gốc</th><th>Khách hàng</th><th class="text-end">Giá trị trả</th><th>Hoàn tiền</th><th>Trạng thái</th><th class="text-center">Thao tác</th></tr></thead>
  <tbody data-progressive-list data-progressive-initial="15" data-progressive-batch="15" data-progressive-controls="returnsMore">
  <?php foreach($returns as $row): $status=$statusInfo[$row['status']??'draft']??$statusInfo['draft']; $rowJson=htmlspecialchars(json_encode($row,JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8'); ?>
    <tr data-progressive-item>
      <td><code><?= htmlspecialchars($row['id']??'') ?></code><div class="small text-muted"><?= !empty($row['created_at'])?date('H:i d/m/Y',strtotime($row['created_at'])):'' ?></div></td>
      <td><a href="index.php?page=invoices&branch=<?= urlencode($reqBranch) ?>&ym=<?= htmlspecialchars(substr(str_replace('-','_',$row['invoice_created_at']??''),0,7)) ?>"><?= htmlspecialchars($row['invoice_id']??'') ?></a></td>
      <td><strong><?= htmlspecialchars($row['customer']??'Khách lẻ') ?></strong><div class="small text-muted"><?= htmlspecialchars($row['phone']??'') ?></div></td>
      <td class="text-end fw-800"><?= formatMoney((float)($row['refund_total']??0)) ?></td>
      <td><?= htmlspecialchars($refundMethods[$row['refund_method']??'none']??'') ?></td>
      <td><span class="badge bg-<?= $status['class'] ?> bg-opacity-15 text-<?= $status['class'] ?>"><?= $status['label'] ?></span></td>
      <td><div class="d-flex justify-content-center gap-1 flex-wrap">
        <button class="btn btn-sm btn-outline-secondary" data-return="<?= $rowJson ?>" onclick="showReturnDetail(this)" title="Chi tiết"><i class="bi bi-eye"></i></button>
        <?php if(($row['status']??'')==='approved'): ?><button class="btn btn-sm btn-outline-primary" data-return="<?= $rowJson ?>" onclick="printReturn(this)" title="In phiếu"><i class="bi bi-printer"></i></button><?php endif; ?>
        <?php if(($row['status']??'')==='draft' && $isAdmin): ?><form method="POST" action="index.php?page=returns&branch=<?= urlencode($reqBranch) ?>&action=approve" onsubmit="return confirm('Duyệt phiếu trả, cập nhật tồn kho và tài chính?')"><?= csrfField() ?><input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>"><button class="btn btn-sm btn-primary" title="Duyệt"><i class="bi bi-check2"></i></button></form><?php endif; ?>
        <?php if(($row['status']??'')==='draft' && ($isAdmin||($row['created_by_username']??'')===(currentUser()['username']??''))): ?><form method="POST" action="index.php?page=returns&branch=<?= urlencode($reqBranch) ?>&action=cancel" onsubmit="return confirm('Hủy phiếu nháp này?')"><?= csrfField() ?><input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>"><button class="btn btn-sm btn-outline-secondary" title="Hủy"><i class="bi bi-x-lg"></i></button></form><?php endif; ?>
        <?php if(($row['status']??'')==='approved' && $isAdmin): ?><form method="POST" action="index.php?page=returns&branch=<?= urlencode($reqBranch) ?>&action=reverse" onsubmit="return reverseReturn(this)"><?= csrfField() ?><input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>"><input type="hidden" name="reason"><button class="btn btn-sm btn-outline-danger" title="Hoàn tác"><i class="bi bi-arrow-counterclockwise"></i></button></form><?php endif; ?>
      </div></td>
    </tr>
  <?php endforeach; ?></tbody></table></div><div id="returnsMore"></div><?php endif; ?>
  </div>
</div>

<div class="modal fade" id="returnModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
  <form method="POST" action="index.php?page=returns&branch=<?= urlencode($reqBranch) ?>&action=create" onsubmit="return prepareReturnSubmit()">
    <?= csrfField() ?><input type="hidden" name="items" id="returnItemsJson">
    <div class="modal-header"><div><div class="text-uppercase text-muted fw-700 return-eyebrow">Bán hàng</div><h5 class="modal-title">Lập Phiếu Trả Hàng</h5></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-3 mb-3">
        <div class="col-lg-8"><label class="form-label">Hóa đơn gốc *</label><select class="form-select" name="invoice_id" id="returnInvoice" onchange="renderReturnInvoice()" required><option value="">Chọn hóa đơn khách trả hàng...</option><?php foreach($invoiceOptions as $invoice): ?><option value="<?= htmlspecialchars($invoice['id']) ?>" <?= $selectedInvoice===$invoice['id']?'selected':'' ?>><?= htmlspecialchars($invoice['id'].' · '.$invoice['customer'].' · '.date('d/m/Y',strtotime($invoice['created_at']))) ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-4"><div class="return-invoice-summary" id="returnInvoiceSummary">Chọn hóa đơn để xem hàng có thể trả</div></div>
      </div>
      <div class="table-responsive"><table class="table align-middle return-entry-table"><thead><tr><th style="width:42px"></th><th>Sản phẩm</th><th class="text-end">Còn có thể trả</th><th style="width:150px">Số lượng trả</th><th class="text-center">Nhập lại kho</th><th class="text-end">Tiền hoàn</th></tr></thead><tbody id="returnItemBody"><tr><td colspan="6" class="text-center text-muted py-4">Chưa chọn hóa đơn</td></tr></tbody></table></div>
      <div class="row g-3 mt-1">
        <div class="col-md-4"><label class="form-label">Hoàn phí vận chuyển</label><input type="number" min="0" step="1" class="form-control" name="shipping_refund" id="returnShipping" value="0" oninput="updateReturnTotal()"><small class="text-muted" id="returnShippingHint"></small></div>
        <div class="col-md-4"><label class="form-label">Hình thức xử lý tiền *</label><select class="form-select" name="refund_method" id="returnMethod" required></select></div>
        <div class="col-md-4"><label class="form-label">Lý do trả hàng *</label><input class="form-control" name="reason" required placeholder="Ví dụ: giao nhầm, hàng lỗi..."></div>
        <div class="col-md-8"><label class="form-label">Ghi chú</label><input class="form-control" name="note" placeholder="Tình trạng hàng hoặc thỏa thuận với khách..."></div>
        <div class="col-md-4"><div class="return-total-box"><span>Tổng giá trị trả</span><strong id="returnTotal">0 ₫</strong></div></div>
      </div>
      <div class="alert alert-light border mt-3 mb-0 return-note"><i class="bi bi-info-circle text-primary"></i><span>Chỉ chọn “Nhập lại kho” khi hàng còn bán được. Phiếu nháp không thay đổi tồn kho, công nợ hoặc sổ thu chi cho đến khi chủ cửa hàng duyệt.</span></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary"><i class="bi bi-send-check me-1"></i>Gửi Chờ Duyệt</button></div>
  </form>
</div></div></div>

<div class="modal fade" id="returnDetailModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Chi Tiết Phiếu Trả Hàng</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="returnDetailBody"></div></div></div></div>

<style>
.return-stat-money{font-size:18px}.return-table code{font-size:11px}.return-eyebrow{font-size:11px}.return-invoice-summary{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;color:#6b7280;font-size:12px;min-height:58px;padding:10px 12px}.return-entry-table input[type=number]{min-width:105px;text-align:right}.return-total-box{align-items:flex-end;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;display:flex;flex-direction:column;height:100%;justify-content:center;padding:10px 14px}.return-total-box span{color:#92400e;font-size:11px;font-weight:700}.return-total-box strong{color:#111827;font-family:'JetBrains Mono',monospace;font-size:20px}.return-note{align-items:flex-start;display:flex;font-size:12px;gap:8px}.return-detail-head{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;display:grid;gap:10px;grid-template-columns:repeat(3,1fr);padding:12px}.return-detail-head span{color:#6b7280;display:block;font-size:11px}.return-detail-head strong{display:block;margin-top:2px}@media(max-width:767px){.return-history .table{min-width:850px}.return-entry-table{min-width:760px}.return-detail-head{grid-template-columns:1fr}.return-stat-money{font-size:14px}.return-total-box{align-items:flex-start}}
</style>

<script>
const RETURN_INVOICES=<?= json_encode($invoiceOptions,JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const RETURN_BRANCH=<?= json_encode($branchInfo['name']??'',JSON_UNESCAPED_UNICODE|JSON_HEX_APOS) ?>;
const RETURN_METHODS={none:'Không chi tiền',cash:'Hoàn tiền mặt',transfer:'Hoàn chuyển khoản',account_credit:'Giảm công nợ'};
const returnMoney=n=>new Intl.NumberFormat('vi-VN').format(Math.round(Number(n)||0))+' ₫';
const returnNum=n=>new Intl.NumberFormat('vi-VN',{maximumFractionDigits:2}).format(Number(n)||0);
const returnEsc=s=>String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
function selectedReturnInvoice(){return RETURN_INVOICES[document.getElementById('returnInvoice').value]||null}
function renderReturnInvoice(){const inv=selectedReturnInvoice(),body=document.getElementById('returnItemBody'),method=document.getElementById('returnMethod');if(!inv){body.innerHTML='<tr><td colspan="6" class="text-center text-muted py-4">Chưa chọn hóa đơn</td></tr>';document.getElementById('returnInvoiceSummary').textContent='Chọn hóa đơn để xem hàng có thể trả';method.innerHTML='';updateReturnTotal();return}document.getElementById('returnInvoiceSummary').innerHTML=`<strong>${returnEsc(inv.customer)}</strong><div>${returnEsc(inv.id)} · ${returnMoney(inv.total)}</div>`;const methods=inv.payment==='credit'?['account_credit','cash','transfer','none']:['cash','transfer','none'];method.innerHTML=methods.map(key=>`<option value="${key}">${RETURN_METHODS[key]}</option>`).join('');body.innerHTML=inv.items.map((item,i)=>`<tr><td><input class="form-check-input return-check" type="checkbox" data-index="${i}" onchange="toggleReturnRow(this)"></td><td><strong>${returnEsc(item.name)}</strong><div class="small text-muted">${returnEsc(item.code)} · ${returnEsc(item.unit)} · ${returnMoney(item.price)}/${returnEsc(item.unit)}</div></td><td class="text-end fw-700">${returnNum(item.remaining)} ${returnEsc(item.unit)}</td><td><input type="number" class="form-control return-qty" data-index="${i}" min="0" max="${item.remaining}" step="any" value="0" disabled oninput="updateReturnTotal()"></td><td class="text-center"><input class="form-check-input return-restock" data-index="${i}" type="checkbox" checked disabled title="Hàng còn bán được, nhập lại tồn kho"></td><td class="text-end fw-700" id="returnLine_${i}">0 ₫</td></tr>`).join('');const shipping=document.getElementById('returnShipping');shipping.max=inv.shipping_remaining;shipping.value=0;document.getElementById('returnShippingHint').textContent=inv.shipping_remaining>0?'Tối đa '+returnMoney(inv.shipping_remaining):'Hóa đơn không còn phí vận chuyển để hoàn';updateReturnTotal()}
function toggleReturnRow(check){const i=check.dataset.index,qty=document.querySelector(`.return-qty[data-index="${i}"]`),restock=document.querySelector(`.return-restock[data-index="${i}"]`);qty.disabled=!check.checked;restock.disabled=!check.checked;qty.value=check.checked?Math.min(1,Number(qty.max)):0;updateReturnTotal()}
function updateReturnTotal(){const inv=selectedReturnInvoice();let total=Number(document.getElementById('returnShipping').value)||0;if(inv)inv.items.forEach((item,i)=>{const qty=Number(document.querySelector(`.return-qty[data-index="${i}"]`)?.value)||0;const line=qty*item.price;total+=line;const el=document.getElementById('returnLine_'+i);if(el)el.textContent=returnMoney(line)});document.getElementById('returnTotal').textContent=returnMoney(total)}
function prepareReturnSubmit(){const inv=selectedReturnInvoice();if(!inv){showToast('Vui lòng chọn hóa đơn.','warning');return false}const items=[];inv.items.forEach((item,i)=>{const check=document.querySelector(`.return-check[data-index="${i}"]`),qty=Number(document.querySelector(`.return-qty[data-index="${i}"]`)?.value)||0;if(check?.checked&&qty>0)items.push({code:item.code,line:item.line,qty,restock:document.querySelector(`.return-restock[data-index="${i}"]`)?.checked||false})});if(!items.length){showToast('Vui lòng chọn ít nhất một mặt hàng và nhập số lượng trả.','warning');return false}document.getElementById('returnItemsJson').value=JSON.stringify(items);return true}
function showReturnDetail(button){const row=JSON.parse(button.dataset.return||'{}');document.getElementById('returnDetailBody').innerHTML=`<div class="return-detail-head mb-3"><div><span>Mã phiếu</span><strong>${returnEsc(row.id)}</strong></div><div><span>Hóa đơn gốc</span><strong>${returnEsc(row.invoice_id)}</strong></div><div><span>Khách hàng</span><strong>${returnEsc(row.customer)}</strong></div></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Sản phẩm</th><th class="text-end">SL trả</th><th class="text-center">Nhập kho</th><th class="text-end">Tiền hoàn</th></tr></thead><tbody>${(row.items||[]).map(item=>`<tr><td><strong>${returnEsc(item.product_name)}</strong><div class="small text-muted">${returnEsc(item.product_code)} · ${returnEsc(item.unit)}</div></td><td class="text-end">${returnNum(item.qty)}</td><td class="text-center">${item.restock?'<i class="bi bi-check-circle-fill text-success"></i>':'<span class="text-muted">Không</span>'}</td><td class="text-end fw-700">${returnMoney(item.refund_amount)}</td></tr>`).join('')}</tbody><tfoot><tr><th colspan="3">Tổng giá trị trả</th><th class="text-end">${returnMoney(row.refund_total)}</th></tr></tfoot></table></div><div class="small"><strong>Lý do:</strong> ${returnEsc(row.reason)}${row.note?'<br><strong>Ghi chú:</strong> '+returnEsc(row.note):''}</div>`;bootstrap.Modal.getOrCreateInstance(document.getElementById('returnDetailModal')).show()}
function printReturn(button){const row=JSON.parse(button.dataset.return||'{}'),w=window.open('','_blank','width=760,height=850');w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${returnEsc(row.id)}</title><style>body{font-family:Arial,sans-serif;color:#111;font-size:13px;margin:28px}h1{text-align:center;font-size:20px;margin:18px 0 4px}.sub{text-align:center;color:#555;font-size:11px}.meta{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:20px 0}table{border-collapse:collapse;width:100%}th,td{border:1px solid #bbb;padding:8px}th{background:#f5f5f5}.r{text-align:right}.total{font-size:15px;font-weight:bold}.sign{display:grid;grid-template-columns:1fr 1fr;text-align:center;margin-top:35px}.sign div{min-height:100px}.note{font-size:10px;color:#666;margin-top:18px}@media print{body{margin:10mm}}</style></head><body><strong>${returnEsc(RETURN_BRANCH)}</strong><h1>PHIẾU TRẢ HÀNG</h1><div class="sub">Mã phiếu ${returnEsc(row.id)} · ${returnEsc(row.approved_at||row.created_at)}</div><div class="meta"><div>Hóa đơn gốc: <b>${returnEsc(row.invoice_id)}</b></div><div>Khách hàng: <b>${returnEsc(row.customer)}</b></div><div>Lý do: ${returnEsc(row.reason)}</div><div>Xử lý tiền: ${returnEsc(RETURN_METHODS[row.refund_method]||'')}</div></div><table><thead><tr><th>STT</th><th>Sản phẩm</th><th>ĐVT</th><th class="r">SL</th><th class="r">Đơn giá</th><th class="r">Thành tiền</th></tr></thead><tbody>${(row.items||[]).map((item,i)=>`<tr><td>${i+1}</td><td>${returnEsc(item.product_name)}</td><td>${returnEsc(item.unit)}</td><td class="r">${returnNum(item.qty)}</td><td class="r">${returnMoney(item.price_out)}</td><td class="r">${returnMoney(item.refund_amount)}</td></tr>`).join('')}</tbody><tfoot><tr><td colspan="5" class="r total">Tổng giá trị trả</td><td class="r total">${returnMoney(row.refund_total)}</td></tr></tfoot></table><div class="sign"><div><b>Khách hàng</b><br><small>(Ký, ghi rõ họ tên)</small></div><div><b>Người duyệt</b><br><small>(Ký, ghi rõ họ tên)</small></div></div><div class="note">Phiếu nội bộ ghi nhận việc trả hàng theo hóa đơn gốc; không thay thế hóa đơn điện tử/chứng từ thuế.</div><script>window.onload=()=>window.print()<\/script></body></html>`);w.document.close()}
function reverseReturn(form){const reason=prompt('Lý do hoàn tác phiếu trả hàng:');if(reason===null)return false;if(!reason.trim()){showToast('Vui lòng nhập lý do.','warning');return false}form.querySelector('[name="reason"]').value=reason.trim();return confirm('Hoàn tác sẽ trừ lại tồn kho đã nhập và hủy khoản chi hoàn tiền. Tiếp tục?')}
document.addEventListener('DOMContentLoaded',()=>{renderReturnInvoice();<?php if($selectedInvoice && isset($invoiceOptions[$selectedInvoice])): ?>bootstrap.Modal.getOrCreateInstance(document.getElementById('returnModal')).show();<?php endif; ?>});
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
