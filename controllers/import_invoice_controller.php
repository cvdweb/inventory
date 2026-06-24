<?php
// ============================================================
// IMPORT CONTROLLER (Nhập hàng)
// ============================================================

function importProcess(array $post, bool $insideTransaction = false): array
{
    $branch   = $post['branch']   ?? '';
    if (!$insideTransaction) {
        return withBranchTransaction($branch, fn() => importProcess($post, true));
    }
    $category = $post['category'] ?? '';
    $code     = $post['product_code'] ?? '';
    $qty      = floatval($post['qty'] ?? 0);
    $price    = floatval($post['price_in'] ?? 0);
    $date     = $post['import_date'] ?? date('Y-m-d');
    $note     = $post['note'] ?? '';
    $supplier = $post['supplier'] ?? '';
    $user     = currentUser();

    if (!$branch || !$category || !$code || $qty <= 0) {
        return ['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin'];
    }
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền nhập hàng cho chi nhánh này'];
    }

    $product = productGetByCode($branch, $code);
    if (!$product) {
        return ['success' => false, 'message' => "Không tìm thấy sản phẩm mã: {$code}"];
    }

    $stockOk = updateStock($branch, $code, $qty, 'in');
    if (!$stockOk) {
        return ['success' => false, 'message' => 'Lỗi cập nhật tồn kho'];
    }

    $importData = [
        'branch'        => $branch,
        'category'      => $category,
        'product_code'  => $code,
        'product_name'  => $product['name'],
        'qty'           => $qty,
        'unit'          => $product['unit'],
        'price_in'      => $price,
        'total_amount'  => $qty * $price,
        'import_date'   => $date,
        'supplier'      => $supplier,
        'note'          => $note,
        'created_by'    => $user['name'] ?? 'System',
    ];

    $result = createImport($branch, $importData);
    if (!($result['success'] ?? false)) {
        updateStock($branch, $code, $qty, 'out');
    }
    return $result;
}

// ============================================================
// INVOICE CONTROLLER (Bán hàng)
// ============================================================

