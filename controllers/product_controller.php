<?php
// ============================================================
// PRODUCT CONTROLLER
// ============================================================

function _parseSpecialColors($raw): array
{
    if (is_array($raw)) $data = $raw;
    else $data = json_decode($raw ?: '[]', true) ?: [];
    $result = [];
    foreach ($data as $c) {
        $name = trim($c['name'] ?? '');
        if (!$name) continue;
        $type       = $c['surcharge_type'] ?? 'fixed';
        $surcharge  = max(0, floatval($c['surcharge'] ?? 0));
        $pct        = max(0, min(100, floatval($c['surcharge_pct'] ?? 0)));
        $result[] = [
            'name'           => $name,
            'code'           => trim($c['code'] ?? ''),
            'surcharge_type' => $type,
            'surcharge_pct'  => $pct,
            'surcharge'      => $surcharge, // luôn lưu ₫ đã tính để dùng khi lập HĐ
        ];
    }
    return $result;
}

function _parseMoneyNumber($value): float
{
    $value = trim((string)$value);
    if ($value === '') return 0;
    $value = preg_replace('/[^\d,.\-]/u', '', $value);
    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
        $value = str_replace('.', '', $value);
    } elseif (preg_match('/^\d{1,3}(,\d{3})+$/', $value)) {
        $value = str_replace(',', '', $value);
    } elseif (str_contains($value, ',')) {
        $value = str_replace(',', '.', $value);
    }
    return max(0, (float)$value);
}

function _parseBulkSpecialColors(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') return [];
    $items = preg_split('/[;\n]+/', $raw) ?: [];
    $colors = [];
    foreach ($items as $item) {
        $item = trim($item);
        if ($item === '') continue;
        $name = $item;
        $surcharge = 0;
        if (str_contains($item, ':')) {
            [$name, $fee] = array_map('trim', explode(':', $item, 2));
            $surcharge = _parseMoneyNumber($fee);
        }
        if ($name === '') continue;
        $colors[] = [
            'name'           => $name,
            'code'           => '',
            'surcharge_type' => 'fixed',
            'surcharge_pct'  => 0,
            'surcharge'      => $surcharge,
        ];
    }
    return $colors;
}

function productList(string $branch, string $category = '', string $search = '', bool $includeArchived = false): array
{
    if ($search) {
        $keyword = mb_strtolower(trim($search), 'UTF-8');
        return array_values(array_filter(getAllProducts($branch, $includeArchived), function ($p) use ($keyword) {
            return str_contains(mb_strtolower($p['code'] ?? '', 'UTF-8'), $keyword)
                || str_contains(mb_strtolower($p['name'] ?? '', 'UTF-8'), $keyword);
        }));
    }
    $catInfo = getCategoryByKey($branch, $category);
    if ($category && $catInfo) {
        $file  = getProductFile($branch, $category);
        $prods = readJson($file);
        $result = [];
        $supportsColorSurcharge = in_array('color_surcharge', $catInfo['capabilities'] ?? [], true);
        foreach ($prods as $p) {
            if (!$includeArchived && productIsArchived($p)) continue;
            $p['category_key']  = $category;
            $p['category_name'] = $catInfo['name'];
            if (!$supportsColorSurcharge) $p['special_colors'] = [];
            $result[] = $p;
        }
        return $result;
    }
    return getAllProducts($branch, $includeArchived);
}

