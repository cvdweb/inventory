<?php
$topbarFullTitle = trim((string)($pageTitle ?? APP_NAME));
$topbarParts = preg_split('/\s+[—–]\s+/u', $topbarFullTitle, 2) ?: [$topbarFullTitle];
$topbarMainTitle = trim((string)($topbarParts[0] ?? $topbarFullTitle));
$topbarContext = trim((string)($topbarParts[1] ?? ''));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#111827">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Trường Phú">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> — <?= APP_NAME ?></title>
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href="assets/icons/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/icons/icon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="bi bi-box-seam-fill"></i></div>
    <div class="brand-text">
      <div class="brand-name">QuanLy NX</div>
      <div class="brand-sub">Nhập Xuất Hàng Hóa</div>
    </div>
  </div>

  <?php $user = currentUser(); $userBranch = $user['branch'] ?? null; ?>
  
  <!-- User info -->
  <a href="index.php?page=profile" class="sidebar-user" style="text-decoration:none;transition:background .15s"
     onmouseover="this.style.background='rgba(255,255,255,.06)'" onmouseout="this.style.background=''">
    <div class="user-avatar"><i class="bi <?= $user['icon'] ?? 'bi-person' ?>"></i></div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($user['name'] ?? '') ?></div>
      <div class="user-role"><?= match($user['role'] ?? '') { 'superadmin' => 'Super Admin', 'admin' => 'Chủ Cửa Hàng', 'employee' => 'Nhân Viên', default => '' } ?></div>
    </div>
    <i class="bi bi-pencil-square ms-auto" style="font-size:13px;color:#6b7280;flex-shrink:0"></i>
  </a>

  <nav class="sidebar-nav">
    <a href="index.php" class="nav-item <?= ($page === 'dashboard') ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
    </a>

    <?php if (canViewBusinessData()): ?>
    <?php foreach (getAccessibleBranches() as $bId => $b): ?>
    <div class="nav-section"><?= $b['short'] ?> — <?= $b['name'] ?></div>
    <a href="index.php?page=products&branch=<?= $bId ?>" class="nav-item <?= ($page === 'products' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-box2-fill"></i><span>Sản Phẩm</span>
    </a>
    <a href="index.php?page=imports&branch=<?= $bId ?>" class="nav-item <?= ($page === 'imports' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-download"></i><span>Nhập Hàng</span>
    </a>
    <?php if (featureEnabled('inventory')): ?><a href="index.php?page=inventory&branch=<?= $bId ?>" class="nav-item <?= ($page === 'inventory' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-clipboard-check-fill"></i><span>Kiểm Kê Kho</span>
    </a><?php endif; ?>
    <a href="index.php?page=invoice&branch=<?= $bId ?>" class="nav-item <?= ($page === 'invoice' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-receipt"></i><span>Lập Hóa Đơn</span>
    </a>
    <a href="index.php?page=invoices&branch=<?= $bId ?>" class="nav-item <?= ($page === 'invoices' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-journal-text"></i><span>DS Hóa Đơn</span>
    </a>
    <?php if (featureEnabled('returns_menu')): ?><a href="index.php?page=returns&branch=<?= $bId ?>" class="nav-item <?= ($page === 'returns' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-arrow-return-left"></i><span>Trả Hàng</span>
    </a><?php endif; ?>
    <?php if (featureEnabled('receivables')): ?><a href="index.php?page=receivables&branch=<?= $bId ?>" class="nav-item <?= ($page === 'receivables' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-wallet2"></i><span>Công Nợ</span>
    </a><?php endif; ?>
    <?php if (featureEnabled('cashbook')): ?><a href="index.php?page=cashbook&branch=<?= $bId ?>" class="nav-item <?= ($page === 'cashbook' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-cash-stack"></i><span>Thu Chi</span>
    </a><?php endif; ?>
    <?php if (in_array($user['role'] ?? '', ['superadmin', 'admin'], true) && featureEnabled('reports')): ?>
    <a href="index.php?page=reports&branch=<?= $bId ?>" class="nav-item <?= ($page === 'reports' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-bar-chart-fill"></i><span>Báo Cáo</span>
    </a>
    <?php endif; ?>
    <?php endforeach; ?>

    <div class="nav-section">Công Cụ</div>
    <a href="index.php?page=search_invoices" class="nav-item <?= ($page === 'search_invoices') ? 'active' : '' ?>">
      <i class="bi bi-search"></i><span>Tìm Kiếm HĐ</span>
    </a>
    <?php endif; // canViewBusinessData ?>

    <div class="nav-section">Hệ Thống</div>
    <?php if (in_array($user['role'] ?? '', ['superadmin', 'admin'], true)): ?>
    <a href="index.php?page=categories" class="nav-item <?= ($page === 'categories') ? 'active' : '' ?>">
      <i class="bi bi-collection-fill"></i><span>Nhóm Hàng</span>
    </a>
    <?php endif; ?>
    <?php if (canManageUsers()): ?>
    <a href="index.php?page=users" class="nav-item <?= ($page === 'users') ? 'active' : '' ?>">
      <i class="bi bi-people-fill"></i><span>Tài Khoản NV</span>
    </a>
    <?php endif; ?>
    <?php if (in_array($user['role'] ?? '', ['superadmin', 'admin'], true)): ?>
    <a href="index.php?page=backup" class="nav-item <?= ($page === 'backup') ? 'active' : '' ?>">
      <i class="bi bi-cloud-arrow-up-fill"></i><span>Sao Lưu</span>
    </a>
    <?php if (featureEnabled('integrity')): ?><a href="index.php?page=integrity&branch=<?= urlencode(firstAccessibleBranchId()) ?>" class="nav-item <?= ($page === 'integrity') ? 'active' : '' ?>">
      <i class="bi bi-shield-check"></i><span>Toàn Vẹn Dữ Liệu</span>
    </a><?php endif; ?>
    <?php endif; ?>
    <?php if (($user['role'] ?? '') === 'superadmin'): ?>
    <a href="index.php?page=license" class="nav-item <?= ($page === 'license') ? 'active' : '' ?>">
      <i class="bi bi-key-fill"></i><span>Giấy Phép</span>
    </a>
    <?php endif; ?>
    <a href="index.php?page=help" class="nav-item <?= ($page === 'help') ? 'active' : '' ?>">
      <i class="bi bi-book-fill"></i><span>Hướng Dẫn SD</span>
    </a>
    <a href="index.php?page=profile" class="nav-item <?= ($page === 'profile') ? 'active' : '' ?>">
      <i class="bi bi-person-circle"></i><span>Tài Khoản Của Tôi</span>
    </a>
    <a href="index.php?page=logout" class="nav-item nav-logout">
      <i class="bi bi-box-arrow-left"></i><span>Đăng Xuất</span>
    </a>
  </nav>
