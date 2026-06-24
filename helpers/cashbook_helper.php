<?php

function cashbookFile(string $branch, string $yearMonth = ''): string
{
    if (!$yearMonth) $yearMonth = date('Y_m');
    return DATA_PATH . "/{$branch}/cashbook_{$yearMonth}.json";
}

function cashbookCategories(): array
{
    return [
        'income' => [
            'sale' => 'Thu bán hàng',
            'debt_payment' => 'Thu công nợ',
            'other_income' => 'Thu khác',
            'adjustment_in' => 'Điều chỉnh tăng quỹ',
        ],
        'expense' => [
            'sales_return' => 'Hoàn tiền trả hàng',
            'supplier_payment' => 'Chi trả nhà cung cấp',
            'shipping' => 'Chi vận chuyển / bốc xếp',
            'salary' => 'Chi lương / ứng lương',
            'utilities' => 'Chi điện nước / mặt bằng',
            'other_expense' => 'Chi khác',
            'adjustment_out' => 'Điều chỉnh giảm quỹ',
        ],
    ];
}

function cashbookMethods(): array
{
    return [
        'cash' => 'Tiền mặt',
        'transfer' => 'Chuyển khoản',
        'other' => 'Khác',
    ];
}

function cashbookTypeLabel(string $type): string
{
    return $type === 'expense' ? 'Khoản chi' : 'Khoản thu';
}

function cashbookCategoryLabel(string $type, string $category): string
{
    $categories = cashbookCategories();
    return $categories[$type][$category] ?? $category;
}

function cashbookMethodLabel(string $method): string
{
    $methods = cashbookMethods();
    return $methods[$method] ?? $method;
}

function cashbookCanCreateType(string $type): bool
{
    $role = currentUser()['role'] ?? '';
    return in_array($role, ['superadmin', 'admin', 'employee'], true)
        && in_array($type, ['income', 'expense'], true);
}

function cashbookCanManageEntry(array $entry): bool
{
    if (($entry['source_type'] ?? 'manual') !== 'manual') return false;
    return in_array(currentUser()['role'] ?? '', ['superadmin', 'admin'], true);
}

function cashbookCanDelete(): bool
{
    return in_array(currentUser()['role'] ?? '', ['superadmin', 'admin'], true);
}

function cashbookParseAmount($value): float
{
    if (is_numeric($value)) return (float)$value;
    $value = preg_replace('/[^\d,.-]/', '', (string)$value);
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);
    return (float)$value;
}

function cashbookYearMonthFromDate(string $date): string
{
    return str_replace('-', '_', substr($date, 0, 7));
}