function productSave(string $branch, string $category, array $data): array
{
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền quản lý sản phẩm tại chi nhánh này'];
    }
    // Validate nhóm hàng tồn tại
    $catInfo = getCategoryByKey($branch, $category);
    if (!$catInfo) {
        return ['success' => false, 'message' => "Nhóm hàng không hợp lệ: '{$category}'"];
    }

    // Validate các trường bắt buộc
    $code = trim($data['code'] ?? '');
    $name = trim($data['name'] ?? '');
    if (!$code) return ['success' => false, 'message' => 'Vui lòng nhập mã sản phẩm'];
    if (!$name) return ['success' => false, 'message' => 'Vui lòng nhập tên sản phẩm'];

    $unit = trim($data['unit'] ?? '');
    $allowedUnits = getCategoryUnits($branch, $category);
    if ($unit === '' || !in_array($unit, $allowedUnits, true)) {
        return ['success' => false, 'message' => 'Đơn vị tính không thuộc nhóm hàng đã chọn'];
    }

    $priceIn = _parseMoneyNumber($data['price_in'] ?? 0);
    $priceOut = _parseMoneyNumber($data['price_out'] ?? 0);
    $supportsColorSurcharge = categoryHasCapability($branch, $category, 'color_surcharge');
    $specialColors = $supportsColorSurcharge
        ? _parseSpecialColors($data['special_colors'] ?? '[]')
        : [];
    if ($priceOut <= 0) {
        return ['success' => false, 'message' => 'GiÃ¡ bÃ¡n pháº£i lá»›n hÆ¡n 0'];
    }

    $file     = getProductFile($branch, $category);
    $products = readJson($file);
    $isNew    = empty($data['id']);

    if ($isNew) {
        // Kiểm tra trùng mã trong toàn chi nhánh
        $allProds = getAllProducts($branch, true);
        foreach ($allProds as $existing) {
            if (strtoupper($existing['code']) === strtoupper($code)) {
                return ['success' => false, 'message' => "Mã sản phẩm '{$code}' đã tồn tại trong chi nhánh này"];
            }
        }

        $newProduct = [
            'id'             => uniqid('P'),
            'code'           => strtoupper($code),
            'name'           => $name,
            'unit'           => $unit,
            'price_in'       => $priceIn,
            'price_out'      => $priceOut,
            'stock'          => floatval($data['stock'] ?? 0),
            'min_stock'      => floatval($data['min_stock'] ?? 5),
            'special_colors' => $specialColors,
            'branch_id'      => $branch,
            'active'         => true,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
        $products[] = $newProduct;

    } else {
        // Sửa — tìm theo id
        $found = false;
        foreach ($products as &$p) {
            if ($p['id'] === $data['id']) {
                // Chỉ cập nhật các trường cho phép sửa
                // Tồn kho KHÔNG được sửa trực tiếp (phải qua nhập/xuất hàng)
                $p['code']            = strtoupper($code);
                $p['name']            = $name;
                $p['unit']            = $unit;
                $p['price_in']        = $priceIn;
                $p['price_out']       = $priceOut;
                $p['min_stock']       = floatval($data['min_stock'] ?? $p['min_stock']);
                $p['special_colors']  = $specialColors;
                $p['updated_at']      = date('Y-m-d H:i:s');
                // stock giữ nguyên — không ghi đè
                $found = true;
                break;
            }
        }

        // Nếu không tìm thấy trong file này, sản phẩm có thể thuộc nhóm khác
        if (!$found) {
            return ['success' => false, 'message' => 'Không tìm thấy sản phẩm trong nhóm này. Hãy chọn đúng nhóm hàng.'];
        }
    }

    $ok = writeJson($file, array_values($products));
    return $ok
        ? ['success' => true, 'message' => $isNew
            ? "Đã thêm sản phẩm '{$name}' vào nhóm '{$catInfo['name']}'"
            : "Đã cập nhật sản phẩm '{$name}'"]
        : ['success' => false, 'message' => 'Lỗi ghi file — vui lòng thử lại'];
}

function _csvProductHeaderKey(string $header): string
{
    $normalized = slugify(preg_replace('/^\xEF\xBB\xBF/', '', trim($header)) ?? '');
    $aliases = [
        'ma_sp' => 'code', 'ma_san_pham' => 'code', 'ma' => 'code',
        'ten_san_pham' => 'name', 'ten_sp' => 'name', 'ten_hang' => 'name',
        'don_vi' => 'unit', 'don_vi_tinh' => 'unit', 'dvt' => 'unit',
        'gia_nhap' => 'price_in', 'gia_von' => 'price_in',
        'gia_ban' => 'price_out',
        'ton_kho_ban_dau' => 'stock', 'ton_kho' => 'stock',
        'ton_kho_toi_thieu' => 'min_stock', 'muc_ton_toi_thieu' => 'min_stock',
        'mau_dac_biet' => 'special_colors', 'mau_phu_thu' => 'special_colors',
    ];
    return $aliases[$normalized] ?? '';
}

