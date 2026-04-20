<?php
// ============================================================
// HÀM XỬ LÝ JSON AN TOÀN VỚI FLOCK
// ============================================================

/**
 * Đọc file JSON an toàn
 */
function readJson(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }
    $fp = fopen($file, 'r');
    if (!$fp) return [];

    flock($fp, LOCK_SH); // Khóa đọc
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if (empty($content)) return [];
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * Ghi file JSON an toàn
 */
function writeJson(string $file, array $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fp = fopen($file, 'c+');
    if (!$fp) return false;

    flock($fp, LOCK_EX); // Khóa ghi độc quyền
    ftruncate($fp, 0);
    rewind($fp);
    $written = fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $written !== false;
}

/**
 * Lấy đường dẫn file sản phẩm theo chi nhánh + nhóm (dùng categories.json động)
 */
function getProductFile(string $branch, string $category): string
{
    $cat = getCategoryByKey($branch, $category);
    $filename = $cat['file'] ?? "products_{$category}.json";
    return DATA_PATH . "/{$branch}/{$filename}";
}

/**
 * Lấy tất cả sản phẩm của 1 chi nhánh (dùng categories.json động)
 */
function getAllProducts(string $branch): array
{
    return getAllProductsDynamic($branch);
}

/**
 * Lấy sản phẩm theo mã hoặc tên
 */
function searchProducts(string $branch, string $keyword): array
{
    $all = getAllProducts($branch);
    $keyword = mb_strtolower(trim($keyword), 'UTF-8');
    return array_values(array_filter($all, function($p) use ($keyword) {
        return str_contains(mb_strtolower($p['code'] ?? '', 'UTF-8'), $keyword)
            || str_contains(mb_strtolower($p['name'] ?? '', 'UTF-8'), $keyword);
    }));
}

/**
 * Cập nhật tồn kho (an toàn với flock)
 * $type: 'in' = nhập, 'out' = xuất
 */
