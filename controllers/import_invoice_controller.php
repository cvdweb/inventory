<?php
// ============================================================
// IMPORT CONTROLLER (Nhập hàng)
// ============================================================

function importProcess(array $post): array
{
    $branch   = $post['branch']   ?? '';
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

    return createImport($branch, $importData);
}

// ============================================================
// INVOICE CONTROLLER (Bán hàng)
// ============================================================

function invoiceProcess(array $post): array
{
    $branch       = $post['branch']        ?? '';
    $customer     = $post['customer']      ?? 'Khách lẻ';
    $phone        = $post['phone']         ?? '';
    $address      = $post['address']       ?? '';
    $items        = json_decode($post['items'] ?? '[]', true) ?: [];
    $note         = $post['note']          ?? '';
    $delivery_note= $post['delivery_note'] ?? '';
    $payment      = $post['payment']       ?? 'cash';
    $delivery_date= $post['delivery_date'] ?? '';   // rỗng = lấy tại quầy
    $user         = currentUser();

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

        $lineTotal = $qty * floatval($item['price_out'] ?? $product['price_out']);
        $processedItems[] = [
            'product_code' => $code,
            'product_name' => $product['name'],
            'unit'         => $product['unit'],
            'qty'          => $qty,
            'price_out'    => floatval($item['price_out'] ?? $product['price_out']),
            'line_total'   => $lineTotal,
        ];
    }

    if (empty($processedItems)) {
        return ['success' => false, 'message' => 'Không có sản phẩm hợp lệ'];
    }

    // Trừ tồn kho
    foreach ($processedItems as $item) {
        updateStock($branch, $item['product_code'], $item['qty'], 'out');
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
        'created_by'      => $user['name'] ?? 'System',
    ];

    return createInvoice($branch, $invoiceData);
}

// ============================================================
// CẬP NHẬT TRẠNG THÁI GIAO HÀNG
// ============================================================

function updateDeliveryStatus(string $branch, string $invoiceId, string $status): array
{
    $yearMonth = date('Y_m');
    // Thử tháng hiện tại trước, rồi dò ngược nếu không thấy
    $found = false;
    for ($i = 0; $i < 12; $i++) {
        $ym   = date('Y_m', strtotime("-{$i} months"));
        $file = DATA_PATH . "/{$branch}/invoices_{$ym}.json";
        if (!file_exists($file)) continue;

        $fp = fopen($file, 'c+');
        if (!$fp) continue;

        flock($fp, LOCK_EX);
        $content  = stream_get_contents($fp);
        $invoices = json_decode($content ?: '[]', true) ?: [];

        foreach ($invoices as &$inv) {
            if ($inv['id'] === $invoiceId) {
                $inv['delivery_status'] = $status;
                if ($status === 'delivered') {
                    $inv['delivered_at'] = date('Y-m-d H:i:s');
                }
                $found = true;
                break;
            }
        }

        if ($found) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($invoices, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($fp);
        }
        flock($fp, LOCK_UN);
        fclose($fp);
        if ($found) break;
    }

    return $found
        ? ['success' => true,  'message' => 'Đã cập nhật trạng thái giao hàng']
        : ['success' => false, 'message' => 'Không tìm thấy hóa đơn'];
}
