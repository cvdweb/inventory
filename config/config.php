<?php
// ============================================================
// CẤU HÌNH HỆ THỐNG QUẢN LÝ NHẬP XUẤT HÀNG HÓA
// ============================================================

define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');

// Cài đặt hệ thống do người dùng thiết lập trong trang "Cài Đặt".
// Cho phép copy dự án và chỉ cần đổi tên cửa hàng tại đây.
$systemSettingsFile = DATA_PATH . '/system_settings.json';
$systemSettings = [];
if (is_file($systemSettingsFile)) {
    $decodedSettings = json_decode((string)@file_get_contents($systemSettingsFile), true);
    if (is_array($decodedSettings)) $systemSettings = $decodedSettings;
}
// Fallback: thông tin khách hàng đã nhập ở hồ sơ giấy phép (bản cũ chưa có Cài Đặt).
$licenseCustomer = [];
$systemLicenseFile = DATA_PATH . '/system_license.json';
if (is_file($systemLicenseFile)) {
    $decodedLicense = json_decode((string)@file_get_contents($systemLicenseFile), true);
    if (is_array($decodedLicense) && is_array($decodedLicense['customer'] ?? null)) {
        $licenseCustomer = $decodedLicense['customer'];
    }
}
$settingValue = function (string $key) use ($systemSettings, $licenseCustomer): string {
    $value = trim((string)($systemSettings[$key] ?? ''));
    if ($value === '') $value = trim((string)($licenseCustomer[$key === 'store_name' ? 'name' : $key] ?? ''));
    return $value;
};
$settingStoreName = trim((string)($systemSettings['store_name'] ?? '')) ?: trim((string)($licenseCustomer['name'] ?? ''));

define('APP_NAME', trim((string)($systemSettings['app_name'] ?? '')) !== ''
    ? (string)$systemSettings['app_name']
    : 'Quản Lý Nhập Xuất Hàng Hóa');
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
    'name'    => ($v = $settingValue('store_name')) !== '' ? $v : 'Công ty TNHH TM & DV Trường Phú', // Tên doanh nghiệp
    'address' => ($v = $settingValue('address')) !== '' ? $v : 'Nam Sông Hậu, KV Cà Lăng A, p.Vĩnh Châu, TP. Cần Thơ', // Địa chỉ
    'phone'   => ($v = $settingValue('phone')) !== '' ? $v : '0299 6295999 - 6282666 DĐ: 0913 862162, Ngân: 0343317275', // Số điện thoại
    'email'   => ($v = $settingValue('email')) !== '' ? $v : 'truongphuvlxd65@gmail.com', // Email
    'tax_code'=> $settingValue('tax_code'),
    'slogan'  => trim((string)($systemSettings['slogan'] ?? '')),
]);
unset($systemSettings, $systemSettingsFile, $decodedSettings, $licenseCustomer, $systemLicenseFile, $settingStoreName, $settingValue, $v);

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
