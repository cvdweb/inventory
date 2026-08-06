<?php
$pageTitle = 'Dashboard';
include BASE_PATH . '/views/layouts/header.php';

$user      = currentUser();
$branches  = getAccessibleBranches();
$allStats  = [];
$cashbookMonth = date('Y_m');
foreach ($branches as $bId => $b) {
    if (function_exists('cashbookSyncReceivablePayments')) {
        cashbookSyncReceivablePayments($bId, $cashbookMonth);
    }
    $cashbookEntries = function_exists('getCashbookEntries') ? getCashbookEntries($bId, $cashbookMonth) : [];
    $allStats[$bId] = [
        'info' => $b,
        'stats' => getDashboardStats($bId),
        'cashbook' => function_exists('cashbookSummary') ? cashbookSummary($cashbookEntries) : ['income' => 0, 'expense' => 0, 'balance' => 0, 'count' => 0],
    ];
}

// Tổng hợp
$totalOrders  = array_sum(array_column(array_column($allStats, 'stats'), 'today_orders'));
$totalRevenue = array_sum(array_column(array_column($allStats, 'stats'), 'today_revenue'));
$totalLow     = array_sum(array_column(array_column($allStats, 'stats'), 'low_stock'));
$totalCashIncome = array_sum(array_column(array_column($allStats, 'cashbook'), 'income'));
$totalCashExpense = array_sum(array_column(array_column($allStats, 'cashbook'), 'expense'));
$totalCashBalance = $totalCashIncome - $totalCashExpense;
$totalCashCount = array_sum(array_column(array_column($allStats, 'cashbook'), 'count'));
$license      = licenseGet();
$licenseStatus = licenseStatus($license);
$licensePayments = $license['payments'] ?? [];
$lastLicensePayment = !empty($licensePayments) ? end($licensePayments) : null;
$licensePackageLabel = $lastLicensePayment
    ? ((int)($lastLicensePayment['package_months'] ?? 0) . ' tháng')
    : 'Chưa thanh toán';
if ($lastLicensePayment && (int)($lastLicensePayment['free_months'] ?? 0) > 0) {
    $licensePackageLabel .= ' (giảm phí ' . (int)$lastLicensePayment['free_months'] . ' tháng)';
}
$dashboardFeatureProfile = featureProfileInfo();
?>

<div class="page-header d-flex align-items-center justify-content-between">
  <div>
    <h2><i class="bi bi-grid-1x2-fill me-2 text-amber"></i>Dashboard</h2>
    <p>Tổng quan hệ thống — <?= date('d/m/Y') ?></p>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body py-3">
    <div class="row g-3 align-items-center">
      <div class="col-md-3">
        <div class="text-muted" style="font-size:11px;font-weight:800;text-transform:uppercase">Chế độ sử dụng</div>
        <div class="fw-800"><?= htmlspecialchars($dashboardFeatureProfile['label']) ?> <span class="text-muted fw-600">· <?= htmlspecialchars($licensePackageLabel) ?></span></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted" style="font-size:11px;font-weight:800;text-transform:uppercase">Ngày bắt đầu tính phí</div>
        <div class="fw-800"><?= date('d/m/Y', strtotime($license['customer']['started_at'] ?? date('Y-m-d'))) ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted" style="font-size:11px;font-weight:800;text-transform:uppercase">Ngày hết hạn</div>
        <div class="fw-800"><?= date('d/m/Y', strtotime($licenseStatus['end_date'])) ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted" style="font-size:11px;font-weight:800;text-transform:uppercase">Còn lại</div>
        <div class="fw-800 <?= $licenseStatus['days_remaining'] < 0 ? 'text-danger' : ($licenseStatus['days_remaining'] <= 15 ? 'text-warning' : 'text-success') ?>">
          <?= (int)$licenseStatus['days_remaining'] ?> ngày
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions mb-4">
  <div class="quick-actions-title">
    <i class="bi bi-lightning-charge-fill"></i> Thao Tác Nhanh
  </div>
  <div class="quick-actions-grid">
    <?php if (in_array($user['role'], ['superadmin','admin','employee'], true)): ?>
    <a href="index.php?page=invoice&branch=<?= firstAccessibleBranchId() ?>" class="quick-action-card primary">
      <div class="quick-action-icon"><i class="bi bi-receipt-cutoff"></i></div>
      <div class="quick-action-text">
        <span class="quick-action-label">Lập Hóa Đơn</span>
        <span class="quick-action-desc">Bán hàng mới</span>
      </div>
    </a>
    <?php endif; ?>
    <?php if (in_array($user['role'], ['superadmin','admin','employee'], true)): ?>
    <a href="index.php?page=imports&branch=<?= firstAccessibleBranchId() ?>" class="quick-action-card">
      <div class="quick-action-icon"><i class="bi bi-download"></i></div>
      <div class="quick-action-text">
        <span class="quick-action-label">Nhập Hàng</span>
        <span class="quick-action-desc">Thêm kho</span>
      </div>
    </a>
    <?php endif; ?>
    <?php if (in_array($user['role'], ['superadmin','admin','employee'], true)): ?>
    <a href="index.php?page=invoices&branch=<?= firstAccessibleBranchId() ?>" class="quick-action-card">
      <div class="quick-action-icon"><i class="bi bi-journal-text"></i></div>
      <div class="quick-action-text">
        <span class="quick-action-label">DS Hóa Đơn</span>
        <span class="quick-action-desc">Xem lịch sử</span>
      </div>
    </a>
    <?php endif; ?>
    <a href="index.php?page=search_invoices" class="quick-action-card">
      <div class="quick-action-icon"><i class="bi bi-search"></i></div>
      <div class="quick-action-text">
        <span class="quick-action-label">Tìm HĐ</span>
        <span class="quick-action-desc">Tra cứu nhanh</span>
      </div>
    </a>
  </div>
