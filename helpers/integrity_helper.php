<?php

function integrityCashbookSources(string $branch): array
{
    $sources = [];
    foreach (glob(DATA_PATH . "/{$branch}/cashbook_*.json") ?: [] as $file) {
        foreach (readJson($file) as $entry) {
            $type = $entry['source_type'] ?? 'manual';
            $id = $entry['source_id'] ?? '';
            if ($type === 'manual' || $id === '') continue;
            $entry['_file'] = basename($file);
            $sources[$type . ':' . $id] = $entry;
        }
    }
    return $sources;
}

function integrityIssueCatalog(): array
{
    return [
        'duplicate_product_code' => ['area'=>'products','area_label'=>'Sản phẩm & tồn kho','entity'=>'Sản phẩm','repairable'=>false,'expected'=>'Mỗi mã sản phẩm chỉ thuộc một sản phẩm'],
        'negative_stock' => ['area'=>'products','area_label'=>'Sản phẩm & tồn kho','entity'=>'Sản phẩm','repairable'=>false,'expected'=>'Tồn kho không âm'],
        'missing_product_reference' => ['area'=>'products','area_label'=>'Sản phẩm & tồn kho','entity'=>'Sản phẩm lịch sử','repairable'=>false,'expected'=>'Giữ sản phẩm ở trạng thái lưu trữ để bảo toàn lịch sử'],
        'adjustment_product_missing' => ['area'=>'inventory','area_label'=>'Kiểm kho','entity'=>'Phiếu kiểm kho','repairable'=>false,'expected'=>'Sản phẩm tham chiếu còn tồn tại hoặc được lưu trữ'],
        'cancelled_invoice_has_income' => ['area'=>'invoices','area_label'=>'Hóa đơn & thu chi','entity'=>'Hóa đơn','repairable'=>true,'expected'=>'Khoản thu của hóa đơn đã hủy phải bị vô hiệu hóa'],
        'invoice_income_missing' => ['area'=>'invoices','area_label'=>'Hóa đơn & thu chi','entity'=>'Hóa đơn','repairable'=>true,'expected'=>'Có khoản thu khớp với hóa đơn đã thanh toán'],
        'invoice_income_mismatch' => ['area'=>'invoices','area_label'=>'Hóa đơn & thu chi','entity'=>'Hóa đơn','repairable'=>true,'expected'=>'Số tiền và phương thức thu khớp hóa đơn'],
        'invoice_income_not_allowed' => ['area'=>'invoices','area_label'=>'Hóa đơn & thu chi','entity'=>'Hóa đơn','repairable'=>true,'expected'=>'Không có khoản thu bán hàng khi hóa đơn chưa thu ngay'],
        'duplicate_return_id' => ['area'=>'returns','area_label'=>'Trả hàng','entity'=>'Phiếu trả hàng','repairable'=>false,'expected'=>'Mỗi phiếu trả hàng có mã duy nhất'],
        'return_invoice_missing' => ['area'=>'returns','area_label'=>'Trả hàng','entity'=>'Phiếu trả hàng','repairable'=>false,'expected'=>'Hóa đơn gốc phải tồn tại'],
        'return_restock_product_missing' => ['area'=>'returns','area_label'=>'Trả hàng','entity'=>'Phiếu trả hàng','repairable'=>false,'expected'=>'Sản phẩm nhập lại kho còn tồn tại hoặc được lưu trữ'],
        'return_expense_missing' => ['area'=>'returns','area_label'=>'Trả hàng & thu chi','entity'=>'Phiếu trả hàng','repairable'=>true,'expected'=>'Có khoản chi hoàn tiền khớp phiếu trả'],
        'return_expense_not_allowed' => ['area'=>'returns','area_label'=>'Trả hàng & thu chi','entity'=>'Phiếu trả hàng','repairable'=>true,'expected'=>'Không có khoản chi khi phiếu trả không phát sinh hoàn tiền'],
        'return_expense_mismatch' => ['area'=>'returns','area_label'=>'Trả hàng & thu chi','entity'=>'Phiếu trả hàng','repairable'=>true,'expected'=>'Khoản chi khớp số tiền và phương thức hoàn'],
        'return_qty_exceeded' => ['area'=>'returns','area_label'=>'Trả hàng','entity'=>'Phiếu trả hàng','repairable'=>false,'expected'=>'Tổng số lượng trả không vượt số lượng đã bán'],
        'cancelled_payment_has_income' => ['area'=>'receivables','area_label'=>'Công nợ & thu chi','entity'=>'Phiếu thu công nợ','repairable'=>true,'expected'=>'Bút toán của phiếu thu đã hủy phải bị vô hiệu hóa'],
        'payment_income_missing' => ['area'=>'receivables','area_label'=>'Công nợ & thu chi','entity'=>'Phiếu thu công nợ','repairable'=>true,'expected'=>'Có bút toán thu khớp phiếu thu'],
        'payment_income_mismatch' => ['area'=>'receivables','area_label'=>'Công nợ & thu chi','entity'=>'Phiếu thu công nợ','repairable'=>true,'expected'=>'Số tiền bút toán khớp phiếu thu'],
        'orphan_invoice_income' => ['area'=>'cashbook','area_label'=>'Sổ thu chi','entity'=>'Bút toán tự động','repairable'=>false,'expected'=>'Bút toán phải có hóa đơn nguồn hợp lệ'],
        'orphan_payment_income' => ['area'=>'cashbook','area_label'=>'Sổ thu chi','entity'=>'Bút toán tự động','repairable'=>false,'expected'=>'Bút toán phải có phiếu thu nguồn hợp lệ'],
        'orphan_return_expense' => ['area'=>'cashbook','area_label'=>'Sổ thu chi','entity'=>'Bút toán tự động','repairable'=>false,'expected'=>'Bút toán phải có phiếu trả hàng nguồn hợp lệ'],
    ];
}

