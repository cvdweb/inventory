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
 * Phân tích chuỗi số tiền Việt Nam thành float an toàn.
 * Hỗ trợ:
 * - Dấu chấm phân cách hàng nghìn: "100.000", "1.500.000"
 * - Dấu phẩy phân cách hàng nghìn (kiểu Excel): "100,000", "1,500,000"
 * - Có kèm ký hiệu tiền tệ: "100.000 ₫", "150,000 đ", "1.000.000 VND"
 * - Viết tắt tiện lợi: "50k", "50K" -> 50000, "1.5tr", "1,5tr", "1.5m", "1.5M" -> 1500000, "2ty", "2 tỷ" -> 2000000000
 * - Thập phân không mong muốn từ Excel (ví dụ "100.000,00" hoặc "100,000.00") -> 100000
 */
function parseVnMoney($value): float
{
    if ($value === null || $value === '') return 0.0;
    if (is_int($value) || is_float($value)) return max(0.0, (float)$value);

    $str = trim((string)$value);
    if ($str === '') return 0.0;

    // Chuẩn hóa chữ thường không dấu và ký tự trắng
    $clean = mb_strtolower($str, 'UTF-8');
    $clean = str_replace(['₫', 'đ', 'vnd', 'vnđ', ' '], '', $clean);

    // Kiểm tra các hậu tố viết tắt (k, tr, trieu, m, b, ty)
    $multiplier = 1.0;
    if (preg_match('/(ty|tỷ|b)$/u', $clean)) {
        $multiplier = 1000000000.0;
        $clean = preg_replace('/(ty|tỷ|b)$/u', '', $clean);
    } elseif (preg_match('/(tr|trieu|triệu|m)$/u', $clean)) {
        $multiplier = 1000000.0;
        $clean = preg_replace('/(tr|trieu|triệu|m)$/u', '', $clean);
    } elseif (preg_match('/(k|nghin|nghìn)$/u', $clean)) {
        $multiplier = 1000.0;
        $clean = preg_replace('/(k|nghin|nghìn)$/u', '', $clean);
    }

    $clean = preg_replace('/[^\d,.\-]/u', '', $clean);
    if ($clean === '' || $clean === '-') return 0.0;

    // Xử lý dấu phân cách
    if (str_contains($clean, '.') && str_contains($clean, ',')) {
        $lastDot = strrpos($clean, '.');
        $lastComma = strrpos($clean, ',');
        if ($lastComma > $lastDot) {
            // Chuẩn VN: 1.000.000,00 -> . là nghìn, , là thập phân
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else {
            // Chuẩn US: 1,000,000.00 -> , là nghìn, . là thập phân
            $clean = str_replace(',', '', $clean);
        }
    } elseif (str_contains($clean, '.')) {
        // Chỉ có dấu chấm
        if ($multiplier > 1.0) {
            // ví dụ 1.5tr -> 1.5 * 1000000 = 1500000
        } else {
            // Tiền VNĐ thông thường: dấu chấm luôn là phân cách hàng nghìn (10.000, 1.500.000)
            $clean = str_replace('.', '', $clean);
        }
    } elseif (str_contains($clean, ',')) {
        // Chỉ có dấu phẩy
        if ($multiplier > 1.0) {
            // ví dụ 1,5tr -> 1.5 * 1000000 = 1500000
            $clean = str_replace(',', '.', $clean);
        } elseif (preg_match('/^\d{1,3}(,\d{3})+$/', $clean)) {
            // Dấu phẩy kiểu US thousand separator: 10,000 hoặc 1,000,000
            $clean = str_replace(',', '', $clean);
        } else {
            // Dấu phẩy thập phân (ví dụ 10000,50)
            $clean = str_replace(',', '.', $clean);
        }
    }

    $num = (float)$clean * $multiplier;
    return max(0.0, round($num));
}

/**
 * Phân tích số lượng / tồn kho / kích thước (có hỗ trợ số thập phân m2, kg...).
 * Hỗ trợ: "1,5", "1.5", "1000", "1.000", "10.000", "0,25"
 */
function parseVnQuantity($value): float
{
    if ($value === null || $value === '') return 0.0;
    if (is_int($value) || is_float($value)) return (float)$value;

    $str = trim((string)$value);
    if ($str === '') return 0.0;

    $clean = preg_replace('/[^\d,.\-]/u', '', $str);
    if ($clean === '' || $clean === '-') return 0.0;

    if (str_contains($clean, '.') && str_contains($clean, ',')) {
        $lastDot = strrpos($clean, '.');
        $lastComma = strrpos($clean, ',');
        if ($lastComma > $lastDot) {
            // 1.000,5 -> 1000.5
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else {
            // 1,000.5 -> 1000.5
            $clean = str_replace(',', '', $clean);
        }
    } elseif (str_contains($clean, ',')) {
        if (preg_match('/^\d{1,3}(,\d{3})+$/', $clean)) {
            // 1,000 -> 1000
            $clean = str_replace(',', '', $clean);
        } else {
            // 1,5 -> 1.5
            $clean = str_replace(',', '.', $clean);
        }
    } elseif (str_contains($clean, '.')) {
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $clean)) {
            // 1.000 hoặc 10.000 -> 1000 hoặc 10000
            $clean = str_replace('.', '', $clean);
        } else {
            // 1.5 hoặc 0.25 -> 1.5 hoặc 0.25
        }
    }

    return (float)$clean;
}