</div>

<!-- Tổng hợp -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card stat-amber">
      <div class="stat-icon"><i class="bi bi-receipt-cutoff"></i></div>
      <div class="stat-value"><?= $totalOrders ?></div>
      <div class="stat-label">Đơn hàng hôm nay</div>
      <div class="stat-bg"><i class="bi bi-receipt-cutoff"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-value" style="font-size:18px"><?= formatMoney($totalRevenue) ?></div>
      <div class="stat-label">Doanh thu hôm nay</div>
      <div class="stat-bg"><i class="bi bi-cash-stack"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-red">
      <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <div class="stat-value"><?= $totalLow ?></div>
      <div class="stat-label">Hàng sắp hết</div>
      <div class="stat-bg"><i class="bi bi-exclamation-triangle"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-blue">
      <div class="stat-icon"><i class="bi bi-buildings"></i></div>
      <div class="stat-value"><?= count($branches) ?></div>
      <div class="stat-label">Chi nhánh hoạt động</div>
      <div class="stat-bg"><i class="bi bi-buildings"></i></div>
    </div>
  </div>
</div>

<?php if(featureEnabled('cashbook')): ?><!-- Thu chi tháng này -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-arrow-down-circle"></i></div>
      <div class="stat-value" style="font-size:18px"><?= formatMoney($totalCashIncome) ?></div>
      <div class="stat-label">Thu tháng này</div>
      <div class="stat-bg"><i class="bi bi-arrow-down-circle"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-red">
      <div class="stat-icon"><i class="bi bi-arrow-up-circle"></i></div>
      <div class="stat-value" style="font-size:18px"><?= formatMoney($totalCashExpense) ?></div>
      <div class="stat-label">Chi tháng này</div>
      <div class="stat-bg"><i class="bi bi-arrow-up-circle"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-amber">
      <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
      <div class="stat-value <?= $totalCashBalance < 0 ? 'text-danger' : '' ?>" style="font-size:18px"><?= formatMoney($totalCashBalance) ?></div>
      <div class="stat-label">Chênh lệch thu chi</div>
      <div class="stat-bg"><i class="bi bi-wallet2"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-blue">
      <div class="stat-icon"><i class="bi bi-list-check"></i></div>
      <div class="stat-value"><?= (int)$totalCashCount ?></div>
      <div class="stat-label">Phiếu thu/chi</div>
      <div class="stat-bg"><i class="bi bi-list-check"></i></div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Chi nhánh -->
