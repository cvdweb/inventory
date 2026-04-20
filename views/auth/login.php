<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng Nhập — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <div class="logo-icon"><i class="bi bi-box-seam-fill"></i></div>
      <h1><?= APP_NAME ?></h1>
      <p>Hệ thống quản lý nhập xuất hàng hóa</p>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" style="font-size:13px">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['timeout'])): ?>
    <div class="alert alert-warning py-2 mb-3" style="font-size:13px">
      <i class="bi bi-clock me-2"></i>Phiên làm việc đã hết hạn. Vui lòng đăng nhập lại.
    </div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=login">
      <div class="mb-3">
        <label class="form-label">Tên đăng nhập</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" name="username" class="form-control" placeholder="Nhập tên đăng nhập"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label">Mật khẩu</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-box-arrow-in-right me-2"></i>Đăng Nhập
      </button>
    </form>

    <!-- <hr class="my-4">
    <div class="text-muted d-none" style="font-size:12px">
      <div class="fw-700 mb-2">Tài khoản mặc định:</div>
      <div class="d-flex flex-column gap-1">
        <div><code>admin</code> / <code>admin123</code> — Quản trị viên</div>
        <div><code>nv_vlxd</code> / <code>vlxd123</code> — BH Vật Liệu XD</div>
        <div><code>nv_maiton</code> / <code>maiton123</code> — BH Mái Tôn</div>
        <div><code>nv_nhaphang</code> / <code>nhap123</code> — Nhập Hàng</div>
      </div>
    </div> -->
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
