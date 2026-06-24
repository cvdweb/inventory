<?php

function inventoryAdjustmentTypes(): array
{
    return [
        'stocktake' => 'Kiểm kê thực tế',
        'increase' => 'Điều chỉnh tăng',
        'decrease' => 'Điều chỉnh giảm',
    ];
}

function inventoryAdjustmentReasons(): array
{
    return [
        'stocktake' => ['Kiểm kê định kỳ', 'Kiểm kê đột xuất', 'Đối chiếu tồn kho'],
        'increase' => ['Phát hiện thừa khi kiểm kê', 'Bổ sung tồn đầu kỳ', 'Sửa sai số liệu'],
        'decrease' => ['Hư hỏng', 'Thất thoát', 'Dùng nội bộ', 'Hàng mẫu/tặng', 'Sửa sai số liệu'],
    ];
}

function inventoryAdjustmentFile(string $branch, string $yearMonth = ''): string
{
    return DATA_PATH . "/{$branch}/stock_adjustments_" . ($yearMonth ?: date('Y_m')) . '.json';
}

function inventoryAdjustmentFiles(string $branch): array
{
    $files = glob(DATA_PATH . "/{$branch}/stock_adjustments_*.json") ?: [];
    rsort($files);
    return $files;
}

function getInventoryAdjustments(string $branch, bool $includeCancelled = true): array
{
    $rows = [];
    foreach (inventoryAdjustmentFiles($branch) as $file) {
        foreach (readJson($file) as $row) {
            if (!$includeCancelled && in_array($row['status'] ?? '', ['cancelled', 'reversed'], true)) continue;
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return $rows;
}

function inventoryAdjustmentFind(string $branch, string $id): ?array
{
    foreach (inventoryAdjustmentFiles($branch) as $file) {
        foreach (readJson($file) as $index => $row) {
            if (($row['id'] ?? '') === $id) return ['file' => $file, 'index' => $index, 'record' => $row];
        }
    }
    return null;
}

function inventoryAdjustmentCreate(string $branch, array $post, bool $insideTransaction = false): array
{
    if (!$insideTransaction) {
        return withBranchTransaction($branch, fn() => inventoryAdjustmentCreate($branch, $post, true));
    }
    if (!canAccessBranch($branch)) return ['success' => false, 'message' => 'Không có quyền truy cập chi nhánh này'];

    $type = (string)($post['type'] ?? 'stocktake');
    if (!isset(inventoryAdjustmentTypes()[$type])) return ['success' => false, 'message' => 'Loại phiếu không hợp lệ'];
    $reason = trim((string)($post['reason'] ?? ''));
    if ($reason === '') return ['success' => false, 'message' => 'Vui lòng nhập lý do'];

    $submitted = json_decode((string)($post['items'] ?? '[]'), true);
    if (!is_array($submitted) || !$submitted) return ['success' => false, 'message' => 'Phiếu phải có ít nhất một sản phẩm'];

    $items = [];
    $seen = [];
    foreach ($submitted as $item) {
        $code = trim((string)($item['code'] ?? ''));
        if ($code === '' || isset($seen[$code])) continue;
        $product = productGetByCode($branch, $code, true);
        if (!$product) return ['success' => false, 'message' => "Không tìm thấy sản phẩm {$code}"];

        $systemQty = (float)($product['stock'] ?? 0);
        if ($type === 'stocktake') {
            $actualQty = (float)($item['actual_qty'] ?? -1);
            if ($actualQty < 0) return ['success' => false, 'message' => "Tồn thực tế của {$product['name']} không hợp lệ"];
            $difference = $actualQty - $systemQty;
        } else {
            $adjustQty = (float)($item['adjust_qty'] ?? 0);
            if ($adjustQty <= 0) return ['success' => false, 'message' => "Số lượng điều chỉnh của {$product['name']} phải lớn hơn 0"];
            $actualQty = null;
            $difference = $type === 'increase' ? $adjustQty : -$adjustQty;
            if ($systemQty + $difference < -0.000001) {
                return ['success' => false, 'message' => "Không thể giảm {$product['name']} vượt tồn hiện tại"];
            }
        }

        $items[] = [
            'product_code' => $code,
            'product_name' => $product['name'] ?? $code,
            'unit' => $product['unit'] ?? '',
            'system_qty' => $systemQty,
            'actual_qty' => $actualQty,
            'difference' => $difference,
        ];
        $seen[$code] = true;
    }
    if (!$items) return ['success' => false, 'message' => 'Không có sản phẩm hợp lệ'];

    $user = currentUser();
    $record = [
        'id' => 'ADJ-' . branchCodePrefix(getBranchInfo($branch)['short'] ?? 'CN') . '-' . date('YmdHis') . '-' . random_int(100, 999),
        'branch' => $branch,
        'type' => $type,
        'reason' => $reason,
        'note' => trim((string)($post['note'] ?? '')),
        'status' => 'draft',
        'items' => $items,
        'created_by' => $user['name'] ?? 'System',
        'created_by_username' => $user['username'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $file = inventoryAdjustmentFile($branch);
    $saved = updateJson($file, function (array $rows) use ($record): array {
        $rows[] = $record;
        return $rows;
    });
    return $saved
        ? ['success' => true, 'message' => 'Đã lập phiếu nháp. Tồn kho chưa thay đổi', 'id' => $record['id']]
        : ['success' => false, 'message' => 'Không lưu được phiếu kiểm kê'];
}

function inventoryApplyDifferences(string $branch, array $items, bool $reverse = false): array
{
    $prepared = [];
    foreach ($items as $item) {
        $difference = (float)($item['difference'] ?? 0) * ($reverse ? -1 : 1);
        if ($reverse && abs($difference) < 0.000001) continue;
        $product = productGetByCode($branch, (string)($item['product_code'] ?? ''), true);
        if (!$product) return ['success' => false, 'message' => 'Sản phẩm ' . ($item['product_code'] ?? '') . ' không còn trong dữ liệu'];
        $current = (float)($product['stock'] ?? 0);
        if (!$reverse && abs($current - (float)($item['system_qty'] ?? 0)) > 0.000001) {
            return ['success' => false, 'message' => "Tồn kho {$product['name']} đã thay đổi từ khi lập phiếu. Hãy hủy phiếu và kiểm kê lại"];
        }
        if (abs($difference) < 0.000001) continue;
        if ($current + $difference < -0.000001) {
            return ['success' => false, 'message' => "Tồn kho {$product['name']} không đủ để thực hiện"];
        }
        $prepared[] = ['code' => $product['code'], 'difference' => $difference, 'from' => $current, 'to' => $current + $difference];
    }

    $applied = [];
    foreach ($prepared as $item) {
        $ok = updateStock($branch, $item['code'], abs($item['difference']), $item['difference'] > 0 ? 'in' : 'out');
        if (!$ok) {
            foreach (array_reverse($applied) as $done) {
                updateStock($branch, $done['code'], abs($done['difference']), $done['difference'] > 0 ? 'out' : 'in');
            }
            return ['success' => false, 'message' => 'Không cập nhật được tồn kho; mọi thay đổi đã được hoàn tác'];
        }
        $applied[] = $item;
    }
    return ['success' => true, 'applied' => $applied];
}

function inventoryAdjustmentApprove(string $branch, string $id, bool $insideTransaction = false): array
{
    if (!$insideTransaction) return withBranchTransaction($branch, fn() => inventoryAdjustmentApprove($branch, $id, true));
    if (!in_array(currentUser()['role'] ?? '', ['superadmin', 'admin'], true)) return ['success' => false, 'message' => 'Chỉ chủ cửa hàng được duyệt phiếu'];
    $found = inventoryAdjustmentFind($branch, $id);
    if (!$found || ($found['record']['status'] ?? '') !== 'draft') return ['success' => false, 'message' => 'Phiếu không tồn tại hoặc không còn chờ duyệt'];

    $apply = inventoryApplyDifferences($branch, $found['record']['items'] ?? []);
    if (!$apply['success']) return $apply;
    $saved = updateJson($found['file'], function (array $rows) use ($id, $apply): array {
        foreach ($rows as &$row) {
            if (($row['id'] ?? '') !== $id) continue;
            $row['status'] = 'approved';
            $row['approved_by'] = currentUser()['name'] ?? 'System';
            $row['approved_at'] = date('Y-m-d H:i:s');
            $row['applied'] = $apply['applied'];
            break;
        }
        unset($row);
        return $rows;
    });
    if (!$saved) {
        inventoryApplyDifferences($branch, $found['record']['items'] ?? [], true);
        return ['success' => false, 'message' => 'Không lưu được trạng thái duyệt; tồn kho đã được hoàn tác'];
    }
    return ['success' => true, 'message' => 'Đã duyệt phiếu và cập nhật tồn kho'];
}

function inventoryAdjustmentCancel(string $branch, string $id, bool $insideTransaction = false): array
{
    if (!$insideTransaction) return withBranchTransaction($branch, fn() => inventoryAdjustmentCancel($branch, $id, true));
    $found = inventoryAdjustmentFind($branch, $id);
    if (!$found || ($found['record']['status'] ?? '') !== 'draft') return ['success' => false, 'message' => 'Chỉ được hủy phiếu đang chờ duyệt'];
    $user = currentUser();
    $canCancel = in_array($user['role'] ?? '', ['superadmin', 'admin'], true)
        || ($found['record']['created_by_username'] ?? '') === ($user['username'] ?? '');
    if (!$canCancel) return ['success' => false, 'message' => 'Không có quyền hủy phiếu này'];
    $saved = updateJson($found['file'], function (array $rows) use ($id, $user): array {
        foreach ($rows as &$row) {
            if (($row['id'] ?? '') !== $id) continue;
            $row['status'] = 'cancelled';
            $row['cancelled_by'] = $user['name'] ?? 'System';
            $row['cancelled_at'] = date('Y-m-d H:i:s');
            break;
        }
        unset($row);
        return $rows;
    });
    return $saved ? ['success' => true, 'message' => 'Đã hủy phiếu nháp'] : ['success' => false, 'message' => 'Không hủy được phiếu'];
}

function inventoryAdjustmentReverse(string $branch, string $id, string $reason, bool $insideTransaction = false): array
{
    if (!$insideTransaction) return withBranchTransaction($branch, fn() => inventoryAdjustmentReverse($branch, $id, $reason, true));
    if (!in_array(currentUser()['role'] ?? '', ['superadmin', 'admin'], true)) return ['success' => false, 'message' => 'Chỉ chủ cửa hàng được hoàn tác'];
    if (trim($reason) === '') return ['success' => false, 'message' => 'Vui lòng nhập lý do hoàn tác'];
    $found = inventoryAdjustmentFind($branch, $id);
    if (!$found || ($found['record']['status'] ?? '') !== 'approved') return ['success' => false, 'message' => 'Chỉ được hoàn tác phiếu đã duyệt'];
    $apply = inventoryApplyDifferences($branch, $found['record']['items'] ?? [], true);
    if (!$apply['success']) return $apply;
    $saved = updateJson($found['file'], function (array $rows) use ($id, $reason): array {
        foreach ($rows as &$row) {
            if (($row['id'] ?? '') !== $id) continue;
            $row['status'] = 'reversed';
            $row['reversed_by'] = currentUser()['name'] ?? 'System';
            $row['reversed_at'] = date('Y-m-d H:i:s');
            $row['reverse_reason'] = trim($reason);
            break;
        }
        unset($row);
        return $rows;
    });
    if (!$saved) {
        inventoryApplyDifferences($branch, $found['record']['items'] ?? []);
        return ['success' => false, 'message' => 'Không lưu được hoàn tác; tồn kho đã được khôi phục'];
    }
    return ['success' => true, 'message' => 'Đã hoàn tác phiếu và khôi phục tồn kho'];
}
