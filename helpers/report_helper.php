<?php

function reportPreviousYearMonth(string $yearMonth): string
{
    $date = DateTime::createFromFormat('!Y_m', $yearMonth) ?: new DateTime('first day of this month');
    return $date->modify('-1 month')->format('Y_m');
}

function reportMonthPrefix(string $yearMonth): string
{
    return str_replace('_', '-', $yearMonth);
}

function reportPaymentLabels(): array
{
    return [
        'cash' => 'Tiền mặt',
        'transfer' => 'Chuyển khoản',
        'cod' => 'COD',
        'credit' => 'Công nợ',
    ];
}

function reportBuildMonthly(string $branch, string $yearMonth): array
{
    if (!preg_match('/^\d{4}_\d{2}$/', $yearMonth)) {
        $yearMonth = date('Y_m');
    }

    $invoices = array_values(array_filter(getInvoices($branch, $yearMonth), fn($invoice) => !invoiceIsCancelled($invoice)));
    $imports = getImports($branch, $yearMonth);
    $products = getAllProducts($branch, true);
    $productsByCode = [];
    foreach ($products as $product) {
        $productsByCode[(string)($product['code'] ?? '')] = $product;
    }

    $paymentBreakdown = [];
    foreach (reportPaymentLabels() as $key => $label) {
        $paymentBreakdown[$key] = ['key' => $key, 'label' => $label, 'orders' => 0, 'amount' => 0.0];
    }

    $delivery = [
        'self_pickup' => 0,
        'pending' => 0,
        'delivered' => 0,
        'overdue' => 0,
        'shipping_revenue' => 0.0,
    ];
    $soldMap = [];
    $revenue = 0.0;
    $shippingRevenue = 0.0;
    $creditSales = 0.0;
    $estimatedCost = 0.0;
    $snapshotLines = 0;
    $fallbackCostLines = 0;
    $today = date('Y-m-d');

    foreach ($invoices as $invoice) {
        $total = (float)($invoice['total'] ?? 0);
        $shipping = (float)($invoice['shipping_fee'] ?? 0);
        $revenue += $total;
        $shippingRevenue += $shipping;

        $payment = (string)($invoice['payment'] ?? 'cash');
        if (!isset($paymentBreakdown[$payment])) {
            $paymentBreakdown[$payment] = ['key' => $payment, 'label' => $payment, 'orders' => 0, 'amount' => 0.0];
        }
        $paymentBreakdown[$payment]['orders']++;
        $paymentBreakdown[$payment]['amount'] += $total;
        if ($payment === 'credit') $creditSales += $total;

        $status = (string)($invoice['delivery_status'] ?? 'self_pickup');
        if (!isset($delivery[$status])) $status = 'self_pickup';
        $delivery[$status]++;
        $deliveryDate = (string)($invoice['delivery_date'] ?? '');
        if ($status === 'pending' && $deliveryDate !== '' && $deliveryDate < $today) {
            $delivery['overdue']++;
        }
        $delivery['shipping_revenue'] += $shipping;

        foreach ($invoice['items'] ?? [] as $item) {
            $code = (string)($item['product_code'] ?? $item['code'] ?? '');
            $qty = (float)($item['qty'] ?? 0);
            $lineRevenue = (float)($item['line_total'] ?? 0);
            if (!isset($soldMap[$code])) {
                $soldMap[$code] = [
                    'code' => $code,
                    'name' => (string)($item['product_name'] ?? $item['name'] ?? $code),
                    'qty' => 0.0,
                    'revenue' => 0.0,
                ];
            }
            $soldMap[$code]['qty'] += $qty;
            $soldMap[$code]['revenue'] += $lineRevenue;

            if (array_key_exists('cost_price', $item)) {
                $estimatedCost += $qty * (float)$item['cost_price'];
                $snapshotLines++;
            } else {
                $estimatedCost += $qty * (float)($productsByCode[$code]['price_in'] ?? 0);
                $fallbackCostLines++;
            }
        }
    }

    $grossRevenue = $revenue;
    $grossShippingRevenue = $shippingRevenue;
    $salesReturns = function_exists('salesReturnMonthly')
        ? salesReturnMonthly($branch, $yearMonth)
        : ['rows' => [], 'total' => 0.0, 'restocked_cost' => 0.0, 'count' => 0];
    $returnedShipping = 0.0;
    foreach ($salesReturns['rows'] as $return) {
        $returnedShipping += (float)($return['shipping_refund'] ?? 0);
        foreach ($return['items'] ?? [] as $item) {
            $code = (string)($item['product_code'] ?? '');
            if (!isset($soldMap[$code])) {
                $soldMap[$code] = [
                    'code' => $code,
                    'name' => (string)($item['product_name'] ?? $code),
                    'qty' => 0.0,
                    'revenue' => 0.0,
                ];
            }
            $soldMap[$code]['qty'] -= (float)($item['qty'] ?? 0);
            $soldMap[$code]['revenue'] -= (float)($item['refund_amount'] ?? 0);
        }
    }
    $revenue = $grossRevenue - (float)$salesReturns['total'];
    $shippingRevenue = $grossShippingRevenue - $returnedShipping;
    $delivery['shipping_revenue'] = $shippingRevenue;
    $estimatedCost -= (float)$salesReturns['restocked_cost'];

    uasort($soldMap, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

    $previousInvoices = array_values(array_filter(getInvoices($branch, reportPreviousYearMonth($yearMonth)), fn($invoice) => !invoiceIsCancelled($invoice)));
    $previousGrossRevenue = array_sum(array_map(fn($invoice) => (float)($invoice['total'] ?? 0), $previousInvoices));
    $previousReturns = function_exists('salesReturnMonthly') ? salesReturnMonthly($branch, reportPreviousYearMonth($yearMonth)) : ['total' => 0.0];
    $previousRevenue = $previousGrossRevenue - (float)($previousReturns['total'] ?? 0);
    $revenueChange = $previousRevenue > 0
        ? (($revenue - $previousRevenue) / $previousRevenue) * 100
        : ($revenue > 0 ? 100.0 : 0.0);

    $cashbookEntries = getCashbookEntries($branch, $yearMonth);
    $cashbook = cashbookSummary($cashbookEntries);
    $cashbookByCategory = [];
    foreach ($cashbookEntries as $entry) {
        $type = (string)($entry['type'] ?? 'income');
        $category = (string)($entry['category'] ?? 'other_income');
        $key = $type . ':' . $category;
        if (!isset($cashbookByCategory[$key])) {
            $cashbookByCategory[$key] = [
                'type' => $type,
                'category' => $category,
                'label' => cashbookCategoryLabel($type, $category),
                'amount' => 0.0,
                'count' => 0,
            ];
        }
        $cashbookByCategory[$key]['amount'] += (float)($entry['amount'] ?? 0);
        $cashbookByCategory[$key]['count']++;
    }
    usort($cashbookByCategory, fn($a, $b) => $b['amount'] <=> $a['amount']);

    $receivable = getReceivableSummary($branch);
    $monthPrefix = reportMonthPrefix($yearMonth);
    $debtCollected = 0.0;
    foreach (getReceivablePayments($branch) as $payment) {
        if (str_starts_with((string)($payment['paid_at'] ?? ''), $monthPrefix)) {
            $debtCollected += (float)($payment['amount'] ?? 0);
        }
    }

    $aging = ['under_30' => 0.0, 'days_31_60' => 0.0, 'over_60' => 0.0];
    $topDebtors = [];
    $openReceivable = 0.0;
    $customerCredit = 0.0;
    $now = new DateTimeImmutable('today');
    foreach ($receivable['customers'] ?? [] as $customer) {
        $balance = (float)($customer['balance'] ?? 0);
        if ($balance < 0) {
            $customerCredit += abs($balance);
            continue;
        }
        if ($balance === 0.0) continue;
        $openReceivable += $balance;
        $topDebtors[] = $customer;

        $paidPool = (float)($customer['paid'] ?? 0);
        $customerInvoices = $customer['invoices'] ?? [];
        usort($customerInvoices, fn($a, $b) => strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));
        foreach ($customerInvoices as $invoice) {
            $remaining = (float)($invoice['_net_total'] ?? $invoice['total'] ?? 0);
            if ($paidPool > 0) {
                $applied = min($paidPool, $remaining);
                $paidPool -= $applied;
                $remaining -= $applied;
            }
            if ($remaining <= 0) continue;
            $invoiceDate = substr((string)($invoice['created_at'] ?? ''), 0, 10);
            try {
                $days = $invoiceDate ? (new DateTimeImmutable($invoiceDate))->diff($now)->days : 0;
            } catch (Throwable) {
                $days = 0;
            }
            if ($days <= 30) $aging['under_30'] += $remaining;
            elseif ($days <= 60) $aging['days_31_60'] += $remaining;
            else $aging['over_60'] += $remaining;
        }
    }
    usort($topDebtors, fn($a, $b) => ((float)$b['balance']) <=> ((float)$a['balance']));

    $inventoryValue = 0.0;
    $inventoryRetailValue = 0.0;
    $lowStock = 0;
    $outOfStock = 0;
    $stockByCategory = [];
    foreach ($products as $product) {
        $stock = (float)($product['stock'] ?? 0);
        $minStock = (float)($product['min_stock'] ?? 5);
        $inventoryValue += $stock * (float)($product['price_in'] ?? 0);
        $inventoryRetailValue += $stock * (float)($product['price_out'] ?? 0);
        if ($stock <= 0) $outOfStock++;
        elseif ($stock < $minStock) $lowStock++;

        $category = (string)($product['category_name'] ?? 'Chưa phân nhóm');
        if (!isset($stockByCategory[$category])) {
            $stockByCategory[$category] = ['name' => $category, 'products' => 0, 'low' => 0, 'out' => 0, 'value' => 0.0];
        }
        $stockByCategory[$category]['products']++;
        $stockByCategory[$category]['value'] += $stock * (float)($product['price_in'] ?? 0);
        if ($stock <= 0) $stockByCategory[$category]['out']++;
        elseif ($stock < $minStock) $stockByCategory[$category]['low']++;
    }
    uasort($stockByCategory, fn($a, $b) => $b['value'] <=> $a['value']);

    $importTotal = 0.0;
    $importsBySupplier = [];
    foreach ($imports as $import) {
        $amount = (float)($import['total_amount'] ?? 0);
        $importTotal += $amount;
        $supplier = trim((string)($import['supplier'] ?? '')) ?: 'Không ghi nhà cung cấp';
        if (!isset($importsBySupplier[$supplier])) {
            $importsBySupplier[$supplier] = ['name' => $supplier, 'amount' => 0.0, 'count' => 0];
        }
        $importsBySupplier[$supplier]['amount'] += $amount;
        $importsBySupplier[$supplier]['count']++;
    }
    uasort($importsBySupplier, fn($a, $b) => $b['amount'] <=> $a['amount']);

    $dailyRevenue = getRevenueReport($branch, $yearMonth);
    $dailyRevenueByDate = [];
    foreach ($dailyRevenue as $day) $dailyRevenueByDate[$day['date']] = $day;
    foreach ($salesReturns['rows'] as $return) {
        $date = substr((string)($return['approved_at'] ?? ''), 0, 10);
        if ($date === '') continue;
        if (!isset($dailyRevenueByDate[$date])) $dailyRevenueByDate[$date] = ['date'=>$date,'orders'=>0,'revenue'=>0.0];
        $dailyRevenueByDate[$date]['revenue'] -= (float)($return['refund_total'] ?? 0);
    }
    ksort($dailyRevenueByDate);
    $dailyRevenue = array_values($dailyRevenueByDate);

    $stockAdjustments = [];
    $stockAdjustmentIncrease = 0.0;
    $stockAdjustmentDecrease = 0.0;
    foreach (function_exists('getInventoryAdjustments') ? getInventoryAdjustments($branch, false) : [] as $adjustment) {
        if (($adjustment['status'] ?? '') !== 'approved' || !str_starts_with($adjustment['approved_at'] ?? '', $monthPrefix)) continue;
        $stockAdjustments[] = $adjustment;
        foreach ($adjustment['items'] ?? [] as $item) {
            $difference = (float)($item['difference'] ?? 0);
            if ($difference > 0) $stockAdjustmentIncrease += $difference;
            elseif ($difference < 0) $stockAdjustmentDecrease += abs($difference);
        }
    }

    return [
        'year_month' => $yearMonth,
        'invoices' => $invoices,
        'imports' => $imports,
        'products' => $products,
        'daily_revenue' => $dailyRevenue,
        'gross_revenue' => $grossRevenue,
        'sales_returns' => (float)$salesReturns['total'],
        'sales_return_count' => (int)$salesReturns['count'],
        'sales_return_rows' => $salesReturns['rows'],
        'revenue' => $revenue,
        'merchandise_revenue' => $revenue - $shippingRevenue,
        'shipping_revenue' => $shippingRevenue,
        'orders' => count($invoices),
        'average_order' => count($invoices) ? $revenue / count($invoices) : 0.0,
        'previous_revenue' => $previousRevenue,
        'revenue_change' => $revenueChange,
        'payment_breakdown' => array_values($paymentBreakdown),
        'delivery' => $delivery,
        'top_products' => array_slice(array_values($soldMap), 0, 10),
        'estimated_cost' => $estimatedCost,
        'estimated_gross_profit' => ($revenue - $shippingRevenue) - $estimatedCost,
        'cost_snapshot_lines' => $snapshotLines,
        'cost_fallback_lines' => $fallbackCostLines,
        'sales_import_difference' => $revenue - $importTotal,
        'import_total' => $importTotal,
        'cashbook' => $cashbook,
        'cashbook_entries' => $cashbookEntries,
        'cashbook_by_category' => $cashbookByCategory,
        'credit_sales' => $creditSales,
        'debt_collected' => $debtCollected,
        'receivable' => $receivable,
        'open_receivable' => $openReceivable,
        'customer_credit' => $customerCredit,
        'top_debtors' => array_slice($topDebtors, 0, 10),
        'aging' => $aging,
        'inventory_value' => $inventoryValue,
        'inventory_retail_value' => $inventoryRetailValue,
        'low_stock' => $lowStock,
        'out_of_stock' => $outOfStock,
        'stock_by_category' => array_values($stockByCategory),
        'imports_by_supplier' => array_slice(array_values($importsBySupplier), 0, 10),
        'stock_adjustments' => $stockAdjustments,
        'stock_adjustment_increase' => $stockAdjustmentIncrease,
        'stock_adjustment_decrease' => $stockAdjustmentDecrease,
    ];
}
