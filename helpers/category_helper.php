<?php
// ============================================================
// HELPER: QUẢN LÝ NHÓM HÀNG ĐỘNG (categories.json)
// ============================================================

/**
 * Lấy danh sách nhóm hàng của 1 chi nhánh
 */
function getCategories(string $branch, bool $activeOnly = false): array
{
    $file = DATA_PATH . "/{$branch}/categories.json";
    $cats = readJson($file);
    usort($cats, fn($a, $b) => ($a['sort_order'] ?? 99) <=> ($b['sort_order'] ?? 99));
    if ($activeOnly) {
        $cats = array_values(array_filter($cats, fn($c) => $c['active'] ?? true));
    }
    return $cats;
}

/**
 * Lấy 1 nhóm theo key
 */
function getCategoryByKey(string $branch, string $key): ?array
{
    foreach (getCategories($branch) as $c) {
        if ($c['key'] === $key) return $c;
    }
    return null;
}

/**
 * Lưu nhóm (thêm hoặc sửa)
 */
function saveCategory(string $branch, array $data): array
{
    $file = DATA_PATH . "/{$branch}/categories.json";
    $cats = readJson($file);
    $isNew = empty($data['original_key']);
    $key   = slugify($data['name']);

    // Khi thêm mới: kiểm tra trùng key
    if ($isNew) {
        foreach ($cats as $c) {
            if ($c['key'] === $key) {
                return ['success' => false, 'message' => "Nhóm hàng '{$data['name']}' đã tồn tại"];
            }
        }
        $newCat = [
            'key'        => $key,
            'name'       => trim($data['name']),
            'file'       => 'products_' . $key . '.json',
            'icon'       => $data['icon'] ?? 'bi-box',
            'sort_order' => (int)($data['sort_order'] ?? (count($cats) + 1)),
            'active'     => true,
        ];
        $cats[] = $newCat;
    } else {
        // Sửa — tìm theo original_key
        $found = false;
        foreach ($cats as &$c) {
            if ($c['key'] === $data['original_key']) {
                $c['name']       = trim($data['name']);
                $c['icon']       = $data['icon'] ?? $c['icon'];
                $c['sort_order'] = (int)($data['sort_order'] ?? $c['sort_order']);
                $c['active']     = isset($data['active']) ? (bool)$data['active'] : $c['active'];
                $found = true;
                break;
            }
        }
        if (!$found) return ['success' => false, 'message' => 'Không tìm thấy nhóm hàng'];
    }

    usort($cats, fn($a, $b) => ($a['sort_order'] ?? 99) <=> ($b['sort_order'] ?? 99));
    $ok = writeJson($file, $cats);
    return $ok
        ? ['success' => true, 'message' => $isNew ? "Đã thêm nhóm '{$data['name']}'" : "Đã cập nhật nhóm '{$data['name']}'"]
        : ['success' => false, 'message' => 'Lỗi ghi file'];
}

/**
 * Xóa nhóm — chỉ cho phép khi không còn sản phẩm
 */
function deleteCategory(string $branch, string $key): array
{
    $file = DATA_PATH . "/{$branch}/categories.json";
    $cats = readJson($file);

    $cat = null;
    foreach ($cats as $c) {
        if ($c['key'] === $key) { $cat = $c; break; }
    }
    if (!$cat) return ['success' => false, 'message' => 'Không tìm thấy nhóm hàng'];

    // Kiểm tra còn sản phẩm không
    $prodFile = DATA_PATH . "/{$branch}/" . $cat['file'];
    $products = readJson($prodFile);
    if (!empty($products)) {
        return ['success' => false, 'message' => "Không thể xóa — nhóm '{$cat['name']}' còn " . count($products) . " sản phẩm. Hãy xóa hoặc chuyển sản phẩm trước."];
    }

    // Xóa file sản phẩm rỗng nếu tồn tại
    if (file_exists($prodFile)) @unlink($prodFile);

    $cats = array_values(array_filter($cats, fn($c) => $c['key'] !== $key));
    $ok   = writeJson($file, $cats);
    return $ok
        ? ['success' => true, 'message' => "Đã xóa nhóm '{$cat['name']}'"]
        : ['success' => false, 'message' => 'Lỗi ghi file'];
}

/**
 * Tạo slug từ tên tiếng Việt
 */
function slugify(string $str): string
{
    $str = mb_strtolower(trim($str), 'UTF-8');
    $map = [
        'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a',
        'ă'=>'a','ắ'=>'a','ặ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'â'=>'a','ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
        'đ'=>'d',
        'è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e',
        'ê'=>'e','ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
        'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
        'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o',
        'ô'=>'o','ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
        'ơ'=>'o','ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
        'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u',
        'ư'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
        'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
    ];
    $str = strtr($str, $map);
    $str = preg_replace('/[^a-z0-9]+/', '_', $str);
    return trim($str, '_');
}

/**
 * Lấy tất cả sản phẩm của chi nhánh — dùng categories.json động
 */
function getAllProductsDynamic(string $branch): array
{
    $cats = getCategories($branch, true);
    $all  = [];
    foreach ($cats as $cat) {
        $file     = DATA_PATH . "/{$branch}/" . $cat['file'];
        $products = readJson($file);
        foreach ($products as &$p) {
            $p['category_key']  = $cat['key'];
            $p['category_name'] = $cat['name'];
        }
        $all = array_merge($all, $products);
    }
    return $all;
}

// Các icon Bootstrap phổ biến cho nhóm hàng
define('CAT_ICONS', [
    'bi-box'             => 'Hộp',
    'bi-bag-fill'        => 'Túi',
    'bi-bricks'          => 'Gạch',
    'bi-tools'           => 'Công cụ',
    'bi-gear-fill'       => 'Bánh răng',
    'bi-house-fill'      => 'Nhà',
    'bi-rulers'          => 'Thước',
    'bi-bucket'          => 'Xô',
    'bi-lightning-fill'  => 'Điện',
    'bi-droplet-fill'    => 'Nước',
    'bi-truck'           => 'Xe tải',
    'bi-archive-fill'    => 'Kho',
    'bi-layers-fill'     => 'Lớp',
    'bi-wrench-adjustable'=> 'Cờ lê',
    'bi-hammer'          => 'Búa',
    'bi-scissors'        => 'Kéo',
    'bi-paint-bucket'    => 'Sơn',
    'bi-window'          => 'Cửa sổ',
    'bi-grid-fill'       => 'Lưới',
    'bi-stars'           => 'Khác',
]);