function integrityIssueFingerprint(array $issue): string
{
    return ($issue['code'] ?? '') . '|' . ($issue['sourceId'] ?? '');
}

function integrityCheckBranch(string $branch): array
{
    $issues = [];
    $catalog = integrityIssueCatalog();
    $scopes = [
        'products'=>['label'=>'Sản phẩm','count'=>0],
        'invoices'=>['label'=>'Hóa đơn','count'=>0],
        'invoice_items'=>['label'=>'Dòng hàng bán','count'=>0],
        'returns'=>['label'=>'Phiếu trả hàng','count'=>0],
        'adjustments'=>['label'=>'Phiếu kiểm kho','count'=>0],
        'payments'=>['label'=>'Phiếu thu công nợ','count'=>0],
        'cashbook'=>['label'=>'Bút toán tự động','count'=>0],
    ];
    $add = static function (string $severity, string $code, string $message, string $sourceId = '', string $current = '') use (&$issues, $catalog): void {
        $meta = $catalog[$code] ?? ['area'=>'other','area_label'=>'Khác','entity'=>'Dữ liệu','repairable'=>false,'expected'=>'Dữ liệu hợp lệ'];
        $issues[] = array_merge(compact('severity', 'code', 'message', 'sourceId', 'current'), $meta);
    };

    $products = [];
    foreach (getAllProducts($branch, true) as $product) {
        $scopes['products']['count']++;
        $code = (string)($product['code'] ?? '');
        if ($code === '') continue;
        if (isset($products[$code])) $add('error', 'duplicate_product_code', "Mã sản phẩm {$code} bị trùng", $code);
        $products[$code] = $product;
        if ((float)($product['stock'] ?? 0) < -0.000001) {
            $add('error', 'negative_stock', "Sản phẩm {$code} có tồn kho âm", $code);
        }
    }

    $sources = integrityCashbookSources($branch);
    $scopes['cashbook']['count'] = count($sources);
    $invoiceIds = [];
    $missingProductRefs = [];
    foreach (getAllInvoiceFiles($branch) as $file) {
        foreach (readJson($file) as $invoice) {
            $scopes['invoices']['count']++;
            $id = (string)($invoice['id'] ?? '');
            if ($id === '') continue;
            $invoiceIds[$id] = true;
            $cancelled = invoiceIsCancelled($invoice);
            foreach ($invoice['items'] ?? [] as $item) {
                $scopes['invoice_items']['count']++;
                $code = (string)($item['product_code'] ?? $item['code'] ?? '');
                if ($code !== '' && !isset($products[$code])) {
                    $missingProductRefs[$code][] = $id;
                }
            }

            $source = $sources['invoice:' . $id] ?? null;
            $sourceActive = $source && empty($source['deleted_at']);
            $syncExpected = !empty($invoice['cashbook_sync_expected']) || $source !== null;
            $payment = $invoice['payment'] ?? 'cash';
            if ($cancelled && $sourceActive) {
                $add('error', 'cancelled_invoice_has_income', "Hóa đơn đã hủy {$id} vẫn còn khoản thu hiệu lực", $id);
            } elseif (!$cancelled && $syncExpected && in_array($payment, ['cash', 'transfer'], true)) {
                if (!$sourceActive) {
                    $add('error', 'invoice_income_missing', "Hóa đơn {$id} chưa có khoản thu bán hàng", $id);
                } elseif (abs((float)($source['amount'] ?? 0) - (float)($invoice['total'] ?? 0)) > 0.000001 || ($source['method'] ?? '') !== $payment) {
                    $add('error', 'invoice_income_mismatch', "Khoản thu của hóa đơn {$id} không khớp số tiền hoặc phương thức", $id);
                }
            } elseif (!$cancelled && $syncExpected && $sourceActive) {
                $add('error', 'invoice_income_not_allowed', "Hóa đơn {$id} chưa thu tiền ngay nhưng có khoản thu bán hàng", $id);
            }
        }
    }

    foreach ($missingProductRefs as $code => $invoiceList) {
        $count = count(array_unique($invoiceList));
        $add('warning', 'missing_product_reference', "Sản phẩm {$code} không còn trong danh mục nhưng đang được {$count} hóa đơn lịch sử tham chiếu", $code);
    }

    $returnIds = [];
    $approvedReturnQuantities = [];
    foreach (function_exists('getSalesReturns') ? getSalesReturns($branch) : [] as $return) {
        $scopes['returns']['count']++;
        $id = (string)($return['id'] ?? '');
        if ($id === '') continue;
        if (isset($returnIds[$id])) $add('error', 'duplicate_return_id', "Mã phiếu trả hàng {$id} bị trùng", $id);
        $returnIds[$id] = true;
        $status = (string)($return['status'] ?? 'draft');
        $invoiceId = (string)($return['invoice_id'] ?? '');
        $invoice = getInvoiceById($branch, $invoiceId);
        if (!$invoice) {
            $add('error', 'return_invoice_missing', "Phiếu trả {$id} tham chiếu hóa đơn không tồn tại", $id);
            continue;
        }
        if ($status === 'approved') {
            foreach ($return['items'] ?? [] as $item) {
                $code = (string)($item['product_code'] ?? '');
                $lineKey = (string)($item['invoice_line_key'] ?? $code);
                $key = $invoiceId . '|' . $lineKey;
                $approvedReturnQuantities[$key] = ($approvedReturnQuantities[$key] ?? 0) + (float)($item['qty'] ?? 0);
                if (!empty($item['restock']) && !isset($products[$code])) {
                    $add('warning', 'return_restock_product_missing', "Phiếu trả {$id} đã nhập kho sản phẩm không còn trong danh mục: {$code}", $id);
                }
            }
        }

        $source = $sources['sales_return:' . $id] ?? null;
        $sourceActive = $source && empty($source['deleted_at']);
        $expectsExpense = $status === 'approved'
            && in_array($return['refund_method'] ?? '', ['cash', 'transfer'], true)
            && (float)($return['refund_total'] ?? 0) > 0;
        if ($expectsExpense && !$sourceActive) {
            $add('error', 'return_expense_missing', "Phiếu trả {$id} chưa có khoản chi hoàn tiền", $id);
        } elseif (!$expectsExpense && $sourceActive) {
            $add('error', 'return_expense_not_allowed', "Phiếu trả {$id} không được có khoản chi hoàn tiền hiệu lực", $id);
        } elseif ($expectsExpense && (abs((float)($source['amount'] ?? 0) - (float)($return['refund_total'] ?? 0)) > 0.000001 || ($source['method'] ?? '') !== ($return['refund_method'] ?? ''))) {
            $add('error', 'return_expense_mismatch', "Khoản chi của phiếu trả {$id} không khớp", $id);
        }
    }
    foreach ($approvedReturnQuantities as $key => $qty) {
        [$invoiceId, $lineKey] = explode('|', $key, 2);
        $invoice = getInvoiceById($branch, $invoiceId);
        $soldQty = 0.0;
        foreach ($invoice['items'] ?? [] as $index => $item) {
            if (salesReturnInvoiceLineKey($item, $index) === $lineKey || (!str_contains($lineKey, '#') && ($item['product_code'] ?? '') === $lineKey)) $soldQty += (float)($item['qty'] ?? 0);
        }
        if ($qty > $soldQty + 0.000001) $add('error', 'return_qty_exceeded', "Tổng số lượng trả {$lineKey} vượt số đã bán trên hóa đơn {$invoiceId}", $invoiceId);
    }

    foreach (function_exists('getInventoryAdjustments') ? getInventoryAdjustments($branch) : [] as $adjustment) {
        $scopes['adjustments']['count']++;
        foreach ($adjustment['items'] ?? [] as $item) {
            $code = (string)($item['product_code'] ?? '');
            if ($code !== '' && !isset($products[$code])) {
                $add('warning', 'adjustment_product_missing', "Phiếu kiểm kê {$adjustment['id']} tham chiếu sản phẩm không còn trong danh mục: {$code}", $adjustment['id'] ?? '');
            }
        }
    }

    $paymentIds = [];
    foreach (getReceivablePayments($branch, true) as $payment) {
        $scopes['payments']['count']++;
        $id = (string)($payment['id'] ?? '');
        if ($id === '') continue;
        $paymentIds[$id] = true;
        $source = $sources['receivable_payment:' . $id] ?? null;
        $sourceActive = $source && empty($source['deleted_at']);
        if (!empty($payment['deleted_at'])) {
            if ($sourceActive) $add('error', 'cancelled_payment_has_income', "Phiếu thu đã hủy {$id} vẫn còn bút toán hiệu lực", $id);
        } elseif (!$sourceActive) {
            $add('error', 'payment_income_missing', "Phiếu thu {$id} chưa có bút toán thu công nợ", $id);
        } elseif (abs((float)($source['amount'] ?? 0) - (float)($payment['amount'] ?? 0)) > 0.000001) {
            $add('error', 'payment_income_mismatch', "Bút toán của phiếu thu {$id} không khớp số tiền", $id);
        }
    }

    foreach ($sources as $key => $source) {
        if (!empty($source['deleted_at'])) continue;
        $type = $source['source_type'] ?? '';
        $id = $source['source_id'] ?? '';
        if ($type === 'invoice' && !isset($invoiceIds[$id])) {
            $add('warning', 'orphan_invoice_income', "Bút toán thu tham chiếu hóa đơn không tồn tại: {$id}", $id);
        }
        if ($type === 'receivable_payment' && !isset($paymentIds[$id])) {
            $add('warning', 'orphan_payment_income', "Bút toán thu tham chiếu phiếu thu không tồn tại: {$id}", $id);
        }
        if ($type === 'sales_return' && !isset($returnIds[$id])) {
            $add('warning', 'orphan_return_expense', "Bút toán chi tham chiếu phiếu trả hàng không tồn tại: {$id}", $id);
        }
    }

    $counts = ['error' => 0, 'warning' => 0];
    foreach ($issues as $issue) $counts[$issue['severity']]++;
    return [
        'issues'=>$issues,
        'counts'=>$counts,
        'scopes'=>$scopes,
        'total_records'=>array_sum(array_column($scopes, 'count')),
        'checked_at'=>date('Y-m-d H:i:s'),
    ];
}

