<?php
$bulkPreview = $_SESSION['import_bulk_preview'] ?? null;
$bulkPreview = is_array($bulkPreview) && ($bulkPreview['branch'] ?? '') === $reqBranch ? $bulkPreview : null;
$showBulkModal = isset($_GET['bulk_preview']);
$bulkValid = $bulkPreview['valid'] ?? [];
$bulkErrors = $bulkPreview['errors'] ?? [];
?>

<style>
.bulk-steps { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:16px; }
.bulk-step { align-items:center; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; color:#6b7280; display:flex; font-size:12px; font-weight:800; gap:8px; padding:9px 10px; }
.bulk-step span { align-items:center; background:#fff; border:1px solid #d1d5db; border-radius:50%; display:flex; height:23px; justify-content:center; width:23px; }
.bulk-step.active { background:rgba(245,158,11,.08); border-color:rgba(245,158,11,.35); color:#92400e; }
.bulk-step.active span { background:var(--accent); border-color:var(--accent); color:#fff; }
.bulk-drop { background:#f9fafb; border:1.5px dashed #cbd5e1; border-radius:10px; padding:16px; }
.bulk-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
.bulk-summary > div { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:10px; text-align:center; }
.bulk-preview-table { max-height:300px; overflow:auto; }
@media(max-width:576px){ .bulk-steps{grid-template-columns:1fr}.bulk-summary{grid-template-columns:1fr 1fr}.bulk-modal-actions{display:grid!important;grid-template-columns:1fr}.bulk-modal-actions .btn{width:100%} }
</style>

<div class="modal fade" id="bulkImportModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Nhập Hàng Hàng Loạt</h5>
          <div class="text-muted" style="font-size:12px">Một file CSV tạo một phiếu nhập tại <?= htmlspecialchars($branchInfo['name']) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="bulk-steps">
          <div class="bulk-step active"><span>1</span>Thông tin phiếu</div>
          <div class="bulk-step <?= $bulkPreview ? 'active' : '' ?>"><span>2</span>Tải file & kiểm tra</div>
          <div class="bulk-step <?= $bulkPreview && !$bulkErrors && $bulkValid ? 'active' : '' ?>"><span>3</span>Xác nhận nhập kho</div>
        </div>

        <form method="POST" enctype="multipart/form-data" action="index.php?page=imports&branch=<?= urlencode($reqBranch) ?>&action=bulk_preview">
          <?= csrfField() ?>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Nhà cung cấp</label>
              <input class="form-control" name="supplier" value="<?= htmlspecialchars($bulkPreview['supplier'] ?? '') ?>" placeholder="Tên nhà cung cấp">
            </div>
            <div class="col-md-4">
              <label class="form-label">Mã hóa đơn / chứng từ</label>
              <input class="form-control" name="reference_no" value="<?= htmlspecialchars($bulkPreview['reference_no'] ?? '') ?>" placeholder="VD: HD-000123">
            </div>
            <div class="col-md-4">
              <label class="form-label">Ngày nhập *</label>
              <input type="hidden" name="import_date" id="bulkImportDateIso" value="<?= htmlspecialchars($bulkPreview['import_date'] ?? date('Y-m-d')) ?>">
              <input type="text" class="form-control" data-vn-date-target="bulkImportDateIso" value="<?= htmlspecialchars($bulkPreview['import_date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-8">
              <label class="form-label">Ghi chú chung</label>
              <input class="form-control" name="note" value="<?= htmlspecialchars($bulkPreview['note'] ?? '') ?>" placeholder="Nội dung phiếu nhập">
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="update_price" id="bulkUpdatePrice" <?= ($bulkPreview['update_price'] ?? true) ? 'checked' : '' ?>>
                <label class="form-check-label fw-700" for="bulkUpdatePrice">Cập nhật giá nhập mới nhất</label>
              </div>
            </div>
            <div class="col-12">
              <div class="bulk-drop">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                  <div>
                    <div class="fw-800">File CSV hàng hóa</div>
                    <div class="text-muted" style="font-size:12px">Cột: Mã SP, Số lượng, Giá nhập, Ghi chú. Tối đa 5 MB.</div>
                  </div>
                  <a class="btn btn-sm btn-outline-primary" href="index.php?page=imports&branch=<?= urlencode($reqBranch) ?>&action=bulk_template"><i class="bi bi-download me-1"></i>Tải file mẫu</a>
                </div>
                <input class="form-control" type="file" name="csv_file" accept=".csv,text/csv" required>
              </div>
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Đọc và kiểm tra file</button>
            </div>
          </div>
        </form>

        <?php if ($bulkPreview): ?>
        <hr class="my-4">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
          <div>
            <h6 class="fw-800 mb-1">Kết quả kiểm tra: <?= htmlspecialchars($bulkPreview['file_name'] ?? '') ?></h6>
            <div class="text-muted" style="font-size:12px">Chưa cập nhật tồn kho. Hãy kiểm tra kỹ trước khi xác nhận.</div>
          </div>
        </div>
        <div class="bulk-summary mb-3">
          <div><div class="text-muted" style="font-size:11px">Hợp lệ</div><div class="fw-800 text-success fs-5"><?= count($bulkValid) ?></div></div>
          <div><div class="text-muted" style="font-size:11px">Dòng lỗi</div><div class="fw-800 <?= $bulkErrors ? 'text-danger' : 'text-success' ?> fs-5"><?= count($bulkErrors) ?></div></div>
          <div><div class="text-muted" style="font-size:11px">Tổng tiền</div><div class="fw-800 money" style="font-size:15px"><?= formatMoney((float)($bulkPreview['total_amount'] ?? 0)) ?></div></div>
        </div>

        <?php if ($bulkValid): ?>
        <div class="bulk-preview-table table-responsive mb-3">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead><tr><th>Mã SP</th><th>Sản phẩm</th><th class="text-end">Số lượng</th><th class="text-end">Giá nhập</th><th class="text-end">Thành tiền</th><th>Dòng CSV</th></tr></thead>
            <tbody>
            <?php foreach ($bulkValid as $item): ?>
              <tr>
                <td><code><?= htmlspecialchars($item['code']) ?></code></td>
                <td><div class="fw-700"><?= htmlspecialchars($item['name']) ?></div><div class="text-muted" style="font-size:11px"><?= htmlspecialchars($item['note'] ?? '') ?></div></td>
                <td class="text-end fw-700"><?= number_format((float)$item['qty'],2,',','.') ?> <?= htmlspecialchars($item['unit']) ?></td>
                <td class="text-end"><?= formatMoney((float)$item['price_in']) ?></td>
                <td class="text-end money fw-700"><?= formatMoney((float)$item['qty']*(float)$item['price_in']) ?></td>
                <td><?= htmlspecialchars(implode(', ', $item['source_rows'] ?? [])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($bulkErrors): ?>
        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>File còn lỗi. Sửa file và tải lại; hệ thống sẽ không nhập một phần.</div>
        <div class="table-responsive mb-3">
          <table class="table table-sm table-bordered mb-0">
            <thead><tr><th>Dòng</th><th>Mã SP</th><th>Lỗi</th></tr></thead>
            <tbody><?php foreach ($bulkErrors as $item): ?><tr><td><?= (int)$item['row'] ?></td><td><code><?= htmlspecialchars($item['code']) ?></code></td><td class="text-danger"><?= htmlspecialchars(implode('; ', $item['errors'])) ?></td></tr><?php endforeach; ?></tbody>
          </table>
        </div>
        <?php endif; ?>

        <div class="bulk-modal-actions d-flex justify-content-end gap-2">
          <form method="POST" action="index.php?page=imports&branch=<?= urlencode($reqBranch) ?>&action=bulk_cancel">
            <?= csrfField() ?><button class="btn btn-outline-secondary">Hủy preview</button>
          </form>
          <form method="POST" action="index.php?page=imports&branch=<?= urlencode($reqBranch) ?>&action=bulk_commit" onsubmit="return confirm('Xác nhận cộng tồn kho cho <?= count($bulkValid) ?> mặt hàng?')">
            <?= csrfField() ?><button class="btn btn-primary" <?= (!$bulkValid || $bulkErrors) ? 'disabled' : '' ?>><i class="bi bi-check2-circle me-1"></i>Xác nhận nhập kho</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($showBulkModal): ?>
<script>document.addEventListener('DOMContentLoaded',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkImportModal')).show());</script>
<?php endif; ?>