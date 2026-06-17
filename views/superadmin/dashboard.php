<?php
$pageTitle  = 'Bảng Điều Khiển Kỹ Thuật';
$backupPath = BASE_PATH . '/backups';
$backups    = glob($backupPath . '/backup_*.zip') ?: [];
usort($backups, fn($a,$b) => filemtime($b) <=> filemtime($a));
$lastBackup    = !empty($backups) ? date('d/m/Y H:i', filemtime($backups[0])) : 'Chưa có';
$daysSinceLast = !empty($backups) ? floor((time()-filemtime($backups[0]))/86400) : 999;
$dataSize      = 0;
if (is_dir(DATA_PATH)) {
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DATA_PATH, FilesystemIterator::SKIP_DOTS)) as $f) {
        $dataSize += $f->getSize();
    }
}
$users = getAllUsers();
include BASE_PATH . '/views/layouts/header.php';
?>

<!-- Cảnh báo vai trò -->
<div class="alert py-2 mb-4" style="background:#fef2f2;border:1px solid #fecaca;border-left:4px solid #ef4444;font-size:13.5px">
  <i class="bi bi-shield-fill-check me-2 text-danger"></i>
  Bạn đang đăng nhập với tư cách <strong>Kỹ Thuật Viên</strong> —
  tài khoản này <strong>không có quyền xem dữ liệu kinh doanh</strong> của khách hàng.
  Chỉ có thể quản lý tài khoản và sao lưu dữ liệu.
</div>

<div class="page-header">
  <h2><i class="bi bi-cpu me-2 text-danger"></i>Bảng Điều Khiển Kỹ Thuật</h2>
  <p>Quản lý hệ thống — <?= date('d/m/Y H:i') ?></p>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card stat-blue">
      <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
      <div class="stat-value"><?= count($users) ?></div>
      <div class="stat-label">Tài khoản hệ thống</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-archive-fill"></i></div>
      <div class="stat-value"><?= count($backups) ?></div>
      <div class="stat-label">File backup</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card <?= $daysSinceLast > 7 ? 'stat-red' : 'stat-amber' ?>">
      <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
      <div class="stat-value" style="font-size:16px"><?= $lastBackup ?></div>
      <div class="stat-label">Backup gần nhất</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-blue">
      <div class="stat-icon"><i class="bi bi-database-fill"></i></div>
      <div class="stat-value" style="font-size:18px">
        <?= $dataSize > 1048576 ? round($dataSize/1048576,1).' MB' : round($dataSize/1024,1).' KB' ?>
      </div>
      <div class="stat-label">Kích thước dữ liệu</div>
    </div>
  </div>
</div>

<div class="row g-3">

  <!-- Danh sách tài khoản -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header fw-700 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people-fill me-2"></i>Tài Khoản Trong Hệ Thống</span>
        <a href="index.php?page=users" class="btn btn-sm btn-outline-primary">Quản lý</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Tài khoản</th><th>Họ tên</th><th>Vai trò</th><th>Trạng thái</th></tr></thead>
          <tbody>
          <?php
          $roleColors = ['superadmin'=>'danger','owner'=>'warning','admin'=>'info','sales'=>'primary','warehouse'=>'success'];
          $roleLabels = ['superadmin'=>'Kỹ Thuật','owner'=>'Chủ CH','admin'=>'Quản lý','sales'=>'Bán hàng','warehouse'=>'Nhập hàng'];
          foreach ($users as $u):
            $active = $u['active'] ?? true;
          ?>
          <tr class="<?= !$active ? 'opacity-50' : '' ?>">
            <td><code style="font-size:11px"><?= htmlspecialchars($u['username']) ?></code></td>
            <td style="font-size:13px"><?= htmlspecialchars($u['name']) ?></td>
            <td>
              <span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?> bg-opacity-15 text-<?= $roleColors[$u['role']] ?? 'secondary' ?>" style="font-size:10px">
                <?= $roleLabels[$u['role']] ?? $u['role'] ?>
              </span>
            </td>
            <td>
              <?php if ($active): ?>
              <span class="badge bg-success bg-opacity-10 text-success" style="font-size:10px">Hoạt động</span>
              <?php else: ?>
              <span class="badge bg-danger bg-opacity-10 text-danger" style="font-size:10px">Đã khóa</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Sao lưu nhanh -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header fw-700 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-cloud-arrow-up-fill me-2 text-primary"></i>Sao Lưu Dữ Liệu</span>
        <a href="index.php?page=backup" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
      </div>
      <div class="card-body">
        <?php if ($daysSinceLast > 7): ?>
        <div class="alert alert-warning py-2 mb-3" style="font-size:13px">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          Đã <strong><?= $daysSinceLast ?> ngày</strong> chưa backup — nên sao lưu ngay!
        </div>
        <?php endif; ?>

        <form method="POST" action="index.php?page=backup&action=create" class="mb-3">
        <?= csrfField() ?>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-archive-fill me-2"></i>Sao Lưu Ngay
          </button>
        </form>

        <?php if (!empty($backups)): ?>
        <div style="font-size:12.5px;color:#6b7280;margin-bottom:6px">3 backup gần nhất:</div>
        <?php foreach (array_slice($backups, 0, 3) as $idx => $bk): ?>
        <div class="d-flex align-items-center justify-content-between py-1"
          style="border-bottom:1px solid #f3f4f6;font-size:12.5px">
          <span>
            <i class="bi bi-file-earmark-zip-fill me-1 text-primary"></i>
            <?= htmlspecialchars(basename($bk)) ?>
            <?php if ($idx === 0): ?><span class="badge bg-success ms-1" style="font-size:9px">Mới nhất</span><?php endif; ?>
          </span>
          <a href="index.php?page=backup&action=download&file=<?= urlencode(basename($bk)) ?>"
             class="btn btn-xs btn-outline-primary" style="padding:2px 8px;font-size:11px">
            <i class="bi bi-download"></i>
          </a>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="text-center text-muted py-3" style="font-size:13px">
          <i class="bi bi-archive" style="font-size:32px;opacity:.3;display:block;margin-bottom:8px"></i>
          Chưa có file backup nào
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Thông tin hệ thống -->
    <div class="card mt-3">
      <div class="card-header fw-700">
        <i class="bi bi-info-circle me-2"></i>Thông Tin Hệ Thống
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:12.5px">
          <tr><td class="text-muted">PHP Version</td><td><?= phpversion() ?></td></tr>
          <tr><td class="text-muted">Thư mục data</td><td><code style="font-size:11px"><?= DATA_PATH ?></code></td></tr>
          <tr><td class="text-muted">Múi giờ</td><td><?= date_default_timezone_get() ?></td></tr>
          <tr><td class="text-muted">Thời gian server</td><td><?= date('d/m/Y H:i:s') ?></td></tr>
          <tr><td class="text-muted">Phiên bản PM</td><td><?= APP_VERSION ?></td></tr>
        </table>
      </div>
    </div>
  </div>

</div>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
