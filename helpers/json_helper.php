<?php
// ============================================================
// HÀM XỬ LÝ JSON AN TOÀN VỚI FLOCK
// ============================================================

function jsonLockDir(): string
{
    static $dir = null;
    if ($dir !== null) return $dir;
    $dir = rtrim(sys_get_temp_dir(), '/\\') . '/truongphu_locks_' . substr(sha1(BASE_PATH), 0, 12);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir;
}

function jsonLockHandle(string $scope)
{
    $path = jsonLockDir() . '/' . sha1($scope) . '.lock';
    return fopen($path, 'c+');
}

function readJsonUnlocked(string $file): array
{
    $previous = $file . '.previous';
    if (!is_file($file) && is_file($previous)) @copy($previous, $file);
    if (!is_file($file)) return [];

    $content = file_get_contents($file);
    if ($content === false || trim($content) === '') return [];
    $data = json_decode($content, true);
    if (is_array($data)) return $data;

    if (is_file($previous)) {
        $recovery = json_decode((string)file_get_contents($previous), true);
        if (is_array($recovery)) {
            @copy($previous, $file);
            return $recovery;
        }
    }
    error_log('JSON không hợp lệ: ' . $file . ' - ' . json_last_error_msg());
    return [];
}

function writeJsonUnlocked(string $file, array $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return false;

    try {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $suffix = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        error_log('Không mã hóa được JSON ' . $file . ': ' . $e->getMessage());
        return false;
    }

    $temp = $dir . '/.' . basename($file) . ".{$suffix}.tmp";
    $fp = @fopen($temp, 'xb');
    if (!$fp) return false;

    $length = strlen($json);
    $offset = 0;
    while ($offset < $length) {
        $written = fwrite($fp, substr($json, $offset));
        if ($written === false || $written === 0) {
            fclose($fp);
            @unlink($temp);
            return false;
        }
        $offset += $written;
    }
    fflush($fp);
    if (function_exists('fsync')) @fsync($fp);
    fclose($fp);
    @chmod($temp, is_file($file) ? (fileperms($file) & 0777) : 0640);

    // Unix cho phép rename đè file và đây là thao tác nguyên tử.
    if (DIRECTORY_SEPARATOR !== '\\' || !is_file($file)) {
        if (@rename($temp, $file)) return true;
        @unlink($temp);
        return false;
    }

    // Windows không rename đè file: giữ bản cũ để luôn có thể phục hồi.
    $previous = $file . '.previous';
    @unlink($previous);
    if (!@rename($file, $previous)) {
        @unlink($temp);
        return false;
    }
    if (@rename($temp, $file)) {
        @unlink($previous);
        return true;
    }
    @rename($previous, $file);
    @unlink($temp);
    return false;
}

