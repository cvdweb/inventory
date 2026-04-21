<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
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
  <div class="sidebar-user">
    <div class="user-avatar"><i class="bi <?= $user['icon'] ?? 'bi-person' ?>"></i></div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($user['name'] ?? '') ?></div>
      <div class="user-role"><?= match($user['role'] ?? '') { 'superadmin' => 'Super Admin', 'admin' => 'Quản trị viên', 'sales' => 'Bán hàng', 'warehouse' => 'Nhập hàng', default => '' } ?></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a href="index.php" class="nav-item <?= ($page === 'dashboard') ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
    </a>

    <?php foreach (getAccessibleBranches() as $bId => $b): ?>
    <div class="nav-section"><?= $b['short'] ?> — <?= $b['name'] ?></div>
    <a href="index.php?page=products&branch=<?= $bId ?>" class="nav-item <?= ($page === 'products' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-box2-fill"></i><span>Sản Phẩm</span>
    </a>
    <?php if (in_array($user['role'] ?? '', ['superadmin', 'admin', 'warehouse'])): ?>
    <a href="index.php?page=imports&branch=<?= $bId ?>" class="nav-item <?= ($page === 'imports' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-download"></i><span>Nhập Hàng</span>
    </a>
    <?php endif; ?>
    <?php if (in_array($user['role'] ?? '', ['superadmin', 'admin', 'sales'])): ?>
    <a href="index.php?page=invoice&branch=<?= $bId ?>" class="nav-item <?= ($page === 'invoice' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-receipt"></i><span>Lập Hóa Đơn</span>
    </a>
    <a href="index.php?page=invoices&branch=<?= $bId ?>" class="nav-item <?= ($page === 'invoices' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-journal-text"></i><span>DS Hóa Đơn</span>
    </a>
    <?php endif; ?>
    <a href="index.php?page=reports&branch=<?= $bId ?>" class="nav-item <?= ($page === 'reports' && ($reqBranch ?? '') === $bId) ? 'active' : '' ?>">
      <i class="bi bi-bar-chart-fill"></i><span>Báo Cáo</span>
    </a>
    <?php endforeach; ?>

    <div class="nav-section">Hệ Thống</div>
    <?php if (canManageUsers()): ?>
    <a href="index.php?page=categories" class="nav-item <?= ($page === 'categories') ? 'active' : '' ?>">
      <i class="bi bi-collection-fill"></i><span>Nhóm Hàng</span>
    </a>
    <a href="index.php?page=users" class="nav-item <?= ($page === 'users') ? 'active' : '' ?>">
      <i class="bi bi-people-fill"></i><span>Tài Khoản NV</span>
    </a>
    <a href="index.php?page=backup" class="nav-item <?= ($page === 'backup') ? 'active' : '' ?>">
      <i class="bi bi-cloud-arrow-up-fill"></i><span>Sao Lưu</span>
    </a>
    <?php endif; ?>
    <a href="index.php?page=help" class="nav-item <?= ($page === 'help') ? 'active' : '' ?>">
      <i class="bi bi-book-fill"></i><span>Hướng Dẫn SD</span>
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
    <button class="sidebar-toggle btn btn-sm" id="sidebarToggle">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></div>
    <div class="topbar-right">
      <span class="badge-time" id="clock"></span>
    </div>
  </div>

  <!-- Flash message -->
  <?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible m-3 mb-0" role="alert">
    <i class="bi bi-<?= $_SESSION['flash']['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
    <?= htmlspecialchars($_SESSION['flash']['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['flash']); endif; ?>

  <!-- Page content -->
  <div class="content-body">
