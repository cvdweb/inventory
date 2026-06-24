<?php
$reqBranch = $_GET['branch'] ?? firstAccessibleBranchId();
if (!canAccessBranch($reqBranch)) {
    $_SESSION['flash'] = ['type'=>'danger','message'=>'Không có quyền truy cập chi nhánh này'];
    header('Location: index.php'); exit;
}

$branchInfo    = getBranchInfo($reqBranch);
$category      = $_GET['cat'] ?? '';
$search        = $_GET['q'] ?? '';
$productStatus = $_GET['status'] ?? 'active';
$categoriesRaw = getCategories($reqBranch, true);
$categories    = [];
foreach ($categoriesRaw as $c2) { $categories[$c2['key']] = $c2; }
$categoryUnits = [];
$categoryCapabilities = [];
foreach ($categories as $cKey => $cInfo) {
    $categoryUnits[$cKey] = getCategoryUnits($reqBranch, $cKey);
    $categoryCapabilities[$cKey] = $cInfo['capabilities'] ?? [];
}
$products      = productList($reqBranch, $category, $search, $productStatus !== 'active');
if ($productStatus === 'archived') {
    $products = array_values(array_filter($products, 'productIsArchived'));
}
$pageTitle     = 'Sản Phẩm — ' . $branchInfo['name'];
$canManage     = in_array(currentUser()['role'], ['superadmin', 'admin'], true);
$bulkEnabled   = featureEnabled('bulk_import');
$bulkPreview   = $_SESSION['product_bulk_preview'] ?? null;
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex flex-wrap align-items-start gap-3 justify-content-between">
  <div>
    <h2><i class="bi bi-box2-fill me-2 text-<?= $branchInfo['color'] ?>"></i><?= htmlspecialchars($branchInfo['name']) ?></h2>
    <p>Quản lý sản phẩm — <?= count($products) ?> sản phẩm</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary" onclick="printPriceList()">
      <i class="bi bi-printer me-1"></i>In Bảng Giá
    </button>
    <?php if ($canManage && $bulkEnabled): ?>
    <button class="btn btn-outline-primary" onclick="openBulkModal()">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Nhập CSV
    </button>
    <?php endif; ?>
    <?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="openAddModal()">
      <i class="bi bi-plus-lg me-1"></i>Thêm Sản Phẩm
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if ($canManage && $bulkEnabled && $bulkPreview && ($bulkPreview['branch'] ?? '') === $reqBranch): ?>
<?php
  $validRows = $bulkPreview['valid'] ?? [];
  $errorRows = $bulkPreview['errors'] ?? [];
  $previewWarnings = $bulkPreview['warnings'] ?? [];