</div>

<!-- MAIN CONTENT -->
<div class="main-content" id="mainContent">
  <!-- Top bar -->
  <div class="topbar">
    <button type="button" class="sidebar-toggle btn btn-sm" id="sidebarToggle" aria-label="Mở menu" title="Mở menu">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-title" title="<?= htmlspecialchars($topbarFullTitle) ?>">
      <span class="topbar-title-main"><?= htmlspecialchars($topbarMainTitle) ?></span>
      <?php if ($topbarContext !== ''): ?>
      <span class="topbar-title-context"><?= htmlspecialchars($topbarContext) ?></span>
      <?php endif; ?>
    </div>
    <div class="topbar-right">
      <div class="pwa-desktop-nav-slot" id="pwaDesktopNavSlot"></div>
      <button class="btn btn-sm btn-outline-primary" id="pwaInstallBtn" type="button" style="display:none">
        <i class="bi bi-download me-1"></i>Cài app
      </button>
      <span class="badge-time" id="clock"></span>
    </div>
  </div>

  <!-- Flash message -->
  <?php
    $licenseState = function_exists('licenseStatus') ? licenseStatus() : null;
    $showLicenseAlert = $licenseState && in_array($licenseState['state'], ['warning','grace','expired','locked'], true);
  ?>
  <?php if ($showLicenseAlert): ?>
  <div class="alert alert-<?= in_array($licenseState['state'], ['expired','locked'], true) ? 'danger' : 'warning' ?> m-3 mb-0" role="alert" style="font-size:13.5px">
    <i class="bi bi-<?= in_array($licenseState['state'], ['expired','locked'], true) ? 'lock-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
    <strong><?= htmlspecialchars($licenseState['label']) ?>.</strong>
    <?php if ($licenseState['state'] === 'warning'): ?>
      Còn <?= max(0, (int)$licenseState['days_remaining']) ?> ngày sử dụng, hết hạn ngày <?= date('d/m/Y', strtotime($licenseState['end_date'])) ?>.
    <?php elseif ($licenseState['state'] === 'grace'): ?>
      Đã quá hạn, còn <?= max(0, (int)$licenseState['grace_days_remaining']) ?> ngày gia hạn trước khi khóa quyền ghi.
    <?php elseif ($licenseState['state'] === 'locked'): ?>
      <?= htmlspecialchars($licenseState['lock_reason'] ?: 'Hệ thống đang bị khóa quyền ghi.') ?>
    <?php else: ?>
      Hệ thống đã hết hạn. Bạn vẫn có thể xem dữ liệu và sao lưu.
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['flash'])): $flashToast=$_SESSION['flash']; unset($_SESSION['flash']); ?>
  <script type="application/json" data-app-toasts><?= json_encode([['type'=>$flashToast['type']??'info','message'=>$flashToast['message']??'']],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
  <?php endif; ?>

  <!-- Page content -->
  <div class="content-body">