function invoiceProcess(array $post, bool $insideTransaction = false): array
{
    $branch       = $post['branch']        ?? '';
    if (!$insideTransaction) {
        return withBranchTransaction($branch, fn() => invoiceProcess($post, true));
    }
    $customer     = $post['customer']      ?? 'Khách lẻ';
    $phone        = $post['phone']         ?? '';
    $address      = $post['address']       ?? '';
    $items        = json_decode($post['items'] ?? '[]', true) ?: [];
    $note         = $post['note']          ?? '';
    $delivery_note= $post['delivery_note'] ?? '';
    $payment      = $post['payment']       ?? 'cash';
    $delivery_date= $post['delivery_date'] ?? '';   // rỗng = lấy tại quầy
    $shipping_fee = floatval($post['shipping_fee'] ?? 0);  // Giá vận chuyển
    $user         = currentUser();

    if ($payment === 'credit' && function_exists('featureEnabled') && !featureEnabled('receivables')) {
        return ['success' => false, 'message' => 'Chế độ sử dụng hiện tại không hỗ trợ bán hàng công nợ'];
    }

    if (!$branch || empty($items)) {
        return ['success' => false, 'message' => 'Hóa đơn không có sản phẩm'];
    }
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền bán hàng cho chi nhánh này'];
    }

    // Kiểm tra tồn kho và tính tiền
    $processedItems = [];
    foreach ($items as $item) {
        $code = $item['code'] ?? '';
        $qty  = floatval($item['qty'] ?? 0);
        if (!$code || $qty <= 0) continue;

        $product = productGetByCode($branch, $code);
        if (!$product) {
            return ['success' => false, 'message' => "Không tìm thấy sản phẩm: {$code}"];
        }
        if (($product['stock'] ?? 0) < $qty) {
            return ['success' => false, 'message' => "Sản phẩm '{$product['name']}' không đủ tồn kho (còn " . ($product['stock'] ?? 0) . " {$product['unit']})"];
        }

        $priceOut = floatval($item['price_out'] ?? $product['price_out']);
        $costPrice = floatval($product['price_in'] ?? 0);
        if ($priceOut <= 0) {
            return ['success' => false, 'message' => "GiÃ¡ bÃ¡n cá»§a '{$product['name']}' pháº£i lá»›n hÆ¡n 0"];
        }
        $lineTotal = $qty * $priceOut;
        $processedItems[] = [
            'product_code' => $code,
            'product_name' => $product['name'],
            'unit'         => $product['unit'],
            'qty'          => $qty,
            'price_out'    => $priceOut,
            'line_total'   => $lineTotal,
            'cost_price'   => $costPrice,
            'cost_total'   => $qty * $costPrice,
        ];
    }

    if (empty($processedItems)) {
        return ['success' => false, 'message' => 'Không có sản phẩm hợp lệ'];
    }

    // Trừ tồn kho
    $deductedItems = [];
    foreach ($processedItems as $item) {
        if (!updateStock($branch, $item['product_code'], $item['qty'], 'out')) {
            foreach ($deductedItems as $deducted) {
                updateStock($branch, $deducted['product_code'], $deducted['qty'], 'in');
            }
            return ['success' => false, 'message' => 'Không thể cập nhật tồn kho. Hóa đơn chưa được tạo.'];
        }
        $deductedItems[] = $item;
    }

    // Xác định trạng thái giao hàng
    $delivery_status = $delivery_date ? 'pending' : 'self_pickup';

    $invoiceData = [
        'customer'        => $customer,
        'phone'           => $phone,
        'address'         => $address,
        'items'           => $processedItems,
        'note'            => $note,
        'delivery_note'   => $delivery_note,
        'payment'         => $payment,
        'delivery_date'   => $delivery_date,
        'delivery_status' => $delivery_status,
        'shipping_fee'    => $shipping_fee,
        'cashbook_sync_expected' => true,
        'created_by'      => $user['name'] ?? 'System',
    ];

    $result = createInvoice($branch, $invoiceData);
    if (!($result['success'] ?? false)) {
        foreach ($deductedItems as $deducted) {
            updateStock($branch, $deducted['product_code'], $deducted['qty'], 'in');
        }
        return $result;
    }

    $savedInvoice = getInvoiceById($branch, $result['id']);
    if ($savedInvoice && function_exists('cashbookSyncInvoice')) {
        $sync = cashbookSyncInvoice($branch, $savedInvoice);
        if (!($sync['success'] ?? false)) {
            $result['warning'] = 'Hóa đơn đã lưu nhưng chưa đồng bộ được với sổ thu chi. Hãy chạy kiểm tra toàn vẹn dữ liệu.';
        }
    }
    return $result;
}

// ============================================================
// CẬP NHẬT TRẠNG THÁI GIAO HÀNG
// ============================================================

function updateDeliveryStatus(string $branch, string $invoiceId, string $status, bool $insideTransaction = false): array
{
    if (!$insideTransaction) {
        return withBranchTransaction($branch, fn() => updateDeliveryStatus($branch, $invoiceId, $status, true));
    }
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền cập nhật giao hàng tại chi nhánh này'];
    }
    $found = false;
    $blocked = false;
    foreach (getAllInvoiceFiles($branch) as $file) {
        $hasRecord = false;
        foreach (readJson($file) as $invoice) {
            if (($invoice['id'] ?? '') === $invoiceId) { $hasRecord = true; break; }
        }
        if (!$hasRecord) continue;
        $saved = updateJson($file, function (array $invoices) use ($invoiceId, $status, &$found, &$blocked): array {
            foreach ($invoices as &$invoice) {
                if (($invoice['id'] ?? '') === $invoiceId) {
                    if (invoiceIsCancelled($invoice)) { $blocked = true; break; }
                    $invoice['delivery_status'] = $status;
                    if ($status === 'delivered') {
                        $invoice['delivered_at'] = date('Y-m-d H:i:s');
                    }
                    $found = true;
                    break;
                }
            }
            unset($invoice);
            return $invoices;
        });
        if ($found && !$saved) return ['success' => false, 'message' => 'Không lưu được trạng thái giao hàng'];
        if ($found || $blocked) break;
    }

    if ($blocked) return ['success' => false, 'message' => 'Không thể cập nhật hóa đơn đã hủy'];

    return $found
        ? ['success' => true,  'message' => 'Đã cập nhật trạng thái giao hàng']
        : ['success' => false, 'message' => 'Không tìm thấy hóa đơn'];
}

