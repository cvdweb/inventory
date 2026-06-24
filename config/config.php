<?php
// ============================================================
// CẤU HÌNH HỆ THỐNG QUẢN LÝ NHẬP XUẤT HÀNG HÓA
// ============================================================

define('APP_NAME', 'Quản Lý Nhập Xuất Hàng Hóa');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('SESSION_TIMEOUT', 7200); // 2 giờ
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 900); // 15 phút
define('LOGIN_LOCK_SECONDS', 900);   // khóa 15 phút
define('PASSWORD_MIN_LENGTH', 8);

// Kho backup phải nằm ngoài document root. Có thể ghi đè bằng biến môi trường
// TRUONGPHU_BACKUP_PATH trên hosting.
$configuredBackupPath = trim((string)getenv('TRUONGPHU_BACKUP_PATH'));
if ($configuredBackupPath === '') {
    $documentRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($documentRoot !== '') {
        $configuredBackupPath = dirname(rtrim($documentRoot, '/\\')) . '/truongphu_private_backups';
    } elseif (PHP_OS_FAMILY === 'Windows') {
        $configuredBackupPath = dirname(dirname(BASE_PATH)) . '/truongphu_private_backups';
    } else {
        $homePath = trim((string)getenv('HOME'));
        $configuredBackupPath = ($homePath !== '' ? $homePath : dirname(dirname(BASE_PATH))) . '/truongphu_private_backups';
    }
}
define('PRIVATE_BACKUP_PATH', $configuredBackupPath);

// ============================================================
// THÔNG TIN DOANH NGHIỆP — hiển thị trên hóa đơn, phiếu giao
// ============================================================
define('BUSINESS', [
    'name'    => 'Công ty TNHH TM & DV Trường Phú',  // Tên doanh nghiệp
    'address' => 'Nam Sông Hậu, KV Cà Lăng A, p.Vĩnh Châu, TP. Cần Thơ', // Địa chỉ
    'phone'   => '0299 6295999 - 6282666 DĐ: 0913 862162, Ngân: 0343317275', // Số điện thoại
    'email'   => 'truongphuvlxd65@gmail.com', // Email
    'tax_code'=> '',
    'slogan'  => '',
]);

// Thông tin riêng từng chi nhánh (ghi đè BUSINESS nếu khác)
define('BRANCH_INFO', [
    'branch_1_vlxd' => [
        'print_name'    => '',
        'print_address' => '',
        'print_phone'   => '',
    ],
    'branch_2_maiton' => [
        'print_name'    => '',
        'print_address' => '',
        'print_phone'   => '',
    ],
]);

// Chi nhánh
define('BRANCHES', [
    'branch_1_vlxd' => [
        'id'    => 'branch_1_vlxd',
        'name'  => 'Vật Liệu Xây Dựng',
        'short' => 'VLXD',
        'icon'  => 'bi-buildings',
        'color' => 'primary',
    ],
    'branch_2_maiton' => [
        'id'    => 'branch_2_maiton',
        'name'  => 'Mái Tôn',
        'short' => 'MT',
        'icon'  => 'bi-house-fill',
        'color' => 'success',
    ],
]);

// Đơn vị tính
define('UNITS', ['kg', 'tấn', 'm', 'm²', 'cái', 'chiếc', 'viên', 'tờ', 'tấm', 'cây', 'bộ', 'bao', 'bịt', 'bọc', 'cuộn', 'thùng', 'vỏ', 'lon', 'chuyến']);

// Múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');
