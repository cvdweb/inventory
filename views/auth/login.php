<?php
$loginLicense = function_exists('licenseGet') ? licenseGet() : [];
$loginCustomer = $loginLicense['customer'] ?? [];
$loginBusinessName = trim($loginCustomer['name'] ?? '') ?: (BUSINESS['name'] ?? APP_NAME);
$loginSystemName = trim($loginCustomer['system_name'] ?? '') ?: APP_NAME;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng Nhập — <?= htmlspecialchars($loginBusinessName) ?></title>
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
      <h1><?= htmlspecialchars($loginBusinessName) ?></h1>
      <p><?= htmlspecialchars($loginSystemName) ?></p>
    </div>

    <form method="POST" action="index.php?page=login">
      <?= csrfField() ?>
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
        <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
      </button>
    </form>

  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php $loginToasts=[]; if(!empty($error))$loginToasts[]=['type'=>'danger','message'=>$error,'duration'=>8000]; if(!empty($_GET['timeout']))$loginToasts[]=['type'=>'warning','message'=>'Phiên làm việc đã hết hạn. Vui lòng đăng nhập lại.','duration'=>7000]; ?>
<?php if($loginToasts): ?><script type="application/json" data-app-toasts><?= json_encode($loginToasts,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script><?php endif; ?>
<script src="assets/js/toast.js?v=<?= filemtime(BASE_PATH . '/assets/js/toast.js') ?>"></script>
</body>
</html>
