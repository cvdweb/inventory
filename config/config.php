<?php
// ============================================================
// CẤU HÌNH HỆ THỐNG QUẢN LÝ NHẬP XUẤT HÀNG HÓA
// ============================================================

define('APP_NAME', 'Quản Lý Nhập Xuất Hàng Hóa');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('SESSION_TIMEOUT', 7200); // 2 giờ

// ============================================================
// THÔNG TIN DOANH NGHIỆP — hiển thị trên hóa đơn, phiếu giao
// ============================================================
define('BUSINESS', [
    'name'    => 'Công ty TNHH TM & DV Trường Phú',  // Tên doanh nghiệp
    'address' => 'Nam Sông Hậu, KV Cà Lăng A, p.Vĩnh Châu, TP. Cần Thơ', // Địa chỉ
    'phone'   => '0299 6295999 - 6282666 DĐ: 0913 862162', // Số điện thoại
    'email'   => 'truongphuvlxd65@gmail.com', // Email
    'tax_code'=> '',
    'slogan'  => 'Chất lượng — Uy tín — Giá tốt',
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
define('UNITS', ['kg', 'tấn', 'viên', 'tờ','tấm', 'cây', 'm', 'm²', 'bộ', 'thùng', 'bao', 'cuộn', 'cái', 'chiếc','chuyến']);

// Múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');
