<?php

function importBulkTemplate(string $branch): void
{
    if (!canAccessBranch($branch)) {
        http_response_code(403);
        echo 'Không có quyền truy cập chi nhánh';
        exit;
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="mau_nhap_hang_' . ($branch ?: 'chi_nhanh') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Mã SP', 'Số lượng', 'Giá nhập', 'Ghi chú']);
    $sample = getAllProducts($branch)[0] ?? [];
    fputcsv($out, [$sample['code'] ?? 'MA-SP', '50', (string)($sample['price_in'] ?? 0), 'Lô hàng mẫu']);
    fclose($out);
    exit;
}

function importBulkKeyExists(string $branch, string $key): bool
{
    foreach (glob(DATA_PATH . "/{$branch}/imports_*.json") ?: [] as $file) {
        foreach (readJson($file) as $import) {
            if (($import['bulk_key'] ?? '') === $key) return true;
        }
    }
    return false;
}

function importBulkPreview(string $branch, array $post, array $file): array
{
    if (!canAccessBranch($branch)) return ['success' => false, 'message' => 'Không có quyền nhập hàng cho chi nhánh này'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['success' => false, 'message' => 'Vui lòng chọn file CSV'];
    if (strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'csv') return ['success' => false, 'message' => 'Chỉ hỗ trợ file .csv'];
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) return ['success' => false, 'message' => 'File CSV không được vượt quá 5 MB'];

    $importDate = trim($post['import_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $importDate)) return ['success' => false, 'message' => 'Ngày nhập không hợp lệ'];

    $products = [];
    foreach (getAllProducts($branch) as $product) {
        $code = strtoupper(trim($product['code'] ?? ''));
        if ($code !== '') $products[$code] = $product;
    }

    $hash = hash_file('sha256', $file['tmp_name']);
    if (!$hash) return ['success' => false, 'message' => 'Không tạo được mã kiểm tra file'];
    $referenceNo = trim($post['reference_no'] ?? '');
    $bulkKey = hash('sha256', $hash . '|' . $importDate . '|' . mb_strtolower($referenceNo, 'UTF-8'));
    if (importBulkKeyExists($branch, $bulkKey)) return ['success' => false, 'message' => 'File này đã được nhập với cùng ngày và mã chứng từ. Hệ thống đã chặn nhập trùng tồn kho.'];

    $fp = fopen($file['tmp_name'], 'r');
    if (!$fp) return ['success' => false, 'message' => 'Không đọc được file CSV'];
    $firstLine = fgets($fp) ?: '';
    $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    rewind($fp);

    $grouped = [];
    $errors = [];
    $rowNo = 0;
    while (($row = fgetcsv($fp, 0, $delimiter)) !== false) {
        $rowNo++;
        if ($rowNo === 1) {
            if (isset($row[0])) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
            continue;
        }
        $row = array_pad($row, 4, '');
        if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;

        $code = strtoupper(trim((string)$row[0]));
        $qty = _parseMoneyNumber($row[1]);
        $price = _parseMoneyNumber($row[2]);
        $note = trim((string)$row[3]);
        $rowErrors = [];
        if ($code === '') $rowErrors[] = 'Thiếu Mã SP';
        if ($code !== '' && !isset($products[$code])) $rowErrors[] = 'Mã SP không tồn tại tại chi nhánh';
        if ($qty <= 0) $rowErrors[] = 'Số lượng phải lớn hơn 0';
        if ($price < 0) $rowErrors[] = 'Giá nhập không được âm';
        if ($rowErrors) {
            $errors[] = ['row' => $rowNo, 'code' => $code, 'qty' => $qty, 'price_in' => $price, 'note' => $note, 'errors' => $rowErrors];
            continue;
        }

        $product = $products[$code];
        if (!isset($grouped[$code])) {
            $grouped[$code] = [
                'row' => $rowNo, 'code' => $code, 'name' => $product['name'] ?? $code,
                'unit' => $product['unit'] ?? '', 'category' => $product['category_key'] ?? $product['category'] ?? '',
                'qty' => 0, 'price_in' => 0, 'note' => '', 'source_rows' => [],
            ];
        }
        $oldQty = (float)$grouped[$code]['qty'];
        $newQty = $oldQty + $qty;
        $grouped[$code]['price_in'] = $newQty > 0 ? (($oldQty * (float)$grouped[$code]['price_in']) + ($qty * $price)) / $newQty : 0;
        $grouped[$code]['qty'] = $newQty;
        $grouped[$code]['source_rows'][] = $rowNo;
        if ($note !== '' && !str_contains($grouped[$code]['note'], $note)) $grouped[$code]['note'] = trim($grouped[$code]['note'] . '; ' . $note, '; ');
    }
    fclose($fp);

    $valid = array_values($grouped);
    $total = array_sum(array_map(fn($item) => (float)$item['qty'] * (float)$item['price_in'], $valid));
    return ['success' => true, 'message' => 'Đã đọc file CSV', 'preview' => [
        'branch' => $branch, 'supplier' => trim($post['supplier'] ?? ''), 'reference_no' => $referenceNo,
        'import_date' => $importDate, 'note' => trim($post['note'] ?? ''), 'update_price' => !empty($post['update_price']),
        'file_name' => basename($file['name'] ?? 'import.csv'), 'file_hash' => $hash, 'bulk_key' => $bulkKey,
        'valid' => $valid, 'errors' => $errors, 'total_amount' => $total, 'created_at' => time(),
    ]];
}

function importBulkCommit(array $preview): array
{
    $branch = $preview['branch'] ?? '';
    $items = $preview['valid'] ?? [];
    if (!$branch || !canAccessBranch($branch)) return ['success' => false, 'message' => 'Không có quyền nhập hàng cho chi nhánh này'];
    if (!$items || !empty($preview['errors'])) return ['success' => false, 'message' => 'File còn lỗi hoặc không có sản phẩm hợp lệ'];
    if (time() - (int)($preview['created_at'] ?? 0) > 1800) return ['success' => false, 'message' => 'Preview đã hết hạn. Vui lòng tải lại file CSV.'];
    $hash = $preview['file_hash'] ?? '';
    $bulkKey = $preview['bulk_key'] ?? '';
    if (!$hash || !$bulkKey || importBulkKeyExists($branch, $bulkKey)) return ['success' => false, 'message' => 'File này đã được nhập hoặc preview không hợp lệ'];

    $productsByCode = [];
    foreach (getAllProducts($branch) as $product) $productsByCode[strtoupper(trim($product['code'] ?? ''))] = $product;
    foreach ($items as $item) {
        if (!isset($productsByCode[$item['code']]) || (float)$item['qty'] <= 0 || (float)$item['price_in'] < 0) {
            return ['success' => false, 'message' => "Dữ liệu mã '{$item['code']}' đã thay đổi. Vui lòng preview lại."];
        }
    }

    $productFiles = [];
    foreach ($items as $item) {
        $category = $item['category'] ?: ($productsByCode[$item['code']]['category_key'] ?? '');
        $file = getProductFile($branch, $category);
        if (!isset($productFiles[$file])) $productFiles[$file] = ['original' => readJson($file), 'updated' => readJson($file)];
    }
    foreach ($items as $item) {
        $category = $item['category'] ?: ($productsByCode[$item['code']]['category_key'] ?? '');
        $file = getProductFile($branch, $category);
        $found = false;
        foreach ($productFiles[$file]['updated'] as &$product) {
            if (strtoupper(trim($product['code'] ?? '')) === $item['code']) {
                $product['stock'] = (float)($product['stock'] ?? 0) + (float)$item['qty'];
                if (!empty($preview['update_price'])) $product['price_in'] = (float)$item['price_in'];
                $product['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        unset($product);
        if (!$found) return ['success' => false, 'message' => "Không cập nhật được tồn kho mã {$item['code']}"];
    }

    $date = $preview['import_date'] ?? date('Y-m-d');
    $ym = str_replace('-', '_', substr($date, 0, 7));
    $importFile = DATA_PATH . "/{$branch}/imports_{$ym}.json";
    $originalImports = readJson($importFile);
    $user = currentUser();
    $import = [
        'id' => 'IMP-' . branchCodePrefix(getBranchInfo($branch)['short'] ?? 'CN') . '-' . date('YmdHis') . '-' . random_int(100, 999),
        'branch' => $branch,
        'items' => array_map(fn($item) => [
            'product_code' => $item['code'], 'product_name' => $item['name'], 'unit' => $item['unit'],
            'category' => $item['category'], 'qty' => (float)$item['qty'], 'price_in' => (float)$item['price_in'],
            'line_total' => (float)$item['qty'] * (float)$item['price_in'], 'note' => $item['note'] ?? '',
        ], $items),
        'total_qty' => array_sum(array_column($items, 'qty')), 'total_amount' => (float)($preview['total_amount'] ?? 0),
        'import_date' => $date, 'supplier' => $preview['supplier'] ?? '', 'reference_no' => $preview['reference_no'] ?? '',
        'note' => $preview['note'] ?? '', 'bulk_hash' => $hash, 'bulk_key' => $bulkKey, 'bulk_file' => $preview['file_name'] ?? '', 'bulk' => true,
        'created_by' => $user['name'] ?? 'System', 'created_at' => date('Y-m-d H:i:s'),
    ];
    $newImports = $originalImports;
    $newImports[] = $import;

    $written = [];
    foreach ($productFiles as $file => $data) {
        if (!writeJson($file, array_values($data['updated']))) {
            foreach ($written as $writtenFile) writeJson($writtenFile, $productFiles[$writtenFile]['original']);
            return ['success' => false, 'message' => 'Không ghi được dữ liệu tồn kho; mọi thay đổi đã được hoàn tác'];
        }
        $written[] = $file;
    }
    if (!writeJson($importFile, $newImports)) {
        foreach ($written as $writtenFile) writeJson($writtenFile, $productFiles[$writtenFile]['original']);
        return ['success' => false, 'message' => 'Không ghi được lịch sử phiếu nhập; tồn kho đã được hoàn tác'];
    }

    return ['success' => true, 'message' => 'Nhập hàng loạt thành công', 'id' => $import['id'], 'count' => count($items), 'total' => $import['total_amount']];
}