function integrityBuildRepairPlan(string $branch, ?array $report = null): array
{
    $report ??= integrityCheckBranch($branch);
    $sourceMap = [
        'cancelled_invoice_has_income'=>'invoice', 'invoice_income_missing'=>'invoice',
        'invoice_income_mismatch'=>'invoice', 'invoice_income_not_allowed'=>'invoice',
        'return_expense_missing'=>'sales_return', 'return_expense_not_allowed'=>'sales_return',
        'return_expense_mismatch'=>'sales_return', 'cancelled_payment_has_income'=>'receivable_payment',
        'payment_income_missing'=>'receivable_payment', 'payment_income_mismatch'=>'receivable_payment',
    ];
    $operationMap = [
        'cancelled_invoice_has_income'=>'deactivate', 'invoice_income_missing'=>'create',
        'invoice_income_mismatch'=>'update', 'invoice_income_not_allowed'=>'deactivate',
        'return_expense_missing'=>'create', 'return_expense_not_allowed'=>'deactivate',
        'return_expense_mismatch'=>'update', 'cancelled_payment_has_income'=>'deactivate',
        'payment_income_missing'=>'create', 'payment_income_mismatch'=>'update',
    ];
    $operationLabels = ['create'=>'Tạo liên kết', 'update'=>'Cập nhật liên kết', 'deactivate'=>'Vô hiệu hóa liên kết'];
    $plan = [];
    $seen = [];
    foreach ($report['issues'] ?? [] as $issue) {
        $code = (string)($issue['code'] ?? '');
        if (empty($issue['repairable']) || !isset($sourceMap[$code])) continue;
        $sourceType = $sourceMap[$code];
        $sourceId = (string)($issue['sourceId'] ?? '');
        $key = $sourceType . ':' . $sourceId;
        if ($sourceId === '' || isset($seen[$key])) continue;
        $seen[$key] = true;
        $operation = $operationMap[$code];
        $plan[] = [
            'issue_code'=>$code,
            'area_label'=>$issue['area_label'] ?? 'Liên kết dữ liệu',
            'source_type'=>$sourceType,
            'source_id'=>$sourceId,
            'operation'=>$operation,
            'operation_label'=>$operationLabels[$operation],
            'before'=>$issue['message'] ?? 'Liên kết đang sai lệch',
            'after'=>$issue['expected'] ?? 'Liên kết khớp chứng từ nguồn',
        ];
    }
    return $plan;
}