?>
<div class="card mb-3" style="border-left:4px solid #3b82f6">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>
      Preview nhập CSV: <strong><?= htmlspecialchars($bulkPreview['categoryName'] ?? '') ?></strong>
    </div>
    <div class="d-flex gap-2">
      <form method="POST" action="index.php?page=products&branch=<?= urlencode($reqBranch) ?>&action=bulk_cancel">
        <?= csrfField() ?>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Hủy preview</button>
      </form>
      <form method="POST" action="index.php?page=products&branch=<?= urlencode($reqBranch) ?>&action=bulk_commit"
        onsubmit="return confirm('Xác nhận nhập <?= count($validRows) ?> sản phẩm hợp lệ?')">
        <?= csrfField() ?>
        <button class="btn btn-sm btn-primary" type="submit" <?= empty($validRows) ? 'disabled' : '' ?>>
          <i class="bi bi-check2-circle me-1"></i>Xác nhận nhập <?= count($validRows) ?> dòng
        </button>
      </form>
    </div>
  </div>
  <div class="card-body">
    <?php foreach ($previewWarnings as $warning): ?>
    <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($warning) ?></div>
    <?php endforeach; ?>
    <div class="row g-3">
      <div class="col-md-6">
        <div class="alert alert-success py-2 mb-2">
          <strong><?= count($validRows) ?></strong> dòng hợp lệ, sẵn sàng nhập.
        </div>
        <div class="table-responsive" style="max-height:260px;overflow:auto">
          <table class="table table-sm mb-0">
            <thead><tr><th>Dòng</th><th>Mã</th><th>Tên</th><th>ĐVT</th><th class="text-end">Giá bán</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($validRows, 0, 30) as $row): ?>
              <tr>
                <td><?= (int)$row['row'] ?></td>
                <td><code><?= htmlspecialchars($row['code']) ?></code></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['unit']) ?></td>
                <td class="text-end money"><?= formatMoney($row['price_out']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (count($validRows) > 30): ?>
              <tr><td colspan="5" class="text-muted text-center">Còn <?= count($validRows) - 30 ?> dòng hợp lệ khác...</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="col-md-6">
        <div class="alert <?= empty($errorRows) ? 'alert-secondary' : 'alert-danger' ?> py-2 mb-2">
          <strong><?= count($errorRows) ?></strong> dòng lỗi hoặc bị bỏ qua.
        </div>
        <div class="table-responsive" style="max-height:260px;overflow:auto">
          <table class="table table-sm mb-0">
            <thead><tr><th>Dòng</th><th>Mã</th><th>Lỗi</th></tr></thead>
            <tbody>
            <?php if (empty($errorRows)): ?>
              <tr><td colspan="3" class="text-muted text-center">Không có lỗi.</td></tr>
            <?php else: foreach (array_slice($errorRows, 0, 40) as $row): ?>
              <tr>
                <td><?= (int)$row['row'] ?></td>
                <td><code><?= htmlspecialchars($row['code'] ?? '') ?></code></td>
                <td><?= htmlspecialchars(implode('; ', $row['errors'] ?? [])) ?></td>
              </tr>
            <?php endforeach; endif; ?>
            <?php if (count($errorRows) > 40): ?>
              <tr><td colspan="3" class="text-muted text-center">Còn <?= count($errorRows) - 40 ?> dòng lỗi khác...</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Filter bar -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form class="d-flex flex-wrap gap-2 align-items-center" method="GET">
      <input type="hidden" name="page" value="products">
      <input type="hidden" name="branch" value="<?= $reqBranch ?>">
      <div class="search-box" style="min-width:220px;position:relative">
        <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none"></i>
        <input type="text" name="q" class="form-control form-control-sm" style="padding-left:32px"
          placeholder="Tìm mã, tên..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="cat" class="form-select form-select-sm" style="width:180px">
        <option value="">Tất cả nhóm</option>
        <?php foreach ($categories as $cKey => $cInfo): ?>
        <option value="<?= $cKey ?>" <?= $category === $cKey ? 'selected' : '' ?>><?= htmlspecialchars($cInfo['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($canManage): ?>
      <select name="status" class="form-select form-select-sm" style="width:150px">
        <option value="active" <?= $productStatus === 'active' ? 'selected' : '' ?>>Đang kinh doanh</option>
        <option value="archived" <?= $productStatus === 'archived' ? 'selected' : '' ?>>Đã lưu trữ</option>
        <option value="all" <?= $productStatus === 'all' ? 'selected' : '' ?>>Tất cả</option>
      </select>
      <?php endif; ?>
      <button type="submit" class="btn btn-sm btn-primary">Lọc</button>
      <a href="index.php?page=products&branch=<?= $reqBranch ?>" class="btn btn-sm btn-outline-secondary">Đặt lại</a>
    </form>
  </div>
</div>

<!-- Product table -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>Mã SP</th><th>Tên sản phẩm</th><th>Nhóm</th><th>ĐVT</th>
          <th class="text-end">Giá nhập</th><th class="text-end">Giá bán</th>
          <th class="text-end">Tồn kho</th><th class="text-center">Trạng thái</th>
          <?php if ($canManage): ?><th class="text-center">Thao tác</th><?php endif; ?>
        </tr></thead>
        <tbody data-progressive-list data-progressive-auto="true" data-progressive-label="sản phẩm" data-progressive-initial="25" data-progressive-batch="25" data-progressive-controls="productListMore">
        <?php if (empty($products)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <i class="bi bi-box-seam"></i>
            <p>Chưa có sản phẩm nào<?= $search ? " khớp với \"$search\"" : '' ?></p>
            <?php if ($canManage && !$search): ?>
            <button class="btn btn-sm btn-primary mt-2" onclick="openAddModal()">
              <i class="bi bi-plus-lg me-1"></i>Thêm sản phẩm đầu tiên
            </button>
            <?php endif; ?>
          </div>
        </td></tr>
        <?php else: foreach ($products as $p): $archived = productIsArchived($p); $lowStock = !$archived && ($p['stock'] ?? 0) < ($p['min_stock'] ?? 5); ?>
        <tr class="<?= $lowStock ? 'stock-low-row' : '' ?>" <?= $archived ? 'style="opacity:.72;background:#f8fafc"' : '' ?> data-progressive-item>
          <td><code><?= htmlspecialchars($p['code'] ?? '') ?></code></td>
          <td class="fw-600"><?= htmlspecialchars($p['name'] ?? '') ?>
            <?php if (!empty($p['special_colors'])): ?>
            <span class="badge ms-1" style="background:rgba(139,92,246,.15);color:#7c3aed;font-size:10px;font-weight:600">
              <i class="bi bi-palette me-1"></i><?= count($p['special_colors']) ?> màu ĐB
            </span>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= htmlspecialchars($p['category_name'] ?? '') ?></span></td>
          <td><?= htmlspecialchars($p['unit'] ?? '') ?></td>
          <td class="text-end money"><?= formatMoney($p['price_in'] ?? 0) ?></td>
          <td class="text-end money fw-700"><?= formatMoney($p['price_out'] ?? 0) ?></td>
          <td class="text-end <?= $lowStock ? 'stock-low' : 'stock-ok' ?>">
            <?= number_format($p['stock'] ?? 0, 2, ',', '.') ?> <?= htmlspecialchars($p['unit'] ?? '') ?>
          </td>
          <td class="text-center">
            <?php if ($archived): ?>
              <span class="badge bg-secondary"><i class="bi bi-archive me-1"></i>Đã lưu trữ</span>
            <?php elseif ($lowStock): ?>
              <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Sắp hết</span>
            <?php else: ?>
              <span class="badge bg-success bg-opacity-10 text-success">Bình thường</span>
            <?php endif; ?>
          </td>
          <?php if ($canManage): ?>
          <td class="text-center">
            <?php if (!$archived): ?>
            <button class="btn btn-sm btn-outline-primary"
              onclick='openEditModal(<?= json_encode([
                "id"             => $p["id"],
                "code"           => $p["code"],
                "name"           => $p["name"],
                "unit"           => $p["unit"],
                "stock"          => $p["stock"] ?? 0,
                "price_in"       => $p["price_in"] ?? 0,
                "price_out"      => $p["price_out"] ?? 0,
                "min_stock"      => $p["min_stock"] ?? 5,
                "category_key"   => $p["category_key"] ?? "",
                "special_colors" => $p["special_colors"] ?? [],
              ], JSON_HEX_APOS|JSON_UNESCAPED_UNICODE) ?>)'>
              <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" action="index.php?page=products&branch=<?= $reqBranch ?>&action=delete&id=<?= urlencode($p['id'] ?? '') ?>&cat=<?= urlencode($p['category_key'] ?? '') ?>" class="d-inline"
               data-product-name="<?= htmlspecialchars($p['name'] ?? '', ENT_QUOTES) ?>"
               data-product-action="archive" onsubmit="return confirmProductAction(this)">
              <?= csrfField() ?>
              <input type="hidden" name="reason" value="Ngừng kinh doanh">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Ngừng kinh doanh"><i class="bi bi-archive"></i></button>
            </form>
            <?php else: ?>
            <form method="POST" action="index.php?page=products&branch=<?= $reqBranch ?>&action=restore&id=<?= urlencode($p['id'] ?? '') ?>&cat=<?= urlencode($p['category_key'] ?? '') ?>" class="d-inline"
              data-product-name="<?= htmlspecialchars($p['name'] ?? '', ENT_QUOTES) ?>"
              data-product-action="restore" onsubmit="return confirmProductAction(this)">
              <?= csrfField() ?>
              <button type="submit" class="btn btn-sm btn-outline-primary" title="Khôi phục"><i class="bi bi-arrow-counterclockwise"></i></button>
            </form>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div id="productListMore"></div>
  </div>
</div>

<?php if ($canManage && $bulkEnabled): ?>
<!-- Modal Nhập CSV -->
<div class="modal fade" id="bulkImportModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>Nhập Sản Phẩm Hàng Loạt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data" action="index.php?page=products&branch=<?= urlencode($reqBranch) ?>&action=bulk_preview">
        <?= csrfField() ?>
        <div class="modal-body">
          <div class="alert alert-info py-2">
            Hệ thống sẽ đọc file và hiển thị preview trước. Chỉ các dòng hợp lệ mới được nhập sau khi bạn xác nhận.
          </div>
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label">Nhóm hàng nhập vào *</label>
              <select name="category" id="bulkCategory" class="form-select" required onchange="updateBulkTemplateLink()">
                <option value="">-- Chọn nhóm --</option>
                <?php foreach ($categories as $cKey => $cInfo): ?>
                <option value="<?= htmlspecialchars($cKey) ?>" <?= $category === $cKey ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cInfo['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Đơn vị trong CSV phải thuộc nhóm đã chọn.</div>
            </div>
            <div class="col-md-7">
              <label class="form-label">File CSV *</label>
              <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
              <div class="form-text">Nên lưu CSV dạng UTF-8 để tiếng Việt hiển thị đúng.</div>
            </div>
            <div class="col-12">
              <a class="btn btn-sm btn-outline-primary" id="bulkTemplateLink" href="#" target="_blank">
                <i class="bi bi-download me-1"></i>Tải file mẫu CSV cho nhóm đã chọn
              </a>
              <div class="form-text mt-2" id="bulkCapabilityHint">File mẫu sẽ thay đổi theo tính năng của nhóm hàng.</div>
            </div>
            <div class="col-12">
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                  <thead>
                    <tr>
                      <th>Mã SP</th><th>Tên sản phẩm</th><th>Đơn vị</th><th>Giá nhập</th>
                      <th>Giá bán</th><th>Tồn kho ban đầu</th><th>Tồn kho tối thiểu</th><th class="bulk-color-column">Màu đặc biệt</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><code>XM001</code></td><td>Xi măng Hà Tiên</td><td>bao</td><td>80000</td>
                      <td>90000</td><td>100</td><td>10</td><td class="bulk-color-column">Đỏ:+20000; Xanh:+15000</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-eye me-1"></i>Đọc file & xem preview
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
<?php if ($canManage): ?>
<!-- Modal Thêm / Sửa Sản Phẩm -->
<div class="modal fade" id="productModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Thêm Sản Phẩm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php?page=products&branch=<?= $reqBranch ?>&action=save">
        <?= csrfField() ?>
        <!-- id rỗng = thêm mới, có giá trị = sửa -->
        <input type="hidden" name="id" id="pId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Mã sản phẩm *</label>
              <input type="text" name="code" id="pCode" class="form-control" required
                placeholder="VD: XM001" autocomplete="off">
            </div>
            <div class="col-md-8">
              <label class="form-label">Tên sản phẩm *</label>
              <input type="text" name="name" id="pName" class="form-control" required
                placeholder="Nhập tên đầy đủ của sản phẩm">
            </div>

            <div class="col-md-4">
              <label class="form-label">Nhóm hàng *</label>
              <select name="category" id="pCategory" class="form-select" required>
                <option value="">-- Chọn nhóm --</option>
                <?php foreach ($categories as $cKey => $cInfo): ?>
                <option value="<?= $cKey ?>"><?= htmlspecialchars($cInfo['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Đơn vị tính *</label>
              <select name="unit" id="pUnit" class="form-select" required>
                <option value="">-- Chọn nhóm trước --</option>
              </select>
              <div class="form-text">Đơn vị lấy theo nhóm hàng đã chọn</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tồn kho tối thiểu</label>
              <input type="number" name="min_stock" id="pMinStock" class="form-control" onfocus="this.select()"
                value="5" min="0" step="0.01">
              <div class="form-text">Cảnh báo khi tồn kho dưới mức này</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Giá nhập (₫)</label>
              <input type="number" name="price_in" id="pPriceIn" class="form-control" onfocus="this.select()"
                value="0" min="0" step="1">
              <div class="form-text">Không bắt buộc chênh lệch lớn với giá bán</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Giá bán (₫) *</label>
              <input type="number" name="price_out" id="pPriceOut" class="form-control" onfocus="this.select()"
                value="0" min="0" step="1" required
                oninput="recalcAllSurcharges()">
              <div class="form-text">Ví dụ: nhập 2.400, bán 2.600 vẫn hợp lệ</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tồn kho ban đầu</label>
              <input type="number" name="stock" id="pStock" class="form-control" onfocus="this.select()"
                value="0" min="0" step="0.01">
              <div class="form-text">Chỉ điền khi thêm mới</div>
            </div>

            <!-- Màu đặc biệt (phụ thu thêm) -->
            <div class="col-12" id="specialColorsSection" hidden>
              <div style="border:1.5px dashed #e5e7eb;border-radius:8px;padding:14px 16px;background:#fafafa">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <label class="form-label mb-0" style="color:#6b7280">
                    <i class="bi bi-palette me-1" style="color:#8b5cf6"></i>
                    Màu Đặc Biệt <span style="font-weight:400;font-size:11.5px">(phụ thu thêm vào giá bán)</span>
                  </label>
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="addColorRow()">
                    <i class="bi bi-plus-lg me-1"></i>Thêm màu
                  </button>
                </div>
                <div id="specialColorsContainer">
                  <!-- Các dòng màu sẽ được render ở đây -->
                </div>
                <div id="specialColorsEmpty" style="font-size:12.5px;color:#9ca3af;text-align:center;padding:8px 0">
                  Chưa có màu đặc biệt — sản phẩm chỉ có 1 mức giá bán
                </div>
                <input type="hidden" name="special_colors" id="pSpecialColors" value="[]">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2 me-1"></i><span id="modalBtnText">Thêm sản phẩm</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal xác nhận thao tác sản phẩm -->
<div class="modal fade" id="productActionModal" tabindex="-1" aria-labelledby="productActionModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered product-action-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="modal-kicker">QUẢN LÝ SẢN PHẨM</div>
          <h5 class="modal-title" id="productActionModalTitle">Xác nhận thao tác</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body">
        <div class="product-action-note">
          <i class="bi bi-info-circle" id="productActionIcon"></i>
          <div>
            <strong id="productActionName">Sản phẩm</strong>
            <span id="productActionMessage"></span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Quay lại</button>
        <button type="button" class="btn" id="confirmProductActionButton"></button>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<script>
let pendingProductActionForm = null;

function confirmProductAction(form) {
  if (form.dataset.actionConfirmed === '1') return true;

  pendingProductActionForm = form;
  const isRestore = form.dataset.productAction === 'restore';
  const productName = form.dataset.productName || 'Sản phẩm';
  const title = document.getElementById('productActionModalTitle');
  const name = document.getElementById('productActionName');
  const message = document.getElementById('productActionMessage');
  const icon = document.getElementById('productActionIcon');
  const confirmButton = document.getElementById('confirmProductActionButton');

  name.textContent = productName;
  title.textContent = isRestore ? 'Khôi phục sản phẩm' : 'Ngừng kinh doanh';
  message.textContent = isRestore
    ? 'Sản phẩm sẽ xuất hiện trở lại và có thể tiếp tục bán hàng.'
    : 'Sản phẩm sẽ được lưu trữ và ẩn khỏi danh sách bán hàng. Hóa đơn và dữ liệu lịch sử vẫn được giữ nguyên.';
  icon.className = isRestore ? 'bi bi-arrow-counterclockwise' : 'bi bi-archive';
  confirmButton.className = isRestore ? 'btn btn-primary' : 'btn btn-danger';
  confirmButton.innerHTML = isRestore
    ? '<i class="bi bi-arrow-counterclockwise me-1"></i>Khôi phục'
    : '<i class="bi bi-archive me-1"></i>Ngừng kinh doanh';

  bootstrap.Modal.getOrCreateInstance(document.getElementById('productActionModal')).show();
  return false;
}

document.getElementById('productActionModal')?.addEventListener('hidden.bs.modal', function () {
  pendingProductActionForm = null;
});

document.getElementById('confirmProductActionButton')?.addEventListener('click', function () {
  if (!pendingProductActionForm) return;
  pendingProductActionForm.dataset.actionConfirmed = '1';
  pendingProductActionForm.requestSubmit();
});

// ── In Bảng Giá ───────────────────────────────────────────────
function printPriceList() {
  const BIZ = <?= json_encode([
    'name'       => BUSINESS['name'],
    'address'    => BUSINESS['address'],
    'phone'      => BUSINESS['phone'],
    'slogan'     => BUSINESS['slogan'] ?? '',
    'branch'     => $branchInfo['name'],
    'date'       => date('d/m/Y'),
  ], JSON_UNESCAPED_UNICODE) ?>;

  // Dữ liệu sản phẩm nhóm theo category
  const GROUPS = <?= (function() use ($categoriesRaw, $reqBranch) {
    $groups = [];
    foreach ($categoriesRaw as $cat) {
        $file  = DATA_PATH . "/{$reqBranch}/" . $cat['file'];
        $prods = readJson($file);
        if (empty($prods)) continue;
        $supportsColors = in_array('color_surcharge', $cat['capabilities'] ?? [], true);
        if (!$supportsColors) {
            foreach ($prods as &$product) $product['special_colors'] = [];
            unset($product);
        }
        $groups[] = [
            'name'     => $cat['name'],
            'supports_colors' => $supportsColors,
            'products' => array_values($prods),
        ];
    }
    return json_encode($groups, JSON_UNESCAPED_UNICODE);
  })() ?>;

  // Sinh HTML bảng từng nhóm
  let tablesHtml = '';
  GROUPS.forEach((group, groupIndex) => {
    if (!group.products.length) return;
    const rows = group.products.map((p, idx) => {
      const hasSpecial = p.special_colors && p.special_colors.length > 0;
      // Dòng sản phẩm gốc
      let html = `<tr>
        <td style="text-align:center;color:#777">${idx+1}</td>
        <td style="font-weight:bold">${_esc(p.name||'')}${hasSpecial?'<span class="variant-tag">màu thường</span>':''}</td>
        <td style="text-align:center">${_esc(p.unit||'')}</td>
        <td style="text-align:right;font-weight:bold;font-size:13pt">${_fmtPrice(p.price_out)}</td>
        <td class="price-note"></td>
      </tr>`;
      // Dòng màu đặc biệt
      if (hasSpecial) {
        p.special_colors.forEach(sc => {
          const finalPrice = (parseFloat(p.price_out)||0) + (parseFloat(sc.surcharge)||0);
          html += `<tr style="background:#faf5ff">
            <td></td>
            <td style="padding-left:20pt;color:#5b21b6">
              <span style="margin-right:4pt">&rdsh;</span>${_esc(sc.name)}
              ${sc.code ? `<span class="variant-code">${_esc(sc.code)}</span>` : ''}
            </td>
            <td style="text-align:center;color:#777">${_esc(p.unit||'')}</td>
            <td style="text-align:right;font-weight:bold;font-size:13pt;color:#7c3aed">${_fmtPrice(finalPrice)}</td>
            <td class="price-note"></td>
          </tr>`;
        });
      }
      return html;
    }).join('');

    tablesHtml += `
      <div class="group-block">
        <div class="group-title">
          <span class="group-index">${String(groupIndex + 1).padStart(2, '0')}</span>
          <span class="group-name">${_esc(group.name)}</span>
          <span class="group-count">${group.products.length} sản phẩm</span>
        </div>
        <table>
          <thead><tr>
            <th style="width:32px;text-align:center">STT</th>
            <th>Tên sản phẩm</th>
            <th style="width:55px;text-align:center">ĐVT</th>
            <th style="width:120px;text-align:right">Giá bán</th>
            <th style="width:115px;text-align:center">Giá biến động</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  });

  const win = window.open('', '_blank', 'width=900,height=750');
  win.document.write(`<!DOCTYPE html>
<html lang="vi"><head>
<meta charset="UTF-8">
<title>Bảng Giá — ${_esc(BIZ.branch)}</title>
<style>
  @page { size: A4; margin: 12mm 16mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Times New Roman', serif; font-size: 12.5pt; color: #000; }

  /* Header */
  .biz-header { text-align: center; padding-bottom: 4mm; border-bottom: 2.5px solid #000; margin-bottom: 4mm; }
  .biz-name   { font-size: 17pt; font-weight: bold; letter-spacing: .5px; }
  .biz-branch { font-size: 11pt; color: #555; margin-top: 1.5mm; }
  .biz-contact{ font-size: 12pt; color: #222; margin-top: 1.5mm; font-weight: bold; }
  .biz-slogan { font-size: 10pt; color: #777; font-style: italic; margin-top: 1mm; }

  /* Tiêu đề bảng giá */
  .doc-title  { text-align: center; font-size: 18pt; font-weight: bold;
    letter-spacing: 3px; text-transform: uppercase; margin: 4mm 0 1mm; }
  .doc-date   { text-align: center; font-size: 10pt; color: #666; margin-bottom: 5mm; }

  /* Nhóm hàng */
  .group-block {
    margin-bottom: 3mm;
    border: 1px solid #cbd5e1;
    border-radius: 2mm;
    overflow: hidden;
  }
  .group-block table { page-break-inside: auto; }
  .group-block tr { page-break-inside: avoid; page-break-after: auto; }
  .group-title {
    page-break-after: avoid;
    display: flex;
    align-items: center;
    gap: 3mm;
    font-size: 13pt;
    font-weight: bold;
    background: #fff;
    color: #000;
    border-bottom: 1px solid #94a3b8;
    padding: 2.4mm 4mm;
    letter-spacing: .5px;
  }
  .group-index {
    width: 9mm;
    height: 9mm;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #000;
    color: #000;
    font-size: 9.5pt;
    font-weight: bold;
    letter-spacing: 0;
  }
  .group-name { flex: 1; text-transform: uppercase; font-weight: 900; color: #000; }
  .group-count {
    color: #444;
    font-size: 9.5pt;
    font-weight: normal;
    letter-spacing: 0;
  }
  .variant-tag {
    display: inline-block;
    margin-left: 6px;
    padding: .5mm 1.8mm;
    border-radius: 999px;
    background: #ede9fe;
    color: #6d28d9;
    font-size: 9.5pt;
    font-weight: normal;
  }
  .variant-code {
    margin-left: 6px;
    color: #7c3aed;
    font-size: 9.5pt;
    font-family: 'Courier New', monospace;
  }
  .price-note {
    text-align: center;
    color: #475569;
    font-size: 10.5pt;
    font-weight: 600;
  }

  /* Bảng */
  table { width: 100%; border-collapse: collapse; font-size: 12pt; }
  thead tr { background: #f1f5f9; }
  th { border: 1px solid #94a3b8; padding: 2mm 3mm; font-weight: bold; font-size: 11pt; }
  td { border: 1px solid #cbd5e1; padding: 2mm 3mm; vertical-align: middle; }
  tr:nth-child(even):not([style]) { background: #f8fafc; }

  /* Footer */
  .footer {
    margin-top: 6mm;
    padding-top: 3mm;
    border-top: 1px dashed #aaa;
    font-size: 10pt;
    color: #666;
    display: flex;
    justify-content: space-between;
  }
  .note-box {
    margin-top: 4mm;
    padding: 3mm 4mm;
    border: 1px solid #e5e7eb;
    border-radius: 2mm;
    font-size: 10.5pt;
    color: #555;
    background: #fafafa;
  }
</style>
</head><body>

<!-- Header doanh nghiệp -->
<div class="biz-header">
  <div class="biz-name">${_esc(BIZ.name)}</div>
  <div class="biz-branch">${_esc(BIZ.branch)}</div>
  <div class="biz-contact">📍 ${_esc(BIZ.address)} &nbsp;|&nbsp; 📞 ${_esc(BIZ.phone)}</div>
  ${BIZ.slogan ? `<div class="biz-slogan">"${_esc(BIZ.slogan)}"</div>` : ''}
</div>

<!-- Tiêu đề -->
<div class="doc-title">Bảng Giá Sản Phẩm</div>
<div class="doc-date">Áp dụng từ ngày ${_esc(BIZ.date)} &nbsp;·&nbsp; Giá có thể thay đổi, vui lòng liên hệ để xác nhận</div>

<!-- Bảng giá từng nhóm -->
${tablesHtml}

<!-- Ghi chú -->
<div class="note-box">
  <b>Ghi chú:</b>
  Giá trên là giá bán lẻ, chưa bao gồm VAT.
  ${GROUPS.some(group => group.supports_colors) ? 'Màu đặc biệt (nền tím) có phụ thu thêm theo từng loại màu.' : ''}
  Liên hệ cửa hàng để biết thêm chi tiết và giá sỉ.
</div>

<div class="footer">
  <span>In lúc: ${new Date().toLocaleString('vi-VN')}</span>
  <span>${_esc(BIZ.name)} — ${_esc(BIZ.phone)}</span>
</div>

<script>window.onload = function(){ window.print(); window.close(); }<\/script>
</body></html>`);
  win.document.close();
}

function _esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function _fmtPrice(n) { return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(Number(n)||0); }
let specialColors = [];
const CATEGORY_UNITS = <?= json_encode($categoryUnits, JSON_UNESCAPED_UNICODE) ?>;
const CATEGORY_CAPABILITIES = <?= json_encode($categoryCapabilities, JSON_UNESCAPED_UNICODE) ?>;

function categorySupports(categoryKey, capability) {
  return (CATEGORY_CAPABILITIES[categoryKey] || []).includes(capability);
}

function updateSpecialColorVisibility(categoryKey) {
  const section = document.getElementById('specialColorsSection');
  if (!section) return;
  const enabled = categorySupports(categoryKey, 'color_surcharge');
  section.hidden = !enabled;
  if (!enabled && specialColors.length) {
    specialColors = [];
    renderColors();
  }
}

function renderColors() {
  const container = document.getElementById('specialColorsContainer');
  const empty     = document.getElementById('specialColorsEmpty');
  const hidden    = document.getElementById('pSpecialColors');
  if (!container) return;

  if (!specialColors.length) {
    container.innerHTML = '';
    empty.style.display = '';
    hidden.value = '[]';
    return;
  }
  empty.style.display = 'none';
  hidden.value = JSON.stringify(specialColors);

  // Lấy giá bán hiện tại để tính %
  const basePrice = parseFloat(document.getElementById('pPriceOut')?.value || 0) || 0;

  container.innerHTML = specialColors.map((c, i) => {
    const surchargeType = c.surcharge_type || 'fixed'; // 'fixed' | 'percent'
    const pctVal  = c.surcharge_pct || 0;
    const fixVal  = c.surcharge     || 0;
    // Giá hiển thị tính toán
    const computed = surchargeType === 'percent'
      ? Math.round(basePrice * pctVal / 100)
      : fixVal;
    const finalPrice = basePrice + computed;

    return `
    <div class="d-flex gap-2 align-items-center mb-2" id="colorRow_${i}">
      <!-- Tên màu -->
      <input type="text" class="form-control form-control-sm" style="flex:2"
        placeholder="Tên màu (VD: Đỏ đậm...)"
        value="${esc(c.name)}"
        oninput="updateColor(${i},'name',this.value)">
      <!-- Mã màu -->
      <input type="text" class="form-control form-control-sm" style="flex:1;font-family:monospace"
        placeholder="Mã màu"
        value="${esc(c.code||'')}"
        oninput="updateColor(${i},'code',this.value)">
      <!-- Loại phụ thu -->
      <select class="form-select form-select-sm" style="width:70px;flex-shrink:0"
        onchange="updateColor(${i},'surcharge_type',this.value);renderColors()">
        <option value="fixed"   ${surchargeType==='fixed'  ?'selected':''}>₫</option>
        <option value="percent" ${surchargeType==='percent'?'selected':''}>%</option>
      </select>
      <!-- Giá trị phụ thu -->
      ${surchargeType === 'percent' ? `
      <div class="input-group input-group-sm" style="width:130px;flex-shrink:0">
        <input type="number" class="form-control" style="text-align:right"
          min="0" max="100" step="1" value="${pctVal}"
          onfocus="this.select()"
          oninput="updateColor(${i},'surcharge_pct',this.value);recalcSurcharge(${i})">
        <span class="input-group-text">%</span>
      </div>` : `
      <div class="input-group input-group-sm" style="width:130px;flex-shrink:0">
        <input type="number" class="form-control" style="text-align:right"
          min="0" step="1" value="${fixVal}"
          onfocus="this.select()"
          oninput="updateColor(${i},'surcharge',this.value)">
        <span class="input-group-text">₫</span>
      </div>`}
      <!-- Preview giá cuối -->
      ${basePrice > 0 ? `
      <div style="flex-shrink:0;font-size:11px;color:#7c3aed;font-weight:700;white-space:nowrap;min-width:90px;text-align:right">
        = ${_fmtPriceShort(finalPrice)}
      </div>` : ''}
      <!-- Xóa -->
      <button type="button" class="btn btn-sm btn-outline-danger" style="flex-shrink:0"
        onclick="removeColor(${i})"><i class="bi bi-x"></i></button>
    </div>`;
  }).join('');
}

// Tính lại surcharge (₫) từ % khi giá bán thay đổi
function recalcSurcharge(idx) {
  const basePrice = parseFloat(document.getElementById('pPriceOut')?.value || 0) || 0;
  const c = specialColors[idx];
  if (!c || c.surcharge_type !== 'percent') return;
  c.surcharge = Math.round(basePrice * (c.surcharge_pct || 0) / 100);
  document.getElementById('pSpecialColors').value = JSON.stringify(specialColors);
}

// Tính lại tất cả khi giá bán thay đổi
function recalcAllSurcharges() {
  const basePrice = parseFloat(document.getElementById('pPriceOut')?.value || 0) || 0;
  specialColors.forEach(c => {
    if (c.surcharge_type === 'percent') {
      c.surcharge = Math.round(basePrice * (c.surcharge_pct || 0) / 100);
    }
  });
  document.getElementById('pSpecialColors').value = JSON.stringify(specialColors);
  if (specialColors.length) renderColors(); // refresh preview
}

function _fmtPriceShort(n) {
  if (n >= 1000000) return (n/1000000).toFixed(1).replace('.0','') + 'M';
  if (n >= 1000)    return (n/1000).toFixed(0) + 'k';
  return n.toLocaleString('vi-VN');
}

function addColorRow() {
  const basePrice = parseFloat(document.getElementById('pPriceOut')?.value || 0) || 0;
  const defaultPct = 10;
  const computed   = Math.round(basePrice * defaultPct / 100);
  specialColors.push({
    name:           '',
    code:           '',
    surcharge_type: 'percent',
    surcharge_pct:  defaultPct,
    surcharge:      computed,   // ₫ tính sẵn
  });
  renderColors();
  const rows = document.querySelectorAll('#specialColorsContainer .d-flex');
  if (rows.length) rows[rows.length-1].querySelector('input')?.focus();
}

function updateColor(idx, field, val) {
  if (!specialColors[idx]) return;
  if (field === 'surcharge' || field === 'surcharge_pct') {
    specialColors[idx][field] = parseFloat(val) || 0;
  } else if (field === 'surcharge_type') {
    specialColors[idx][field] = val;
    // Reset giá trị khi đổi loại
    if (val === 'percent') {
      specialColors[idx].surcharge_pct = specialColors[idx].surcharge_pct || 10;
      recalcSurcharge(idx);
    } else {
      specialColors[idx].surcharge = specialColors[idx].surcharge || 0;
    }
  } else {
    specialColors[idx][field] = val;
  }
  document.getElementById('pSpecialColors').value = JSON.stringify(specialColors);
}

function removeColor(idx) {
  specialColors.splice(idx, 1);
  renderColors();
}

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function updateUnitOptions(categoryKey, selectedUnit = '') {
  const unitSel = document.getElementById('pUnit');
  if (!unitSel) return;
  const units = CATEGORY_UNITS[categoryKey] || [];
  unitSel.innerHTML = '';
  if (!units.length) {
    unitSel.innerHTML = '<option value="">Nhóm này chưa có đơn vị</option>';
    return;
  }
  units.forEach(unit => {
    const opt = document.createElement('option');
    opt.value = unit;
    opt.textContent = unit;
    unitSel.appendChild(opt);
  });
  unitSel.value = units.includes(selectedUnit) ? selectedUnit : units[0];
}

document.getElementById('pCategory')?.addEventListener('change', function() {
  updateUnitOptions(this.value);
  updateSpecialColorVisibility(this.value);
});

function updateBulkTemplateLink() {
  const cat = document.getElementById('bulkCategory')?.value || '';
  const link = document.getElementById('bulkTemplateLink');
  const colorEnabled = categorySupports(cat, 'color_surcharge');
  document.querySelectorAll('.bulk-color-column').forEach(el => {
    el.style.display = colorEnabled ? '' : 'none';
  });
  const hint = document.getElementById('bulkCapabilityHint');
  if (hint) {
    hint.textContent = colorEnabled
      ? 'Nhóm này có màu đặc biệt: file mẫu sẽ bao gồm cột Màu đặc biệt.'
      : 'Nhóm này không dùng màu đặc biệt: file mẫu chỉ gồm các cột sản phẩm cơ bản.';
  }
  if (!link) return;
  if (!cat) {
    link.href = '#';
    link.classList.add('disabled');
    link.setAttribute('aria-disabled', 'true');
    return;
  }
  link.href = `index.php?page=products&branch=<?= urlencode($reqBranch) ?>&action=bulk_template&cat=${encodeURIComponent(cat)}`;
  link.classList.remove('disabled');
  link.removeAttribute('aria-disabled');
}

function openBulkModal() {
  const bulkCat = document.getElementById('bulkCategory');
  if (bulkCat && !bulkCat.value) {
    bulkCat.value = '<?= htmlspecialchars($category, ENT_QUOTES) ?>';
  }
  updateBulkTemplateLink();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkImportModal')).show();
}

// ── Modal thêm ────────────────────────────────────────────────
function openAddModal() {
  document.getElementById('modalTitle').textContent   = 'Thêm Sản Phẩm Mới';
  document.getElementById('modalBtnText').textContent = 'Thêm sản phẩm';
  document.getElementById('pId').value       = '';
  document.getElementById('pCode').value     = '';
  document.getElementById('pName').value     = '';
  document.getElementById('pPriceIn').value  = '0';
  document.getElementById('pPriceOut').value = '0';
  document.getElementById('pStock').value    = '0';
  document.getElementById('pMinStock').value = '5';
  document.getElementById('pCategory').value = '';
  updateUnitOptions('');
  document.getElementById('pStock').removeAttribute('readonly');
  specialColors = [];
  renderColors();
  updateSpecialColorVisibility('');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('productModal')).show();
}

// ── Modal sửa ────────────────────────────────────────────────
function openEditModal(p) {
  document.getElementById('modalTitle').textContent   = 'Sửa Thông Tin Sản Phẩm';
  document.getElementById('modalBtnText').textContent = 'Lưu thay đổi';
  document.getElementById('pId').value       = p.id       || '';
  document.getElementById('pCode').value     = p.code     || '';
  document.getElementById('pName').value     = p.name     || '';
  document.getElementById('pPriceIn').value  = p.price_in  || 0;
  document.getElementById('pPriceOut').value = p.price_out || 0;
  document.getElementById('pStock').value    = p.stock     || 0;
  document.getElementById('pMinStock').value = p.min_stock || 5;
  const catSel = document.getElementById('pCategory');
  if (p.category_key) catSel.value = p.category_key;
  updateUnitOptions(catSel.value, p.unit || '');
  document.getElementById('pStock').setAttribute('readonly', true);
  // Load màu đặc biệt
  specialColors = Array.isArray(p.special_colors) ? p.special_colors : [];
  renderColors();
  updateSpecialColorVisibility(catSel.value);
  bootstrap.Modal.getOrCreateInstance(document.getElementById('productModal')).show();
}
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
