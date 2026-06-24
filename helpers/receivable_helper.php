<?php

function receivablePaymentFile(string $branch, string $yearMonth = ''): string
{
    if (!$yearMonth) $yearMonth = date('Y_m');
    return DATA_PATH . "/{$branch}/receivable_payments_{$yearMonth}.json";
}

function receivableCustomerKey(string $name, string $phone = ''): string
{
    $phoneDigits = preg_replace('/\D+/', '', $phone);
    if ($phoneDigits) {
        return 'phone_' . $phoneDigits;
    }

    $normalized = mb_strtolower(trim($name ?: 'Khach le'), 'UTF-8');
    $normalized = preg_replace('/\s+/u', ' ', $normalized);
    return 'name_' . substr(sha1($normalized), 0, 16);
}

function getReceivablePaymentFiles(string $branch): array
{
    $files = glob(DATA_PATH . "/{$branch}/receivable_payments_*.json") ?: [];
    rsort($files);
    return $files;
}

function getReceivablePayments(string $branch, bool $includeDeleted = false): array
{
    $payments = [];
    foreach (getReceivablePaymentFiles($branch) as $file) {
        foreach (readJson($file) as $row) {
            if (!$includeDeleted && !empty($row['deleted_at'])) continue;
            $payments[] = $row;
        }
    }

    usort($payments, fn($a, $b) => strcmp($b['paid_at'] ?? '', $a['paid_at'] ?? ''));
    return $payments;
}

function getReceivableSummary(string $branch): array
{
    $customers = [];
    $totalDebt = 0;
    $totalPaid = 0;
    $returnsByInvoice = function_exists('salesReturnApprovedByInvoice') ? salesReturnApprovedByInvoice($branch) : [];
    $cashRefundsByCustomer = function_exists('salesReturnCreditCashRefundsByCustomer') ? salesReturnCreditCashRefundsByCustomer($branch) : [];

    foreach (getAllInvoiceFiles($branch) as $file) {
        preg_match('/invoices_(\d{4}_\d{2})\.json$/', $file, $m);
        $ym = $m[1] ?? '';

        foreach (readJson($file) as $invoice) {
            if (invoiceIsCancelled($invoice)) continue;
            if (($invoice['payment'] ?? '') !== 'credit') {
                continue;
            }

            $name = trim($invoice['customer'] ?? '') ?: 'Khách lẻ';
            $phone = trim($invoice['phone'] ?? '');
            $key = receivableCustomerKey($name, $phone);

            if (!isset($customers[$key])) {
                $customers[$key] = [
                    'key' => $key,
                    'name' => $name,
                    'phone' => $phone,
                    'address' => $invoice['address'] ?? '',
                    'debt' => 0,
                    'paid' => 0,
                    'balance' => 0,
                    'invoice_count' => 0,
                    'payment_count' => 0,
                    'last_invoice_at' => '',
                    'last_payment_at' => '',
                    'invoices' => [],
                    'payments' => [],
                ];
            }

            $grossAmount = (float)($invoice['total'] ?? 0);
            $returnedAmount = min($grossAmount, (float)($returnsByInvoice[$invoice['id'] ?? '']['total'] ?? 0));
            $amount = max(0, $grossAmount - $returnedAmount);
            $totalDebt += $amount;
            $customers[$key]['debt'] += $amount;
            $customers[$key]['invoice_count']++;
            $customers[$key]['last_invoice_at'] = max($customers[$key]['last_invoice_at'], $invoice['created_at'] ?? '');
            if (empty($customers[$key]['phone']) && $phone) $customers[$key]['phone'] = $phone;
            if (empty($customers[$key]['address']) && !empty($invoice['address'])) $customers[$key]['address'] = $invoice['address'];
            $invoice['_ym'] = $ym;
            $invoice['_return_total'] = $returnedAmount;
            $invoice['_net_total'] = $amount;
            $customers[$key]['invoices'][] = $invoice;
        }
    }

    foreach ($cashRefundsByCustomer as $key => $amount) {
        if (!isset($customers[$key])) continue;
        $customers[$key]['paid'] -= $amount;
        $customers[$key]['refund_total'] = $amount;
        $totalPaid -= $amount;
    }

    foreach (getReceivablePayments($branch) as $payment) {
        $key = $payment['customer_key'] ?? '';
        if (!$key) {
            $key = receivableCustomerKey($payment['customer_name'] ?? '', $payment['phone'] ?? '');
        }

        if (!isset($customers[$key])) {
            $customers[$key] = [
                'key' => $key,
                'name' => $payment['customer_name'] ?? 'Khách lẻ',
                'phone' => $payment['phone'] ?? '',
                'address' => '',
                'debt' => 0,
                'paid' => 0,
                'balance' => 0,
                'invoice_count' => 0,
                'payment_count' => 0,
                'last_invoice_at' => '',
                'last_payment_at' => '',
                'invoices' => [],
                'payments' => [],
            ];
        }

        $amount = (float)($payment['amount'] ?? 0);
        $totalPaid += $amount;
        $customers[$key]['paid'] += $amount;
        $customers[$key]['payment_count']++;
        $customers[$key]['last_payment_at'] = max($customers[$key]['last_payment_at'], $payment['paid_at'] ?? '');
        $customers[$key]['payments'][] = $payment;
    }

    foreach ($customers as &$customer) {
        $customer['balance'] = $customer['debt'] - $customer['paid'];
        usort($customer['invoices'], fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        usort($customer['payments'], fn($a, $b) => strcmp($b['paid_at'] ?? '', $a['paid_at'] ?? ''));
    }
    unset($customer);

    uasort($customers, function ($a, $b) {
        $cmp = ($b['balance'] <=> $a['balance']);
        return $cmp !== 0 ? $cmp : strcmp($b['last_invoice_at'] ?? '', $a['last_invoice_at'] ?? '');
    });

    return [
        'customers' => array_values($customers),
        'total_debt' => $totalDebt,
        'total_paid' => $totalPaid,
        'total_balance' => $totalDebt - $totalPaid,
    ];
}

function receivableFindCustomer(string $branch, string $customerKey): ?array
{
    foreach (getReceivableSummary($branch)['customers'] as $customer) {
        if (($customer['key'] ?? '') === $customerKey) {
            return $customer;
        }
    }
    return null;
}

function receivableCreatePayment(string $branch, array $post): array
{
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền truy cập chi nhánh này'];
    }

    $customerKey = trim($post['customer_key'] ?? '');
    $customer = $customerKey ? receivableFindCustomer($branch, $customerKey) : null;
    $name = trim($post['customer_name'] ?? ($customer['name'] ?? ''));
    $phone = trim($post['phone'] ?? ($customer['phone'] ?? ''));
    $amount = (float)($post['amount'] ?? 0);
    $paidAt = trim($post['paid_at'] ?? date('Y-m-d'));

    if (!$customerKey && ($name || $phone)) {
        $customerKey = receivableCustomerKey($name ?: 'Khách lẻ', $phone);
    }
    if (!$customerKey || (!$customer && !$name)) {
        return ['success' => false, 'message' => 'Vui lòng chọn khách hàng công nợ'];
    }
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Số tiền thu phải lớn hơn 0'];
    }
    if ($customer && $amount > max(0, (float)($customer['balance'] ?? 0)) + 0.000001) {
        return ['success' => false, 'message' => 'Số tiền thu không được lớn hơn công nợ còn lại'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAt)) {
        return ['success' => false, 'message' => 'Ngày thu không hợp lệ'];
    }

    $user = currentUser();
    $payment = [
        'id' => 'PAY-' . date('YmdHis') . '-' . random_int(100, 999),
        'customer_key' => $customerKey,
        'customer_name' => $name ?: ($customer['name'] ?? 'Khách lẻ'),
        'phone' => $phone ?: ($customer['phone'] ?? ''),
        'amount' => $amount,
        'method' => $post['method'] ?? 'cash',
        'paid_at' => $paidAt,
        'note' => trim($post['note'] ?? ''),
        'created_by' => $user['name'] ?? 'System',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $ym = str_replace('-', '_', substr($paidAt, 0, 7));
    $file = receivablePaymentFile($branch, $ym);
    $rows = readJson($file);
    $rows[] = $payment;

    if (!writeJson($file, $rows)) {
        return ['success' => false, 'message' => 'Không lưu được phiếu thu công nợ'];
    }
    if (function_exists('cashbookSyncReceivablePayment')) {
        $sync = cashbookSyncReceivablePayment($branch, $payment);
        if (!($sync['success'] ?? false)) {
            array_pop($rows);
            writeJson($file, $rows);
            return ['success' => false, 'message' => 'Không đồng bộ được phiếu thu với sổ thu chi. Dữ liệu chưa được lưu.'];
        }
    }
    return ['success' => true, 'message' => 'Đã ghi nhận thu công nợ', 'id' => $payment['id']];
}