// ============================================================
// LẤY HÓA ĐƠN THEO ID (dò tìm qua các tháng)
// ============================================================

function getInvoiceById(string $branch, string $invoiceId): ?array
{
    foreach (getAllInvoiceFiles($branch) as $file) {
        foreach (readJson($file) as $inv) {
            if ($inv['id'] === $invoiceId) return $inv;
        }
    }
    return null;
}

function findInvoiceRecord(string $branch, string $invoiceId): ?array
{
    foreach (getAllInvoiceFiles($branch) as $file) {
        foreach (readJson($file) as $index => $invoice) {
            if (($invoice['id'] ?? '') === $invoiceId) {
                return ['invoice' => $invoice, 'file' => $file, 'index' => $index];
            }
        }
    }
    return null;
}

// ============================================================
// SỬA HÓA ĐƠN (chỉ cho hóa đơn chưa giao)
// ============================================================

function updateInvoice(string $branch, string $invoiceId, array $post, bool $insideTransaction = false): array
{
    if (!$insideTransaction) {
        return withBranchTransaction($branch, fn() => updateInvoice($branch, $invoiceId, $post, true));
    }
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền sửa hóa đơn tại chi nhánh này'];
    }
    // 1. Tìm hóa đơn gốc
    $record = findInvoiceRecord($branch, $invoiceId);
    $original = $record['invoice'] ?? null;
    $targetFile = $record['file'] ?? null;

    if (!$original)     return ['success' => false, 'message' => 'Không tìm thấy hóa đơn'];
    if (invoiceIsCancelled($original))
        return ['success' => false, 'message' => 'Không thể sửa hóa đơn đã hủy'];
    if (($original['delivery_status'] ?? '') === 'delivered')
        return ['success' => false, 'message' => 'Không thể sửa hóa đơn đã giao'];

    $newPayment = $post['payment'] ?? $original['payment'] ?? 'cash';
    if ($newPayment === 'credit' && ($original['payment'] ?? '') !== 'credit' && function_exists('featureEnabled') && !featureEnabled('receivables')) {
        return ['success' => false, 'message' => 'Chế độ sử dụng hiện tại không hỗ trợ bán hàng công nợ'];
    }
    $originalCustomerKey = receivableCustomerKey($original['customer'] ?? 'Khách lẻ', $original['phone'] ?? '');
    $newCustomerKey = receivableCustomerKey($post['customer'] ?? $original['customer'] ?? 'Khách lẻ', $post['phone'] ?? $original['phone'] ?? '');
    $customerHasPayments = ($original['payment'] ?? '') === 'credit'
        && receivableCustomerHasActivePayments($branch, $originalCustomerKey);
    if ($customerHasPayments) {
        if ($newPayment !== 'credit') {
            return ['success' => false, 'message' => 'Khách hàng đã có phiếu thu công nợ. Hãy hủy các phiếu thu liên quan trước khi đổi phương thức thanh toán.'];
        }
        if ($newCustomerKey !== $originalCustomerKey) {
            return ['success' => false, 'message' => 'Không thể đổi khách hàng vì hóa đơn đã phát sinh phiếu thu công nợ.'];
        }
    }

    // 2. Parse items mới
    $newItems = json_decode($post['items'] ?? '[]', true) ?: [];
    if (empty($newItems)) return ['success' => false, 'message' => 'Hóa đơn phải có ít nhất 1 sản phẩm'];

    // 3. Hoàn tồn kho theo items CŨ
    $restoredOldItems = [];
    foreach ($original['items'] as $oldItem) {
        if (!updateStock($branch, $oldItem['product_code'], $oldItem['qty'], 'in')) {
            foreach ($restoredOldItems as $restored) updateStock($branch, $restored['product_code'], $restored['qty'], 'out');
            return ['success' => false, 'message' => 'Không thể hoàn tồn kho cũ để sửa hóa đơn'];
        }
        $restoredOldItems[] = $oldItem;
    }

    // 4. Kiểm tra tồn kho và trừ theo items MỚI
    $processedItems = [];
    $originalCostByCode = [];
    foreach ($original['items'] ?? [] as $oldItem) {
        if (array_key_exists('cost_price', $oldItem)) {
            $originalCostByCode[(string)($oldItem['product_code'] ?? '')] = (float)$oldItem['cost_price'];
        }
    }
    foreach ($newItems as $item) {
        $code = $item['code'] ?? '';
        $qty  = floatval($item['qty'] ?? 0);
        if (!$code || $qty <= 0) continue;

        $product = productGetByCode($branch, $code, true);
        if (!$product) {
            // Hoàn lại tồn kho cũ nếu gặp lỗi
            foreach ($original['items'] as $oldItem) {
                updateStock($branch, $oldItem['product_code'], $oldItem['qty'], 'out');
            }
            return ['success' => false, 'message' => "Không tìm thấy sản phẩm: {$code}"];
        }
        if (($product['stock'] ?? 0) < $qty) {
            // Hoàn lại tồn kho cũ nếu gặp lỗi
            foreach ($original['items'] as $oldItem) {
                updateStock($branch, $oldItem['product_code'], $oldItem['qty'], 'out');
            }
            return ['success' => false, 'message' => "'{$product['name']}' không đủ tồn kho (còn " . ($product['stock'] ?? 0) . " {$product['unit']})"];
        }

        $priceOut = floatval($item['price_out'] ?? $product['price_out']);
        $costPrice = $originalCostByCode[$code] ?? floatval($product['price_in'] ?? 0);
        if ($priceOut <= 0) {
            foreach ($original['items'] as $oldItem) {
                updateStock($branch, $oldItem['product_code'], $oldItem['qty'], 'out');
            }
            return ['success' => false, 'message' => "GiÃ¡ bÃ¡n cá»§a '{$product['name']}' pháº£i lá»›n hÆ¡n 0"];
        }

        $processedItems[] = [
            'product_code' => $code,
            'product_name' => $product['name'],
            'unit'         => $product['unit'],
            'qty'          => $qty,
            'price_out'    => $priceOut,
            'line_total'   => $qty * $priceOut,
            'cost_price'   => $costPrice,
            'cost_total'   => $qty * $costPrice,
        ];
    }

    if (empty($processedItems)) {
        foreach ($original['items'] as $oldItem) updateStock($branch, $oldItem['product_code'], $oldItem['qty'], 'out');
        return ['success' => false, 'message' => 'Hóa đơn phải có ít nhất một sản phẩm hợp lệ'];
    }

    $subtotal = array_sum(array_column($processedItems, 'line_total'));
    $shippingFee = floatval($post['shipping_fee'] ?? $original['shipping_fee'] ?? 0);
    $total = $subtotal + $shippingFee;
    if ($customerHasPayments) {
        $receivableCustomer = receivableFindCustomer($branch, $originalCustomerKey);
        $projectedDebt = (float)($receivableCustomer['debt'] ?? 0) - (float)($original['total'] ?? 0) + $total;
        $paid = (float)($receivableCustomer['paid'] ?? 0);
        if ($projectedDebt + 0.000001 < $paid) {
            foreach ($original['items'] as $oldItem) updateStock($branch, $oldItem['product_code'], $oldItem['qty'], 'out');
            return ['success' => false, 'message' => 'Không thể giảm hóa đơn vì tổng công nợ sẽ thấp hơn số tiền khách đã thanh toán.'];
        }
    }

    // 5. Trừ tồn kho mới
    $deductedNewItems = [];
    foreach ($processedItems as $item) {
        if (!updateStock($branch, $item['product_code'], $item['qty'], 'out')) {
            foreach ($deductedNewItems as $deducted) updateStock($branch, $deducted['product_code'], $deducted['qty'], 'in');
            foreach ($original['items'] as $oldItem) updateStock($branch, $oldItem['product_code'], $oldItem['qty'], 'out');
            return ['success' => false, 'message' => 'Không thể cập nhật tồn kho mới; hóa đơn giữ nguyên'];
        }
        $deductedNewItems[] = $item;
    }

    // 6. Ghi log thay đổi
    $user    = currentUser();
    $editLog = $original['edit_log'] ?? [];
    $editLog[] = [
        'edited_by'    => $user['name'] ?? 'System',
        'edited_at'    => date('Y-m-d H:i:s'),
        'old_total'    => $original['total'],
        'new_total'    => $total,
        'old_item_cnt' => count($original['items']),
        'new_item_cnt' => count($processedItems),
    ];

    // 7. Cập nhật hóa đơn trong file
    $invoices = readJson($targetFile);

    $delivery_date   = $post['delivery_date']   ?? $original['delivery_date']   ?? '';
    $delivery_status = $original['delivery_status'] ?? 'self_pickup';
    if ($delivery_date && $delivery_status === 'self_pickup') $delivery_status = 'pending';
    if (!$delivery_date) $delivery_status = 'self_pickup';

    foreach ($invoices as &$inv) {
        if ($inv['id'] === $invoiceId) {
            $inv['customer']        = $post['customer']      ?? $original['customer'];
            $inv['phone']           = $post['phone']         ?? $original['phone'];
            $inv['address']         = $post['address']       ?? $original['address']       ?? '';
            $inv['note']            = $post['note']          ?? $original['note']          ?? '';
            $inv['delivery_note']   = $post['delivery_note'] ?? $original['delivery_note'] ?? '';
            $inv['payment']         = $newPayment;
            $inv['delivery_date']   = $delivery_date;
            $inv['delivery_status'] = $delivery_status;
            $inv['shipping_fee']    = $shippingFee;
            $inv['items']           = $processedItems;
            $inv['total']           = $total;
            $inv['cashbook_sync_expected'] = true;
            $inv['updated_at']      = date('Y-m-d H:i:s');
            $inv['edit_log']        = $editLog;
            $updatedInvoice = $inv;
            break;
        }
    }

    if (!writeJson($targetFile, array_values($invoices))) {
        foreach ($deductedNewItems as $deducted) updateStock($branch, $deducted['product_code'], $deducted['qty'], 'in');
        foreach ($original['items'] as $oldItem) updateStock($branch, $oldItem['product_code'], $oldItem['qty'], 'out');
        return ['success' => false, 'message' => 'Không thể ghi hóa đơn; thay đổi tồn kho đã được hoàn tác'];
    }

    if (isset($updatedInvoice) && function_exists('cashbookSyncInvoice')) {
        $sync = cashbookSyncInvoice($branch, $updatedInvoice);
        if (!($sync['success'] ?? false)) {
            return ['success' => true, 'id' => $invoiceId, 'total' => $total,
                'message' => 'Đã cập nhật hóa đơn nhưng chưa đồng bộ được sổ thu chi'];
        }
    }
    return ['success' => true, 'id' => $invoiceId, 'total' => $total,
            'message' => 'Đã cập nhật hóa đơn thành công'];
}