function _csvProductHeaderMap(array $headerRow): array
{
    $map = [];
    foreach ($headerRow as $index => $header) {
        $key = _csvProductHeaderKey((string)$header);
        if ($key !== '' && !isset($map[$key])) $map[$key] = $index;
    }
    return $map;
}

function _csvProductValue(array $row, array $map, string $key, string $default = ''): string
{
    if (!array_key_exists($key, $map)) return $default;
    return trim((string)($row[$map[$key]] ?? $default));
}

function productBulkTemplate(string $branch, string $category): void
{
    if (!canAccessBranch($branch)) {
        http_response_code(403);
        exit('Không có quyền truy cập chi nhánh này');
    }
    $catInfo = getCategoryByKey($branch, $category);
    if (!$catInfo) {
        http_response_code(404);
        echo 'Nhóm hàng không hợp lệ';
        exit;
    }
    $filename = 'mau_nhap_san_pham_' . $category . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    $supportsColorSurcharge = categoryHasCapability($branch, $category, 'color_surcharge');
    $headers = ['Mã SP', 'Tên sản phẩm', 'Đơn vị', 'Giá nhập', 'Giá bán', 'Tồn kho ban đầu', 'Tồn kho tối thiểu'];
    $sample = ['VD001', 'Tên sản phẩm mẫu', '', '100000', '120000', '10', '2'];
    if ($supportsColorSurcharge) {
        $headers[] = 'Màu đặc biệt';
        $sample[] = 'Đỏ:+20000; Xanh:+15000';
    }
    fputcsv($out, $headers);
    $units = getCategoryUnits($branch, $category);
    $sample[2] = $units[0] ?? 'cái';
    fputcsv($out, $sample);
    fclose($out);
    exit;
}

function productBulkPreview(string $branch, string $category, array $file): array
{
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền quản lý sản phẩm tại chi nhánh này'];
    }
    $catInfo = getCategoryByKey($branch, $category);
    if (!$catInfo) {
        return ['success' => false, 'message' => 'Nhóm hàng không hợp lệ'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Vui lòng chọn file CSV'];
    }
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        return ['success' => false, 'message' => 'Chỉ hỗ trợ file .csv ở phiên bản này'];
    }

    $allowedUnits = getCategoryUnits($branch, $category);
    $supportsColorSurcharge = categoryHasCapability($branch, $category, 'color_surcharge');
    $existingCodes = [];
    foreach (getAllProducts($branch, true) as $p) {
        $existingCodes[strtoupper(trim($p['code'] ?? ''))] = true;
    }

    $fp = fopen($file['tmp_name'], 'r');
    if (!$fp) {
        return ['success' => false, 'message' => 'Không đọc được file CSV'];
    }

    $valid = [];
    $errors = [];
    $seen = [];
    $firstLine = fgets($fp) ?: '';
    $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    rewind($fp);
    $headerRow = fgetcsv($fp, 0, $delimiter) ?: [];
    if (isset($headerRow[0])) $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headerRow[0]);
    $headerMap = _csvProductHeaderMap($headerRow);
    $requiredHeaders = ['code' => 'Mã SP', 'name' => 'Tên sản phẩm', 'unit' => 'Đơn vị', 'price_out' => 'Giá bán'];
    $missingHeaders = [];
    foreach ($requiredHeaders as $key => $label) {
        if (!isset($headerMap[$key])) $missingHeaders[] = $label;
    }
    if ($missingHeaders) {
        fclose($fp);
        return ['success' => false, 'message' => 'File CSV thiếu cột bắt buộc: ' . implode(', ', $missingHeaders)];
    }
    $warnings = [];
    if (!$supportsColorSurcharge && isset($headerMap['special_colors'])) {
        $warnings[] = 'Cột Màu đặc biệt được bỏ qua vì nhóm hàng chưa bật tính năng màu và phụ phí.';
    }

    $rowNo = 1;
    while (($row = fgetcsv($fp, 0, $delimiter)) !== false) {
        $rowNo++;
        if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }

        $code = strtoupper(_csvProductValue($row, $headerMap, 'code'));
        $name = _csvProductValue($row, $headerMap, 'name');
        $unit = _csvProductValue($row, $headerMap, 'unit');
        $priceIn = _parseMoneyNumber(_csvProductValue($row, $headerMap, 'price_in', '0'));
        $priceOut = _parseMoneyNumber(_csvProductValue($row, $headerMap, 'price_out', '0'));
        $stock = _parseMoneyNumber(_csvProductValue($row, $headerMap, 'stock', '0'));
        $minStockRaw = _csvProductValue($row, $headerMap, 'min_stock', '5');
        $minStock = _parseMoneyNumber($minStockRaw === '' ? 5 : $minStockRaw);
        $specialColors = $supportsColorSurcharge
            ? _parseBulkSpecialColors(_csvProductValue($row, $headerMap, 'special_colors'))
            : [];

        $rowErrors = [];
        if ($code === '') $rowErrors[] = 'Thiếu Mã SP';
        if ($name === '') $rowErrors[] = 'Thiếu Tên sản phẩm';
        if ($unit === '') $rowErrors[] = 'Thiếu Đơn vị';
        if ($unit !== '' && !in_array($unit, $allowedUnits, true)) {
            $rowErrors[] = "Đơn vị '{$unit}' không thuộc nhóm";
        }
        if (isset($existingCodes[$code])) $rowErrors[] = 'Mã SP đã tồn tại';
        if (isset($seen[$code])) $rowErrors[] = 'Mã SP bị trùng trong file';
        if ($priceOut <= 0) $rowErrors[] = 'Giá bán phải lớn hơn 0';

        $item = [
            'row'            => $rowNo,
            'code'           => $code,
            'name'           => $name,
            'unit'           => $unit,
            'price_in'       => $priceIn,
            'price_out'      => $priceOut,
            'stock'          => $stock,
            'min_stock'      => $minStock,
            'special_colors' => $specialColors,
        ];

        if ($rowErrors) {
            $errors[] = $item + ['errors' => $rowErrors];
        } else {
            $valid[] = $item;
            $seen[$code] = true;
        }
    }
    fclose($fp);

    return [
        'success' => true,
        'message' => 'Đã đọc file CSV',
        'preview' => [
            'branch'       => $branch,
            'category'     => $category,
            'categoryName' => $catInfo['name'],
            'valid'        => $valid,
            'errors'       => $errors,
            'warnings'     => $warnings,
            'capabilities' => $catInfo['capabilities'] ?? [],
            'created_at'   => time(),
        ],
    ];
}