function integrityFindPayment(string $branch, string $id): ?array
{
    foreach (getReceivablePayments($branch, true) as $payment) {
        if (($payment['id'] ?? '') === $id) return $payment;
    }
    return null;
}

function integrityApplyPlanAction(string $branch, array $action): array
{
    $type = $action['source_type'] ?? '';
    $id = (string)($action['source_id'] ?? '');
    if ($type === 'invoice') {
        $source = getInvoiceById($branch, $id);
        $result = $source ? cashbookSyncInvoice($branch, $source) : ['success'=>false,'message'=>'Không tìm thấy hóa đơn nguồn'];
    } elseif ($type === 'receivable_payment') {
        $source = integrityFindPayment($branch, $id);
        $result = $source ? cashbookSyncReceivablePayment($branch, $source) : ['success'=>false,'message'=>'Không tìm thấy phiếu thu nguồn'];
    } elseif ($type === 'sales_return') {
        $found = function_exists('salesReturnFind') ? salesReturnFind($branch, $id) : null;
        $result = $found ? cashbookSyncSalesReturn($branch, $found['record']) : ['success'=>false,'message'=>'Không tìm thấy phiếu trả hàng nguồn'];
    } else {
        $result = ['success'=>false,'message'=>'Loại liên kết không hỗ trợ'];
    }
    return array_merge($action, [
        'status'=>($result['success'] ?? false) ? 'success' : 'failed',
        'result_message'=>($result['success'] ?? false) ? 'Đã thực hiện theo chứng từ nguồn' : ($result['message'] ?? 'Không thể khắc phục'),
    ]);
}

