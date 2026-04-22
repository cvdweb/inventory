<?php
$pageTitle   = 'Tìm Kiếm Hóa Đơn';
$keyword     = trim($_GET['q'] ?? '');
$filterBranch= $_GET['branch'] ?? '';

// Xác định chi nhánh tìm kiếm (theo quyền)
$accessibleBranches = getAccessibleBranches();
$searchBranches     = $filterBranch && isset($accessibleBranches[$filterBranch])
    ? [$filterBranch]
    : array_keys($accessibleBranches);

$results   = $keyword ? searchInvoices($searchBranches, $keyword) : [];
$searched  = $keyword !== '';

include BASE_PATH . '/views/layouts/header.php';

function deliveryBadgeSearch(array $inv): string {
    $st   = $inv['delivery_status'] ?? 'self_pickup';
    $date = $inv['delivery_date']   ?? '';
    $today= date('Y-m-d');
    return match(true) {
        $st==='delivered'              => '<span class="badge" style="background:#10b981;font-size:10px">Đã giao</span>',
        $st==='pending'&&$date<$today  => '<span class="badge" style="background:#ef4444;font-size:10px">Quá hạn</span>',
        $st==='pending'                => '<span class="badge" style="background:#f59e0b;font-size:10px">Chờ giao</span>',
        default                        => '<span class="badge bg-secondary" style="font-size:10px">Tại quầy</span>',
    };
}
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-search me-2" style="color:#8b5cf6"></i>Tìm Kiếm Hóa Đơn</h2>
    <p>Tìm xuyên suốt tất cả tháng, năm — theo tên khách, SĐT, mã/tên sản phẩm</p>
  </div>
</div>