function productBulkCommit(array $preview): array
{
    $branch = $preview['branch'] ?? '';
    $category = $preview['category'] ?? '';
    $valid = $preview['valid'] ?? [];
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền quản lý sản phẩm tại chi nhánh này'];
    }
    $catInfo = getCategoryByKey($branch, $category);
    if (!$catInfo) {
        return ['success' => false, 'message' => 'Preview không hợp lệ'];
    }
    if (empty($valid)) {
        return ['success' => false, 'message' => 'Không có dòng hợp lệ để nhập'];
    }

    $file = getProductFile($branch, $category);
    $products = readJson($file);
    $supportsColorSurcharge = categoryHasCapability($branch, $category, 'color_surcharge');
    $existingCodes = [];
    foreach (getAllProducts($branch, true) as $p) {
        $existingCodes[strtoupper(trim($p['code'] ?? ''))] = true;
    }

    $added = 0;
    $skipped = 0;
    foreach ($valid as $item) {
        if (isset($existingCodes[$item['code']])) {
            $skipped++;
            continue;
        }
        $products[] = [
            'id'             => uniqid('P'),
            'code'           => $item['code'],
            'name'           => $item['name'],
            'unit'           => $item['unit'],
            'price_in'       => (float)$item['price_in'],
            'price_out'      => (float)$item['price_out'],
            'stock'          => (float)$item['stock'],
            'min_stock'      => (float)$item['min_stock'],
            'special_colors' => $supportsColorSurcharge ? ($item['special_colors'] ?? []) : [],
            'branch_id'      => $branch,
            'active'         => true,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
        $existingCodes[$item['code']] = true;
        $added++;
    }

    $ok = writeJson($file, array_values($products));
    return $ok
        ? ['success' => true, 'message' => "Đã nhập {$added} sản phẩm vào nhóm '{$catInfo['name']}'" . ($skipped ? " ({$skipped} dòng bị bỏ qua do trùng mã)" : '')]
        : ['success' => false, 'message' => 'Lỗi ghi file sản phẩm'];
}

