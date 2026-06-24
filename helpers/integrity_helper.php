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

function integrityCheckBranch(string $branch): array
{
    $issues = [];
    $add = static function (string $severity, string $code, string $message, string $sourceId = '') use (&$issues): void {
        $issues[] = compact('severity', 'code', 'message', 'sourceId');
    };

    $products = [];
    foreach (getAllProducts($branch, true) as $product) {
        $code = (string)($product['code'] ?? '');
        if ($code === '') continue;
        if (isset($products[$code])) $add('error', 'duplicate_product_code', "Mã sản phẩm {$code} bị trùng", $code);
        $products[$code] = $product;
        if ((float)($product['stock'] ?? 0) < -0.000001) {
            $add('error', 'negative_stock', "Sản phẩm {$code} có tồn kho âm", $code);
        }
    }

    $sources = integrityCashbookSources($branch);
    $invoiceIds = [];
    $missingProductRefs = [];
    foreach (getAllInvoiceFiles($branch) as $file) {
        foreach (readJson($file) as $invoice) {
            $id = (string)($invoice['id'] ?? '');
            if ($id === '') continue;
            $invoiceIds[$id] = true;
            $cancelled = invoiceIsCancelled($invoice);
            foreach ($invoice['items'] ?? [] as $item) {
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
        foreach ($adjustment['items'] ?? [] as $item) {
            $code = (string)($item['product_code'] ?? '');
            if ($code !== '' && !isset($products[$code])) {
                $add('warning', 'adjustment_product_missing', "Phiếu kiểm kê {$adjustment['id']} tham chiếu sản phẩm không còn trong danh mục: {$code}", $adjustment['id'] ?? '');
            }
        }
    }

    $paymentIds = [];
    foreach (getReceivablePayments($branch, true) as $payment) {
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
    return ['issues' => $issues, 'counts' => $counts, 'checked_at' => date('Y-m-d H:i:s')];
}

function integrityRepairLinks(string $branch): array
{
    $synced = 0;
    $failed = 0;
    $existingSources = integrityCashbookSources($branch);
    foreach (getAllInvoiceFiles($branch) as $file) {
        foreach (readJson($file) as $invoice) {
            $sourceExists = isset($existingSources['invoice:' . ($invoice['id'] ?? '')]);
            if (empty($invoice['cashbook_sync_expected']) && !$sourceExists) continue;
            $result = cashbookSyncInvoice($branch, $invoice);
            ($result['success'] ?? false) ? $synced++ : $failed++;
        }
    }
    foreach (getReceivablePayments($branch, true) as $payment) {
        $result = cashbookSyncReceivablePayment($branch, $payment);
        ($result['success'] ?? false) ? $synced++ : $failed++;
    }
    foreach (function_exists('getSalesReturns') ? getSalesReturns($branch) : [] as $return) {
        $result = cashbookSyncSalesReturn($branch, $return);
        ($result['success'] ?? false) ? $synced++ : $failed++;
    }
    return $failed === 0
        ? ['success' => true, 'message' => "Đã đồng bộ {$synced} liên kết chứng từ"]
        : ['success' => false, 'message' => "Đã đồng bộ {$synced} liên kết, {$failed} liên kết bị lỗi"];
}
