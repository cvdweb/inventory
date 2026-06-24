<?php

define('LICENSE_FILE', DATA_PATH . '/system_license.json');

function licenseDefaultData(): array
{
    return [
        'enabled' => true,
        'customer' => [
            'name' => BUSINESS['name'] ?? '',
            'system_name' => 'Hệ thống quản lý nhập xuất hàng hóa',
            'owner' => '',
            'phone' => '',
            'address' => BUSINESS['address'] ?? '',
            'tax_code' => BUSINESS['tax_code'] ?? '',
            'started_at' => date('Y-m-d'),
            'activated_at' => date('Y-m-d'),
            'note' => '',
        ],
        'pricing' => [
            'monthly_price' => 200000,
            'currency' => 'VND',
            'packages' => [
                ['months' => 1, 'pay_months' => 1, 'free_months' => 0],
                ['months' => 6, 'pay_months' => 5, 'free_months' => 1],
                ['months' => 12, 'pay_months' => 10, 'free_months' => 2],
            ],
        ],
        'policy' => [
            'warn_before_days' => 15,
            'grace_days' => 7,
            'block_write_when_expired' => true,
            'allow_backup_when_expired' => true,
        ],
        'status' => [
            'locked' => false,
            'lock_reason' => '',
        ],
        'payments' => [],
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function licenseDeepMerge(array $base, array $override): array
{
    foreach ($override as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !licenseArrayIsList($value)) {
            $base[$key] = licenseDeepMerge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}

function licenseArrayIsList(array $value): bool
{
    if ($value === []) return true;
    return array_keys($value) === range(0, count($value) - 1);
}

function licenseGet(): array
{
    $data = readJson(LICENSE_FILE);
    $license = licenseDeepMerge(licenseDefaultData(), $data);
    if (!file_exists(LICENSE_FILE) || $license !== $data) {
        licenseSave($license);
    }
    return $license;
}

function licenseSave(array $license): bool
{
    $license['updated_at'] = date('Y-m-d H:i:s');
    return writeJson(LICENSE_FILE, $license);
}

function licensePackageByMonths(array $license, int $months): array
{
    foreach ($license['pricing']['packages'] ?? [] as $package) {
        if ((int)($package['months'] ?? 0) === $months) {
            return [
                'months' => $months,
                'pay_months' => (int)($package['pay_months'] ?? $months),
                'free_months' => (int)($package['free_months'] ?? 0),
            ];
        }
    }
    return ['months' => $months, 'pay_months' => $months, 'free_months' => 0];
}

function licenseAddMonths(string $date, int $months): string
{
    $base = DateTimeImmutable::createFromFormat('Y-m-d', $date) ?: new DateTimeImmutable(date('Y-m-d'));
    $day = (int)$base->format('d');
    $targetMonth = $base->modify('first day of this month')->modify("+{$months} months");
    $targetDay = min($day, (int)$targetMonth->format('t'));
    return $targetMonth->setDate(
        (int)$targetMonth->format('Y'),
        (int)$targetMonth->format('m'),
        $targetDay
    )->format('Y-m-d');
}

function licenseCalculateEndDate(array $license): string
{
    $start = $license['customer']['started_at'] ?? date('Y-m-d');
    $totalMonths = 0;
    foreach ($license['payments'] ?? [] as $payment) {
        $months = (int)($payment['package_months'] ?? $payment['use_months'] ?? 0);
        if ($months > 0) {
            $totalMonths += $months;
        }
    }

    if ($totalMonths <= 0) {
        return $start;
    }

    $renewalDate = DateTimeImmutable::createFromFormat('Y-m-d', licenseAddMonths($start, $totalMonths));
    return ($renewalDate ?: new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d');
}

function licenseStatus(array $license = null): array
{
    $license = $license ?? licenseGet();
    $today = new DateTimeImmutable(date('Y-m-d'));
    $endDate = licenseCalculateEndDate($license);
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $endDate) ?: $today;
    $warnBefore = (int)($license['policy']['warn_before_days'] ?? 15);
    $graceDays = (int)($license['policy']['grace_days'] ?? 7);
    $locked = (bool)($license['status']['locked'] ?? false);

    $daysRemaining = (int)$today->diff($end)->format('%r%a');
    $graceEnd = $end->modify("+{$graceDays} days");
    $daysToGraceEnd = (int)$today->diff($graceEnd)->format('%r%a');

    if (!($license['enabled'] ?? true)) {
        $state = 'disabled';
        $label = 'Không kiểm tra giấy phép';
    } elseif ($locked) {
        $state = 'locked';
        $label = 'Đã khóa';
    } elseif ($daysRemaining >= 0) {
        $state = $daysRemaining <= $warnBefore ? 'warning' : 'active';
        $label = $state === 'warning' ? 'Sắp hết hạn' : 'Đang hoạt động';
    } elseif ($daysToGraceEnd >= 0) {
        $state = 'grace';
        $label = 'Quá hạn trong thời gian gia hạn';
    } else {
        $state = 'expired';
        $label = 'Hết hạn';
    }

    $writeBlocked = in_array($state, ['locked', 'expired'], true)
        && (bool)($license['policy']['block_write_when_expired'] ?? true);

    return [
        'state' => $state,
        'label' => $label,
        'end_date' => $endDate,
        'days_remaining' => $daysRemaining,
        'grace_days_remaining' => $daysToGraceEnd,
        'write_blocked' => $writeBlocked,
        'lock_reason' => $license['status']['lock_reason'] ?? '',
    ];
}

function licenseFormatMoney(float $amount): string
{
    return number_format($amount, 0, ',', '.') . ' đ';
}

function licensePaymentAmount(array $license, int $months): array
{
    $package = licensePackageByMonths($license, $months);
    $monthly = (float)($license['pricing']['monthly_price'] ?? 200000);
    $amount = $monthly * (int)$package['pay_months'];
    return $package + ['amount' => $amount, 'monthly_price' => $monthly];
}

function licenseSaveSettings(array $post): array
{
    $license = licenseGet();
    $license['enabled'] = !empty($post['enabled']);
    $license['customer'] = [
        'name' => trim($post['customer_name'] ?? ''),
        'system_name' => trim($post['system_name'] ?? 'Hệ thống quản lý nhập xuất hàng hóa'),
        'owner' => trim($post['customer_owner'] ?? ''),
        'phone' => trim($post['customer_phone'] ?? ''),
        'address' => trim($post['customer_address'] ?? ''),
        'tax_code' => trim($post['customer_tax_code'] ?? ($license['customer']['tax_code'] ?? '')),
        'started_at' => trim($post['started_at'] ?? date('Y-m-d')),
        'activated_at' => trim($post['activated_at'] ?? date('Y-m-d')),
        'note' => trim($post['note'] ?? ''),
    ];
    $license['pricing']['monthly_price'] = max(0, _parseMoneyNumber($post['monthly_price'] ?? 200000));
    $license['policy']['warn_before_days'] = max(0, (int)($post['warn_before_days'] ?? 15));
    $license['policy']['grace_days'] = max(0, (int)($post['grace_days'] ?? 7));
    $license['policy']['block_write_when_expired'] = !empty($post['block_write_when_expired']);
    $license['policy']['allow_backup_when_expired'] = !empty($post['allow_backup_when_expired']);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $license['customer']['started_at'])) {
        return ['success' => false, 'message' => 'Ngày bắt đầu tính phí không hợp lệ'];
    }
    if ($license['customer']['tax_code'] !== '' && !preg_match('/^\d{10}(-\d{3})?$/', $license['customer']['tax_code'])) {
        return ['success' => false, 'message' => 'Mã số thuế phải gồm 10 chữ số hoặc dạng 10 chữ số-3 chữ số'];
    }

    return licenseSave($license)
        ? ['success' => true, 'message' => 'Đã cập nhật cấu hình giấy phép']
        : ['success' => false, 'message' => 'Không lưu được cấu hình giấy phép'];
}

function licenseAddPayment(array $post): array
{
    $license = licenseGet();
    $months = max(1, (int)($post['package_months'] ?? 1));
    $calc = licensePaymentAmount($license, $months);
    $amount = isset($post['amount']) && trim((string)$post['amount']) !== ''
        ? _parseMoneyNumber($post['amount'])
        : (float)$calc['amount'];
    $paidAt = trim($post['paid_at'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAt)) {
        return ['success' => false, 'message' => 'Ngày thanh toán không hợp lệ'];
    }
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Số tiền thanh toán phải lớn hơn 0'];
    }

    $user = currentUser();
    $license['payments'][] = [
        'id' => 'LICPAY-' . date('YmdHis') . '-' . random_int(100, 999),
        'paid_at' => $paidAt,
        'package_months' => (int)$calc['months'],
        'pay_months' => (int)$calc['pay_months'],
        'free_months' => (int)$calc['free_months'],
        'amount' => $amount,
        'method' => $post['method'] ?? 'cash',
        'note' => trim($post['payment_note'] ?? ''),
        'created_by' => $user['name'] ?? 'System',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    return licenseSave($license)
        ? ['success' => true, 'message' => 'Đã ghi nhận thanh toán giấy phép']
        : ['success' => false, 'message' => 'Không lưu được thanh toán giấy phép'];
}

function licenseDeletePayment(string $paymentId): array
{
    $license = licenseGet();
    $before = count($license['payments'] ?? []);
    $license['payments'] = array_values(array_filter($license['payments'] ?? [], fn($p) => ($p['id'] ?? '') !== $paymentId));
    if (count($license['payments']) === $before) {
        return ['success' => false, 'message' => 'Không tìm thấy thanh toán giấy phép'];
    }
    return licenseSave($license)
        ? ['success' => true, 'message' => 'Đã xóa thanh toán giấy phép']
        : ['success' => false, 'message' => 'Không xóa được thanh toán giấy phép'];
}

function licenseUpdateLock(array $post): array
{
    $license = licenseGet();
    $license['status']['locked'] = !empty($post['locked']);
    $license['status']['lock_reason'] = trim($post['lock_reason'] ?? '');
    return licenseSave($license)
        ? ['success' => true, 'message' => $license['status']['locked'] ? 'Đã khóa quyền ghi hệ thống' : 'Đã mở khóa hệ thống']
        : ['success' => false, 'message' => 'Không cập nhật được trạng thái khóa'];
}

function licenseWriteAllowedPage(string $page, string $action = ''): bool
{
    if (isSuperAdmin()) return true;
    if (in_array($page, ['profile', 'logout'], true)) return true;
    if ($page === 'backup') return (bool)(licenseGet()['policy']['allow_backup_when_expired'] ?? true);
    return !licenseStatus()['write_blocked'];
}

function licenseEnforceWriteAllowed(string $page, string $action = ''): void
{
    if (licenseWriteAllowedPage($page, $action)) return;
    $status = licenseStatus();
    $message = $status['state'] === 'locked'
        ? 'Hệ thống đang bị khóa quyền ghi. Vui lòng liên hệ kỹ thuật.'
        : 'Hệ thống đã hết hạn sử dụng. Bạn vẫn có thể xem dữ liệu và sao lưu, nhưng không thể tạo/sửa dữ liệu mới.';
    $_SESSION['flash'] = ['type' => 'danger', 'message' => $message];
    $target = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header('Location: ' . $target);
    exit;
}