function getCashbookEntries(string $branch, string $yearMonth = '', bool $includeDeleted = false): array
{
    $rows = readJson(cashbookFile($branch, $yearMonth));
    if (!$includeDeleted) {
        $rows = array_values(array_filter($rows, fn($row) => empty($row['deleted_at'])));
    }
    usort($rows, function ($a, $b) {
        $cmp = strcmp($b['entry_date'] ?? '', $a['entry_date'] ?? '');
        return $cmp !== 0 ? $cmp : strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
    return $rows;
}

function cashbookSummary(array $entries): array
{
    $income = 0;
    $expense = 0;
    foreach ($entries as $entry) {
        $amount = (float)($entry['amount'] ?? 0);
        if (($entry['type'] ?? '') === 'expense') {
            $expense += $amount;
        } else {
            $income += $amount;
        }
    }
    return [
        'income' => $income,
        'expense' => $expense,
        'balance' => $income - $expense,
        'count' => count($entries),
    ];
}

function cashbookFindEntry(string $branch, string $entryId): ?array
{
    foreach (glob(DATA_PATH . "/{$branch}/cashbook_*.json") ?: [] as $file) {
        foreach (readJson($file) as $entry) {
            if (($entry['id'] ?? '') === $entryId) {
                $entry['_file'] = $file;
                return $entry;
            }
        }
    }
    return null;
}

function cashbookSaveManual(string $branch, array $post): array
{
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền truy cập chi nhánh này'];
    }

    $type = $post['type'] ?? 'income';
    if (!in_array($type, ['income', 'expense'], true)) {
        return ['success' => false, 'message' => 'Loại thu chi không hợp lệ'];
    }
    if (!cashbookCanCreateType($type)) {
        return ['success' => false, 'message' => 'Bạn không có quyền tạo loại phiếu này'];
    }

    $categories = cashbookCategories();
    $category = $post['category'] ?? '';
    if (!isset($categories[$type][$category])) {
        return ['success' => false, 'message' => 'Khoản mục thu chi không hợp lệ'];
    }

    $amount = cashbookParseAmount($post['amount'] ?? 0);
    $entryDate = trim($post['entry_date'] ?? date('Y-m-d'));
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Số tiền phải lớn hơn 0'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
        return ['success' => false, 'message' => 'Ngày ghi nhận không hợp lệ'];
    }

    $method = $post['method'] ?? 'cash';
    if (!isset(cashbookMethods()[$method])) $method = 'cash';

    $user = currentUser();
    $id = trim($post['id'] ?? '');
    $now = date('Y-m-d H:i:s');
    $entry = [
        'id' => $id ?: cashbookNextId($branch, $type, $entryDate),
        'branch' => $branch,
        'type' => $type,
        'category' => $category,
        'amount' => $amount,
        'method' => $method,
        'person' => trim($post['person'] ?? ''),
        'description' => trim($post['description'] ?? ''),
        'entry_date' => $entryDate,
        'source_type' => 'manual',
        'source_id' => '',
        'created_by' => $user['name'] ?? 'System',
        'created_at' => $now,
        'updated_at' => $now,
    ];

    if ($id) {
        return cashbookUpdateManual($branch, $id, $entry);
    }

    $file = cashbookFile($branch, cashbookYearMonthFromDate($entryDate));
    $rows = readJson($file);
    $rows[] = $entry;

    return writeJson($file, $rows)
        ? ['success' => true, 'message' => 'Đã lưu phiếu thu chi', 'id' => $entry['id']]
        : ['success' => false, 'message' => 'Không lưu được phiếu thu chi'];
}

function cashbookUpdateManual(string $branch, string $id, array $newEntry): array
{
    $current = cashbookFindEntry($branch, $id);
    if (!$current) return ['success' => false, 'message' => 'Không tìm thấy phiếu thu chi'];
    if (!cashbookCanManageEntry($current)) return ['success' => false, 'message' => 'Phiếu này không được sửa trực tiếp tại sổ thu chi'];

    $oldFile = $current['_file'];
    $newFile = cashbookFile($branch, cashbookYearMonthFromDate($newEntry['entry_date']));
    $newEntry['created_by'] = $current['created_by'] ?? $newEntry['created_by'];
    $newEntry['created_at'] = $current['created_at'] ?? $newEntry['created_at'];

    if ($oldFile === $newFile) {
        $rows = readJson($oldFile);
        foreach ($rows as &$row) {
            if (($row['id'] ?? '') === $id) {
                $row = array_merge($row, $newEntry);
                break;
            }
        }
        unset($row);
        return writeJson($oldFile, $rows)
            ? ['success' => true, 'message' => 'Đã cập nhật phiếu thu chi', 'id' => $id]
            : ['success' => false, 'message' => 'Không cập nhật được phiếu'];
    }

    $oldRows = array_values(array_filter(readJson($oldFile), fn($row) => ($row['id'] ?? '') !== $id));
    $newRows = readJson($newFile);
    $newRows[] = $newEntry;

    return writeJson($oldFile, $oldRows) && writeJson($newFile, $newRows)
        ? ['success' => true, 'message' => 'Đã cập nhật phiếu thu chi', 'id' => $id]
        : ['success' => false, 'message' => 'Không cập nhật được phiếu'];
}

