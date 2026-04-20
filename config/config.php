<?php
// ============================================================
// CẤU HÌNH HỆ THỐNG QUẢN LÝ NHẬP XUẤT HÀNG HÓA
// ============================================================

define('APP_NAME', 'Quản Lý Nhập Xuất Hàng Hóa');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('SESSION_TIMEOUT', 7200); // 2 giờ

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
define('UNITS', ['kg', 'tấn', 'viên', 'tờ', 'cây', 'm', 'm²', 'bộ', 'thùng', 'bao', 'cuộn', 'cái', 'chiếc']);

// Múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');