<div class="row g-3 mb-4">
<?php foreach ($allStats as $bId => $bs): $b = $bs['info']; $s = $bs['stats']; $cb = $bs['cashbook']; ?>
<div class="col-md-6">
  <div class="card h-100">
    <div class="card-header d-flex align-items-center gap-2">
      <i class="bi <?= $b['icon'] ?> text-<?= $b['color'] ?>"></i>
      <span><?= $b['name'] ?></span>
      <span class="ms-auto badge bg-<?= $b['color'] ?>"><?= $b['short'] ?></span>
    </div>
    <div class="card-body">
      <div class="row g-2 mb-3">
        <div class="col-6">
          <div class="p-3 rounded-3 bg-light text-center">
            <div class="fw-800 text-dark fs-5"><?= $s['today_orders'] ?></div>
            <div class="text-muted" style="font-size:11px">Đơn hôm nay</div>
          </div>
        </div>
        <div class="col-6">
          <div class="p-3 rounded-3 bg-light text-center">
            <div class="fw-800 text-success" style="font-size:14px"><?= formatMoney($s['today_revenue']) ?></div>
            <div class="text-muted" style="font-size:11px">Doanh thu</div>
          </div>
        </div>
        <div class="col-6">
          <div class="p-3 rounded-3 bg-light text-center">
            <div class="fw-800 text-dark fs-5"><?= $s['total_products'] ?></div>
            <div class="text-muted" style="font-size:11px">Sản phẩm</div>
          </div>
        </div>
        <div class="col-6">
          <div class="p-3 rounded-3 bg-light text-center">
            <div class="fw-800 <?= $s['low_stock'] > 0 ? 'text-danger' : 'text-success' ?> fs-5"><?= $s['low_stock'] ?></div>
            <div class="text-muted" style="font-size:11px">Sắp hết hàng</div>
          </div>
        </div>
      </div>

      <?php if(featureEnabled('cashbook')): ?><div class="p-3 rounded-3 mb-3" style="background:#f9fafb;border:1px solid #e5e7eb">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-800" style="font-size:12px"><i class="bi bi-cash-stack me-1"></i>Thu chi tháng này</div>
          <span class="text-muted" style="font-size:11px"><?= (int)$cb['count'] ?> phiếu</span>
        </div>
        <div class="row g-2 text-center">
          <div class="col-4">
            <div class="text-muted" style="font-size:10.5px">Thu</div>
            <div class="fw-800 text-success" style="font-size:12px"><?= formatMoney($cb['income']) ?></div>
          </div>
          <div class="col-4">
            <div class="text-muted" style="font-size:10.5px">Chi</div>
            <div class="fw-800 text-danger" style="font-size:12px"><?= formatMoney($cb['expense']) ?></div>
          </div>
          <div class="col-4">
            <div class="text-muted" style="font-size:10.5px">Lệch</div>
            <div class="fw-800 <?= $cb['balance'] < 0 ? 'text-danger' : 'text-success' ?>" style="font-size:12px"><?= formatMoney($cb['balance']) ?></div>
          </div>
        </div>
      </div><?php endif; ?>

      <div class="d-flex gap-2 flex-wrap">
        <a href="index.php?page=products&branch=<?= $bId ?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-box2"></i> Sản phẩm
        </a>
        <?php if (in_array($user['role'], ['superadmin','admin','employee'], true)): ?>
        <a href="index.php?page=imports&branch=<?= $bId ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-download"></i> Nhập hàng
        </a>
        <?php endif; ?>
        <?php if (in_array($user['role'], ['superadmin','admin','employee'], true)): ?>
        <a href="index.php?page=invoice&branch=<?= $bId ?>" class="btn btn-sm btn-primary">
          <i class="bi bi-receipt"></i> Lập hóa đơn
        </a>
        <?php endif; ?>
        <?php if (in_array($user['role'], ['superadmin','admin'], true) && featureEnabled('reports')): ?>
        <a href="index.php?page=reports&branch=<?= $bId ?>" class="btn btn-sm btn-outline-success">
          <i class="bi bi-bar-chart"></i> Báo cáo
        </a>
        <?php endif; ?>
        <?php if(featureEnabled('cashbook')): ?><a href="index.php?page=cashbook&branch=<?= $bId ?>&ym=<?= urlencode($cashbookMonth) ?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-cash-stack"></i> Thu chi
        </a><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Hàng sắp hết -->
<?php
$lowItems = [];
foreach ($allStats as $bId => $bs) {
    foreach ($bs['stats']['low_stock_list'] as $p) {
        $p['branch_name'] = $bs['info']['name'];
        $p['branch_id']   = $bId;
        $lowItems[]       = $p;
    }
}
if (!empty($lowItems)):
?>
<div class="card">
  <div class="card-header text-danger">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>Cảnh Báo Hàng Sắp Hết (<?= count($lowItems) ?> sản phẩm)
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>Mã SP</th><th>Tên sản phẩm</th><th>Chi nhánh</th><th>Tồn kho</th><th>Tối thiểu</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($lowItems as $p): ?>
        <tr class="stock-low-row">
          <td><code><?= htmlspecialchars($p['code']) ?></code></td>
          <td class="fw-600"><?= htmlspecialchars($p['name']) ?></td>
          <td><span class="branch-badge-<?= ($p['branch_id'] === 'branch_1_vlxd') ? 'vlxd' : 'mt' ?>"><?= htmlspecialchars($p['branch_name']) ?></span></td>
          <td class="stock-low"><?= number_format($p['stock'],2,',','.') ?> <?= htmlspecialchars($p['unit']) ?></td>
          <td class="text-muted"><?= number_format($p['min_stock'],2,',','.') ?> <?= htmlspecialchars($p['unit']) ?></td>
          <td>
            <a href="index.php?page=imports&branch=<?= $p['branch_id'] ?>&product_code=<?= urlencode($p['code']) ?>" class="btn btn-sm btn-warning">Nhập hàng</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