function cashbookSoftDelete(string $branch, string $id): array
{
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền truy cập chi nhánh này'];
    }
    if (!cashbookCanDelete()) {
        return ['success' => false, 'message' => 'Bạn không có quyền xóa phiếu thu chi'];
    }

    $entry = cashbookFindEntry($branch, $id);
    if (!$entry) return ['success' => false, 'message' => 'Không tìm thấy phiếu thu chi'];
    if (!cashbookCanManageEntry($entry)) {
        return ['success' => false, 'message' => 'Phiếu tự động không được xóa trực tiếp tại sổ thu chi'];
    }

    $rows = readJson($entry['_file']);
    foreach ($rows as &$row) {
        if (($row['id'] ?? '') === $id) {
            $row['deleted_at'] = date('Y-m-d H:i:s');
            $row['deleted_by'] = currentUser()['name'] ?? 'System';
            break;
        }
    }
    unset($row);

    return writeJson($entry['_file'], $rows)
        ? ['success' => true, 'message' => 'Đã xóa phiếu thu chi']
        : ['success' => false, 'message' => 'Không xóa được phiếu'];
}

function cashbookNextId(string $branch, string $type, string $date): string
{
    $prefix = $type === 'expense' ? 'PAY' : 'RC';
    $branchPrefix = branchCodePrefix(getBranchInfo($branch)['short'] ?? 'CN');
    return "{$prefix}-{$branchPrefix}-" . date('YmdHis') . '-' . random_int(100, 999);
}

function cashbookUpsertSource(string $branch, array $entry): array
{
    $sourceType = $entry['source_type'] ?? '';
    $sourceId = $entry['source_id'] ?? '';
    if (!$sourceType || !$sourceId) {
        return ['success' => false, 'message' => 'Nguồn dữ liệu thu chi không hợp lệ'];
    }

    $entryDate = $entry['entry_date'] ?? date('Y-m-d');
    $file = cashbookFile($branch, cashbookYearMonthFromDate($entryDate));
    $rows = readJson($file);
    $found = false;
    foreach ($rows as &$row) {
        if (($row['source_type'] ?? '') === $sourceType && ($row['source_id'] ?? '') === $sourceId) {
            $row = array_merge($row, $entry, ['updated_at' => date('Y-m-d H:i:s')]);
            unset($row['deleted_at'], $row['deleted_by'], $row['delete_reason']);
            $found = true;
            break;
        }
    }
    unset($row);
    if (!$found) {
        $rows[] = array_merge([
            'id' => cashbookNextId($branch, $entry['type'] ?? 'income', $entryDate),
            'branch' => $branch,
            'created_at' => date('Y-m-d H:i:s'),
        ], $entry, ['updated_at' => date('Y-m-d H:i:s')]);
    }

    return writeJson($file, $rows)
        ? ['success' => true]
        : ['success' => false, 'message' => 'Không đồng bộ được sổ thu chi'];
}

function cashbookMarkSourceDeleted(string $branch, string $sourceType, string $sourceId, string $reason = ''): bool
{
    $success = true;
    foreach (glob(DATA_PATH . "/{$branch}/cashbook_*.json") ?: [] as $file) {
        $rows = readJson($file);
        $changed = false;
        foreach ($rows as &$row) {
            if (($row['source_type'] ?? '') === $sourceType && ($row['source_id'] ?? '') === $sourceId && empty($row['deleted_at'])) {
                $row['deleted_at'] = date('Y-m-d H:i:s');
                $row['deleted_by'] = 'System';
                $row['delete_reason'] = $reason ?: 'Chứng từ nguồn đã bị hủy';
                $changed = true;
            }
        }
        unset($row);
        if ($changed && !writeJson($file, $rows)) $success = false;
    }
    return $success;
}

