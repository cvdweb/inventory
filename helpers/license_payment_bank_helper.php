<?php
// ============================================================
// HELPER: TÀI KHOẢN NGÂN HÀNG NHẬN THANH TOÁN GIA HẠN GIẤY PHÉP
// Chỉ superadmin mới được cấu hình/xem
// ============================================================

define('LICENSE_PAYMENT_BANK_FILE', DATA_PATH . '/license_payment_bank.json');

function licensePaymentBankDefault(): array
{
    return [
        'bank_name' => '',
        'bank_bin' => '',
        'bank_account_no' => '',
        'bank_account_holder' => '',
        'updated_at' => '',
        'updated_by' => '',
    ];
}

function licensePaymentBankGet(): array
{
    if (!function_exists('isSuperAdmin') || !isSuperAdmin()) {
        return licensePaymentBankDefault();
    }
    $data = readJson(LICENSE_PAYMENT_BANK_FILE);
    if (!is_array($data)) {
        $data = [];
    }
    return array_merge(licensePaymentBankDefault(), $data);
}

function licensePaymentBankGetPublic(): array
{
    $data = readJson(LICENSE_PAYMENT_BANK_FILE);
    if (!is_array($data)) {
        $data = [];
    }
    return array_merge(licensePaymentBankDefault(), $data);
}

function licensePaymentBankSave(array $post): array
{
    if (!function_exists('isSuperAdmin') || !isSuperAdmin()) {
        return ['success' => false, 'message' => 'Chỉ superadmin mới có quyền cấu hình tài khoản nhận thanh toán gia hạn'];
    }

    $bankName = trim((string)($post['renew_bank_name'] ?? ''));
    $bankBin = str_replace(' ', '', trim((string)($post['renew_bank_bin'] ?? '')));
    $bankAccountNo = str_replace(' ', '', trim((string)($post['renew_bank_account_no'] ?? '')));
    $bankAccountHolder = trim((string)($post['renew_bank_account_holder'] ?? ''));

    if ($bankName === '' || $bankAccountNo === '' || $bankAccountHolder === '') {
        return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ Tên ngân hàng, Số tài khoản và Chủ tài khoản'];
    }

    $data = [
        'bank_name' => $bankName,
        'bank_bin' => $bankBin,
        'bank_account_no' => $bankAccountNo,
        'bank_account_holder' => $bankAccountHolder,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => function_exists('currentUser') ? (currentUser()['username'] ?? 'superadmin') : 'superadmin',
    ];

    if (!writeJson(LICENSE_PAYMENT_BANK_FILE, $data)) {
        return ['success' => false, 'message' => 'Không lưu được thông tin tài khoản ngân hàng'];
    }

    return ['success' => true, 'message' => 'Đã lưu tài khoản nhận thanh toán gia hạn'];
}
