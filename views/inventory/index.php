<?php
$reqBranch = $_GET['branch'] ?? firstAccessibleBranchId();
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }
$branchInfo = getBranchInfo($reqBranch);
$products = getAllProducts($reqBranch);
usort($products, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
$adjustments = getInventoryAdjustments($reqBranch);
$isAdmin = in_array(currentUser()['role'] ?? '', ['superadmin', 'admin'], true);
$types = inventoryAdjustmentTypes();
$reasons = inventoryAdjustmentReasons();
$statusInfo = [
    'draft' => ['label'=>'Chờ duyệt','class'=>'warning','icon'=>'bi-clock'],
    'approved' => ['label'=>'Đã duyệt','class'=>'success','icon'=>'bi-check-circle'],
    'cancelled' => ['label'=>'Đã hủy','class'=>'secondary','icon'=>'bi-x-circle'],
    'reversed' => ['label'=>'Đã hoàn tác','class'=>'danger','icon'=>'bi-arrow-counterclockwise'],
];
$draftCount = count(array_filter($adjustments, fn($row) => ($row['status'] ?? '') === 'draft'));
$approvedMonth = count(array_filter($adjustments, fn($row) => ($row['status'] ?? '') === 'approved' && str_starts_with($row['approved_at'] ?? '', date('Y-m'))));
$pageTitle = 'Kiểm Kê Kho — ' . $branchInfo['name'];
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-clipboard-check-fill me-2 text-primary"></i>Kiểm Kê & Điều Chỉnh Kho</h2>
    <p><?= htmlspecialchars($branchInfo['name']) ?> · Mọi thay đổi phải được chủ cửa hàng duyệt</p>
  </div>
  <div class="d-flex gap-2">
    <a href="index.php?page=help&topic=inventory" class="btn btn-outline-secondary context-help-btn" title="Hướng dẫn kiểm kê kho"><i class="bi bi-question-circle"></i><span class="context-help-label">Hướng dẫn</span></a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjustmentModal"><i class="bi bi-plus-lg me-1"></i>Lập Phiếu</button>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="stat-card stat-amber"><div class="stat-icon"><i class="bi bi-clock-history"></i></div><div class="stat-value"><?= $draftCount ?></div><div class="stat-label">Chờ duyệt</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card stat-green"><div class="stat-icon"><i class="bi bi-check2-circle"></i></div><div class="stat-value"><?= $approvedMonth ?></div><div class="stat-label">Đã duyệt tháng này</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card stat-blue"><div class="stat-icon"><i class="bi bi-boxes"></i></div><div class="stat-value"><?= count($products) ?></div><div class="stat-label">Sản phẩm đang bán</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-journal-check"></i></div><div class="stat-value"><?= count($adjustments) ?></div><div class="stat-label">Tổng phiếu</div></div></div>
</div>

<div class="card inventory-history">
  <div class="card-header"><span><i class="bi bi-clock-history me-2"></i>Lịch Sử Kiểm Kê & Điều Chỉnh</span></div>
  <div class="card-body p-0">
    <?php if (!$adjustments): ?>
      <div class="empty-state"><i class="bi bi-clipboard2-check"></i><p>Chưa có phiếu kiểm kê hoặc điều chỉnh kho</p></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle inventory-table">
        <thead><tr><th>Mã phiếu</th><th>Loại phiếu</th><th>Lý do</th><th class="text-center">Mặt hàng</th><th>Trạng thái</th><th>Người lập</th><th class="text-center">Thao tác</th></tr></thead>
        <tbody data-progressive-list data-progressive-initial="15" data-progressive-batch="15" data-progressive-controls="inventoryMore">
        <?php foreach ($adjustments as $row):
          $status = $statusInfo[$row['status'] ?? 'draft'] ?? $statusInfo['draft'];
          $itemsJson = htmlspecialchars(json_encode($row['items'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        ?>
          <tr data-progressive-item>
            <td><code><?= htmlspecialchars($row['id'] ?? '') ?></code><div class="small text-muted mt-1"><?= !empty($row['created_at']) ? date('H:i d/m/Y', strtotime($row['created_at'])) : '' ?></div></td>
            <td class="fw-700"><?= htmlspecialchars($types[$row['type'] ?? ''] ?? '') ?></td>
            <td><div class="fw-600"><?= htmlspecialchars($row['reason'] ?? '') ?></div><?php if(!empty($row['note'])): ?><small class="text-muted"><?= htmlspecialchars($row['note']) ?></small><?php endif; ?></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-secondary" data-items="<?= $itemsJson ?>" onclick="showAdjustmentItems(this)"><?= count($row['items'] ?? []) ?> mặt hàng</button></td>
            <td><span class="badge bg-<?= $status['class'] ?> bg-opacity-15 text-<?= $status['class'] ?>"><i class="bi <?= $status['icon'] ?> me-1"></i><?= $status['label'] ?></span></td>
            <td><div class="fw-600"><?= htmlspecialchars($row['created_by'] ?? '') ?></div><?php if(!empty($row['approved_by'])): ?><small class="text-success">Duyệt: <?= htmlspecialchars($row['approved_by']) ?></small><?php endif; ?></td>
            <td class="text-center"><div class="d-flex justify-content-center gap-1 flex-wrap">
              <?php if (($row['status'] ?? '') === 'draft' && $isAdmin): ?>
              <form method="POST" action="index.php?page=inventory&branch=<?= urlencode($reqBranch) ?>&action=approve" onsubmit="return confirm('Duyệt phiếu và cập nhật tồn kho?')"><?= csrfField() ?><input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>"><button class="btn btn-sm btn-primary" title="Duyệt"><i class="bi bi-check2"></i></button></form>
              <?php endif; ?>
              <?php if (($row['status'] ?? '') === 'draft' && ($isAdmin || ($row['created_by_username'] ?? '') === (currentUser()['username'] ?? ''))): ?>
              <form method="POST" action="index.php?page=inventory&branch=<?= urlencode($reqBranch) ?>&action=cancel" onsubmit="return confirm('Hủy phiếu nháp này?')"><?= csrfField() ?><input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>"><button class="btn btn-sm btn-outline-secondary" title="Hủy"><i class="bi bi-x-lg"></i></button></form>
              <?php endif; ?>
              <?php if (($row['status'] ?? '') === 'approved' && $isAdmin): ?>
              <form method="POST" action="index.php?page=inventory&branch=<?= urlencode($reqBranch) ?>&action=reverse" onsubmit="return reverseAdjustment(this)"><?= csrfField() ?><input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>"><input type="hidden" name="reason"><button class="btn btn-sm btn-outline-danger" title="Hoàn tác"><i class="bi bi-arrow-counterclockwise"></i></button></form>
              <?php endif; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div id="inventoryMore"></div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="adjustmentModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <form method="POST" action="index.php?page=inventory&branch=<?= urlencode($reqBranch) ?>&action=create" onsubmit="return prepareAdjustmentSubmit()">
        <?= csrfField() ?><input type="hidden" name="items" id="adjustmentItemsJson">
        <div class="modal-header"><div><div class="text-uppercase text-muted fw-700" style="font-size:11px">Quản lý tồn kho</div><h5 class="modal-title">Lập Phiếu Kiểm Kê / Điều Chỉnh</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-4"><label class="form-label">Loại phiếu *</label><select class="form-select" name="type" id="adjustmentType" onchange="onAdjustmentTypeChange()"><option value="stocktake">Kiểm kê thực tế</option><option value="increase">Điều chỉnh tăng</option><option value="decrease">Điều chỉnh giảm</option></select></div>
            <div class="col-md-4"><label class="form-label">Lý do *</label><select class="form-select" name="reason" id="adjustmentReason" required></select></div>
            <div class="col-md-4"><label class="form-label">Ghi chú</label><input class="form-control" name="note" placeholder="Thông tin bổ sung..."></div>
          </div>
          <div class="inventory-product-picker">
            <div class="flex-grow-1"><label class="form-label">Thêm sản phẩm</label><input class="form-control" id="inventoryProductCode" list="inventoryProductList" placeholder="Nhập mã hoặc chọn sản phẩm..."><datalist id="inventoryProductList"><?php foreach($products as $product): ?><option value="<?= htmlspecialchars($product['code']) ?>"><?= htmlspecialchars($product['name']) ?> · tồn <?= rtrim(rtrim(number_format((float)($product['stock']??0),2,',','.'),'0'),',') ?> <?= htmlspecialchars($product['unit']??'') ?></option><?php endforeach; ?></datalist></div>
            <button type="button" class="btn btn-outline-primary" onclick="addInventoryProduct()"><i class="bi bi-plus-lg me-1"></i>Thêm</button>
          </div>
          <div class="table-responsive mt-3"><table class="table align-middle inventory-entry-table"><thead><tr><th>Sản phẩm</th><th class="text-end">Tồn hệ thống</th><th id="entryValueHeading" class="text-end">Tồn thực tế</th><th class="text-end">Chênh lệch</th><th></th></tr></thead><tbody id="inventoryEntryBody"><tr id="inventoryEmptyRow"><td colspan="5" class="text-center text-muted py-4">Chưa thêm sản phẩm</td></tr></tbody></table></div>
          <div class="alert alert-light border mb-0" style="font-size:13px"><i class="bi bi-info-circle me-1 text-primary"></i>Phiếu mới chỉ ở trạng thái chờ duyệt. Nếu tồn kho thay đổi trước khi duyệt, hệ thống sẽ yêu cầu kiểm kê lại.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary"><i class="bi bi-send-check me-1"></i>Gửi Chờ Duyệt</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="adjustmentItemsModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Chi Tiết Chênh Lệch</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Sản phẩm</th><th class="text-end">Tồn lúc lập</th><th class="text-end">Tồn thực tế</th><th class="text-end">Chênh lệch</th></tr></thead><tbody id="adjustmentDetailBody"></tbody></table></div></div></div></div></div>

<style>
.inventory-product-picker{display:flex;align-items:end;gap:10px;padding:14px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px}.inventory-table code{font-size:11px}.inventory-entry-table input{min-width:110px;text-align:right}.difference-positive{color:#059669;font-weight:800}.difference-negative{color:#dc2626;font-weight:800}@media(max-width:767px){.inventory-product-picker{align-items:stretch;flex-direction:column}.inventory-product-picker .btn{width:100%}.inventory-history .table{min-width:850px}.inventory-entry-table{min-width:680px}}
</style>

<script>
const INVENTORY_PRODUCTS = <?= json_encode(array_column(array_map(fn($p)=>['code'=>$p['code'],'name'=>$p['name'],'unit'=>$p['unit']??'','stock'=>(float)($p['stock']??0)],$products),null,'code'), JSON_UNESCAPED_UNICODE|JSON_HEX_APOS) ?>;
const INVENTORY_REASONS = <?= json_encode($reasons, JSON_UNESCAPED_UNICODE) ?>;
const inventoryItems = new Map();
const invNum = n => new Intl.NumberFormat('vi-VN',{maximumFractionDigits:2}).format(Number(n)||0);
const invEsc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

function onAdjustmentTypeChange(){const type=document.getElementById('adjustmentType').value;const reason=document.getElementById('adjustmentReason');reason.innerHTML=(INVENTORY_REASONS[type]||[]).map(v=>`<option value="${invEsc(v)}">${invEsc(v)}</option>`).join('');document.getElementById('entryValueHeading').textContent=type==='stocktake'?'Tồn thực tế':'Số lượng điều chỉnh';renderInventoryItems();}
function addInventoryProduct(){const input=document.getElementById('inventoryProductCode');const code=input.value.trim();const product=INVENTORY_PRODUCTS[code];if(!product){showToast('Vui lòng chọn sản phẩm hợp lệ.','warning');return}if(!inventoryItems.has(code))inventoryItems.set(code,{...product,value:document.getElementById('adjustmentType').value==='stocktake'?product.stock:1});input.value='';renderInventoryItems();}
function removeInventoryProduct(code){inventoryItems.delete(code);renderInventoryItems()}
function updateInventoryValue(code,value){const item=inventoryItems.get(code);if(!item)return;item.value=Math.max(0,Number(value)||0);renderInventoryDifference(code)}
function inventoryDifference(item){const type=document.getElementById('adjustmentType').value;if(type==='stocktake')return item.value-item.stock;return (type==='increase'?1:-1)*item.value}
function renderInventoryDifference(code){const item=inventoryItems.get(code);const el=document.getElementById('diff_'+CSS.escape(code));if(!item||!el)return;const diff=inventoryDifference(item);el.className='text-end '+(diff>0?'difference-positive':diff<0?'difference-negative':'text-muted');el.textContent=(diff>0?'+':'')+invNum(diff)+' '+item.unit}
function renderInventoryItems(){const body=document.getElementById('inventoryEntryBody');const type=document.getElementById('adjustmentType').value;if(!inventoryItems.size){body.innerHTML='<tr><td colspan="5" class="text-center text-muted py-4">Chưa thêm sản phẩm</td></tr>';return}body.innerHTML=[...inventoryItems.values()].map(item=>`<tr><td><strong>${invEsc(item.name)}</strong><div class="small text-muted">${invEsc(item.code)} · ${invEsc(item.unit)}</div></td><td class="text-end fw-700">${invNum(item.stock)} ${invEsc(item.unit)}</td><td><input type="number" min="0" step="any" class="form-control" value="${item.value}" oninput="updateInventoryValue('${invEsc(item.code)}',this.value)"></td><td id="diff_${invEsc(item.code)}"></td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeInventoryProduct('${invEsc(item.code)}')"><i class="bi bi-trash"></i></button></td></tr>`).join('');[...inventoryItems.keys()].forEach(renderInventoryDifference)}
function prepareAdjustmentSubmit(){if(!inventoryItems.size){showToast('Vui lòng thêm ít nhất một sản phẩm.','warning');return false}const type=document.getElementById('adjustmentType').value;const items=[...inventoryItems.values()].map(item=>type==='stocktake'?{code:item.code,actual_qty:item.value}:{code:item.code,adjust_qty:item.value});if(type!=='stocktake'&&items.some(item=>item.adjust_qty<=0)){showToast('Số lượng điều chỉnh phải lớn hơn 0.','warning');return false}document.getElementById('adjustmentItemsJson').value=JSON.stringify(items);return true}
function showAdjustmentItems(button){const items=JSON.parse(button.dataset.items||'[]');document.getElementById('adjustmentDetailBody').innerHTML=items.map(item=>{const diff=Number(item.difference)||0;return`<tr><td><strong>${invEsc(item.product_name)}</strong><div class="small text-muted">${invEsc(item.product_code)} · ${invEsc(item.unit)}</div></td><td class="text-end">${invNum(item.system_qty)}</td><td class="text-end">${item.actual_qty===null?'—':invNum(item.actual_qty)}</td><td class="text-end ${diff>0?'difference-positive':diff<0?'difference-negative':''}">${diff>0?'+':''}${invNum(diff)}</td></tr>`}).join('');bootstrap.Modal.getOrCreateInstance(document.getElementById('adjustmentItemsModal')).show()}
function reverseAdjustment(form){const reason=prompt('Lý do hoàn tác phiếu:');if(reason===null)return false;if(!reason.trim()){showToast('Vui lòng nhập lý do.','warning');return false}form.querySelector('[name="reason"]').value=reason.trim();return confirm('Hoàn tác sẽ đảo ngược thay đổi tồn kho. Tiếp tục?')}
document.getElementById('inventoryProductCode')?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();addInventoryProduct()}});onAdjustmentTypeChange();
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
