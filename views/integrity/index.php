<?php
$reqBranch = $_GET['branch'] ?? firstAccessibleBranchId();
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }
$branchInfo = getBranchInfo($reqBranch);
$result = integrityCheckBranch($reqBranch);
$pageTitle = 'Kiểm Tra Toàn Vẹn Dữ Liệu';
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header d-flex flex-wrap align-items-start justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-shield-check me-2 text-primary"></i>Kiểm Tra Toàn Vẹn Dữ Liệu</h2>
    <p><?= htmlspecialchars($branchInfo['name']) ?> · Kiểm tra lúc <?= date('H:i d/m/Y', strtotime($result['checked_at'])) ?></p>
  </div>
  <div class="d-flex gap-2"><a href="index.php?page=help&topic=integrity" class="btn btn-outline-secondary context-help-btn" title="Hướng dẫn kiểm tra toàn vẹn"><i class="bi bi-question-circle"></i><span class="context-help-label">Hướng dẫn</span></a><form method="GET" class="d-flex gap-2">
    <input type="hidden" name="page" value="integrity">
    <select class="form-select" name="branch" onchange="this.form.submit()">
      <?php foreach (getAccessibleBranches() as $id => $branch): ?>
      <option value="<?= htmlspecialchars($id) ?>" <?= $id === $reqBranch ? 'selected' : '' ?>><?= htmlspecialchars($branch['name'] ?? $id) ?></option>
      <?php endforeach; ?>
    </select>
  </form></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon text-danger"><i class="bi bi-x-octagon"></i></div><div class="stat-value text-danger"><?= (int)$result['counts']['error'] ?></div><div class="stat-label">Lỗi cần xử lý</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon text-warning"><i class="bi bi-exclamation-triangle"></i></div><div class="stat-value text-warning"><?= (int)$result['counts']['warning'] ?></div><div class="stat-label">Cảnh báo</div></div></div>
</div>

<?php if (empty($result['issues'])): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>Không phát hiện sai lệch dữ liệu.</div>
<?php else: ?>
<div class="card mb-3"><div class="card-body p-0"><div class="table-responsive">
  <table class="table table-hover mb-0">
    <thead><tr><th>Mức độ</th><th>Nội dung</th><th>Mã tham chiếu</th></tr></thead>
    <tbody>
    <?php foreach ($result['issues'] as $issue): ?>
      <tr>
        <td><span class="badge <?= $issue['severity'] === 'error' ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= $issue['severity'] === 'error' ? 'Lỗi' : 'Cảnh báo' ?></span></td>
        <td><?= htmlspecialchars($issue['message']) ?></td>
        <td><code><?= htmlspecialchars($issue['sourceId']) ?></code></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div></div></div>
<?php endif; ?>

<div class="card"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
  <div><div class="fw-bold">Đồng bộ liên kết chứng từ</div><div class="text-muted small">Tạo lại hoặc vô hiệu hóa bút toán tự động dựa trên hóa đơn và phiếu thu gốc. Không thay đổi chứng từ thủ công.</div></div>
  <form method="POST" action="index.php?page=integrity&branch=<?= urlencode($reqBranch) ?>&action=repair" onsubmit="return confirm('Đồng bộ lại các liên kết hóa đơn, công nợ và sổ thu chi?')">
    <?= csrfField() ?>
    <button class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Đồng bộ liên kết</button>
  </form>
</div></div>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