function receivableDeletePayment(string $branch, string $paymentId, string $reason = ''): array
{
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền truy cập chi nhánh này'];
    }

    foreach (getReceivablePaymentFiles($branch) as $file) {
        $rows = readJson($file);
        foreach ($rows as &$row) {
            if (($row['id'] ?? '') !== $paymentId) continue;
            if (!empty($row['deleted_at'])) {
                return ['success' => false, 'message' => 'Phiếu thu đã được hủy trước đó'];
            }
            $row['deleted_at'] = date('Y-m-d H:i:s');
            $row['deleted_by'] = currentUser()['username'] ?? currentUser()['name'] ?? 'System';
            $row['delete_reason'] = trim($reason) ?: 'Hủy phiếu thu';
            unset($row);
            if (!writeJson($file, $rows)) {
                return ['success' => false, 'message' => 'Không hủy được phiếu thu'];
            }
            if (function_exists('cashbookMarkSourceDeleted')) {
                cashbookMarkSourceDeleted($branch, 'receivable_payment', $paymentId, trim($reason) ?: 'Hủy phiếu thu');
            }
            return ['success' => true, 'message' => 'Đã hủy phiếu thu; lịch sử vẫn được lưu'];
        }
        unset($row);
    }

    return ['success' => false, 'message' => 'Không tìm thấy phiếu thu'];
}

function receivableCustomerHasActivePayments(string $branch, string $customerKey): bool
{
    foreach (getReceivablePayments($branch) as $payment) {
        if (($payment['customer_key'] ?? '') === $customerKey) return true;
    }
    return false;
}
