<?php
declare(strict_types=1);

session_start();
$authAttemptsTestFile = sys_get_temp_dir() . '/truongphu_auth_test_' . bin2hex(random_bytes(5)) . '.json';
$featureSettingsTestFile = sys_get_temp_dir() . '/truongphu_feature_test_' . bin2hex(random_bytes(5)) . '.json';
putenv('TRUONGPHU_AUTH_ATTEMPTS_PATH=' . $authAttemptsTestFile);
putenv('TRUONGPHU_FEATURE_SETTINGS_PATH=' . $featureSettingsTestFile);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/helpers/json_helper.php';
require_once BASE_PATH . '/helpers/feature_helper.php';
require_once BASE_PATH . '/helpers/branch_helper.php';
require_once BASE_PATH . '/helpers/user_helper.php';
require_once BASE_PATH . '/helpers/category_helper.php';
require_once BASE_PATH . '/helpers/backup_helper.php';
require_once BASE_PATH . '/helpers/cashbook_helper.php';
require_once BASE_PATH . '/helpers/sales_return_helper.php';
require_once BASE_PATH . '/helpers/receivable_helper.php';
require_once BASE_PATH . '/helpers/inventory_adjustment_helper.php';
require_once BASE_PATH . '/helpers/report_helper.php';
require_once BASE_PATH . '/helpers/integrity_helper.php';
require_once BASE_PATH . '/controllers/auth_controller.php';
require_once BASE_PATH . '/controllers/product_controller.php';
require_once BASE_PATH . '/controllers/import_invoice_controller.php';

$_SESSION['user_info'] = ['username' => 'test', 'name' => 'Test', 'role' => 'superadmin'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.250';
for ($attempt = 0; $attempt < LOGIN_MAX_ATTEMPTS; $attempt++) authRecordFailure('rate_limit_test');
assertTrue(authThrottleStatus('rate_limit_test')['blocked'], 'Phải khóa đăng nhập sau số lần sai cho phép');
authClearFailures('rate_limit_test');
assertTrue(!authThrottleStatus('rate_limit_test')['blocked'], 'Phải mở khóa sau khi xóa lịch sử đăng nhập sai');
@unlink($authAttemptsTestFile);
$branch = '__integrity_test_' . bin2hex(random_bytes(4));
$dir = DATA_PATH . '/' . $branch;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

assertTrue(array_keys(ROLES) === ['superadmin', 'admin', 'employee'], 'Hệ thống phải chỉ có 3 vai trò');
assertTrue(currentFeatureProfile() === 'full', 'Thiếu cấu hình chức năng phải mặc định chế độ Đầy đủ');
assertTrue(!featureProfileHas('basic', 'receivables') && !featureProfileHas('basic', 'cashbook'), 'Chế độ Cơ bản không được bật nghiệp vụ nâng cao');
assertTrue(featureProfileHas('standard', 'receivables') && featureProfileHas('standard', 'inventory') && !featureProfileHas('standard', 'cashbook'), 'Chế độ Tiêu chuẩn phải có đúng nhóm chức năng');
assertTrue(featureProfileHas('full', 'cashbook') && featureProfileHas('full', 'integrity'), 'Chế độ Đầy đủ phải có toàn bộ chức năng');
assertTrue(featureSaveProfile('standard')['success'] && currentFeatureProfile()==='standard', 'Không lưu được chế độ Tiêu chuẩn');
assertTrue(count(featureGetSettings()['history']??[])===1, 'Đổi chế độ phải lưu lịch sử');
assertTrue(featureSaveProfile('full')['success'] && currentFeatureProfile()==='full', 'Không chuyển lại được chế độ Đầy đủ');
$backupRoot = realpath(backupDir());
$appRoot = realpath(BASE_PATH);
assertTrue(
    $backupRoot !== false && $appRoot !== false
        && $backupRoot !== $appRoot
        && !str_starts_with($backupRoot, $appRoot . DIRECTORY_SEPARATOR),
    'Kho backup phải nằm ngoài thư mục web của ứng dụng'
);
$_SESSION['user_info'] = ['username' => 'employee_test', 'name' => 'Nhân viên', 'role' => 'employee', 'branch' => [$branch]];
assertTrue(canAccessBranch($branch), 'Nhân viên phải truy cập được chi nhánh được gán');
assertTrue(!canAccessBranch($branch . '_other'), 'Nhân viên không được truy cập chi nhánh khác');
$_SESSION['user_info'] = ['username' => 'admin_test', 'name' => 'Chủ cửa hàng', 'role' => 'admin', 'branch' => null];
assertTrue(canAccessBranch($branch . '_other'), 'Admin phải truy cập được tất cả chi nhánh');
assertTrue(canManageTargetUser(['username' => 'employee_test', 'role' => 'employee']), 'Admin phải quản lý được nhân viên');
assertTrue(!canManageTargetUser(['username' => 'admin_other', 'role' => 'admin']), 'Admin không được quản lý admin khác');
assertTrue(!canManageTargetUser(['username' => 'root', 'role' => 'superadmin']), 'Admin không được quản lý superadmin');
$_SESSION['user_info'] = ['username' => 'test', 'name' => 'Test', 'role' => 'superadmin'];

function removeTestDirectory(string $dir): void
{
    $dataRoot = realpath(DATA_PATH);
    $resolvedParent = realpath(dirname($dir));
    if (!$dataRoot || $resolvedParent !== $dataRoot || !str_starts_with(basename($dir), '__integrity_test_')) return;
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
        $path = $dir . '/' . $name;
        if (is_file($path)) unlink($path);
    }
    rmdir($dir);
}

