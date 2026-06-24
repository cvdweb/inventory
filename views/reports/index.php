<?php
$reqBranch = $_GET['branch'] ?? firstAccessibleBranchId();
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }

$branchInfo = getBranchInfo($reqBranch);
$yearMonth = $_GET['ym'] ?? date('Y_m');
if (!preg_match('/^\d{4}_\d{2}$/', $yearMonth)) $yearMonth = date('Y_m');
$data = reportBuildMonthly($reqBranch, $yearMonth);
$cashbookFeature = featureEnabled('cashbook');
$pageTitle = 'Báo Cáo — ' . $branchInfo['name'];

function reportSignedPercent(float $value): string
{
    return ($value > 0 ? '+' : '') . number_format($value, 1, ',', '.') . '%';
}

include BASE_PATH . '/views/layouts/header.php';
?>

<style>
.report-header { align-items:end; display:flex; gap:18px; justify-content:space-between; }
.report-header-actions { align-items:end; display:flex; gap:8px; }
.report-tabs { background:#f3f4f6; border:1px solid #e5e7eb; border-radius:8px; display:flex; gap:4px; margin-bottom:16px; overflow-x:auto; padding:4px; scrollbar-width:none; }
.report-tabs::-webkit-scrollbar { display:none; }
.report-tabs .nav-link { border:0; border-radius:6px; color:#4b5563; font-size:12.5px; font-weight:800; min-height:38px; padding:8px 13px; white-space:nowrap; }
.report-tabs .nav-link.active { background:#fff; box-shadow:0 1px 3px rgba(17,24,39,.1); color:#92400e; }
.report-kpi { background:#fff; border:1px solid #e5e7eb; border-radius:8px; height:100%; padding:15px; }
.report-kpi-head { align-items:center; display:flex; gap:8px; justify-content:space-between; }
.report-kpi-icon { align-items:center; background:#fffbeb; border-radius:7px; color:#d97706; display:inline-flex; font-size:16px; height:34px; justify-content:center; width:34px; }
.report-kpi-trend { border-radius:6px; font-size:10.5px; font-weight:800; padding:4px 6px; }
.report-kpi-trend.up { background:#ecfdf5; color:#047857; }
.report-kpi-trend.down { background:#fef2f2; color:#b91c1c; }
.report-kpi-value { color:#111827; font-family:'JetBrains Mono',monospace; font-size:19px; font-weight:800; margin-top:12px; overflow-wrap:anywhere; }
.report-kpi-label { color:#6b7280; font-size:11.5px; font-weight:700; margin-top:4px; }
.report-kpi-note { color:#9ca3af; font-size:10.5px; line-height:1.4; margin-top:5px; }
.report-card { border:1px solid #e5e7eb; border-radius:8px; box-shadow:none; height:100%; overflow:hidden; }
.report-card .card-header { align-items:center; background:#fff; border-bottom:1px solid #e5e7eb; display:flex; font-size:13px; font-weight:800; justify-content:space-between; min-height:48px; padding:11px 14px; }
.report-card .card-header i { color:#d97706; }
.report-table { font-size:12.5px; }
.report-table th { color:#6b7280; font-size:10.5px; text-transform:uppercase; }
.report-money { font-family:'JetBrains Mono',monospace; font-weight:800; }
.report-row-label { color:#111827; font-weight:700; }
.report-subtext { color:#9ca3af; font-size:10.5px; margin-top:2px; }
.report-progress { background:#f3f4f6; border-radius:4px; height:8px; overflow:hidden; }
.report-progress > span { background:#f59e0b; display:block; height:100%; }
.report-breakdown { display:grid; gap:12px; }
.report-breakdown-row { display:grid; gap:4px; grid-template-columns:minmax(0,1fr) auto; }
.report-breakdown-label { color:#374151; font-size:12px; font-weight:700; }
.report-breakdown-value { color:#111827; font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:800; }
.report-breakdown-meta { color:#9ca3af; font-size:10.5px; }
.report-breakdown-row .report-progress { grid-column:1 / -1; }
.report-note { align-items:flex-start; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; color:#92400e; display:flex; font-size:11.5px; gap:8px; line-height:1.5; margin-bottom:14px; padding:10px 12px; }
.report-aging { display:grid; gap:10px; grid-template-columns:repeat(3,minmax(0,1fr)); }
.report-aging-item { border-right:1px solid #e5e7eb; min-width:0; padding-right:10px; }
.report-aging-item:last-child { border-right:0; padding-right:0; }
.report-aging-item strong { display:block; font-family:'JetBrains Mono',monospace; font-size:13px; overflow-wrap:anywhere; }
.report-aging-item span { color:#6b7280; display:block; font-size:10.5px; margin-top:3px; }
.report-delivery-grid { display:grid; gap:10px; grid-template-columns:repeat(2,minmax(0,1fr)); }
.report-delivery-item { align-items:center; background:#f9fafb; border:1px solid #e5e7eb; border-radius:7px; display:flex; gap:9px; padding:10px; }
.report-delivery-item i { color:#d97706; font-size:18px; }
.report-delivery-item strong { display:block; font-size:16px; }
.report-delivery-item span { color:#6b7280; display:block; font-size:10.5px; }
@media (max-width:768px) {
  .report-header { align-items:stretch; flex-direction:column; }
  .report-header-actions { display:grid; grid-template-columns:1fr auto; }
  .report-header-actions .form-control { min-height:42px; }
  .report-tabs { margin-left:-8px; margin-right:-8px; border-left:0; border-radius:0; border-right:0; }
  .report-kpi { padding:12px; }
  .report-kpi-value { font-size:16px; }
  .report-aging { grid-template-columns:1fr; }
  .report-aging-item { border-bottom:1px solid #e5e7eb; border-right:0; padding:0 0 8px; }
  .report-aging-item:last-child { border-bottom:0; padding-bottom:0; }
}
</style>

<div class="page-header report-header">
  <div>
    <h2><i class="bi bi-bar-chart-fill me-2" style="color:#d97706"></i>Báo Cáo</h2>
    <p><?= htmlspecialchars($branchInfo['name']) ?> — dữ liệu tháng <?= date('m/Y', strtotime(str_replace('_', '-', $yearMonth) . '-01')) ?></p>
  </div>
  <div class="report-header-actions">
    <a href="index.php?page=help&topic=reports" class="btn btn-sm btn-outline-secondary context-help-btn" title="Hướng dẫn đọc báo cáo"><i class="bi bi-question-circle"></i><span class="context-help-label">Hướng dẫn</span></a>
    <form method="GET" class="d-flex gap-2">
      <input type="hidden" name="page" value="reports">
      <input type="hidden" name="branch" value="<?= htmlspecialchars($reqBranch) ?>">
      <input type="month" name="ym_picker" class="form-control form-control-sm" value="<?= str_replace('_', '-', $yearMonth) ?>"
        onchange="this.form.ym.value=this.value.replace('-', '_');this.form.submit()">
      <input type="hidden" name="ym" value="<?= htmlspecialchars($yearMonth) ?>">
      <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i></button>
    </form>
  </div>
</div>

<ul class="nav report-tabs" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#reportBusiness" type="button"><i class="bi bi-graph-up-arrow me-1"></i>Kinh doanh</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#reportFinance" type="button"><i class="bi bi-wallet2 me-1"></i><?= $cashbookFeature?'Thu chi & Công nợ':'Công nợ' ?></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#reportOperations" type="button"><i class="bi bi-boxes me-1"></i>Kho & Giao hàng</button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="reportBusiness">
    <div class="row g-3 mb-3">
      <div class="col-6 col-xl-3"><div class="report-kpi">
        <div class="report-kpi-head"><span class="report-kpi-icon"><i class="bi bi-cash-stack"></i></span><span class="report-kpi-trend <?= $data['revenue_change'] >= 0 ? 'up' : 'down' ?>"><?= reportSignedPercent($data['revenue_change']) ?></span></div>
        <div class="report-kpi-value"><?= formatMoney($data['revenue']) ?></div><div class="report-kpi-label">Doanh thu thuần tháng</div><div class="report-kpi-note">Gộp <?= formatMoney($data['gross_revenue']) ?> · Trả <?= formatMoney($data['sales_returns']) ?></div>
      </div></div>
      <div class="col-6 col-xl-3"><div class="report-kpi">
        <div class="report-kpi-head"><span class="report-kpi-icon"><i class="bi bi-receipt"></i></span></div>
        <div class="report-kpi-value"><?= $data['orders'] ?></div><div class="report-kpi-label">Hóa đơn</div><div class="report-kpi-note"><?= $data['sales_return_count'] ?> phiếu trả · TB <?= formatMoney($data['average_order']) ?> / đơn</div>
      </div></div>
      <div class="col-6 col-xl-3"><div class="report-kpi">
        <div class="report-kpi-head"><span class="report-kpi-icon"><i class="bi bi-graph-up"></i></span></div>
        <div class="report-kpi-value"><?= formatMoney($data['estimated_gross_profit']) ?></div><div class="report-kpi-label">Lãi gộp ước tính</div>
        <div class="report-kpi-note"><?= $data['cost_fallback_lines'] > 0 ? $data['cost_fallback_lines'] . ' dòng cũ dùng giá nhập hiện tại' : 'Toàn bộ dùng giá vốn tại lúc bán' ?></div>
      </div></div>
      <div class="col-6 col-xl-3"><div class="report-kpi">
        <div class="report-kpi-head"><span class="report-kpi-icon"><i class="bi bi-arrow-left-right"></i></span></div>
        <div class="report-kpi-value"><?= formatMoney($data['sales_import_difference']) ?></div><div class="report-kpi-label">Chênh lệch bán - nhập</div><div class="report-kpi-note">Không phải lợi nhuận kế toán</div>
      </div></div>
    </div>

    <?php if ($data['cost_fallback_lines'] > 0): ?>
    <div class="report-note"><i class="bi bi-info-circle-fill"></i><span>Hóa đơn cũ chưa lưu giá vốn tại thời điểm bán nên lãi gộp đang dùng giá nhập hiện tại để ước tính. Hóa đơn mới đã bắt đầu lưu giá vốn, độ chính xác sẽ tăng dần.</span></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
      <div class="col-lg-8"><div class="card report-card">
        <div class="card-header"><span><i class="bi bi-graph-up me-2"></i>Doanh thu theo ngày</span><small class="text-muted"><?= count($data['daily_revenue']) ?> ngày có bán hàng</small></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 report-table">
          <thead><tr><th>Ngày</th><th class="text-center">Đơn</th><th class="text-end">Doanh thu</th><th style="width:32%">Tỷ trọng</th></tr></thead><tbody>
          <?php $maxRevenue = max(array_column($data['daily_revenue'], 'revenue') ?: [1]); ?>
          <?php if (!$data['daily_revenue']): ?><tr><td colspan="4"><div class="empty-state"><i class="bi bi-bar-chart"></i><p>Chưa có dữ liệu bán hàng</p></div></td></tr><?php endif; ?>
          <?php foreach ($data['daily_revenue'] as $day): $pct = $maxRevenue > 0 ? ($day['revenue'] / $maxRevenue * 100) : 0; ?>
          <tr><td class="report-row-label"><?= date('d/m/Y', strtotime($day['date'])) ?></td><td class="text-center"><?= $day['orders'] ?></td><td class="text-end report-money"><?= formatMoney($day['revenue']) ?></td><td><div class="report-progress"><span style="width:<?= round($pct, 1) ?>%"></span></div></td></tr>
          <?php endforeach; ?>
          </tbody></table></div></div>
      </div></div>
      <div class="col-lg-4"><div class="card report-card">
        <div class="card-header"><span><i class="bi bi-credit-card me-2"></i>Cơ cấu thanh toán</span></div>
        <div class="card-body"><div class="report-breakdown">
          <?php foreach ($data['payment_breakdown'] as $payment): $pct = $data['gross_revenue'] > 0 ? $payment['amount'] / $data['gross_revenue'] * 100 : 0; ?>
          <div class="report-breakdown-row"><span class="report-breakdown-label"><?= htmlspecialchars($payment['label']) ?></span><span class="report-breakdown-value"><?= formatMoney($payment['amount']) ?></span><span class="report-breakdown-meta"><?= $payment['orders'] ?> đơn · <?= number_format($pct, 1, ',', '.') ?>%</span><span></span><div class="report-progress"><span style="width:<?= round($pct, 1) ?>%"></span></div></div>
          <?php endforeach; ?>
        </div></div>
      </div></div>
    </div>

    <div class="card report-card"><div class="card-header"><span><i class="bi bi-fire me-2"></i>Sản phẩm bán chạy</span></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 report-table"><thead><tr><th>#</th><th>Sản phẩm</th><th class="text-end">Số lượng</th><th class="text-end">Doanh thu</th></tr></thead><tbody data-progressive-list data-progressive-initial="8" data-progressive-batch="8" data-progressive-controls="reportTopProductsMore">
      <?php if (!$data['top_products']): ?><tr><td colspan="4"><div class="empty-state"><p>Chưa có dữ liệu</p></div></td></tr><?php endif; ?>
      <?php foreach ($data['top_products'] as $rank => $product): ?><tr data-progressive-item><td><span class="badge <?= $rank < 3 ? 'bg-warning text-dark' : 'bg-secondary bg-opacity-10 text-secondary' ?>"><?= $rank + 1 ?></span></td><td><div class="report-row-label"><?= htmlspecialchars($product['name']) ?></div><div class="report-subtext"><?= htmlspecialchars($product['code']) ?></div></td><td class="text-end"><?= number_format($product['qty'], 2, ',', '.') ?></td><td class="text-end report-money"><?= formatMoney($product['revenue']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div><div id="reportTopProductsMore"></div></div></div>
    <?php if($data['sales_return_rows']): ?><div class="card report-card mt-3"><div class="card-header"><span><i class="bi bi-arrow-return-left me-2"></i>Hàng trả trong tháng</span><a class="btn btn-sm btn-outline-primary" href="index.php?page=returns&branch=<?= urlencode($reqBranch) ?>">Mở trả hàng</a></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 report-table"><thead><tr><th>Phiếu trả</th><th>Hóa đơn gốc</th><th>Khách hàng</th><th class="text-center">Mặt hàng</th><th class="text-end">Giá trị trả</th></tr></thead><tbody data-progressive-list data-progressive-initial="8" data-progressive-batch="8" data-progressive-controls="reportReturnsMore">
      <?php foreach($data['sales_return_rows'] as $return): ?><tr data-progressive-item><td class="report-row-label"><?= htmlspecialchars($return['id']??'') ?></td><td><?= htmlspecialchars($return['invoice_id']??'') ?></td><td><?= htmlspecialchars($return['customer']??'Khách lẻ') ?></td><td class="text-center"><?= count($return['items']??[]) ?></td><td class="text-end report-money text-danger"><?= formatMoney((float)($return['refund_total']??0)) ?></td></tr><?php endforeach; ?>
      </tbody></table></div><div id="reportReturnsMore"></div></div></div><?php endif; ?>
  </div>

  <div class="tab-pane fade" id="reportFinance">
    <div class="report-note"><i class="bi bi-info-circle-fill"></i><span><?= $cashbookFeature?'Thu chi bên dưới là các phiếu đã ghi nhận trong Sổ Thu Chi. Doanh thu và dòng tiền là hai khái niệm khác nhau: bán công nợ tạo doanh thu nhưng chưa tạo tiền thu.':'Công nợ được tổng hợp từ hóa đơn bán chịu, phiếu thu và hàng trả đã duyệt.' ?></span></div>
    <div class="row g-3 mb-3">
      <?php $financeKpis = $cashbookFeature ? [
        ['icon'=>'bi-arrow-down-circle','value'=>$data['cashbook']['income'],'label'=>'Tổng thu đã ghi nhận','note'=>$data['cashbook']['count'].' phiếu thu/chi'],
        ['icon'=>'bi-arrow-up-circle','value'=>$data['cashbook']['expense'],'label'=>'Tổng chi đã ghi nhận','note'=>'Theo sổ thu chi'],
        ['icon'=>'bi-wallet','value'=>$data['cashbook']['balance'],'label'=>'Dòng tiền ròng','note'=>'Tổng thu trừ tổng chi'],
        ['icon'=>'bi-person-exclamation','value'=>$data['open_receivable'],'label'=>'Tổng dư nợ hiện tại','note'=>$data['customer_credit'] > 0 ? 'Khách trả dư ' . formatMoney($data['customer_credit']) : count($data['top_debtors']).' khách còn nợ nổi bật'],
      ] : [
        ['icon'=>'bi-receipt','value'=>$data['credit_sales'],'label'=>'Công nợ phát sinh tháng','note'=>'Từ hóa đơn bán chịu'],
        ['icon'=>'bi-cash-coin','value'=>$data['debt_collected'],'label'=>'Đã thu trong tháng','note'=>'Các phiếu thu còn hiệu lực'],
        ['icon'=>'bi-person-exclamation','value'=>$data['open_receivable'],'label'=>'Tổng dư nợ hiện tại','note'=>count($data['top_debtors']).' khách còn nợ nổi bật'],
        ['icon'=>'bi-person-check','value'=>$data['customer_credit'],'label'=>'Khách trả dư','note'=>'Tiền cửa hàng đang giữ của khách'],
      ]; foreach ($financeKpis as $kpi): ?>
      <div class="col-6 col-xl-3"><div class="report-kpi"><div class="report-kpi-head"><span class="report-kpi-icon"><i class="bi <?= $kpi['icon'] ?>"></i></span></div><div class="report-kpi-value"><?= formatMoney($kpi['value']) ?></div><div class="report-kpi-label"><?= $kpi['label'] ?></div><div class="report-kpi-note"><?= $kpi['note'] ?></div></div></div>
      <?php endforeach; ?>
    </div>
    <div class="row g-3 mb-3">
      <?php if($cashbookFeature): ?><div class="col-lg-7"><div class="card report-card"><div class="card-header"><span><i class="bi bi-list-check me-2"></i>Thu chi theo khoản mục</span></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 report-table"><thead><tr><th>Khoản mục</th><th>Loại</th><th class="text-center">Phiếu</th><th class="text-end">Số tiền</th></tr></thead><tbody data-progressive-list data-progressive-initial="8" data-progressive-batch="8" data-progressive-controls="reportCashbookMore">
        <?php if (!$data['cashbook_by_category']): ?><tr><td colspan="4"><div class="empty-state"><p>Chưa có phiếu thu chi</p></div></td></tr><?php endif; ?>
        <?php foreach ($data['cashbook_by_category'] as $row): ?><tr data-progressive-item><td class="report-row-label"><?= htmlspecialchars($row['label']) ?></td><td><span class="badge <?= $row['type'] === 'expense' ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success' ?>"><?= $row['type'] === 'expense' ? 'Chi' : 'Thu' ?></span></td><td class="text-center"><?= $row['count'] ?></td><td class="text-end report-money"><?= formatMoney($row['amount']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><div id="reportCashbookMore"></div></div></div></div><?php endif; ?>
      <div class="<?= $cashbookFeature?'col-lg-5':'col-12' ?>"><div class="card report-card"><div class="card-header"><span><i class="bi bi-hourglass-split me-2"></i>Tuổi nợ hiện tại</span></div><div class="card-body">
        <div class="report-aging"><div class="report-aging-item"><strong class="text-success"><?= formatMoney($data['aging']['under_30']) ?></strong><span>Không quá 30 ngày</span></div><div class="report-aging-item"><strong class="text-warning"><?= formatMoney($data['aging']['days_31_60']) ?></strong><span>31–60 ngày</span></div><div class="report-aging-item"><strong class="text-danger"><?= formatMoney($data['aging']['over_60']) ?></strong><span>Trên 60 ngày</span></div></div>
        <hr><div class="d-flex justify-content-between mb-2"><span class="report-row-label">Công nợ phát sinh tháng</span><strong class="report-money"><?= formatMoney($data['credit_sales']) ?></strong></div><div class="d-flex justify-content-between"><span class="report-row-label">Đã thu trong tháng</span><strong class="report-money text-success"><?= formatMoney($data['debt_collected']) ?></strong></div>
      </div></div></div>
    </div>
    <div class="card report-card"><div class="card-header"><span><i class="bi bi-people me-2"></i>Khách hàng còn nợ nhiều</span><a class="btn btn-sm btn-outline-primary" href="index.php?page=receivables&branch=<?= urlencode($reqBranch) ?>">Mở công nợ</a></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 report-table"><thead><tr><th>Khách hàng</th><th>Liên hệ</th><th class="text-center">Hóa đơn</th><th class="text-end">Còn nợ</th></tr></thead><tbody data-progressive-list data-progressive-initial="8" data-progressive-batch="8" data-progressive-controls="reportDebtorsMore">
      <?php if (!$data['top_debtors']): ?><tr><td colspan="4"><div class="empty-state"><p>Không có công nợ</p></div></td></tr><?php endif; ?>
      <?php foreach ($data['top_debtors'] as $customer): ?><tr data-progressive-item><td class="report-row-label"><?= htmlspecialchars($customer['name']) ?></td><td><?= htmlspecialchars($customer['phone'] ?: '-') ?></td><td class="text-center"><?= (int)$customer['invoice_count'] ?></td><td class="text-end report-money text-danger"><?= formatMoney($customer['balance']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div><div id="reportDebtorsMore"></div></div></div>
  </div>

  <div class="tab-pane fade" id="reportOperations">
    <div class="row g-3 mb-3">
      <?php $stockKpis = [
        ['icon'=>'bi-box-seam','value'=>$data['inventory_value'],'label'=>'Giá trị tồn theo giá nhập','note'=>'Giá trị tồn hiện tại'],
        ['icon'=>'bi-download','value'=>$data['import_total'],'label'=>'Nhập hàng trong tháng','note'=>count($data['imports']).' phiếu nhập'],
      ]; foreach ($stockKpis as $kpi): ?>
      <div class="col-6 col-xl-3"><div class="report-kpi"><div class="report-kpi-head"><span class="report-kpi-icon"><i class="bi <?= $kpi['icon'] ?>"></i></span></div><div class="report-kpi-value"><?= formatMoney($kpi['value']) ?></div><div class="report-kpi-label"><?= $kpi['label'] ?></div><div class="report-kpi-note"><?= $kpi['note'] ?></div></div></div>
      <?php endforeach; ?>
      <div class="col-6 col-xl-3"><div class="report-kpi"><div class="report-kpi-head"><span class="report-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span></div><div class="report-kpi-value"><?= $data['low_stock'] ?></div><div class="report-kpi-label">Sản phẩm sắp hết</div><div class="report-kpi-note">Dưới mức tồn tối thiểu</div></div></div>
      <div class="col-6 col-xl-3"><div class="report-kpi"><div class="report-kpi-head"><span class="report-kpi-icon"><i class="bi bi-x-octagon"></i></span></div><div class="report-kpi-value"><?= $data['out_of_stock'] ?></div><div class="report-kpi-label">Sản phẩm hết hàng</div><div class="report-kpi-note">Tồn kho bằng 0</div></div></div>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-lg-7"><div class="card report-card"><div class="card-header"><span><i class="bi bi-boxes me-2"></i>Tồn kho theo nhóm hàng</span></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 report-table"><thead><tr><th>Nhóm hàng</th><th class="text-center">SP</th><th class="text-center">Sắp hết</th><th class="text-center">Hết hàng</th><th class="text-end">Giá trị tồn</th></tr></thead><tbody data-progressive-list data-progressive-initial="10" data-progressive-batch="10" data-progressive-controls="reportStockMore">
        <?php foreach ($data['stock_by_category'] as $category): ?><tr data-progressive-item><td class="report-row-label"><?= htmlspecialchars($category['name']) ?></td><td class="text-center"><?= $category['products'] ?></td><td class="text-center <?= $category['low'] ? 'text-warning fw-bold' : '' ?>"><?= $category['low'] ?></td><td class="text-center <?= $category['out'] ? 'text-danger fw-bold' : '' ?>"><?= $category['out'] ?></td><td class="text-end report-money"><?= formatMoney($category['value']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><div id="reportStockMore"></div></div></div></div>
      <div class="col-lg-5"><div class="card report-card"><div class="card-header"><span><i class="bi bi-truck me-2"></i>Tình trạng giao hàng</span></div><div class="card-body"><div class="report-delivery-grid">
        <div class="report-delivery-item"><i class="bi bi-shop"></i><div><strong><?= $data['delivery']['self_pickup'] ?></strong><span>Lấy tại quầy</span></div></div>
        <div class="report-delivery-item"><i class="bi bi-clock"></i><div><strong><?= $data['delivery']['pending'] ?></strong><span>Đang chờ giao</span></div></div>
        <div class="report-delivery-item"><i class="bi bi-check-circle"></i><div><strong><?= $data['delivery']['delivered'] ?></strong><span>Đã giao</span></div></div>
        <div class="report-delivery-item"><i class="bi bi-exclamation-circle"></i><div><strong class="text-danger"><?= $data['delivery']['overdue'] ?></strong><span>Quá hạn giao</span></div></div>
      </div><hr><div class="d-flex justify-content-between"><span class="report-row-label">Phí vận chuyển đã thu</span><strong class="report-money"><?= formatMoney($data['delivery']['shipping_revenue']) ?></strong></div></div></div></div>
    </div>
    <div class="row g-3"><div class="col-lg-7"><div class="card report-card"><div class="card-header"><span><i class="bi bi-building me-2"></i>Nhập hàng theo nhà cung cấp</span></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 report-table"><thead><tr><th>Nhà cung cấp</th><th class="text-center">Phiếu nhập</th><th class="text-end">Giá trị nhập</th></tr></thead><tbody data-progressive-list data-progressive-initial="8" data-progressive-batch="8" data-progressive-controls="reportSuppliersMore">
      <?php if (!$data['imports_by_supplier']): ?><tr><td colspan="3"><div class="empty-state"><p>Chưa có dữ liệu nhập hàng</p></div></td></tr><?php endif; ?>
      <?php foreach ($data['imports_by_supplier'] as $supplier): ?><tr data-progressive-item><td class="report-row-label"><?= htmlspecialchars($supplier['name']) ?></td><td class="text-center"><?= $supplier['count'] ?></td><td class="text-end report-money"><?= formatMoney($supplier['amount']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div><div id="reportSuppliersMore"></div></div></div></div>
      <div class="col-lg-5"><div class="card report-card"><div class="card-header"><span><i class="bi bi-clipboard-check me-2"></i>Điều chỉnh tồn kho tháng</span><a class="btn btn-sm btn-outline-primary" href="index.php?page=inventory&branch=<?= urlencode($reqBranch) ?>">Mở kiểm kê</a></div><div class="card-body"><div class="report-delivery-grid"><div class="report-delivery-item"><i class="bi bi-journal-check"></i><div><strong><?= count($data['stock_adjustments']) ?></strong><span>Phiếu đã duyệt</span></div></div><div class="report-delivery-item"><i class="bi bi-plus-circle"></i><div><strong><?= number_format($data['stock_adjustment_increase'],2,',','.') ?></strong><span>Tổng lượng tăng</span></div></div><div class="report-delivery-item"><i class="bi bi-dash-circle"></i><div><strong><?= number_format($data['stock_adjustment_decrease'],2,',','.') ?></strong><span>Tổng lượng giảm</span></div></div><div class="report-delivery-item"><i class="bi bi-arrow-left-right"></i><div><strong><?= number_format($data['stock_adjustment_increase']-$data['stock_adjustment_decrease'],2,',','.') ?></strong><span>Chênh lệch ròng</span></div></div></div></div></div></div>
    </div>
  </div>
</div>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
