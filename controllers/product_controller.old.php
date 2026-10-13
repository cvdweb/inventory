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
        $result[] = [
            'name'      => $name,
            'code'      => trim($c['code'] ?? ''),
            'surcharge' => max(0, floatval($c['surcharge'] ?? 0)),
        ];
    }
    return $result;
}

function productList(string $branch, string $category = '', string $search = ''): array
{
    if ($search) return searchProducts($branch, $search);
    $catInfo = getCategoryByKey($branch, $category);
    if ($category && $catInfo) {
        $file  = getProductFile($branch, $category);
        $prods = readJson($file);
        foreach ($prods as &$p) {
            $p['category_key']  = $category;
            $p['category_name'] = $catInfo['name'];
        }
        return array_values($prods);
    }
    return getAllProducts($branch);
}

function productSave(string $branch, string $category, array $data): array
{
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

    $file     = getProductFile($branch, $category);
    $products = readJson($file);
    $isNew    = empty($data['id']);

    if ($isNew) {
        // Kiểm tra trùng mã trong toàn chi nhánh
        $allProds = getAllProducts($branch);
        foreach ($allProds as $existing) {
            if (strtoupper($existing['code']) === strtoupper($code)) {
                return ['success' => false, 'message' => "Mã sản phẩm '{$code}' đã tồn tại trong chi nhánh này"];
            }
        }

        $newProduct = [
            'id'             => uniqid('P'),
            'code'           => strtoupper($code),
            'name'           => $name,
            'unit'           => $data['unit'] ?? 'cái',
            'price_in'       => floatval($data['price_in'] ?? 0),
            'price_out'      => floatval($data['price_out'] ?? 0),
            'stock'          => floatval($data['stock'] ?? 0),
            'min_stock'      => floatval($data['min_stock'] ?? 5),
            'special_colors' => _parseSpecialColors($data['special_colors'] ?? '[]'),
            'branch_id'      => $branch,
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
                $p['unit']            = $data['unit'] ?? $p['unit'];
                $p['price_in']        = floatval($data['price_in'] ?? $p['price_in']);
                $p['price_out']       = floatval($data['price_out'] ?? $p['price_out']);
                $p['min_stock']       = floatval($data['min_stock'] ?? $p['min_stock']);
                $p['special_colors']  = _parseSpecialColors($data['special_colors'] ?? '[]');
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

function productDelete(string $branch, string $category, string $productId): array
{
    if (!$category) {
        // Tìm category nếu không được truyền
        $all = getAllProducts($branch);
        foreach ($all as $p) {
            if ($p['id'] === $productId) {
                $category = $p['category_key'];
                break;
            }
        }
    }
    $file     = getProductFile($branch, $category);
    $products = readJson($file);
    $before   = count($products);
    $products = array_values(array_filter($products, fn($p) => $p['id'] !== $productId));

    if (count($products) === $before) {
        return ['success' => false, 'message' => 'Không tìm thấy sản phẩm để xóa'];
    }

    $ok = writeJson($file, $products);
    return $ok
        ? ['success' => true, 'message' => 'Đã xóa sản phẩm']
        : ['success' => false, 'message' => 'Lỗi xóa sản phẩm'];
}

function productGetByCode(string $branch, string $code): ?array
{
    foreach (getAllProducts($branch) as $p) {
        if (($p['code'] ?? '') === $code) return $p;
    }
    return null;
}
