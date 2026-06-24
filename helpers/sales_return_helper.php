<?php

function salesReturnFile(string $branch, string $yearMonth = ''): string
{
    return DATA_PATH . "/{$branch}/sales_returns_" . ($yearMonth ?: date('Y_m')) . '.json';
}

function salesReturnFiles(string $branch): array
{
    $files = glob(DATA_PATH . "/{$branch}/sales_returns_*.json") ?: [];
    rsort($files);
    return $files;
}

function salesReturnInvoiceLineKey(array $item, int $index): string
{
    return (string)($item['product_code'] ?? $item['code'] ?? '') . '#' . $index;
}

function getSalesReturns(string $branch, bool $includeInactive = true): array
{
    $returns = [];
    foreach (salesReturnFiles($branch) as $file) {
        foreach (readJson($file) as $row) {
            if (!$includeInactive && ($row['status'] ?? '') !== 'approved') continue;
            $returns[] = $row;
        }
    }
    usort($returns, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return $returns;
}

function salesReturnFind(string $branch, string $id): ?array
{
    foreach (salesReturnFiles($branch) as $file) {
        foreach (readJson($file) as $index => $row) {
            if (($row['id'] ?? '') === $id) return ['file'=>$file,'index'=>$index,'record'=>$row];
        }
    }
    return null;
}

function salesReturnQuantitiesForInvoice(string $branch, string $invoiceId, bool $includeDraft = false, string $excludeId = ''): array
{
    $result = ['items'=>[], 'shipping_refund'=>0.0, 'total'=>0.0];
    foreach (getSalesReturns($branch) as $return) {
        if (($return['invoice_id'] ?? '') !== $invoiceId || ($return['id'] ?? '') === $excludeId) continue;
        $status = $return['status'] ?? '';
        if ($status !== 'approved' && !($includeDraft && $status === 'draft')) continue;
        foreach ($return['items'] ?? [] as $item) {
            $key = (string)($item['invoice_line_key'] ?? $item['product_code'] ?? '');
            $result['items'][$key] = ($result['items'][$key] ?? 0) + (float)($item['qty'] ?? 0);
        }
        $result['shipping_refund'] += (float)($return['shipping_refund'] ?? 0);
        $result['total'] += (float)($return['refund_total'] ?? 0);
    }
    return $result;
}

function salesReturnApprovedByInvoice(string $branch): array
{
    $map = [];
    foreach (getSalesReturns($branch, false) as $return) {
        $invoiceId = (string)($return['invoice_id'] ?? '');
        if ($invoiceId === '') continue;
        if (!isset($map[$invoiceId])) $map[$invoiceId] = ['total'=>0.0,'items'=>[],'shipping_refund'=>0.0];
        $map[$invoiceId]['total'] += (float)($return['refund_total'] ?? 0);
        $map[$invoiceId]['shipping_refund'] += (float)($return['shipping_refund'] ?? 0);
        foreach ($return['items'] ?? [] as $item) {
            $key = (string)($item['invoice_line_key'] ?? $item['product_code'] ?? '');
            $map[$invoiceId]['items'][$key] = ($map[$invoiceId]['items'][$key] ?? 0) + (float)($item['qty'] ?? 0);
        }
    }
    return $map;
}

function salesReturnCreditCashRefundsByCustomer(string $branch): array
{
    $refunds = [];
    foreach (getSalesReturns($branch, false) as $return) {
        if (($return['original_payment'] ?? '') !== 'credit') continue;
        if (!in_array($return['refund_method'] ?? '', ['cash', 'transfer'], true)) continue;
        $key = receivableCustomerKey($return['customer'] ?? '', $return['phone'] ?? '');
        $refunds[$key] = ($refunds[$key] ?? 0) + (float)($return['refund_total'] ?? 0);
    }
    return $refunds;
}

function salesReturnCreate(string $branch, array $post, bool $insideTransaction = false): array
{
    if (!$insideTransaction) return withBranchTransaction($branch, fn() => salesReturnCreate($branch, $post, true));
    if (!canAccessBranch($branch)) return ['success'=>false,'message'=>'Không có quyền truy cập chi nhánh này'];
    $invoiceId = trim((string)($post['invoice_id'] ?? ''));
    $invoice = getInvoiceById($branch, $invoiceId);
    if (!$invoice || invoiceIsCancelled($invoice)) return ['success'=>false,'message'=>'Không tìm thấy hóa đơn hợp lệ'];
    if (!in_array($invoice['delivery_status'] ?? 'self_pickup', ['delivered', 'self_pickup'], true)) return ['success'=>false,'message'=>'Chỉ lập trả hàng cho hóa đơn đã giao hoặc khách đã nhận tại cửa hàng'];

    $submitted = json_decode((string)($post['items'] ?? '[]'), true);
    if (!is_array($submitted) || !$submitted) return ['success'=>false,'message'=>'Vui lòng chọn ít nhất một mặt hàng trả'];
    $sold = [];
    foreach ($invoice['items'] ?? [] as $index => $item) {
        $key = salesReturnInvoiceLineKey($item, $index);
        $sold[$key] = ['item' => $item, 'index' => $index, 'key' => $key];
    }
    $reserved = salesReturnQuantitiesForInvoice($branch, $invoiceId, true);
    $items = [];
    $seen = [];
    $merchandiseRefund = 0.0;
    foreach ($submitted as $item) {
        $code = trim((string)($item['code'] ?? ''));
        $qty = (float)($item['qty'] ?? 0);
        $lineIndex = filter_var($item['line'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $lineKey = $lineIndex !== false ? $code . '#' . $lineIndex : '';
        if ($lineKey === '' || !isset($sold[$lineKey])) {
            foreach ($sold as $candidateKey => $candidate) {
                if (($candidate['item']['product_code'] ?? '') === $code && !isset($seen[$candidateKey])) { $lineKey = $candidateKey; break; }
            }
        }
        if ($code === '' || $qty <= 0 || isset($seen[$lineKey]) || !isset($sold[$lineKey])) continue;
        $soldItem = $sold[$lineKey]['item'];
        $available = (float)($soldItem['qty'] ?? 0) - (float)($reserved['items'][$lineKey] ?? $reserved['items'][$code] ?? 0);
        if ($qty > $available + 0.000001) return ['success'=>false,'message'=>"Số lượng trả {$soldItem['product_name']} vượt số lượng còn có thể trả"];
        $price = (float)($soldItem['price_out'] ?? 0);
        $lineRefund = $qty * $price;
        $product = productGetByCode($branch, $code, true);
        $costPrice = array_key_exists('cost_price', $soldItem) ? (float)$soldItem['cost_price'] : (float)($product['price_in'] ?? 0);
        $items[] = [
            'product_code'=>$code,
            'invoice_line'=>(int)$sold[$lineKey]['index'],
            'invoice_line_key'=>$lineKey,
            'product_name'=>$soldItem['product_name'] ?? $code,
            'unit'=>$soldItem['unit'] ?? '',
            'qty'=>$qty,
            'price_out'=>$price,
            'refund_amount'=>$lineRefund,
            'cost_price'=>$costPrice,
            'cost_total'=>$qty * $costPrice,
            'restock'=>!empty($item['restock']),
        ];
        $merchandiseRefund += $lineRefund;
        $seen[$lineKey] = true;
    }
    if (!$items) return ['success'=>false,'message'=>'Không có mặt hàng trả hợp lệ'];

    $shippingAvailable = max(0, (float)($invoice['shipping_fee'] ?? 0) - (float)$reserved['shipping_refund']);
    $shippingRefund = max(0, (float)($post['shipping_refund'] ?? 0));
    if ($shippingRefund > $shippingAvailable + 0.000001) return ['success'=>false,'message'=>'Phí vận chuyển hoàn lại vượt số tiền còn có thể hoàn'];
    $refundMethod = (string)($post['refund_method'] ?? 'none');
    if (!in_array($refundMethod, ['none','cash','transfer','account_credit'], true)) $refundMethod = 'none';
    if (($invoice['payment'] ?? '') !== 'credit' && $refundMethod === 'account_credit') {
        return ['success'=>false,'message'=>'Chỉ hóa đơn công nợ mới được giảm công nợ'];
    }
    $reason = trim((string)($post['reason'] ?? ''));
    if ($reason === '') return ['success'=>false,'message'=>'Vui lòng nhập lý do trả hàng'];

    $user = currentUser();
    $record = [
        'id'=>'RET-' . branchCodePrefix(getBranchInfo($branch)['short'] ?? 'CN') . '-' . date('YmdHis') . '-' . random_int(100,999),
        'branch'=>$branch,
        'invoice_id'=>$invoiceId,
        'invoice_created_at'=>$invoice['created_at'] ?? '',
        'original_payment'=>$invoice['payment'] ?? '',
        'customer'=>$invoice['customer'] ?? 'Khách lẻ',
        'phone'=>$invoice['phone'] ?? '',
        'items'=>$items,
        'merchandise_refund'=>$merchandiseRefund,
        'shipping_refund'=>$shippingRefund,
        'refund_total'=>$merchandiseRefund + $shippingRefund,
        'refund_method'=>$refundMethod,
        'reason'=>$reason,
        'note'=>trim((string)($post['note'] ?? '')),
        'status'=>'draft',
        'created_by'=>$user['name'] ?? 'System',
        'created_by_username'=>$user['username'] ?? '',
        'created_at'=>date('Y-m-d H:i:s'),
    ];
    $file = salesReturnFile($branch);
    $saved = updateJson($file, function(array $rows) use($record): array {$rows[]=$record;return $rows;});
    return $saved ? ['success'=>true,'message'=>'Đã lập phiếu trả hàng chờ duyệt','id'=>$record['id']] : ['success'=>false,'message'=>'Không lưu được phiếu trả hàng'];
}

function salesReturnApplyStock(string $branch, array $items, bool $reverse = false): array
{
    $prepared = [];
    foreach ($items as $item) {
        if (empty($item['restock'])) continue;
        $product = productGetByCode($branch, (string)($item['product_code'] ?? ''), true);
        if (!$product) return ['success'=>false,'message'=>'Sản phẩm ' . ($item['product_code'] ?? '') . ' không còn trong kho. Hãy bỏ chọn nhập lại kho cho mặt hàng này'];
        $qty = (float)($item['qty'] ?? 0);
        if ($reverse && (float)($product['stock'] ?? 0) < $qty) return ['success'=>false,'message'=>"Không đủ tồn {$product['name']} để hoàn tác phiếu trả"];
        $prepared[]=['code'=>$product['code'],'qty'=>$qty];
    }
    $applied=[];
    foreach($prepared as $item){
        $type=$reverse?'out':'in';
        if(!updateStock($branch,$item['code'],$item['qty'],$type)){
            foreach(array_reverse($applied) as $done) updateStock($branch,$done['code'],$done['qty'],$reverse?'in':'out');
            return ['success'=>false,'message'=>'Không cập nhật được tồn kho; thay đổi đã được hoàn tác'];
        }
        $applied[]=$item;
    }
    return ['success'=>true,'applied'=>$applied];
}

function salesReturnApprove(string $branch, string $id, bool $insideTransaction = false): array
{
    if (!$insideTransaction) return withBranchTransaction($branch, fn()=>salesReturnApprove($branch,$id,true));
    if (!in_array(currentUser()['role'] ?? '', ['superadmin','admin'], true)) return ['success'=>false,'message'=>'Chỉ chủ cửa hàng được duyệt trả hàng'];
    $found=salesReturnFind($branch,$id);
    if(!$found||($found['record']['status']??'')!=='draft') return ['success'=>false,'message'=>'Phiếu không tồn tại hoặc không còn chờ duyệt'];
    $return=$found['record'];
    $invoice=getInvoiceById($branch,$return['invoice_id']??'');
    if(!$invoice||invoiceIsCancelled($invoice)||!in_array($invoice['delivery_status']??'self_pickup',['delivered','self_pickup'],true)) return ['success'=>false,'message'=>'Hóa đơn gốc không còn hợp lệ để trả hàng'];
    $approved=salesReturnQuantitiesForInvoice($branch,$return['invoice_id'],false,$id);
    $sold=[];foreach($invoice['items']??[] as $index=>$item)$sold[salesReturnInvoiceLineKey($item,$index)]=$item;
    foreach($return['items']??[] as $item){$code=$item['product_code']??'';$key=$item['invoice_line_key']??$code;$available=(float)($sold[$key]['qty']??0)-(float)($approved['items'][$key]??$approved['items'][$code]??0);if((float)$item['qty']>$available+0.000001)return ['success'=>false,'message'=>'Số lượng trả không còn hợp lệ: '.($item['product_name']??$code)];}
    $shippingAvailable=max(0,(float)($invoice['shipping_fee']??0)-(float)($approved['shipping_refund']??0));
    if((float)($return['shipping_refund']??0)>$shippingAvailable+0.000001)return ['success'=>false,'message'=>'Phí vận chuyển hoàn lại không còn hợp lệ'];

    $stock=salesReturnApplyStock($branch,$return['items']??[]);
    if(!$stock['success'])return $stock;
    $approvedAt=date('Y-m-d H:i:s');
    $approvedReturn=$return;
    $approvedReturn['status']='approved';$approvedReturn['approved_by']=currentUser()['name']??'System';$approvedReturn['approved_at']=$approvedAt;
    $saved=updateJson($found['file'],function(array $rows)use($id,$approvedReturn):array{foreach($rows as &$row){if(($row['id']??'')===$id){$row=$approvedReturn;break;}}unset($row);return $rows;});
    if(!$saved){salesReturnApplyStock($branch,$return['items']??[],true);return ['success'=>false,'message'=>'Không lưu được trạng thái duyệt; tồn kho đã hoàn tác'];}
    if(function_exists('cashbookSyncSalesReturn')){
        $sync=cashbookSyncSalesReturn($branch,$approvedReturn);
        if(!($sync['success']??false)){
            updateJson($found['file'],function(array $rows)use($id,$return):array{foreach($rows as &$row){if(($row['id']??'')===$id){$row=$return;break;}}unset($row);return $rows;});
            salesReturnApplyStock($branch,$return['items']??[],true);
            return ['success'=>false,'message'=>'Không đồng bộ được hoàn tiền với sổ thu chi; phiếu vẫn ở trạng thái nháp'];
        }
    }
    return ['success'=>true,'message'=>'Đã duyệt trả hàng, cập nhật tồn kho và tài chính'];
}

function salesReturnCancel(string $branch,string $id,bool $insideTransaction=false):array
{
    if(!$insideTransaction)return withBranchTransaction($branch,fn()=>salesReturnCancel($branch,$id,true));
    $found=salesReturnFind($branch,$id);if(!$found||($found['record']['status']??'')!=='draft')return ['success'=>false,'message'=>'Chỉ được hủy phiếu nháp'];
    $user=currentUser();$allowed=in_array($user['role']??'', ['superadmin','admin'],true)||($found['record']['created_by_username']??'')===($user['username']??'');if(!$allowed)return ['success'=>false,'message'=>'Không có quyền hủy phiếu'];
    $saved=updateJson($found['file'],function(array $rows)use($id,$user):array{foreach($rows as &$row){if(($row['id']??'')===$id){$row['status']='cancelled';$row['cancelled_by']=$user['name']??'System';$row['cancelled_at']=date('Y-m-d H:i:s');break;}}unset($row);return $rows;});
    return $saved?['success'=>true,'message'=>'Đã hủy phiếu trả hàng nháp']:['success'=>false,'message'=>'Không hủy được phiếu'];
}

function salesReturnReverse(string $branch,string $id,string $reason,bool $insideTransaction=false):array
{
    if(!$insideTransaction)return withBranchTransaction($branch,fn()=>salesReturnReverse($branch,$id,$reason,true));
    if(!in_array(currentUser()['role']??'', ['superadmin','admin'],true))return ['success'=>false,'message'=>'Chỉ chủ cửa hàng được hoàn tác'];
    if(trim($reason)==='')return ['success'=>false,'message'=>'Vui lòng nhập lý do hoàn tác'];
    $found=salesReturnFind($branch,$id);if(!$found||($found['record']['status']??'')!=='approved')return ['success'=>false,'message'=>'Chỉ được hoàn tác phiếu đã duyệt'];
    $return=$found['record'];$stock=salesReturnApplyStock($branch,$return['items']??[],true);if(!$stock['success'])return $stock;
    $reversedReturn=$return;
    $reversedReturn['status']='reversed';$reversedReturn['reversed_by']=currentUser()['name']??'System';$reversedReturn['reversed_at']=date('Y-m-d H:i:s');$reversedReturn['reverse_reason']=trim($reason);
    $saved=updateJson($found['file'],function(array $rows)use($id,$reversedReturn):array{foreach($rows as &$row){if(($row['id']??'')===$id){$row=$reversedReturn;break;}}unset($row);return $rows;});
    if(!$saved){salesReturnApplyStock($branch,$return['items']??[]);return ['success'=>false,'message'=>'Không lưu được hoàn tác; tồn kho đã khôi phục'];}
    if(function_exists('cashbookSyncSalesReturn')){
        $sync=cashbookSyncSalesReturn($branch,$reversedReturn);
        if(!($sync['success']??false)){
            updateJson($found['file'],function(array $rows)use($id,$return):array{foreach($rows as &$row){if(($row['id']??'')===$id){$row=$return;break;}}unset($row);return $rows;});
            salesReturnApplyStock($branch,$return['items']??[]);
            cashbookSyncSalesReturn($branch,$return);
            return ['success'=>false,'message'=>'Không hoàn tác được khoản chi; phiếu và tồn kho đã được khôi phục'];
        }
    }
    return ['success'=>true,'message'=>'Đã hoàn tác phiếu trả hàng'];
}

function salesReturnMonthly(string $branch,string $yearMonth):array
{
    $prefix=str_replace('_','-',$yearMonth);$rows=[];$total=0.0;$restockedCost=0.0;
    foreach(getSalesReturns($branch,false) as $return){if(!str_starts_with($return['approved_at']??'',$prefix))continue;$rows[]=$return;$total+=(float)($return['refund_total']??0);foreach($return['items']??[] as $item)if(!empty($item['restock']))$restockedCost+=(float)($item['cost_total']??0);}
    return ['rows'=>$rows,'total'=>$total,'restocked_cost'=>$restockedCost,'count'=>count($rows)];
}
