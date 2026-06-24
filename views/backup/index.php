<?php
$pageTitle = 'Sao Lưu & Phục Hồi Dữ Liệu';
include BASE_PATH . '/views/layouts/header.php';

$dataPath   = DATA_PATH;
$backupPath = backupDir();

$backups = [];
foreach (glob($backupPath . '/backup_*.zip') ?: [] as $f) {
    $backups[] = ['name'=>basename($f),'size'=>filesize($f),'time'=>filemtime($f),'path'=>$f];
}
usort($backups, fn($a,$b) => $b['time'] <=> $a['time']);

function dirSize(string $dir): int {
    $size = 0;
    if (!is_dir($dir)) return 0;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS)) as $f) {
        $size += $f->getSize();
    }
    return $size;
}
function fmtSize(int $b): string {
    if($b<1024) return $b.' B';
    if($b<1048576) return round($b/1024,1).' KB';
    return round($b/1048576,1).' MB';
}
$dataSize      = dirSize($dataPath);
$daysSinceLast = !empty($backups) ? floor((time()-$backups[0]['time'])/86400) : 999;
$statusColor   = $daysSinceLast<=1 ? 'green' : ($daysSinceLast<=7 ? 'amber' : 'red');
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-shield-lock-fill me-2" style="color:#3b82f6"></i>Sao Lưu & Phục Hồi Dữ Liệu</h2>
    <p>Bảo vệ dữ liệu sản phẩm, hóa đơn, người dùng và cấu hình cửa hàng</p>
  </div>
  <div class="d-flex gap-2"><a href="index.php?page=help&topic=backup" class="btn btn-outline-secondary context-help-btn" title="Hướng dẫn sao lưu và phục hồi"><i class="bi bi-question-circle"></i><span class="context-help-label">Hướng dẫn</span></a><form method="POST" action="index.php?page=backup&action=create"><?= csrfField() ?><button type="submit" class="btn btn-primary"><i class="bi bi-archive-fill me-2"></i>Sao Lưu Ngay</button></form></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card stat-blue">
      <div class="stat-icon"><i class="bi bi-database-fill"></i></div>
      <div class="stat-value" style="font-size:20px"><?= fmtSize($dataSize) ?></div>
      <div class="stat-label">Kích thước dữ liệu</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-archive"></i></div>
      <div class="stat-value"><?= count($backups) ?></div>
      <div class="stat-label">File backup hiện có</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-amber">
      <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
      <div class="stat-value" style="font-size:16px"><?= !empty($backups)?date('d/m/Y',$backups[0]['time']):'Chưa có' ?></div>
      <div class="stat-label">Backup gần nhất</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-<?= $statusColor ?>">
      <div class="stat-icon"><i class="bi bi-shield-<?= $statusColor==='green'?'check':'exclamation' ?>"></i></div>
      <div class="stat-value" style="font-size:16px">
        <?= $daysSinceLast>=999?'—':($daysSinceLast===0?'Hôm nay':$daysSinceLast.' ngày') ?>
      </div>
      <div class="stat-label">Kể từ backup cuối</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100" style="border-left:4px solid #3b82f6">
      <div class="card-header fw-700">
        <i class="bi bi-calendar-check me-2 text-primary"></i>Lên Lịch Tự Động
      </div>
      <div class="card-body">
        <p style="font-size:13.5px;margin-bottom:12px">Trên cPanel, vào <strong>Cron Jobs</strong> và thêm lệnh:</p>
        <div class="row g-3">
          <div class="col-md-6">
            <div style="background:#1e293b;border-radius:8px;padding:14px 16px">
              <div style="font-size:11px;color:#94a3b8;margin-bottom:6px;font-weight:700">Hằng ngày - 23:00</div>
              <code style="color:#34d399;font-size:11.5px;font-family:'JetBrains Mono',monospace;display:block;word-break:break-all">0 23 * * * php <?= BASE_PATH ?>/cron_backup.php daily</code>
            </div>
          </div>
          <div class="col-md-6">
            <div style="background:#1e293b;border-radius:8px;padding:14px 16px">
              <div style="font-size:11px;color:#94a3b8;margin-bottom:6px;font-weight:700">Hằng tuần - Chủ nhật 23:00</div>
              <code style="color:#34d399;font-size:11.5px;font-family:'JetBrains Mono',monospace;display:block;word-break:break-all">0 23 * * 0 php <?= BASE_PATH ?>/cron_backup.php weekly</code>
            </div>
          </div>
        </div>
        <div style="font-size:12px;color:#6b7280;margin-top:10px">
          <i class="bi bi-info-circle me-1"></i>Nên tải thêm bản backup về máy hoặc Google Drive định kỳ.
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100" style="border-left:4px solid #ef4444">
      <div class="card-header fw-700">
        <i class="bi bi-arrow-counterclockwise me-2 text-danger"></i>Phục Hồi Từ File ZIP
      </div>
      <div class="card-body">
        <div class="alert alert-danger py-2" style="font-size:13px">
          Phục hồi sẽ thay thế toàn bộ thư mục <code>data/</code>. Hệ thống tự tạo một backup an toàn trước khi phục hồi.
        </div>
        <form method="POST" action="index.php?page=backup&action=restore_upload" enctype="multipart/form-data"
          onsubmit="return confirmRestore('Phục hồi từ file upload? Dữ liệu hiện tại sẽ được thay bằng dữ liệu trong file ZIP.')">
          <?= csrfField() ?>
          <label class="form-label">Chọn file backup .zip</label>
          <input type="file" name="restore_file" class="form-control mb-2" accept=".zip,application/zip" required>
          <div class="form-text mb-3">Chỉ chấp nhận ZIP có cấu trúc <code>data/...</code> và các file JSON hợp lệ.</div>
          <button type="submit" class="btn btn-danger w-100">
            <i class="bi bi-upload me-1"></i>Upload & Phục Hồi
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span class="fw-700"><i class="bi bi-list-ul me-2"></i>Danh Sách File Backup (<?= count($backups) ?>)</span>
    <?php if(count($backups)>0): ?>
    <form method="POST" action="index.php?page=backup&action=cleanup" class="m-0"
       onsubmit="return confirm('Chỉ giữ 10 file mới nhất, xóa phần còn lại?')">
      <?= csrfField() ?>
      <button type="submit" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-trash me-1"></i>Dọn dẹp
      </button>
    </form>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <?php if(empty($backups)): ?>
    <div class="empty-state"><i class="bi bi-archive"></i><p>Chưa có backup. Nhấn <b>Sao Lưu Ngay</b> để bắt đầu.</p></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Tên file</th><th>Kích thước</th><th>Thời gian</th><th class="text-center">Thao tác</th></tr></thead>
        <tbody>
        <?php foreach($backups as $idx=>$bk): ?>
        <tr <?= $idx===0?'style="background:#f0fdf4"':'' ?>>
          <td>
            <i class="bi bi-file-earmark-zip-fill me-2" style="color:#3b82f6"></i>
            <span class="fw-600" style="font-size:13px"><?= htmlspecialchars($bk['name']) ?></span>
            <?php if($idx===0): ?><span class="badge bg-success ms-2" style="font-size:10px">Mới nhất</span><?php endif; ?>
          </td>
          <td class="text-muted" style="font-size:13px"><?= fmtSize($bk['size']) ?></td>
          <td style="font-size:13px"><?= date('d/m/Y H:i:s',$bk['time']) ?></td>
          <td class="text-center">
            <a href="index.php?page=backup&action=download&file=<?= urlencode($bk['name']) ?>"
               class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Tải về</a>
            <form method="POST" action="index.php?page=backup&action=restore_existing" class="d-inline ms-1"
              onsubmit="return confirmRestore('Phục hồi từ file <?= htmlspecialchars($bk['name'], ENT_QUOTES) ?>? Dữ liệu hiện tại sẽ được thay thế.')">
              <?= csrfField() ?>
              <input type="hidden" name="file" value="<?= htmlspecialchars($bk['name']) ?>">
              <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-counterclockwise me-1"></i>Phục hồi</button>
            </form>
            <form method="POST" action="index.php?page=backup&action=delete" class="d-inline ms-1"
               onsubmit="return confirm('Xóa file backup này?')">
              <?= csrfField() ?>
              <input type="hidden" name="file" value="<?= htmlspecialchars($bk['name']) ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-3" style="border-left:4px solid #f59e0b">
  <div class="card-body py-2" style="font-size:13px">
    <i class="bi bi-lightbulb-fill text-warning me-2"></i>
    <strong>Chuẩn vận hành:</strong> Luôn tải backup về máy/Drive trước khi nâng cấp code. Chỉ phục hồi file ZIP lấy từ hệ thống này hoặc từ bản backup đã kiểm chứng.
  </div>
</div>

<script>
function confirmRestore(message) {
  return confirm(message + "\n\nHệ thống sẽ tự tạo một backup an toàn trước khi phục hồi. Tiếp tục?");
}
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