<!-- Form tìm kiếm -->
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" action="index.php" id="searchForm">
      <input type="hidden" name="page" value="search_invoices">
      <div class="row g-2 align-items-end">

        <!-- Ô tìm kiếm chính -->
        <div class="col-md-6">
          <label class="form-label fw-700">Từ khóa tìm kiếm</label>
          <div style="position:relative">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none"></i>
            <input type="text" name="q" id="searchInput"
              class="form-control form-control-lg"
              style="padding-left:40px;font-size:15px"
              placeholder="Tên khách, SĐT, mã màu sơn, tên SP..."
              value="<?= htmlspecialchars($keyword) ?>"
              autocomplete="off" autofocus>
          </div>
          <div class="form-text d-none">
            <i class="bi bi-lightbulb me-1 text-warning"></i>
            Ví dụ: <code>NE3025</code> (mã màu sơn), <code>0901234</code> (SĐT), <code>Nguyễn Văn</code> (tên khách)
          </div>
        </div>

        <!-- Lọc chi nhánh -->
        <div class="col-md-3">
          <label class="form-label fw-700">Chi nhánh</label>
          <select name="branch" class="form-select form-select-lg">
            <option value="">Tất cả chi nhánh</option>
            <?php foreach ($accessibleBranches as $bId => $b): ?>
            <option value="<?= $bId ?>" <?= $filterBranch===$bId?'selected':'' ?>>
              <?= htmlspecialchars($b['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Nút tìm -->
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary btn-lg w-100">
            <i class="bi bi-search me-2"></i>Tìm Kiếm
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Kết quả -->
<?php if (!$searched): ?>
<!-- Trạng thái chờ tìm -->
<div class="card">
  <div class="card-body">
    <div class="empty-state" style="padding:60px 20px">
      <i class="bi bi-receipt-cutoff" style="font-size:52px;opacity:.2;display:block;margin-bottom:16px"></i>
      <p style="color:#9ca3af;font-size:14px;margin-bottom:8px">Nhập từ khóa để tìm kiếm hóa đơn</p>
      <p style="color:#c4c9d4;font-size:13px">
        Hệ thống sẽ tìm qua tất cả tháng và năm đã lưu
      </p>
    </div>
    <!-- Gợi ý tìm kiếm -->
    <div class="row g-2 mt-2" style="max-width:600px;margin:0 auto">
      <?php
      $tips = [
        ['icon'=>'bi-palette','color'=>'#8b5cf6','label'=>'Mã màu sơn','example'=>'NE3025, SW6119'],
        ['icon'=>'bi-person',  'color'=>'#3b82f6','label'=>'Tên khách hàng','example'=>'Nguyễn Văn An'],
        ['icon'=>'bi-phone',   'color'=>'#10b981','label'=>'Số điện thoại','example'=>'0901234567'],
        ['icon'=>'bi-box',     'color'=>'#f59e0b','label'=>'Tên sản phẩm','example'=>'Xi Măng, Tôn Lạnh'],
        ['icon'=>'bi-upc',     'color'=>'#ef4444','label'=>'Mã sản phẩm','example'=>'XM001, TL002'],
        ['icon'=>'bi-receipt', 'color'=>'#6b7280','label'=>'Mã hóa đơn','example'=>'INV-VLXD-...'],
      ];
      foreach ($tips as $t): ?>
      <div class="col-md-4">
        <div onclick="document.getElementById('searchInput').value='<?= $t['example'] ?>';document.getElementById('searchInput').focus()"
          style="border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;cursor:pointer;transition:all .15s"
          onmouseover="this.style.borderColor='<?= $t['color'] ?>';this.style.background='#fafafa'"
          onmouseout="this.style.borderColor='#e5e7eb';this.style.background=''">
          <div class="d-flex align-items-center gap-2">
            <i class="bi <?= $t['icon'] ?>" style="color:<?= $t['color'] ?>;font-size:16px"></i>
            <div>
              <div style="font-weight:700;font-size:12.5px"><?= $t['label'] ?></div>
              <div style="font-size:11px;color:#9ca3af;font-family:'JetBrains Mono',monospace"><?= $t['example'] ?></div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif (empty($results)): ?>
<!-- Không tìm thấy -->
<div class="card">
  <div class="card-body">
    <div class="empty-state" style="padding:48px 20px">
      <i class="bi bi-search" style="font-size:48px;opacity:.2;display:block;margin-bottom:12px"></i>
      <p style="font-size:15px;font-weight:700;color:#374151;margin-bottom:6px">
        Không tìm thấy kết quả cho "<span style="color:#8b5cf6"><?= htmlspecialchars($keyword) ?></span>"
      </p>
      <p style="font-size:13px;color:#9ca3af">
        Thử tìm với từ khóa khác, hoặc kiểm tra lại mã sản phẩm / tên khách hàng
      </p>
    </div>
  </div>
</div>

<?php else: ?>
<!-- Có kết quả -->
<div class="d-flex align-items-center justify-content-between mb-2">
  <div style="font-size:13.5px;color:#374151">
    Tìm thấy <strong style="color:#8b5cf6"><?= count($results) ?></strong> hóa đơn
    cho từ khóa "<strong><?= htmlspecialchars($keyword) ?></strong>"
    <?php if ($filterBranch): ?>
    trong <strong><?= htmlspecialchars($accessibleBranches[$filterBranch]['name'] ?? '') ?></strong>
    <?php endif; ?>
  </div>
  <?php if (count($results) >= 100): ?>
  <span class="badge bg-warning text-dark">Hiển thị 100 kết quả đầu — hãy thu hẹp từ khóa</span>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>Mã hóa đơn</th>
          <th>Khách hàng</th>
          <th>Sản phẩm khớp</th>
          <th>Chi nhánh</th>
          <th class="text-end">Tổng tiền</th>
          <th>Giao hàng</th>
          <th>Ngày lập</th>
          <th class="text-center">Thao tác</th>
        </tr></thead>
        <tbody>
        <?php foreach ($results as $inv):
          // Highlight các item khớp keyword
          $kwLow    = mb_strtolower($keyword, 'UTF-8');
          $matchedItems = array_filter($inv['items'] ?? [], function($item) use ($kwLow) {
              return str_contains(mb_strtolower($item['product_code']??'','UTF-8'), $kwLow)
                  || str_contains(mb_strtolower($item['product_name']??'','UTF-8'), $kwLow);
          });
          $branch    = $inv['_branch'] ?? '';
          $ym        = $inv['_ym']     ?? date('Y_m');
          $canEdit   = in_array(currentUser()['role'],['superadmin','admin'])
                    && ($inv['delivery_status']??'') !== 'delivered';
        ?>
        <tr>
          <td>
            <code style="font-size:11px;word-break:break-all"><?= htmlspecialchars($inv['id']??'') ?></code>
            <div style="font-size:10px;color:#9ca3af;font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($ym) ?></div>
          </td>
          <td>
            <div class="fw-600" style="font-size:13.5px"><?= htmlspecialchars($inv['customer']??'') ?></div>
            <?php if (!empty($inv['phone'])): ?>
            <div style="font-size:12px;color:#9ca3af"><?= htmlspecialchars($inv['phone']) ?></div>
            <?php endif; ?>
          </td>
          <td style="max-width:280px">
            <?php if (!empty($matchedItems)): ?>
              <?php foreach ($matchedItems as $mi): ?>
              <div style="margin-bottom:3px">
                <span style="background:#f3e8ff;color:#7c3aed;border-radius:4px;padding:1px 6px;font-size:11px;font-family:'JetBrains Mono',monospace;font-weight:700">
                  <?= htmlspecialchars($mi['product_code']??'') ?>
                </span>
                <span style="font-size:12.5px;margin-left:4px"><?= htmlspecialchars($mi['product_name']??'') ?></span>
                <span style="font-size:11px;color:#9ca3af;margin-left:4px">× <?= number_format($mi['qty']??0,2,',','.') ?> <?= htmlspecialchars($mi['unit']??'') ?></span>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <span style="font-size:12px;color:#9ca3af;font-style:italic">Khớp theo thông tin khách</span>
            <?php endif; ?>
          </td>
          <td>
            <?php $bi = BRANCHES[$branch] ?? null; if ($bi): ?>
            <span class="badge bg-<?= $bi['color'] ?> bg-opacity-15 text-<?= $bi['color'] ?>"
              style="font-size:11px"><?= htmlspecialchars($bi['short']) ?></span>
            <?php endif; ?>
          </td>
          <td class="text-end money fw-700 text-success"><?= formatMoney($inv['total']??0) ?></td>
          <td><?= deliveryBadgeSearch($inv) ?></td>
          <td style="font-size:12px;white-space:nowrap">
            <?= htmlspecialchars(substr($inv['created_at']??'',0,16)) ?>
          </td>
          <td class="text-center">
            <div class="d-flex gap-1 justify-content-center">
              <!-- Xem chi tiết -->
              <button class="btn btn-sm btn-outline-secondary" title="Xem hóa đơn"
                onclick='viewInvoice(<?= json_encode($inv, JSON_HEX_APOS|JSON_UNESCAPED_UNICODE) ?>)'>
                <i class="bi bi-eye"></i>
              </button>
              <!-- Lập HĐ mới từ đơn này -->
              <a href="index.php?page=invoice&branch=<?= $branch ?>"
                 class="btn btn-sm btn-outline-primary" title="Tạo hóa đơn mới cho chi nhánh này">
                <i class="bi bi-plus-circle"></i>
              </a>
              <!-- Sửa (admin + chưa giao) -->
              <?php if ($canEdit): ?>
              <a href="index.php?page=edit_invoice&branch=<?= $branch ?>&id=<?= urlencode($inv['id']??'') ?>&ym=<?= $ym ?>"
                 class="btn btn-sm btn-outline-warning" title="Sửa hóa đơn">
                <i class="bi bi-pencil"></i>
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal xem chi tiết -->
<div class="modal fade" id="invoiceDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Chi Tiết Hóa Đơn</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="invoiceDetailBody"></div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" onclick="printInvoiceDetail()">
          <i class="bi bi-printer me-1"></i>In
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>

<script>
const HIGHLIGHT_KW = <?= json_encode(mb_strtolower($keyword, 'UTF-8')) ?>;

function _esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function _money(n){return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(Number(n)||0);}
function _payLabel(p){return {cash:'Tiền mặt',transfer:'Chuyển khoản',cod:'COD',credit:'Công nợ'}[p]||p||'';}

function viewInvoice(inv) {
  const branchName = inv._branch_name || '';
  const rows = (inv.items||[]).map(i => {
    // Highlight sản phẩm khớp keyword
    const matched = HIGHLIGHT_KW && (
      (i.product_code||'').toLowerCase().includes(HIGHLIGHT_KW) ||
      (i.product_name||'').toLowerCase().includes(HIGHLIGHT_KW)
    );
    const rowStyle = matched ? 'background:#fef9c3;font-weight:700' : '';
    const codeStyle= matched ? 'color:#7c3aed;font-weight:800' : '';
    return `<tr style="${rowStyle}">
      <td><code style="font-size:12px;${codeStyle}">${_esc(i.product_code)}</code>
        ${matched ? '<span style="font-size:10px;color:#7c3aed;margin-left:4px">✦ khớp</span>' : ''}
      </td>
      <td>${_esc(i.product_name)}</td>
      <td class="text-center">${Number(i.qty).toLocaleString('vi-VN')} ${_esc(i.unit)}</td>
      <td class="text-end">${_money(i.price_out)}</td>
      <td class="text-end fw-700">${_money(i.line_total)}</td>
    </tr>`;
  }).join('');

  document.getElementById('invoiceDetailBody').innerHTML = `
    <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom">
      <div>
        <div class="fw-800" style="font-size:17px">${_esc(branchName)}</div>
        <div class="text-muted" style="font-size:12px"><?= APP_NAME ?></div>
      </div>
      <div class="text-end">
        <div style="font-size:12px;color:#6b7280">Mã hóa đơn</div>
        <code style="font-size:13px;font-weight:700">${_esc(inv.id)}</code>
        <div class="text-muted" style="font-size:12px">${_esc(inv.created_at||'')}</div>
      </div>
    </div>
    <div class="row g-2 mb-3" style="font-size:13.5px">
      <div class="col-6"><span class="text-muted">Khách hàng:</span> <b>${_esc(inv.customer||'Khách lẻ')}</b></div>
      <div class="col-6"><span class="text-muted">SĐT:</span> ${_esc(inv.phone||'—')}</div>
      <div class="col-6"><span class="text-muted">Thanh toán:</span> ${_payLabel(inv.payment)}</div>
      <div class="col-6"><span class="text-muted">Người lập:</span> ${_esc(inv.created_by||'')}</div>
      ${inv.address ? `<div class="col-12"><span class="text-muted">Địa chỉ:</span> ${_esc(inv.address)}</div>` : ''}
    </div>
    ${HIGHLIGHT_KW ? '<div style="font-size:12px;color:#7c3aed;margin-bottom:8px;background:#faf5ff;padding:6px 10px;border-radius:6px"><i class="bi bi-stars me-1"></i>Dòng màu vàng = sản phẩm khớp từ khóa tìm kiếm</div>' : ''}
    <table class="table table-bordered table-sm mb-2">
      <thead class="table-light">
        <tr><th>Mã SP</th><th>Tên hàng hóa</th><th class="text-center">SL</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th></tr>
      </thead>
      <tbody>${rows}</tbody>
      <tfoot>
        <tr><td colspan="4" class="text-end fw-700">Tổng cộng:</td>
          <td class="text-end fw-800 text-success" style="font-size:15px">${_money(inv.total||0)}</td>
        </tr>
      </tfoot>
    </table>
    ${inv.note ? `<div class="text-muted" style="font-size:13px"><b>Ghi chú:</b> ${_esc(inv.note)}</div>` : ''}
  `;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('invoiceDetailModal')).show();
}

function printInvoiceDetail() {
  const body = document.getElementById('invoiceDetailBody');
  if (!body) return;
  const win = window.open('','_blank','width=900,height=700');
  win.document.write(`<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>Hóa Đơn</title>
  <style>
    @page{size:A4;margin:15mm 20mm}*{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Times New Roman',serif;font-size:13pt}
    .d-flex{display:flex}.justify-content-between{justify-content:space-between}
    .align-items-start{align-items:flex-start}.mb-3{margin-bottom:5mm}.pb-3{padding-bottom:4mm}
    .border-bottom{border-bottom:2px solid #000}.text-muted{color:#555}.text-end{text-align:right}
    .fw-700,.fw-800,b,strong{font-weight:bold}.row{display:grid;grid-template-columns:1fr 1fr;gap:2mm 8mm;margin-bottom:5mm}
    code{font-family:'Courier New',monospace;font-weight:bold}
    table{width:100%;border-collapse:collapse;font-size:12pt;margin-bottom:5mm}
    thead tr{background:#f0f0f0}th{border:1px solid #888;padding:2.5mm 3mm;font-weight:bold}
    td{border:1px solid #bbb;padding:2mm 3mm}tfoot td{font-weight:bold;font-size:14pt;background:#f9f9f9}
    .text-success{color:#000}.table-light{background:#f0f0f0}.table-sm td,.table-sm th{padding:2mm 3mm}
    i[class^="bi"]{display:none}
  </style></head><body>
  <div>${body.innerHTML}</div>
  <script>window.onload=function(){window.print();window.close()}<\/script></body></html>`);
  win.document.close();
}

// Enter để submit
document.getElementById('searchInput')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') document.getElementById('searchForm').submit();
});
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