function integrityHistoryFile(string $branch): string
{
    return DATA_PATH . "/{$branch}/integrity_history.json";
}

function integritySaveRun(string $branch, array $run): array
{
    $user = function_exists('currentUser') ? currentUser() : [];
    $run = array_merge([
        'id'=>'INT-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2))),
        'branch'=>$branch,
        'type'=>'check',
        'created_at'=>date('Y-m-d H:i:s'),
        'created_by'=>$user['name'] ?? $user['username'] ?? 'System',
        'created_by_username'=>$user['username'] ?? '',
    ], $run);
    $saved = updateJson(integrityHistoryFile($branch), function (array $rows) use ($run): array {
        array_unshift($rows, $run);
        return array_slice($rows, 0, 60);
    });
    return $saved ? $run : [];
}

function integrityGetHistory(string $branch, int $limit = 20): array
{
    return array_slice(readJson(integrityHistoryFile($branch)), 0, max(1, min(60, $limit)));
}

function integrityRecordCheck(string $branch): array
{
    $report = integrityCheckBranch($branch);
    $run = integritySaveRun($branch, [
        'type'=>'check',
        'counts_before'=>$report['counts'],
        'counts_after'=>$report['counts'],
        'total_records'=>$report['total_records'] ?? 0,
        'scopes'=>$report['scopes'] ?? [],
        'issues'=>$report['issues'] ?? [],
        'actions'=>[],
        'summary'=>empty($report['issues']) ? 'Không phát hiện sai lệch' : 'Đã ghi nhận ' . count($report['issues']) . ' sai lệch',
    ]);
    return $run
        ? ['success'=>true,'message'=>'Đã kiểm tra và lưu kết quả','run'=>$run,'report'=>$report]
        : ['success'=>false,'message'=>'Không thể lưu lịch sử kiểm tra','report'=>$report];
}