function cashbookSyncReceivablePayment(string $branch, array $payment): array
{
    if (empty($payment['id'])) return ['success' => false, 'message' => 'Thiếu mã phiếu thu'];
    if (!empty($payment['deleted_at'])) {
        $ok = cashbookMarkSourceDeleted($branch, 'receivable_payment', $payment['id'], $payment['delete_reason'] ?? 'Phiếu thu đã hủy');
        return ['success' => $ok, 'message' => $ok ? '' : 'Không hủy được bút toán phiếu thu'];
    }
    return cashbookUpsertSource($branch, [
        'type' => 'income',
        'category' => 'debt_payment',
        'amount' => (float)($payment['amount'] ?? 0),
        'method' => $payment['method'] ?? 'cash',
        'person' => $payment['customer_name'] ?? '',
        'description' => trim('Thu công nợ' . (!empty($payment['note']) ? ': ' . $payment['note'] : '')),
        'entry_date' => $payment['paid_at'] ?? date('Y-m-d'),
        'source_type' => 'receivable_payment',
        'source_id' => $payment['id'],
        'created_by' => $payment['created_by'] ?? 'System',
    ]);
}

function cashbookSyncInvoice(string $branch, array $invoice): array
{
    $invoiceId = $invoice['id'] ?? '';
    if ($invoiceId === '') return ['success' => false, 'message' => 'Thiếu mã hóa đơn'];

    $payment = $invoice['payment'] ?? 'cash';
    if (invoiceIsCancelled($invoice) || !in_array($payment, ['cash', 'transfer'], true)) {
        $ok = cashbookMarkSourceDeleted($branch, 'invoice', $invoiceId, invoiceIsCancelled($invoice) ? 'Hóa đơn đã hủy' : 'Hóa đơn không thu tiền ngay');
        return ['success' => $ok, 'message' => $ok ? '' : 'Không hủy được bút toán hóa đơn'];
    }

    return cashbookUpsertSource($branch, [
        'type' => 'income',
        'category' => 'sale',
        'amount' => (float)($invoice['total'] ?? 0),
        'method' => $payment,
        'person' => $invoice['customer'] ?? 'Khách lẻ',
        'description' => 'Thu bán hàng - ' . $invoiceId,
        'entry_date' => substr($invoice['created_at'] ?? date('Y-m-d'), 0, 10),
        'source_type' => 'invoice',
        'source_id' => $invoiceId,
        'created_by' => $invoice['created_by'] ?? 'System',
    ]);
}

function cashbookSyncSalesReturn(string $branch, array $return): array
{
    $returnId = (string)($return['id'] ?? '');
    if ($returnId === '') return ['success' => false, 'message' => 'Thiếu mã phiếu trả hàng'];

    $method = (string)($return['refund_method'] ?? 'none');
    $isCashRefund = ($return['status'] ?? '') === 'approved'
        && in_array($method, ['cash', 'transfer'], true)
        && (float)($return['refund_total'] ?? 0) > 0;

    if (!$isCashRefund) {
        $ok = cashbookMarkSourceDeleted($branch, 'sales_return', $returnId, 'Phiếu trả hàng không phát sinh chi tiền');
        return ['success' => $ok, 'message' => $ok ? '' : 'Không hủy được bút toán hoàn tiền'];
    }

    return cashbookUpsertSource($branch, [
        'type' => 'expense',
        'category' => 'sales_return',
        'amount' => (float)($return['refund_total'] ?? 0),
        'method' => $method,
        'person' => $return['customer'] ?? 'Khách lẻ',
        'description' => 'Hoàn tiền trả hàng - ' . $returnId,
        'entry_date' => substr($return['approved_at'] ?? date('Y-m-d'), 0, 10),
        'source_type' => 'sales_return',
        'source_id' => $returnId,
        'created_by' => $return['approved_by'] ?? 'System',
    ]);
}

function cashbookSyncReceivablePayments(string $branch, string $yearMonth): void
{
    $file = receivablePaymentFile($branch, $yearMonth);
    foreach (readJson($file) as $payment) {
        cashbookSyncReceivablePayment($branch, $payment);
    }
}