function updateStock(string $branch, string $productCode, float $qty, string $type): bool
{
    $categories = getCategories($branch, true);
    foreach ($categories as $catInfo) {
        $file = DATA_PATH . "/{$branch}/" . $catInfo['file'];
        if (!file_exists($file)) continue;

        $fp = fopen($file, 'c+');
        if (!$fp) continue;

        flock($fp, LOCK_EX);
        $content = stream_get_contents($fp);
        $products = json_decode($content ?: '[]', true) ?: [];

        $found = false;
        foreach ($products as &$p) {
            if ($p['code'] === $productCode) {
                if ($type === 'in') {
                    $p['stock'] = ($p['stock'] ?? 0) + $qty;
                } else {
                    $p['stock'] = max(0, ($p['stock'] ?? 0) - $qty);
                }
                $p['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }

        if ($found) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($fp);
        }
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($found) return true;
    }
    return false;
}

/**
 * Lưu phiếu nhập hàng
 */
function createImport(string $branch, array $importData): array
{
    $yearMonth = date('Y_m');
    $file = DATA_PATH . "/{$branch}/imports_{$yearMonth}.json";

    $fp = fopen($file, 'c+');
    if (!$fp) return ['success' => false, 'message' => 'Không thể mở file'];

    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $imports = json_decode($content ?: '[]', true) ?: [];

    $importData['id']         = 'IMP-' . strtoupper($branch[7] ?? 'X') . '-' . date('YmdHis') . '-' . rand(100, 999);
    $importData['created_at'] = date('Y-m-d H:i:s');
    $imports[]                = $importData;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($imports, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return ['success' => true, 'id' => $importData['id']];
}

/**
 * Lưu hóa đơn bán hàng
 */
function createInvoice(string $branch, array $invoiceData): array
{
    $yearMonth = date('Y_m');
    $file = DATA_PATH . "/{$branch}/invoices_{$yearMonth}.json";

    $fp = fopen($file, 'c+');
    if (!$fp) return ['success' => false, 'message' => 'Không thể mở file'];

    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $invoices = json_decode($content ?: '[]', true) ?: [];

    $prefix = ($branch === 'branch_1_vlxd') ? 'VLXD' : 'MT';
    $invoiceData['id']         = "INV-{$prefix}-" . date('YmdHis') . '-' . rand(100, 999);
    $invoiceData['created_at'] = date('Y-m-d H:i:s');
    $invoiceData['branch']     = $branch;

    // Tính tổng
    $total = 0;
    foreach ($invoiceData['items'] as $item) {
        $total += $item['line_total'];
    }
    $invoiceData['total'] = $total;
    $invoices[]           = $invoiceData;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($invoices, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return ['success' => true, 'id' => $invoiceData['id'], 'total' => $total];
}

/**
 * Lấy hóa đơn theo tháng
 */
function getInvoices(string $branch, string $yearMonth = ''): array
{
    if (!$yearMonth) $yearMonth = date('Y_m');
    $file = DATA_PATH . "/{$branch}/invoices_{$yearMonth}.json";
    return readJson($file);
}

/**
 * Lấy phiếu nhập theo tháng
 */
function getImports(string $branch, string $yearMonth = ''): array
{
    if (!$yearMonth) $yearMonth = date('Y_m');
    $file = DATA_PATH . "/{$branch}/imports_{$yearMonth}.json";
    return readJson($file);
}

/**
 * Thống kê dashboard
 */
function getDashboardStats(string $branch): array
{
    $today    = date('Y-m-d');
    $invoices = getInvoices($branch);
    $products = getAllProducts($branch);

    $todayOrders  = 0;
    $todayRevenue = 0;
    foreach ($invoices as $inv) {
        if (str_starts_with($inv['created_at'] ?? '', $today)) {
            $todayOrders++;
            $todayRevenue += $inv['total'] ?? 0;
        }
    }

    $lowStock = array_filter($products, fn($p) => ($p['stock'] ?? 0) < ($p['min_stock'] ?? 5));
    $totalStock = array_sum(array_column($products, 'stock'));

    return [
        'today_orders'   => $todayOrders,
        'today_revenue'  => $todayRevenue,
        'low_stock'      => count($lowStock),
        'total_stock'    => $totalStock,
        'total_products' => count($products),
        'low_stock_list' => array_values($lowStock),
    ];
}

/**
 * Format tiền VND
 */
function formatMoney(float $amount): string
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

/**
 * Lấy danh sách khách hàng
 */
function getCustomers(string $branch): array
{
    return readJson(DATA_PATH . "/{$branch}/customers.json");
}

/**
 * Lưu khách hàng
 */
function saveCustomer(string $branch, array $customer): bool
{
    $file      = DATA_PATH . "/{$branch}/customers.json";
    $customers = readJson($file);
    $found     = false;
    foreach ($customers as &$c) {
        if ($c['id'] === $customer['id']) { $c = $customer; $found = true; break; }
    }
    if (!$found) {
        $customer['id'] = 'CUS-' . date('YmdHis');
        $customers[]    = $customer;
    }
    return writeJson($file, $customers);
}

/**
 * Lấy danh sách nhà cung cấp
 */
function getSuppliers(string $branch): array
{
    return readJson(DATA_PATH . "/{$branch}/suppliers.json");
}

/**
 * Lấy báo cáo doanh thu theo ngày trong tháng
 */
function getRevenueReport(string $branch, string $yearMonth = ''): array
{
    if (!$yearMonth) $yearMonth = date('Y_m');
    $invoices = getInvoices($branch, $yearMonth);
    $report   = [];
    foreach ($invoices as $inv) {
        $day = substr($inv['created_at'] ?? '', 0, 10);
        if (!isset($report[$day])) {
            $report[$day] = ['date' => $day, 'orders' => 0, 'revenue' => 0];
        }
        $report[$day]['orders']++;
        $report[$day]['revenue'] += $inv['total'] ?? 0;
    }
    ksort($report);
    return array_values($report);
}