try {
    mkdir($dir, 0755, true);
    $atomicFile = $dir . '/atomic.json';
    assertTrue(writeJson($atomicFile, ['counter' => 1]), 'Không ghi được JSON nguyên tử');
    assertTrue(updateJson($atomicFile, fn(array $data) => ['counter' => (int)($data['counter'] ?? 0) + 1]), 'Không cập nhật được JSON dưới cùng khóa');
    assertTrue((readJson($atomicFile)['counter'] ?? 0) === 2, 'Kết quả cập nhật JSON nguyên tử không chính xác');
    assertTrue(empty(glob($dir . '/.*.tmp')) && !is_file($atomicFile . '.previous'), 'Không được để lại file tạm sau khi ghi thành công');
    $nestedTransaction = withBranchTransaction($branch, fn() => withBranchTransaction($branch, fn() => 'ok'));
    assertTrue($nestedTransaction === 'ok', 'Khóa giao dịch chi nhánh phải hỗ trợ gọi lồng nhau');
    writeJson($dir . '/categories.json', [
        ['key' => 'general', 'name' => 'Nhóm thử nghiệm', 'file' => 'products_general.json', 'units' => ['cái'], 'capabilities' => [], 'active' => true],
        ['key' => 'paint', 'name' => 'Nhóm màu cũ', 'file' => 'products_paint.json', 'units' => ['thùng'], 'active' => true],
    ]);
    writeJson($dir . '/products_general.json', [
        ['id' => 'P1', 'code' => 'SP1', 'name' => 'Sản phẩm 1', 'unit' => 'cái', 'stock' => 8, 'price_in' => 50, 'price_out' => 100, 'active' => true],
        ['id' => 'P2', 'code' => 'SP2', 'name' => 'Sản phẩm chưa dùng', 'unit' => 'cái', 'stock' => 0, 'price_in' => 10, 'price_out' => 20, 'active' => true],
    ]);
    writeJson($dir . '/products_paint.json', [[
        'id' => 'P3', 'code' => 'SON1', 'name' => 'Sản phẩm màu cũ', 'unit' => 'thùng', 'stock' => 0,
        'price_in' => 80, 'price_out' => 100, 'active' => true,
        'special_colors' => [['name' => 'Đỏ', 'code' => '', 'surcharge_type' => 'fixed', 'surcharge_pct' => 0, 'surcharge' => 20]],
    ]]);

    writeJson(FEATURE_SETTINGS_FILE, ['profile'=>'basic','history'=>[]]);
    featureResetCache();
    $_SESSION['user_info'] = ['username'=>'admin_test','name'=>'Chủ cửa hàng','role'=>'admin','branch'=>null];
    assertTrue(!featureEnabled('receivables') && !featureEnabled('bulk_import'), 'Admin phải bị giới hạn theo chế độ Cơ bản');
    $blockedCreditInvoice = invoiceProcess([
        'branch'=>$branch,'customer'=>'Khách thử','phone'=>'0900000000','payment'=>'credit',
        'items'=>json_encode([['code'=>'SP1','qty'=>1,'price_out'=>100]]),
    ]);
    assertTrue(!$blockedCreditInvoice['success'], 'Backend phải chặn bán công nợ trong chế độ Cơ bản');
    assertTrue((float)(productGetByCode($branch, 'SP1', true)['stock'] ?? 0) === 8.0, 'Yêu cầu công nợ bị chặn không được thay đổi tồn kho');
    $_SESSION['user_info'] = ['username'=>'test','name'=>'Test','role'=>'superadmin'];
    assertTrue(featureEnabled('receivables'), 'Superadmin phải luôn truy cập được toàn bộ chức năng');
    writeJson(FEATURE_SETTINGS_FILE, ['profile'=>'full','history'=>[]]);
    featureResetCache();

    $adjustment = inventoryAdjustmentCreate($branch, [
        'type' => 'increase', 'reason' => 'Bổ sung tồn đầu kỳ',
        'items' => json_encode([['code' => 'SP1', 'adjust_qty' => 2]]),
    ]);
    assertTrue($adjustment['success'], 'Không lập được phiếu điều chỉnh tồn kho');
    assertTrue((float)(productGetByCode($branch, 'SP1', true)['stock'] ?? 0) === 8.0, 'Phiếu điều chỉnh nháp không được thay đổi tồn kho');
    $_SESSION['user_info'] = ['username'=>'employee_test','name'=>'Nhân viên','role'=>'employee','branch'=>[$branch]];
    assertTrue(!inventoryAdjustmentApprove($branch, $adjustment['id'])['success'], 'Nhân viên không được tự duyệt điều chỉnh tồn kho');
    $_SESSION['user_info'] = ['username'=>'test','name'=>'Test','role'=>'superadmin'];
    assertTrue(inventoryAdjustmentApprove($branch, $adjustment['id'])['success'], 'Không duyệt được phiếu điều chỉnh tồn kho');
    assertTrue((float)(productGetByCode($branch, 'SP1', true)['stock'] ?? 0) === 10.0, 'Duyệt điều chỉnh phải cập nhật tồn kho');
    assertTrue(inventoryAdjustmentReverse($branch, $adjustment['id'], 'Kiểm thử hoàn tác')['success'], 'Không hoàn tác được phiếu điều chỉnh tồn kho');
    assertTrue((float)(productGetByCode($branch, 'SP1', true)['stock'] ?? 0) === 8.0, 'Hoàn tác điều chỉnh phải khôi phục tồn kho');
    $staleAdjustment = inventoryAdjustmentCreate($branch, [
        'type'=>'stocktake','reason'=>'Kiểm kê đột xuất','items'=>json_encode([['code'=>'SP1','actual_qty'=>8]]),
    ]);
    assertTrue($staleAdjustment['success'], 'Không lập được phiếu kiểm kê thử nghiệm');
    assertTrue(updateStock($branch, 'SP1', 1, 'in'), 'Không mô phỏng được thay đổi tồn kho');
    assertTrue(!inventoryAdjustmentApprove($branch, $staleAdjustment['id'])['success'], 'Không được duyệt phiếu khi tồn kho đã thay đổi sau lúc kiểm kê');
    assertTrue(updateStock($branch, 'SP1', 1, 'out'), 'Không khôi phục được tồn kho thử nghiệm');
    assertTrue(inventoryAdjustmentCancel($branch, $staleAdjustment['id'])['success'], 'Không hủy được phiếu kiểm kê cũ');

    assertTrue(!categoryHasCapability($branch, 'general', 'color_surcharge'), 'Nhóm thường không được tự bật màu đặc biệt');
    assertTrue(categoryHasCapability($branch, 'paint', 'color_surcharge'), 'Phải tự nhận diện capability từ dữ liệu màu cũ');
    $disableCapability = saveCategory($branch, [
        'original_key' => 'paint', 'name' => 'Nhóm màu cũ', 'units' => ['thùng'], 'sort_order' => 2,
        'active' => 1, 'capabilities' => [],
    ]);
    assertTrue(!$disableCapability['success'], 'Không được tắt capability khi sản phẩm vẫn còn cấu hình màu');
    $persistCapability = saveCategory($branch, [
        'original_key' => 'paint', 'name' => 'Nhóm màu cũ', 'units' => ['thùng'], 'sort_order' => 2,
        'active' => 1, 'capabilities' => ['color_surcharge'],
    ]);
    assertTrue($persistCapability['success'], 'Không lưu được capability cho nhóm hàng');

    $paintCsv = $dir . '/paint.csv';
    file_put_contents($paintCsv, "Tên sản phẩm,Màu đặc biệt,Giá bán,Mã SP,Đơn vị\nSơn mới,Đỏ:+20000; Xanh:+15000,150000,SON2,thùng\n");
    $paintPreview = productBulkPreview($branch, 'paint', ['error' => UPLOAD_ERR_OK, 'name' => 'paint.csv', 'tmp_name' => $paintCsv]);
    assertTrue($paintPreview['success'], 'Không đọc được CSV có thứ tự cột động');
    assertTrue(($paintPreview['preview']['valid'][0]['code'] ?? '') === 'SON2', 'Ánh xạ cột CSV theo tiêu đề bị sai');
    assertTrue(count($paintPreview['preview']['valid'][0]['special_colors'] ?? []) === 2, 'Không đọc được màu đặc biệt từ CSV');

    $generalCsv = $dir . '/general.csv';
    file_put_contents($generalCsv, "Mã SP,Tên sản phẩm,Đơn vị,Giá bán,Màu đặc biệt\nSP3,Sản phẩm 3,cái,300,Đỏ:+50\n");
    $generalPreview = productBulkPreview($branch, 'general', ['error' => UPLOAD_ERR_OK, 'name' => 'general.csv', 'tmp_name' => $generalCsv]);
    assertTrue($generalPreview['success'], 'Không đọc được CSV nhóm thường');
    assertTrue(empty($generalPreview['preview']['valid'][0]['special_colors']), 'Nhóm thường không được nhận dữ liệu màu từ CSV');
    assertTrue(!empty($generalPreview['preview']['warnings']), 'Phải cảnh báo khi CSV có cột màu không được hỗ trợ');

    $invoice = [
        'id' => 'INV-TEST-1', 'branch' => $branch, 'customer' => 'Khách thử', 'phone' => '0900000001',
        'payment' => 'cash', 'status' => 'active', 'delivery_status' => 'self_pickup', 'total' => 200, 'cashbook_sync_expected' => true,
        'created_at' => date('Y-m-d H:i:s'), 'created_by' => 'Test',
        'items' => [['product_code' => 'SP1', 'product_name' => 'Sản phẩm 1', 'qty' => 2, 'unit' => 'cái', 'price_out' => 100, 'line_total' => 200]],
    ];
    writeJson($dir . '/invoices_' . date('Y_m') . '.json', [$invoice]);

    $beforeSync = integrityCheckBranch($branch);
    assertTrue(count(array_filter($beforeSync['issues'], fn($i) => $i['code'] === 'invoice_income_missing')) === 1, 'Phải phát hiện hóa đơn thiếu bút toán thu');
    assertTrue(($beforeSync['total_records'] ?? 0) > 0 && ($beforeSync['scopes']['invoices']['count'] ?? 0) === 1, 'Báo cáo phải ghi đúng phạm vi dữ liệu đã quét');
    $repairPlan = integrityBuildRepairPlan($branch, $beforeSync);
    assertTrue(count($repairPlan) === 1 && ($repairPlan[0]['source_id'] ?? '') === 'INV-TEST-1', 'Kế hoạch sửa phải chỉ chứa hóa đơn bị sai lệch');
    $repairResult = integrityRepairLinks($branch);
    assertTrue($repairResult['success'], 'Đồng bộ hóa đơn thất bại');
    assertTrue(count(array_filter(integrityCheckBranch($branch)['issues'], fn($i) => $i['code'] === 'invoice_income_missing')) === 0, 'Sai lệch phải biến mất sau khi khắc phục');
    $integrityHistory = integrityGetHistory($branch);
    assertTrue(count($integrityHistory) === 1 && ($integrityHistory[0]['type'] ?? '') === 'repair', 'Khắc phục phải được lưu vào lịch sử');
    assertTrue(($integrityHistory[0]['actions'][0]['status'] ?? '') === 'resolved', 'Lịch sử phải ghi nhận thao tác đã khắc phục thành công');
    assertTrue(integrityRecordCheck($branch)['success'], 'Không lưu được lịch sử kiểm tra thủ công');
    $integrityHistory = integrityGetHistory($branch);
    assertTrue(count($integrityHistory) === 2 && ($integrityHistory[0]['type'] ?? '') === 'check', 'Lịch sử phải lưu riêng lần kiểm tra không sửa dữ liệu');

    $salesReturn = salesReturnCreate($branch, [
        'invoice_id' => 'INV-TEST-1', 'reason' => 'Khách trả thử nghiệm', 'refund_method' => 'cash',
        'shipping_refund' => 0, 'items' => json_encode([['code' => 'SP1', 'qty' => 1, 'restock' => true]]),
    ]);
    assertTrue($salesReturn['success'], 'Không lập được phiếu trả hàng');
    assertTrue((float)(productGetByCode($branch, 'SP1', true)['stock'] ?? 0) === 8.0, 'Phiếu trả hàng nháp không được tăng tồn kho');
    $_SESSION['user_info'] = ['username'=>'employee_test','name'=>'Nhân viên','role'=>'employee','branch'=>[$branch]];
    assertTrue(!salesReturnApprove($branch, $salesReturn['id'])['success'], 'Nhân viên không được tự duyệt trả hàng');
    $_SESSION['user_info'] = ['username'=>'test','name'=>'Test','role'=>'superadmin'];
    assertTrue(salesReturnApprove($branch, $salesReturn['id'])['success'], 'Không duyệt được phiếu trả hàng');
    assertTrue((float)(productGetByCode($branch, 'SP1', true)['stock'] ?? 0) === 9.0, 'Duyệt trả hàng phải nhập lại tồn kho đúng một lần');
    $returnSource = integrityCashbookSources($branch)['sales_return:' . $salesReturn['id']] ?? null;
    assertTrue($returnSource && empty($returnSource['deleted_at']) && (float)$returnSource['amount'] === 100.0, 'Hoàn tiền trả hàng phải tạo khoản chi tương ứng');
    $returnReport = reportBuildMonthly($branch, date('Y_m'));
    assertTrue((float)$returnReport['gross_revenue'] === 200.0 && (float)$returnReport['revenue'] === 100.0, 'Báo cáo phải tách doanh thu gộp và doanh thu thuần sau trả hàng');
    assertTrue((int)$returnReport['sales_return_count'] === 1 && (float)$returnReport['sales_returns'] === 100.0, 'Báo cáo phải ghi nhận đúng phiếu trả hàng');
    $excessReturn = salesReturnCreate($branch, [
        'invoice_id' => 'INV-TEST-1', 'reason' => 'Trả vượt', 'refund_method' => 'cash',
        'items' => json_encode([['code' => 'SP1', 'qty' => 2, 'restock' => true]]),
    ]);
    assertTrue(!$excessReturn['success'], 'Không được trả vượt số lượng đã bán');
    assertTrue(salesReturnReverse($branch, $salesReturn['id'], 'Kiểm thử hoàn tác')['success'], 'Không hoàn tác được phiếu trả hàng');
    assertTrue((float)(productGetByCode($branch, 'SP1', true)['stock'] ?? 0) === 8.0, 'Hoàn tác trả hàng phải trừ lại tồn kho đã nhập');
    $returnSource = integrityCashbookSources($branch)['sales_return:' . $salesReturn['id']] ?? null;
    assertTrue($returnSource && !empty($returnSource['deleted_at']), 'Hoàn tác trả hàng phải hủy khoản chi hoàn tiền');

    $duplicateLineInvoice = $invoice;
    $duplicateLineInvoice['id'] = 'INV-TEST-LINES';
    $duplicateLineInvoice['total'] = 220;
    $duplicateLineInvoice['cashbook_sync_expected'] = false;
    $duplicateLineInvoice['items'] = [
        ['product_code'=>'SP1','product_name'=>'Sản phẩm 1','qty'=>1,'unit'=>'cái','price_out'=>100,'line_total'=>100],
        ['product_code'=>'SP1','product_name'=>'Sản phẩm 1 - giá riêng','qty'=>1,'unit'=>'cái','price_out'=>120,'line_total'=>120],
    ];
    $invoiceRows = readJson($dir . '/invoices_' . date('Y_m') . '.json');
    $invoiceRows[] = $duplicateLineInvoice;
    writeJson($dir . '/invoices_' . date('Y_m') . '.json', $invoiceRows);
    $lineReturn = salesReturnCreate($branch, [
        'invoice_id'=>'INV-TEST-LINES','reason'=>'Kiểm thử dòng giá riêng','refund_method'=>'none',
        'items'=>json_encode([['code'=>'SP1','line'=>1,'qty'=>1,'restock'=>false]]),
    ]);
    assertTrue($lineReturn['success'], 'Không lập được phiếu trả theo dòng hóa đơn');
    $lineReturnRecord = salesReturnFind($branch, $lineReturn['id'])['record'] ?? [];
    assertTrue((float)($lineReturnRecord['refund_total'] ?? 0) === 120.0, 'Tiền hoàn phải lấy đúng đơn giá của dòng hóa đơn được chọn');
    assertTrue(salesReturnCancel($branch, $lineReturn['id'])['success'], 'Không hủy được phiếu trả theo dòng thử nghiệm');

    $archive = productDelete($branch, 'general', 'P1', 'Kiểm thử');
    assertTrue($archive['success'], 'Không lưu trữ được sản phẩm đã phát sinh');
    assertTrue(productIsArchived(productGetByCode($branch, 'SP1', true) ?? []), 'Sản phẩm đã phát sinh phải được lưu trữ');
    assertTrue(productGetByCode($branch, 'SP1') === null, 'Sản phẩm lưu trữ không được xuất hiện trong bán hàng');

    $deleteUnused = productDelete($branch, 'general', 'P2', 'Kiểm thử');
    assertTrue($deleteUnused['success'] && productGetByCode($branch, 'SP2', true) === null, 'Sản phẩm chưa dùng, tồn kho 0 phải được xóa vật lý');

    $cancel = cancelInvoice($branch, 'INV-TEST-1', 'Khách đổi ý');
    assertTrue($cancel['success'], 'Hủy hóa đơn thất bại: ' . ($cancel['message'] ?? ''));
    assertTrue((float)(productGetByCode($branch, 'SP1', true)['stock'] ?? 0) === 10.0, 'Hủy hóa đơn phải hoàn tồn kho đúng một lần');
    assertTrue(invoiceIsCancelled(getInvoiceById($branch, 'INV-TEST-1') ?? []), 'Hóa đơn phải có trạng thái đã hủy');

    $creditInvoice = [
        'id' => 'INV-TEST-CREDIT', 'branch' => $branch, 'customer' => 'Khách nợ', 'phone' => '0900000002',
        'payment' => 'credit', 'status' => 'active', 'delivery_status' => 'self_pickup', 'total' => 100,
        'created_at' => date('Y-m-d H:i:s'), 'created_by' => 'Test',
        'items' => [['product_code' => 'SP1', 'product_name' => 'Sản phẩm 1', 'qty' => 2, 'unit' => 'cái', 'price_out' => 50, 'line_total' => 100]],
    ];
    $invoiceRows = readJson($dir . '/invoices_' . date('Y_m') . '.json');
    $invoiceRows[] = $creditInvoice;
    writeJson($dir . '/invoices_' . date('Y_m') . '.json', $invoiceRows);
    $creditReturn = salesReturnCreate($branch, [
        'invoice_id' => 'INV-TEST-CREDIT', 'reason' => 'Giảm công nợ do trả hàng', 'refund_method' => 'account_credit',
        'items' => json_encode([['code' => 'SP1', 'qty' => 1, 'restock' => false]]),
    ]);
    assertTrue($creditReturn['success'] && salesReturnApprove($branch, $creditReturn['id'])['success'], 'Không xử lý được trả hàng giảm công nợ');
    $creditCustomer = receivableFindCustomer($branch, receivableCustomerKey('Khách nợ', '0900000002'));
    assertTrue((float)($creditCustomer['balance'] ?? -1) === 50.0, 'Trả hàng công nợ phải giảm đúng dư nợ');
    $payment = receivableCreatePayment($branch, [
        'customer_key' => receivableCustomerKey('Khách nợ', '0900000002'), 'amount' => 40,
        'paid_at' => date('Y-m-d'), 'method' => 'cash', 'note' => 'Kiểm thử',
    ]);
    assertTrue($payment['success'], 'Tạo phiếu thu thất bại');
    $paymentId = $payment['id'];
    assertTrue(!cancelInvoice($branch, 'INV-TEST-CREDIT', 'Kiểm thử khóa công nợ')['success'], 'Không được hủy hóa đơn khi khách đã có phiếu thu công nợ');
    assertTrue(!updateInvoice($branch, 'INV-TEST-CREDIT', ['payment' => 'cash', 'items' => '[]'])['success'], 'Không được đổi phương thức khi đã có phiếu thu công nợ');
    assertTrue(receivableDeletePayment($branch, $paymentId, 'Nhập nhầm')['success'], 'Hủy phiếu thu thất bại');
    assertTrue(count(getReceivablePayments($branch)) === 0, 'Phiếu thu đã hủy không được tính vào công nợ');
    $allPayments = getReceivablePayments($branch, true);
    assertTrue(!empty($allPayments[0]['deleted_at']), 'Phiếu thu đã hủy phải còn lịch sử');

    $pendingFeatureAdjustment=inventoryAdjustmentCreate($branch,[
        'type'=>'stocktake','reason'=>'Kiểm kê định kỳ','items'=>json_encode([['code'=>'SP1','actual_qty'=>10]]),
    ]);
    assertTrue($pendingFeatureAdjustment['success'], 'Không tạo được phiếu chờ để kiểm tra hạ chế độ');
    $basicReadiness=featureProfileReadiness('basic',[$branch]);
    assertTrue(!$basicReadiness['success'] && count($basicReadiness['blockers'])===2, 'Phải chặn chế độ Cơ bản khi còn công nợ và phiếu kiểm kê chờ duyệt');
    assertTrue(inventoryAdjustmentCancel($branch,$pendingFeatureAdjustment['id'])['success'], 'Không hủy được phiếu kiểm kê kiểm thử chế độ');

    $final = integrityCheckBranch($branch);
    assertTrue(($final['counts']['error'] ?? 0) === 0, 'Dữ liệu kiểm thử còn lỗi toàn vẹn: ' . json_encode($final['issues'], JSON_UNESCAPED_UNICODE));
    echo "OK - integrity regression tests passed\n";
} finally {
    removeTestDirectory($dir);
    @unlink($featureSettingsTestFile);
}