function cancelInvoice(string $branch, string $invoiceId, string $reason, bool $insideTransaction = false): array
{
    if (!$insideTransaction) {
        return withBranchTransaction($branch, fn() => cancelInvoice($branch, $invoiceId, $reason, true));
    }
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền truy cập chi nhánh này'];
    }
    $reason = trim($reason);
    if ($reason === '') return ['success' => false, 'message' => 'Vui lòng nhập lý do hủy hóa đơn'];

    $record = findInvoiceRecord($branch, $invoiceId);
    if (!$record) return ['success' => false, 'message' => 'Không tìm thấy hóa đơn'];
    $invoice = $record['invoice'];
    if (invoiceIsCancelled($invoice)) return ['success' => false, 'message' => 'Hóa đơn đã được hủy trước đó'];
    if (($invoice['delivery_status'] ?? '') === 'delivered') {
        return ['success' => false, 'message' => 'Hóa đơn đã giao phải xử lý bằng quy trình trả hàng, không thể hủy trực tiếp'];
    }

    if (($invoice['payment'] ?? '') === 'credit') {
        $customerKey = receivableCustomerKey($invoice['customer'] ?? 'Khách lẻ', $invoice['phone'] ?? '');
        if (receivableCustomerHasActivePayments($branch, $customerKey)) {
            return ['success' => false, 'message' => 'Khách hàng đã có phiếu thu công nợ. Hãy hủy phiếu thu trước khi hủy hóa đơn để tránh sai lệch công nợ.'];
        }
    }

    foreach ($invoice['items'] ?? [] as $item) {
        if (!productGetByCode($branch, $item['product_code'] ?? '', true)) {
            return ['success' => false, 'message' => 'Không thể hoàn kho vì sản phẩm ' . ($item['product_code'] ?? '') . ' không còn trong dữ liệu'];
        }
    }

    $restored = [];
    foreach ($invoice['items'] ?? [] as $item) {
        if (!updateStock($branch, $item['product_code'] ?? '', (float)($item['qty'] ?? 0), 'in')) {
            foreach ($restored as $done) updateStock($branch, $done['product_code'], $done['qty'], 'out');
            return ['success' => false, 'message' => 'Không thể hoàn tồn kho; hóa đơn chưa bị hủy'];
        }
        $restored[] = ['product_code' => $item['product_code'], 'qty' => (float)$item['qty']];
    }

    $rows = readJson($record['file']);
    $index = $record['index'];
    $rows[$index]['status'] = 'cancelled';
    $rows[$index]['delivery_status'] = 'cancelled';
    $rows[$index]['cancelled_at'] = date('Y-m-d H:i:s');
    $rows[$index]['cancelled_by'] = currentUser()['username'] ?? currentUser()['name'] ?? 'System';
    $rows[$index]['cancel_reason'] = $reason;
    $rows[$index]['audit_log'] = $rows[$index]['audit_log'] ?? [];
    $rows[$index]['audit_log'][] = [
        'action' => 'cancel',
        'at' => $rows[$index]['cancelled_at'],
        'by' => $rows[$index]['cancelled_by'],
        'reason' => $reason,
    ];

    if (!writeJson($record['file'], array_values($rows))) {
        foreach ($restored as $done) updateStock($branch, $done['product_code'], $done['qty'], 'out');
        return ['success' => false, 'message' => 'Không thể lưu trạng thái hủy; tồn kho đã được hoàn tác'];
    }

    if (function_exists('cashbookMarkSourceDeleted')) {
        cashbookMarkSourceDeleted($branch, 'invoice', $invoiceId, 'Hủy hóa đơn: ' . $reason);
    }
    return ['success' => true, 'message' => 'Đã hủy hóa đơn và hoàn lại tồn kho'];
}