function productReferenceSummary(string $branch, string $productCode): array
{
    $invoiceCount = 0;
    foreach (getAllInvoiceFiles($branch) as $file) {
        foreach (readJson($file) as $document) {
            foreach ($document['items'] ?? [] as $item) {
                if (($item['product_code'] ?? $item['code'] ?? '') === $productCode) {
                    $invoiceCount++;
                    break;
                }
            }
        }
    }

    $importCount = 0;
    foreach (glob(DATA_PATH . "/{$branch}/imports_*.json") ?: [] as $file) {
        foreach (readJson($file) as $document) {
            foreach ($document['items'] ?? [] as $item) {
                if (($item['product_code'] ?? $item['code'] ?? '') === $productCode) {
                    $importCount++;
                    break;
                }
            }
        }
    }
    return ['invoices' => $invoiceCount, 'imports' => $importCount, 'total' => $invoiceCount + $importCount];
}

function productDelete(string $branch, string $category, string $productId, string $reason = ''): array
{
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền quản lý sản phẩm tại chi nhánh này'];
    }
    if (!$category) {
        // Tìm category nếu không được truyền
        $all = getAllProducts($branch, true);
        foreach ($all as $p) {
            if ($p['id'] === $productId) {
                $category = $p['category_key'];
                break;
            }
        }
    }
    $file     = getProductFile($branch, $category);
    $products = readJson($file);
    $index = null;
    foreach ($products as $i => $product) {
        if (($product['id'] ?? '') === $productId) { $index = $i; break; }
    }
    if ($index === null) {
        return ['success' => false, 'message' => 'Không tìm thấy sản phẩm để xóa'];
    }

    $product = $products[$index];
    $refs = productReferenceSummary($branch, $product['code'] ?? '');
    $stock = (float)($product['stock'] ?? 0);
    if ($refs['total'] > 0 || abs($stock) > 0.000001) {
        $products[$index]['active'] = false;
        $products[$index]['archived_at'] = date('Y-m-d H:i:s');
        $products[$index]['archived_by'] = currentUser()['username'] ?? 'system';
        $products[$index]['archive_reason'] = trim($reason) ?: 'Ngừng kinh doanh';
        $products[$index]['updated_at'] = date('Y-m-d H:i:s');
        $ok = writeJson($file, array_values($products));
        return $ok
            ? ['success' => true, 'message' => "Đã lưu trữ sản phẩm. Lịch sử {$refs['invoices']} hóa đơn, {$refs['imports']} phiếu nhập và tồn kho vẫn được giữ nguyên."]
            : ['success' => false, 'message' => 'Không thể lưu trữ sản phẩm'];
    }

    array_splice($products, $index, 1);

    $ok = writeJson($file, $products);
    return $ok
        ? ['success' => true, 'message' => 'Đã xóa sản phẩm chưa phát sinh dữ liệu']
        : ['success' => false, 'message' => 'Lỗi xóa sản phẩm'];
}

function productRestore(string $branch, string $category, string $productId): array
{
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền quản lý sản phẩm tại chi nhánh này'];
    }
    $file = getProductFile($branch, $category);
    $products = readJson($file);
    foreach ($products as &$product) {
        if (($product['id'] ?? '') !== $productId) continue;
        $product['active'] = true;
        unset($product['archived_at'], $product['archived_by'], $product['archive_reason']);
        $product['updated_at'] = date('Y-m-d H:i:s');
        return writeJson($file, array_values($products))
            ? ['success' => true, 'message' => 'Đã khôi phục sản phẩm']
            : ['success' => false, 'message' => 'Không thể khôi phục sản phẩm'];
    }
    return ['success' => false, 'message' => 'Không tìm thấy sản phẩm'];
}

function productGetByCode(string $branch, string $code, bool $includeArchived = false): ?array
{
    foreach (getAllProducts($branch, $includeArchived) as $p) {
        if (($p['code'] ?? '') === $code) return $p;
    }
    return null;
}