function integrityRepairLinks(string $branch): array
{
    $before = integrityCheckBranch($branch);
    $plan = integrityBuildRepairPlan($branch, $before);
    $actions = [];
    foreach ($plan as $action) $actions[] = integrityApplyPlanAction($branch, $action);
    $after = integrityCheckBranch($branch);
    $remaining = [];
    foreach ($after['issues'] ?? [] as $issue) $remaining[integrityIssueFingerprint($issue)] = true;
    foreach ($actions as &$action) {
        if ($action['status'] === 'success') {
            $fingerprint = ($action['issue_code'] ?? '') . '|' . ($action['source_id'] ?? '');
            $action['status'] = isset($remaining[$fingerprint]) ? 'failed' : 'resolved';
            $action['result_message'] = $action['status'] === 'resolved' ? 'Đã khắc phục và kiểm tra lại thành công' : 'Đã đồng bộ nhưng sai lệch vẫn còn';
        }
    }
    unset($action);
    $resolved = count(array_filter($actions, fn($a) => ($a['status'] ?? '') === 'resolved'));
    $failed = count(array_filter($actions, fn($a) => ($a['status'] ?? '') === 'failed'));
    $run = integritySaveRun($branch, [
        'type'=>'repair',
        'counts_before'=>$before['counts'],
        'counts_after'=>$after['counts'],
        'total_records'=>$after['total_records'] ?? 0,
        'scopes'=>$after['scopes'] ?? [],
        'issues'=>$before['issues'] ?? [],
        'actions'=>$actions,
        'summary'=>$plan ? "Đã khắc phục {$resolved} liên kết, {$failed} thao tác chưa thành công" : 'Không có sai lệch có thể tự động khắc phục',
    ]);
    if (!$run) return ['success'=>false,'message'=>'Đã xử lý nhưng không thể lưu lịch sử kiểm tra','actions'=>$actions];
    if (!$plan) return ['success'=>true,'message'=>'Không có sai lệch nào có thể tự động khắc phục','run_id'=>$run['id']];
    return [
        'success'=>$failed === 0,
        'message'=>$failed === 0 ? "Đã khắc phục {$resolved} liên kết và kiểm tra lại thành công" : "Đã khắc phục {$resolved} liên kết, {$failed} thao tác chưa thành công",
        'run_id'=>$run['id'],
        'actions'=>$actions,
    ];
}