/**
 * Định dạng số lượng theo phong cách Việt Nam (không số 0 thừa).
 * Ví dụ: 1000 -> "1.000", 1.5 -> "1,5", 1000.5 -> "1.000,5"
 */
function formatVnQuantity(float $qty, string $unit = ''): string
{
    $formatted = number_format($qty, 3, ',', '.');
    if (str_contains($formatted, ',')) {
        $formatted = rtrim(rtrim($formatted, '0'), ',');
    }
    return $unit !== '' ? ($formatted . ' ' . $unit) : $formatted;
}

/**
 * Đọc số tiền ra chữ tiếng Việt
 * Ví dụ: 1500000 -> "Một triệu năm trăm nghìn đồng"
 */
function vnMoneyToWords(float $amount): string
{
    $amount = round($amount);
    if ($amount <= 0) return 'Không đồng';

    $digits = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    $units = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];

    $readThreeDigits = function(int $n, bool $hasHigherGroup) use ($digits): string {
        $h = intdiv($n, 100);
        $t = intdiv($n % 100, 10);
        $u = $n % 10;
        $res = '';

        if ($h > 0 || $hasHigherGroup) {
            $res .= $digits[$h] . ' trăm ';
        }

        if ($t === 0) {
            if ($u > 0 && ($h > 0 || $hasHigherGroup)) {
                $res .= 'lẻ ' . $digits[$u] . ' ';
            } elseif ($u > 0) {
                $res .= $digits[$u] . ' ';
            }
        } elseif ($t === 1) {
            $res .= 'mười ';
            if ($u === 5) $res .= 'lăm ';
            elseif ($u > 0) $res .= $digits[$u] . ' ';
        } else {
            $res .= $digits[$t] . ' mươi ';
            if ($u === 1) $res .= 'mốt ';
            elseif ($u === 4 && $t > 1) $res .= 'tư ';
            elseif ($u === 5) $res .= 'lăm ';
            elseif ($u > 0) $res .= $digits[$u] . ' ';
        }
        return trim($res);
    };

    $groups = [];
    $temp = (int)$amount;
    while ($temp > 0) {
        $groups[] = $temp % 1000;
        $temp = intdiv($temp, 1000);
    }

    $parts = [];
    $groupCount = count($groups);
    for ($i = $groupCount - 1; $i >= 0; $i--) {
        $val = $groups[$i];
        if ($val === 0) continue;
        $hasHigher = ($i < $groupCount - 1);
        $strGroup = $readThreeDigits($val, $hasHigher);
        if ($strGroup !== '') {
            $unit = $units[$i] ?? '';
            $parts[] = $unit !== '' ? ($strGroup . ' ' . $unit) : $strGroup;
        }
    }

    $result = implode(' ', $parts) . ' đồng';
    $result = preg_replace('/\s+/', ' ', trim($result));
    return mb_strtoupper(mb_substr($result, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($result, 1, null, 'UTF-8');
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
