<?php
$reqBranch  = $_GET['branch'] ?? array_key_first(BRANCHES);
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }
$branchInfo = BRANCHES[$reqBranch];
$yearMonth  = $_GET['ym'] ?? date('Y_m');
$pageTitle  = 'Báo Cáo — ' . $branchInfo['name'];

$invoices   = getInvoices($reqBranch, $yearMonth);
$imports    = getImports($reqBranch, $yearMonth);
$products   = getAllProducts($reqBranch);
$report     = getRevenueReport($reqBranch, $yearMonth);
$categoriesRaw = getCategories($reqBranch, true);
$categories = [];
foreach ($categoriesRaw as $c2) { $categories[$c2['key']] = $c2; }

$totalRevenue = array_sum(array_column($invoices, 'total'));
$totalImport  = array_sum(array_column($imports, 'total_amount'));
$totalOrders  = count($invoices);

include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-bar-chart-fill me-2 text-<?= $branchInfo['color'] ?>"></i>Báo Cáo</h2>
    <p><?= htmlspecialchars($branchInfo['name']) ?> — <?= str_replace('_','/',$yearMonth) ?></p>
  </div>
  <form class="d-flex gap-2" method="GET">
    <input type="hidden" name="page" value="reports">
    <input type="hidden" name="branch" value="<?= $reqBranch ?>">
    <input type="month" name="ym" class="form-control form-control-sm"
      value="<?= str_replace('_','-',$yearMonth) ?>"
      onchange="this.value=this.value.replace('-','_');this.form.submit()">
  </form>
</div>

<!-- Summary stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-value" style="font-size:16px"><?= formatMoney($totalRevenue) ?></div>
      <div class="stat-label">Doanh thu tháng</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-amber">
      <div class="stat-icon"><i class="bi bi-receipt"></i></div>
      <div class="stat-value"><?= $totalOrders ?></div>
      <div class="stat-label">Số hóa đơn</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-blue">
      <div class="stat-icon"><i class="bi bi-download"></i></div>
      <div class="stat-value" style="font-size:16px"><?= formatMoney($totalImport) ?></div>
      <div class="stat-label">Tổng nhập hàng</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-red">
      <div class="stat-icon"><i class="bi bi-graph-down"></i></div>
      <div class="stat-value" style="font-size:16px"><?= formatMoney($totalRevenue - $totalImport) ?></div>
      <div class="stat-label">Lợi nhuận ước tính</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Doanh thu theo ngày -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">
        <i class="bi bi-graph-up me-2"></i>Doanh Thu Theo Ngày
      </div>
      <div class="card-body">
        <?php if (empty($report)): ?>
        <div class="empty-state"><i class="bi bi-bar-chart"></i><p>Chưa có dữ liệu</p></div>
        <?php else:
          $maxRev = max(array_column($report, 'revenue')) ?: 1;
        ?>
        <div style="overflow-x:auto">
          <table class="table table-sm mb-0">
            <thead><tr><th>Ngày</th><th class="text-center">Đơn</th><th class="text-end">Doanh thu</th><th style="width:200px">Biểu đồ</th></tr></thead>
            <tbody>
            <?php foreach ($report as $r): $pct = round($r['revenue'] / $maxRev * 100); ?>
            <tr>
              <td><?= date('d/m', strtotime($r['date'])) ?></td>
              <td class="text-center"><?= $r['orders'] ?></td>
              <td class="text-end money"><?= formatMoney($r['revenue']) ?></td>
              <td>
                <div style="background:#e5e7eb;border-radius:4px;height:14px;overflow:hidden">
                  <div style="background:var(--accent);width:<?= $pct ?>%;height:100%;border-radius:4px;transition:width .3s"></div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="fw-700">
                <td>Tổng</td>
                <td class="text-center"><?= array_sum(array_column($report,'orders')) ?></td>
                <td class="text-end money text-success"><?= formatMoney(array_sum(array_column($report,'revenue'))) ?></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Tồn kho theo nhóm -->
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-boxes me-2"></i>Tồn Kho Theo Nhóm Hàng</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Nhóm hàng</th><th class="text-center">SP</th><th class="text-end">Sắp hết</th></tr></thead>
          <tbody>
          <?php foreach ($categories as $cKey => $cInfo):
            $cFile  = DATA_PATH . '/' . $reqBranch . '/' . $cInfo['file'];
            $cProds = readJson($cFile);
            $lowCnt = count(array_filter($cProds, fn($p) => ($p['stock']??0) < ($p['min_stock']??5)));
          ?>
          <tr>
            <td class="fw-600"><?= htmlspecialchars($cInfo['name']) ?></td>
            <td class="text-center"><?= count($cProds) ?></td>
            <td class="text-end <?= $lowCnt > 0 ? 'text-danger fw-700' : 'text-success' ?>">
              <?= $lowCnt > 0 ? $lowCnt . ' SP sắp hết' : 'Ổn' ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Top sản phẩm bán chạy -->
    <div class="card">
      <div class="card-header"><i class="bi bi-fire me-2"></i>Top Sản Phẩm Bán Chạy</div>
      <div class="card-body p-0">
        <?php
        $soldMap = [];
        foreach ($invoices as $inv) {
            foreach ($inv['items'] ?? [] as $item) {
                $code = $item['product_code'];
                $soldMap[$code] = ($soldMap[$code] ?? ['name'=>$item['product_name'],'qty'=>0,'revenue'=>0]);
                $soldMap[$code]['qty']     += $item['qty'];
                $soldMap[$code]['revenue'] += $item['line_total'];
            }
        }
        uasort($soldMap, fn($a,$b) => $b['revenue'] <=> $a['revenue']);
        $top = array_slice($soldMap, 0, 8, true);
        ?>
        <?php if (empty($top)): ?>
        <div class="empty-state" style="padding:24px"><i class="bi bi-fire"></i><p>Chưa có dữ liệu</p></div>
        <?php else: ?>
        <table class="table table-sm mb-0">
          <thead><tr><th>#</th><th>Sản phẩm</th><th class="text-end">Doanh thu</th></tr></thead>
          <tbody>
          <?php $rank = 1; foreach ($top as $code => $info): ?>
          <tr>
            <td><span class="badge <?= $rank <= 3 ? 'bg-warning text-dark' : 'bg-secondary bg-opacity-10 text-secondary' ?>"><?= $rank ?></span></td>
            <td>
              <div class="fw-600" style="font-size:13px"><?= htmlspecialchars($info['name']) ?></div>
              <div class="product-code"><?= htmlspecialchars($code) ?> · SL: <?= number_format($info['qty'],2,',','.') ?></div>
            </td>
            <td class="text-end money"><?= formatMoney($info['revenue']) ?></td>
          </tr>
          <?php $rank++; endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
