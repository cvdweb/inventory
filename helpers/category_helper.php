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
    foreach ($cats as &$cat) {
        $cat['units'] = getCategoryUnitsFromData($branch, $cat);
        $cat['capabilities'] = getCategoryCapabilitiesFromData($branch, $cat);
    }
    unset($cat);
    usort($cats, fn($a, $b) => ($a['sort_order'] ?? 99) <=> ($b['sort_order'] ?? 99));
    if ($activeOnly) {
        $cats = array_values(array_filter($cats, fn($c) => $c['active'] ?? true));
    }
    return $cats;
}

function supportedCategoryCapabilities(): array
{
    return [
        'color_surcharge' => [
            'label' => 'Màu đặc biệt và phụ phí màu',
            'description' => 'Cho phép sản phẩm có nhiều màu với mức phụ phí riêng.',
        ],
    ];
}

function normalizeCategoryCapabilities(array|string|null $capabilities): array
{
    if (is_string($capabilities)) $capabilities = [$capabilities];
    if (!is_array($capabilities)) return [];
    $supported = supportedCategoryCapabilities();
    $result = [];
    foreach ($capabilities as $capability) {
        $capability = trim((string)$capability);
        if ($capability !== '' && isset($supported[$capability]) && !in_array($capability, $result, true)) {
            $result[] = $capability;
        }
    }
    return $result;
}

function getCategoryCapabilitiesFromData(string $branch, array $cat): array
{
    // Khi trường đã tồn tại, kể cả mảng rỗng, tôn trọng cấu hình người dùng.
    if (array_key_exists('capabilities', $cat)) {
        return normalizeCategoryCapabilities($cat['capabilities']);
    }

    // Tương thích dữ liệu cũ: tự nhận diện nhóm đang có sản phẩm dùng màu đặc biệt.
    $file = DATA_PATH . "/{$branch}/" . ($cat['file'] ?? '');
    foreach (readJson($file) as $product) {
        if (!empty($product['special_colors']) && is_array($product['special_colors'])) {
            return ['color_surcharge'];
        }
    }
    return [];
}

function categoryHasCapability(string $branch, string $key, string $capability): bool
{
    $category = getCategoryByKey($branch, $key);
    return $category && in_array($capability, $category['capabilities'] ?? [], true);
}

function categoryCapabilitiesFromPost(array $data): array
{
    return normalizeCategoryCapabilities($data['capabilities'] ?? []);
}

function normalizeCategoryUnits(array|string|null $units): array
{
    if (is_string($units)) {
        $units = preg_split('/[\r\n,]+/', $units) ?: [];
    }
    if (!is_array($units)) {
        $units = [];
    }

    $clean = [];
    foreach ($units as $unit) {
        $unit = trim((string)$unit);
        if ($unit === '') continue;
        if (!in_array($unit, $clean, true)) {
            $clean[] = $unit;
        }
    }
    return $clean;
}

function getCategoryUnitsFromData(string $branch, array $cat): array
{
    $units = normalizeCategoryUnits($cat['units'] ?? []);
    if (!empty($units)) {
        return $units;
    }

    $productUnits = [];
    $file = DATA_PATH . "/{$branch}/" . ($cat['file'] ?? '');
    foreach (readJson($file) as $product) {
        $unit = trim((string)($product['unit'] ?? ''));
        if ($unit !== '' && !in_array($unit, $productUnits, true)) {
            $productUnits[] = $unit;
        }
    }

    if (!empty($productUnits)) {
        return $productUnits;
    }
    return [defined('UNITS') && !empty(UNITS) ? UNITS[0] : 'cái'];
}

function getCategoryUnits(string $branch, string $key): array
{
    $cat = getCategoryByKey($branch, $key);
    return $cat ? ($cat['units'] ?? getCategoryUnitsFromData($branch, $cat)) : [];
}

function categoryUnitsFromPost(array $data): array
{
    $units = normalizeCategoryUnits($data['units'] ?? []);
    $extra = normalizeCategoryUnits($data['new_units'] ?? '');
    return normalizeCategoryUnits(array_merge($units, $extra));
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
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền quản lý nhóm hàng tại chi nhánh này'];
    }
    $file = DATA_PATH . "/{$branch}/categories.json";
    $cats = readJson($file);
    $isNew = empty($data['original_key']);
    $key   = slugify($data['name']);
    $units = categoryUnitsFromPost($data);
    $capabilities = categoryCapabilitiesFromPost($data);
    if (empty($units)) {
        return ['success' => false, 'message' => 'Nhóm hàng phải có ít nhất 1 đơn vị tính'];
    }

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
            'units'      => $units,
            'capabilities' => $capabilities,
            'sort_order' => (int)($data['sort_order'] ?? (count($cats) + 1)),
            'active'     => true,
        ];
        $cats[] = $newCat;
    } else {
        // Sửa — tìm theo original_key
        $found = false;
        foreach ($cats as &$c) {
            if ($c['key'] === $data['original_key']) {
                $oldCapabilities = getCategoryCapabilitiesFromData($branch, $c);
                if (in_array('color_surcharge', $oldCapabilities, true)
                    && !in_array('color_surcharge', $capabilities, true)) {
                    $productFile = DATA_PATH . "/{$branch}/" . ($c['file'] ?? '');
                    foreach (readJson($productFile) as $product) {
                        if (!empty($product['special_colors'])) {
                            return ['success' => false, 'message' => 'Không thể tắt màu đặc biệt vì nhóm vẫn còn sản phẩm có cấu hình màu. Hãy xóa màu khỏi các sản phẩm trước.'];
                        }
                    }
                }
                $c['name']       = trim($data['name']);
                $c['icon']       = $data['icon'] ?? ($c['icon'] ?? 'bi-box');
                $c['units']      = $units;
                $c['capabilities'] = $capabilities;
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
    if (!canAccessBranch($branch)) {
        return ['success' => false, 'message' => 'Không có quyền quản lý nhóm hàng tại chi nhánh này'];
    }
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
function getAllProductsDynamic(string $branch, bool $includeArchived = false): array
{
    $cats = getCategories($branch, $includeArchived ? false : true);
    $all  = [];
    foreach ($cats as $cat) {
        $file     = DATA_PATH . "/{$branch}/" . $cat['file'];
        $products = readJson($file);
        $supportsColorSurcharge = in_array('color_surcharge', $cat['capabilities'] ?? [], true);
        foreach ($products as $p) {
            $isArchived = ($p['active'] ?? true) === false || !empty($p['archived_at']);
            if (!$includeArchived && $isArchived) continue;
            $p['category_key']  = $cat['key'];
            $p['category_name'] = $cat['name'];
            if (!$supportsColorSurcharge) $p['special_colors'] = [];
            $all[] = $p;
        }
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
