<?php
$pageTitle = 'Hướng Dẫn Sử Dụng';
$user      = currentUser();
$role      = $user['role'] ?? 'sales';

// Map role → file tài liệu
$docMap = [
    'superadmin' => [
        'file'  => 'docs/huong_dan_superadmin.docx',
        'label' => 'Super Admin',
        'icon'  => 'bi-shield-fill-check',
        'color' => '#ef4444',
        'bg'    => '#fef2f2',
        'desc'  => 'Toàn quyền hệ thống — Quản lý tài khoản, nhóm hàng, sản phẩm, hóa đơn, báo cáo',
    ],
    'admin' => [
        'file'  => 'docs/huong_dan_admin.docx',
        'label' => 'Admin',
        'icon'  => 'bi-shield-check',
        'color' => '#f59e0b',
        'bg'    => '#fffbeb',
        'desc'  => 'Quản lý sản phẩm, nhóm hàng, tài khoản nhân viên, xem báo cáo toàn hệ thống',
    ],
    'sales' => [
        'file'  => 'docs/huong_dan_sales.docx',
        'label' => 'Nhân Viên Bán Hàng',
        'icon'  => 'bi-person-badge',
        'color' => '#3b82f6',
        'bg'    => '#eff6ff',
        'desc'  => 'Lập hóa đơn bán hàng, xem danh sách hóa đơn, xem sản phẩm và tồn kho',
    ],
    'warehouse' => [
        'file'  => 'docs/huong_dan_warehouse.docx',
        'label' => 'Nhân Viên Nhập Hàng',
        'icon'  => 'bi-truck',
        'color' => '#10b981',
        'bg'    => '#f0fdf4',
        'desc'  => 'Nhập hàng, cập nhật tồn kho, xem sản phẩm tất cả chi nhánh',
    ],
];

$myDoc    = $docMap[$role] ?? $docMap['sales'];
$docPath  = BASE_PATH . '/' . $myDoc['file'];
$docReady = file_exists($docPath);

include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header">
  <h2><i class="bi bi-book-fill me-2" style="color:#8b5cf6"></i>Hướng Dẫn Sử Dụng Hệ Thống</h2>
  <p>Tài liệu hướng dẫn chi tiết dành cho từng vai trò</p>
</div>

<!-- Tài liệu của tôi -->
<div class="card mb-4" style="border-left: 5px solid <?= $myDoc['color'] ?>">
  <div class="card-body">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div style="width:56px;height:56px;border-radius:14px;display:grid;place-items:center;font-size:26px;
        background:<?= $myDoc['bg'] ?>;color:<?= $myDoc['color'] ?>;flex-shrink:0">
        <i class="bi <?= $myDoc['icon'] ?>"></i>
      </div>
      <div>
        <div style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:1px;text-transform:uppercase">Tài liệu của bạn</div>
        <div style="font-size:19px;font-weight:800;color:#111827">Hướng Dẫn — <?= htmlspecialchars($myDoc['label']) ?></div>
        <div style="font-size:13px;color:#6b7280;margin-top:2px"><?= htmlspecialchars($myDoc['desc']) ?></div>
      </div>
    </div>

    <?php if ($docReady): ?>
      <div class="d-flex gap-2 flex-wrap">
        <a href="<?= $myDoc['file'] ?>" download
           class="btn btn-primary">
          <i class="bi bi-download me-2"></i>Tải xuống tài liệu (.docx)
        </a>
        <a href="index.php?page=help&action=view&role=<?= $role ?>"
           class="btn btn-outline-secondary">
          <i class="bi bi-eye me-2"></i>Xem tóm tắt nội dung
        </a>
      </div>
      <div class="mt-2" style="font-size:12px;color:#9ca3af">
        <i class="bi bi-info-circle me-1"></i>
        File .docx — mở bằng Microsoft Word, LibreOffice hoặc Google Docs
      </div>
    <?php else: ?>
      <div class="alert alert-warning mb-0 py-2" style="font-size:13px">
        <i class="bi bi-clock-history me-2"></i>
        Tài liệu cho nhóm <b><?= htmlspecialchars($myDoc['label']) ?></b> đang được chuẩn bị.
        Vui lòng quay lại sau.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
// Nếu request xem tóm tắt
$viewRole = $_GET['role'] ?? '';
$viewAction = $_GET['action'] ?? '';
if ($viewAction === 'view' && $viewRole && isset($docMap[$viewRole])):
  // Chỉ cho xem tài liệu của mình (trừ admin/superadmin xem được tất cả)
  if ($viewRole === $role || in_array($role, ['superadmin','admin'])):
?>
<div class="card mb-4" id="summaryCard">
  <div class="card-header fw-700">
    <i class="bi bi-layout-text-sidebar me-2"></i>Tóm Tắt Nội Dung — <?= htmlspecialchars($docMap[$viewRole]['label']) ?>
  </div>
  <div class="card-body">
    <?php include BASE_PATH . '/views/help/summary_' . $viewRole . '.php'; ?>
  </div>
</div>
<?php endif; endif; ?>

<!-- Danh sách tài liệu (admin/superadmin thấy tất cả) -->
<?php if (in_array($role, ['superadmin', 'admin'])): ?>
<div class="card">
  <div class="card-header fw-700">
    <i class="bi bi-collection me-2"></i>Tất Cả Tài Liệu Trong Hệ Thống
  </div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach ($docMap as $rKey => $rDoc):
        $exists = file_exists(BASE_PATH . '/' . $rDoc['file']);
      ?>
      <div class="col-md-6">
        <div style="border:1px solid #e5e7eb;border-radius:10px;padding:16px;
          border-left:4px solid <?= $rDoc['color'] ?>;
          background:<?= $rKey === $role ? $rDoc['bg'] : '#fff' ?>">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi <?= $rDoc['icon'] ?>" style="font-size:18px;color:<?= $rDoc['color'] ?>"></i>
            <span style="font-weight:700;font-size:14px;color:#111827"><?= htmlspecialchars($rDoc['label']) ?></span>
            <?php if ($rKey === $role): ?>
            <span class="badge ms-auto" style="background:<?= $rDoc['color'] ?>;font-size:10px">Của bạn</span>
            <?php endif; ?>
          </div>
          <div style="font-size:12px;color:#6b7280;margin-bottom:12px"><?= htmlspecialchars($rDoc['desc']) ?></div>
          <div class="d-flex gap-2">
            <?php if ($exists): ?>
              <a href="<?= $rDoc['file'] ?>" download class="btn btn-sm btn-outline-primary">
                <i class="bi bi-download me-1"></i>Tải xuống
              </a>
            <?php else: ?>
              <span class="btn btn-sm btn-outline-secondary disabled">
                <i class="bi bi-clock me-1"></i>Chưa có
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