/** Đọc JSON dưới khóa chia sẻ tương thích với cơ chế thay file nguyên tử. */
function readJson(string $file): array
{
    $lock = jsonLockHandle('file:' . $file);
    if (!$lock) return readJsonUnlocked($file);
    flock($lock, LOCK_SH);
    try {
        return readJsonUnlocked($file);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Ghi JSON nguyên tử; file thật chỉ được thay sau khi file tạm đã ghi hoàn tất. */
function writeJson(string $file, array $data): bool
{
    $lock = jsonLockHandle('file:' . $file);
    if (!$lock) return false;
    flock($lock, LOCK_EX);
    try {
        return writeJsonUnlocked($file, $data);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Cập nhật read-modify-write dưới cùng một khóa để không làm mất thay đổi đồng thời. */
function updateJson(string $file, callable $mutator): bool
{
    $lock = jsonLockHandle('file:' . $file);
    if (!$lock) return false;
    flock($lock, LOCK_EX);
    try {
        $updated = $mutator(readJsonUnlocked($file));
        return is_array($updated) && writeJsonUnlocked($file, $updated);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Khóa giao dịch cấp chi nhánh, có thể gọi lồng nhau trong cùng request. */
function withBranchTransaction(string $branch, callable $operation)
{
    static $active = [];
    $branch = trim($branch);
    if ($branch === '' || isset($active[$branch])) return $operation();

    $lock = jsonLockHandle('branch:' . DATA_PATH . '/' . $branch);
    if (!$lock) throw new RuntimeException('Không tạo được khóa giao dịch chi nhánh');
    flock($lock, LOCK_EX);
    $active[$branch] = true;
    try {
        return $operation();
    } finally {
        unset($active[$branch]);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
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
function getAllProducts(string $branch, bool $includeArchived = false): array
{
    return getAllProductsDynamic($branch, $includeArchived);
}

function productIsArchived(array $product): bool
{
    return ($product['active'] ?? true) === false || !empty($product['archived_at']);
}

function invoiceIsCancelled(array $invoice): bool
{
    return ($invoice['status'] ?? 'active') === 'cancelled' || !empty($invoice['cancelled_at']);
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
    // Cần tìm cả sản phẩm/nhóm đã lưu trữ để hoàn tồn khi hủy chứng từ cũ.
    $categories = getCategories($branch, false);
    foreach ($categories as $catInfo) {
        $file = DATA_PATH . "/{$branch}/" . $catInfo['file'];
        if (!file_exists($file)) continue;

        $found = false;
        $saved = updateJson($file, function (array $products) use ($productCode, $qty, $type, &$found): array {
            foreach ($products as &$product) {
                if (($product['code'] ?? '') === $productCode) {
                    if ($type === 'in') {
                        $product['stock'] = ($product['stock'] ?? 0) + $qty;
                    } else {
                        $product['stock'] = max(0, ($product['stock'] ?? 0) - $qty);
                    }
                    $product['updated_at'] = date('Y-m-d H:i:s');
                    $found = true;
                    break;
                }
            }
            unset($product);
            return $products;
        });

        if ($found) return $saved;
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

    $branchShort = getBranchInfo($branch)['short'] ?? strtoupper($branch[7] ?? 'X');
    $importData['id']         = 'IMP-' . branchCodePrefix($branchShort) . '-' . date('YmdHis') . '-' . rand(100, 999);
    $importData['created_at'] = date('Y-m-d H:i:s');
    $saved = updateJson($file, function (array $imports) use ($importData): array {
        $imports[] = $importData;
        return $imports;
    });
    if (!$saved) return ['success' => false, 'message' => 'Không thể lưu phiếu nhập'];

    return ['success' => true, 'id' => $importData['id']];
}

/**
 * Lưu hóa đơn bán hàng
 */
function createInvoice(string $branch, array $invoiceData): array
{
    $yearMonth = date('Y_m');
    $file = DATA_PATH . "/{$branch}/invoices_{$yearMonth}.json";

    $prefix = branchCodePrefix(getBranchInfo($branch)['short'] ?? 'CN');
    $invoiceData['id']         = "INV-{$prefix}-" . date('YmdHis') . '-' . rand(100, 999);
    $invoiceData['created_at'] = date('Y-m-d H:i:s');
    $invoiceData['branch']     = $branch;
    $invoiceData['status']     = $invoiceData['status'] ?? 'active';

    // Tính tổng (cộng giá vận chuyển)
    $total = 0;
    foreach ($invoiceData['items'] as $item) {
        $total += $item['line_total'];
    }
    $shippingFee = $invoiceData['shipping_fee'] ?? 0;
    $total += $shippingFee;
    $invoiceData['total'] = $total;
    $saved = updateJson($file, function (array $invoices) use ($invoiceData): array {
        $invoices[] = $invoiceData;
        return $invoices;
    });
    if (!$saved) return ['success' => false, 'message' => 'Không thể lưu hóa đơn'];

    return ['success' => true, 'id' => $invoiceData['id'], 'total' => $total];
}

function branchCodePrefix(string $short): string
{
    $short = trim($short);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $short);
    if (is_string($converted) && $converted !== '') {
        $short = $converted;
    }
    $short = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $short) ?? '');
    return $short !== '' ? $short : 'CN';
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
    $allProducts = getAllProducts($branch, true);

    $todayOrders  = 0;
    $todayRevenue = 0;
    foreach ($invoices as $inv) {
        if (invoiceIsCancelled($inv)) continue;
        if (str_starts_with($inv['created_at'] ?? '', $today)) {
            $todayOrders++;
            $todayRevenue += $inv['total'] ?? 0;
        }
    }

    $lowStock = array_filter($products, fn($p) => ($p['stock'] ?? 0) < ($p['min_stock'] ?? 5));
    $totalStock = array_sum(array_column($allProducts, 'stock'));

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
        if (invoiceIsCancelled($inv)) continue;
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

/**
 * Lấy tất cả file hóa đơn của 1 chi nhánh (tất cả tháng/năm)
 */
function getAllInvoiceFiles(string $branch): array
{
    $pattern = DATA_PATH . "/{$branch}/invoices_*.json";
    $files   = glob($pattern) ?: [];
    // Sắp xếp mới nhất trước
    rsort($files);
    return $files;
}

/**
 * Tìm kiếm hóa đơn xuyên suốt tất cả tháng/năm
 * Tìm theo: tên khách, SĐT, mã SP, tên SP, mã hóa đơn
 */
function searchInvoices(array $branches, string $keyword, int $limit = 100): array
{
    if (empty(trim($keyword))) return [];

    $kw      = mb_strtolower(trim($keyword), 'UTF-8');
    $results = [];

    foreach ($branches as $branch) {
        foreach (getAllInvoiceFiles($branch) as $file) {
            // Lấy tháng từ tên file: invoices_2025_04.json → 2025_04
            preg_match('/invoices_(\d{4}_\d{2})\.json$/', $file, $m);
            $ym = $m[1] ?? '';

            $invoices = readJson($file);
            foreach ($invoices as $inv) {
                if (_invoiceMatchesKeyword($inv, $kw)) {
                    $inv['_branch']    = $branch;
                    $inv['_ym']        = $ym;
                    $inv['_branch_name'] = getBranchInfo($branch)['name'] ?? $branch;
                    $results[] = $inv;
                    if (count($results) >= $limit) break 3;
                }
            }
        }
    }

    // Sắp xếp mới nhất trước
    usort($results, fn($a, $b) =>
        strcmp($b['created_at'] ?? '', $a['created_at'] ?? '')
    );

    return $results;
}

/**
 * Kiểm tra hóa đơn có khớp keyword không
 */
function _invoiceMatchesKeyword(array $inv, string $kw): bool
{
    $kw = mb_strtolower($kw, 'UTF-8');

    // Tìm theo mã hóa đơn
    if (str_contains(mb_strtolower($inv['id'] ?? '', 'UTF-8'), $kw)) return true;

    // Tìm theo tên khách hàng
    if (str_contains(mb_strtolower($inv['customer'] ?? '', 'UTF-8'), $kw)) return true;

    // Tìm theo số điện thoại
    if (str_contains($inv['phone'] ?? '', $kw)) return true;

    // Tìm theo địa chỉ
    if (str_contains(mb_strtolower($inv['address'] ?? '', 'UTF-8'), $kw)) return true;

    // Tìm theo ghi chú
    if (str_contains(mb_strtolower($inv['note'] ?? '', 'UTF-8'), $kw)) return true;

    // Tìm theo sản phẩm trong hóa đơn (mã + tên)
    foreach ($inv['items'] ?? [] as $item) {
        if (str_contains(mb_strtolower($item['product_code'] ?? '', 'UTF-8'), $kw)) return true;
        if (str_contains(mb_strtolower($item['product_name'] ?? '', 'UTF-8'), $kw)) return true;
    }

    return false;
}
