<?php
$user = currentUser();
$role = $user['role'] ?? 'employee';
$branch = firstAccessibleBranchId();

$topics = [
    'invoices' => [
        'title'=>'Lập hóa đơn', 'icon'=>'bi-receipt', 'summary'=>'Bán hàng, trừ tồn kho, thanh toán và giao hàng.',
        'roles'=>['superadmin','admin','employee'], 'open_url'=>"index.php?page=invoice&branch=".urlencode($branch),
        'when'=>'Dùng mỗi khi bán hàng cho khách, dù khách lấy tại quầy hay cửa hàng giao tận nơi.',
        'steps'=>[
            'Chọn sản phẩm và nhập đúng số lượng, đơn giá bán thực tế.',
            'Chỉ mở phần Thông tin hóa đơn khi cần ghi khách hàng, đổi phương thức thanh toán hoặc giao hàng.',
            'Kiểm tra danh sách hàng, phí vận chuyển và tổng thanh toán trước khi lưu.',
            'Sau khi lưu, mở hóa đơn mới nhất để kiểm tra và in phiếu cho khách.',
        ],
        'rules'=>[
            'Lưu hóa đơn sẽ trừ tồn kho ngay.',
            'Tiền mặt hoặc chuyển khoản tạo khoản thu bán hàng trong Sổ thu chi; Công nợ tạo dư nợ khách hàng.',
            'Hóa đơn đã giao không sửa hoặc hủy trực tiếp; nếu khách trả hàng phải lập Phiếu trả hàng.',
            'Khách lấy tại quầy được xem là đã nhận hàng và có thể lập trả hàng sau đó.',
        ],
        'example'=>'Khách mua 10 bao xi măng, trả tiền mặt và tự chở: chọn hàng, để Khách lẻ, Tiền mặt, Lấy tại quầy rồi lưu hóa đơn.',
    ],
    'imports' => [
        'title'=>'Nhập hàng', 'icon'=>'bi-download', 'summary'=>'Ghi nhận hàng mua vào và tăng tồn kho.',
        'roles'=>['superadmin','admin','employee'], 'open_url'=>"index.php?page=imports&branch=".urlencode($branch),
        'when'=>'Dùng khi cửa hàng nhận hàng từ nhà cung cấp hoặc nhập bổ sung hàng bán.',
        'steps'=>[
            'Chọn nhóm hàng, sản phẩm và nhà cung cấp.',
            'Nhập số lượng thực nhận và giá nhập của đợt hàng.',
            'Kiểm tra tổng giá trị rồi lưu phiếu nhập.',
            'Với nhiều mặt hàng, tải file mẫu, nhập dữ liệu và kiểm tra bản xem trước trước khi xác nhận.',
        ],
        'rules'=>[
            'Phiếu nhập làm tăng tồn kho và cập nhật dữ liệu giá nhập phục vụ tính giá vốn.',
            'Không dùng Nhập hàng để xử lý hàng khách trả lại; phải dùng chức năng Trả hàng.',
            'Không dùng Nhập hàng để sửa sai tồn kho; phải dùng Kiểm kê và Điều chỉnh kho.',
            'Bản xem trước nhập hàng loạt chưa thay đổi dữ liệu cho đến khi xác nhận.',
        ],
        'example'=>'Nhà cung cấp giao 100 viên gạch và 20 bao xi măng: có thể lập một phiếu nhập hàng loạt, kiểm tra từng dòng rồi xác nhận.',
    ],
    'receivables' => [
        'title'=>'Công nợ', 'icon'=>'bi-wallet2', 'summary'=>'Theo dõi tiền khách còn nợ và các lần thanh toán.',
        'roles'=>['superadmin','admin','employee'], 'open_url'=>"index.php?page=receivables&branch=".urlencode($branch),
        'when'=>'Dùng để xem khách còn nợ bao nhiêu, ghi nhận thu nợ và in phiếu thu đối chiếu.',
        'steps'=>[
            'Khi bán chịu, chọn phương thức Công nợ trên hóa đơn và nhập đúng thông tin khách hàng.',
            'Khi khách trả tiền, mở Công nợ, chọn khách rồi nhập số tiền và phương thức thu.',
            'Kiểm tra số dư còn lại và in Phiếu thu giao khách ký hoặc giữ đối chiếu.',
            'Nếu ghi nhầm, chủ cửa hàng hủy phiếu thu; hệ thống giữ lịch sử thay vì xóa vật lý.',
        ],
        'rules'=>[
            'Chỉ hóa đơn chọn Công nợ mới làm tăng dư nợ.',
            'Số tiền thu không được lớn hơn dư nợ còn lại.',
            'Phiếu thu công nợ tự tạo khoản thu trong Sổ thu chi và không được sửa trực tiếp tại đó.',
            'Trả hàng có thể giảm công nợ; nếu khách đã trả dư, số âm thể hiện cửa hàng đang giữ tiền của khách.',
        ],
        'example'=>'Khách nợ 2.000.000 ₫ và trả 500.000 ₫: lập phiếu thu 500.000 ₫, công nợ còn 1.500.000 ₫.',
    ],
    'cashbook' => [
        'title'=>'Thu chi', 'icon'=>'bi-cash-stack', 'summary'=>'Theo dõi dòng tiền thực nhận và thực chi của cửa hàng.',
        'roles'=>['superadmin','admin','employee'], 'open_url'=>"index.php?page=cashbook&branch=".urlencode($branch),
        'when'=>'Dùng để ghi các khoản tiền thực tế vào hoặc ra khỏi quỹ và đối chiếu cuối ngày.',
        'steps'=>[
            'Chọn đúng Khoản thu hoặc Khoản chi, khoản mục và phương thức thanh toán.',
            'Nhập số tiền, ngày ghi nhận, người liên quan và nội dung dễ đối chiếu.',
            'Cuối ngày so sánh tổng thu, tổng chi và số tiền thực tế trong quỹ.',
            'Mở chứng từ nguồn khi cần sửa khoản tự động thay vì sửa trực tiếp trong Sổ thu chi.',
        ],
        'rules'=>[
            'Doanh thu không đồng nghĩa với tiền đã thu: bán công nợ có doanh thu nhưng chưa có dòng tiền.',
            'Hóa đơn tiền mặt/chuyển khoản, thu công nợ và hoàn tiền trả hàng tạo bút toán tự động.',
            'Bút toán tự động không sửa hoặc xóa trực tiếp để tránh lệch dữ liệu nguồn.',
            'Khoản thủ công phù hợp với tiền điện, lương, vận chuyển, chi nhà cung cấp hoặc thu/chi khác.',
        ],
        'example'=>'Chi 300.000 ₫ thuê bốc xếp: tạo Khoản chi, chọn khoản mục Vận chuyển/bốc xếp, ghi nội dung và người nhận.',
    ],
    'inventory' => [
        'title'=>'Kiểm kê kho', 'icon'=>'bi-clipboard-check', 'summary'=>'Đối chiếu tồn thực tế và điều chỉnh sai lệch có kiểm soát.',
        'roles'=>['superadmin','admin','employee'], 'open_url'=>"index.php?page=inventory&branch=".urlencode($branch),
        'when'=>'Dùng khi đếm hàng thực tế, phát hiện thừa thiếu, hàng hỏng, thất thoát hoặc sai số tồn kho.',
        'steps'=>[
            'Chọn Kiểm kê thực tế để nhập số lượng đếm được, hoặc chọn Điều chỉnh tăng/giảm cho nghiệp vụ rõ nguyên nhân.',
            'Thêm sản phẩm, nhập số lượng và kiểm tra chênh lệch hệ thống tính.',
            'Gửi phiếu chờ duyệt; tồn kho chưa thay đổi ở bước này.',
            'Chủ cửa hàng kiểm tra thực tế rồi duyệt. Khi đó tồn kho mới được cập nhật.',
        ],
        'rules'=>[
            'Nhân viên được lập phiếu nhưng chỉ chủ cửa hàng hoặc superadmin được duyệt và hoàn tác.',
            'Nếu tồn kho thay đổi sau lúc lập phiếu, hệ thống từ chối duyệt và yêu cầu kiểm kê lại.',
            'Điều chỉnh giảm phải ghi đúng lý do như hư hỏng, thất thoát hoặc dùng nội bộ.',
            'Không dùng kiểm kê thay cho nhập hàng, bán hàng hoặc trả hàng.',
        ],
        'example'=>'Hệ thống có 50 bao nhưng đếm thực tế 48 bao: lập Kiểm kê thực tế với tồn 48; sau khi duyệt, kho giảm 2 bao và lưu lịch sử.',
    ],
    'returns' => [
        'title'=>'Trả hàng', 'icon'=>'bi-arrow-return-left', 'summary'=>'Nhận hàng khách trả, xử lý tồn kho, tiền hoàn hoặc công nợ.',
        'roles'=>['superadmin','admin','employee'], 'open_url'=>"index.php?page=returns&branch=".urlencode($branch),
        'when'=>'Dùng khi khách trả một phần hoặc toàn bộ hàng đã nhận theo hóa đơn gốc.',
        'steps'=>[
            'Mở Trả hàng từ hóa đơn gốc hoặc chọn hóa đơn trong trang Trả hàng.',
            'Chọn từng mặt hàng, nhập số lượng trả và xác định hàng có nhập lại kho hay không.',
            'Chọn cách xử lý tiền: tiền mặt, chuyển khoản, giảm công nợ hoặc chưa chi tiền.',
            'Gửi chờ duyệt; chủ cửa hàng kiểm tra hàng và duyệt phiếu, sau đó in cho khách ký.',
        ],
        'rules'=>[
            'Không được trả vượt số lượng còn có thể trả trên hóa đơn.',
            'Chỉ chọn Nhập lại kho khi hàng còn nguyên và có thể bán tiếp; hàng hỏng không nhập kho.',
            'Duyệt phiếu mới cập nhật tồn kho, công nợ và khoản chi hoàn tiền.',
            'Hoàn tác sẽ đảo ngược tồn kho và bút toán tài chính; phải nhập lý do rõ ràng.',
        ],
        'example'=>'Khách trả 2 viên gạch còn nguyên của hóa đơn công nợ: chọn 2 viên, bật Nhập lại kho và chọn Giảm công nợ.',
    ],
    'reports' => [
        'title'=>'Báo cáo', 'icon'=>'bi-bar-chart', 'summary'=>'Đọc doanh thu, dòng tiền, công nợ và tình trạng kho.',
        'roles'=>['superadmin','admin'], 'open_url'=>"index.php?page=reports&branch=".urlencode($branch),
        'when'=>'Dùng để theo dõi kết quả kinh doanh và phát hiện vấn đề cần xử lý.',
        'steps'=>['Chọn đúng tháng báo cáo.','Xem Kinh doanh, sau đó đối chiếu Thu chi & Công nợ.','Kiểm tra tab Kho & Giao hàng để xử lý hàng sắp hết, giao trễ và điều chỉnh tồn.'],
        'rules'=>[
            'Doanh thu thuần bằng doanh thu gộp trừ giá trị hàng trả trong kỳ.',
            'Lãi gộp là số ước tính theo giá vốn lưu tại thời điểm bán; không phải lợi nhuận kế toán cuối cùng.',
            'Dòng tiền ròng lấy từ Sổ thu chi, không lấy trực tiếp từ doanh thu.',
        ],
        'example'=>'Doanh thu gộp 20 triệu và trả hàng 1 triệu thì doanh thu thuần là 19 triệu; tiền thực thu có thể khác nếu có bán công nợ.',
    ],
    'backup' => [
        'title'=>'Sao lưu & phục hồi', 'icon'=>'bi-shield-lock', 'summary'=>'Tạo bản sao dữ liệu và phục hồi khi có sự cố.',
        'roles'=>['superadmin','admin'], 'open_url'=>'index.php?page=backup',
        'when'=>'Sao lưu trước khi cập nhật hệ thống, chuyển hosting hoặc thực hiện thay đổi dữ liệu lớn.',
        'steps'=>['Bấm Sao lưu ngay và chờ hệ thống tạo file hoàn chỉnh.','Tải một bản về máy hoặc nơi lưu trữ độc lập.','Khi phục hồi, chọn đúng bản sao và đọc kỹ thông tin kiểm tra trước khi xác nhận.','Sau phục hồi, kiểm tra sản phẩm, hóa đơn, công nợ và đăng nhập.'],
        'rules'=>[
            'Phục hồi thay thế dữ liệu hiện tại bằng dữ liệu trong bản sao; không phải thao tác gộp dữ liệu.',
            'Luôn tạo thêm một bản sao hiện trạng trước khi phục hồi.',
            'Khi cập nhật code lên hosting, không ghi đè thư mục data đang vận hành.',
            'File sao lưu phải được giữ ngoài thư mục web công khai.',
        ],
        'example'=>'Trước khi deploy phiên bản mới: sao lưu trên hosting, tải file về máy, chỉ cập nhật code rồi chạy kiểm tra toàn vẹn.',
    ],
    'integrity' => [
        'title'=>'Kiểm tra toàn vẹn', 'icon'=>'bi-shield-check', 'summary'=>'Phát hiện liên kết sai giữa hóa đơn, kho, công nợ và thu chi.',
        'roles'=>['superadmin','admin'], 'open_url'=>"index.php?page=integrity&branch=".urlencode($branch),
        'when'=>'Dùng sau phục hồi, sau deploy hoặc khi thấy số liệu giữa các phân hệ không khớp.',
        'steps'=>['Chọn chi nhánh và chạy kiểm tra.','Đọc lỗi màu đỏ trước, cảnh báo màu vàng sau.','Chỉ chạy sửa liên kết tự động khi đã có bản sao lưu.','Kiểm tra lại cho đến khi không còn lỗi nghiêm trọng.'],
        'rules'=>['Không sửa trực tiếp file JSON để xử lý lỗi.','Sửa liên kết chỉ đồng bộ chứng từ; không tự đoán lại số lượng tồn kho thực tế.','Nếu tồn thực tế sai, dùng chức năng Kiểm kê kho.'],
        'example'=>'Hóa đơn tiền mặt thiếu khoản thu sẽ được phát hiện; chức năng sửa liên kết có thể tạo lại bút toán từ hóa đơn gốc.',
    ],
    'license' => [
        'title'=>'Quản lý giấy phép', 'icon'=>'bi-key', 'summary'=>'Thiết lập thời hạn sử dụng và lịch sử thanh toán khách hàng.',
        'roles'=>['superadmin'], 'open_url'=>'index.php?page=license',
        'when'=>'Dùng khi bàn giao hệ thống, gia hạn gói hoặc khóa quyền ghi dữ liệu.',
        'steps'=>['Thiết lập thông tin khách hàng và ngày bắt đầu tính phí.','Chọn chế độ Cơ bản, Tiêu chuẩn hoặc Đầy đủ phù hợp với quy trình của khách.','Chọn gói khách thanh toán và ghi nhận ngày thanh toán.','Kiểm tra ngày hết hạn hệ thống tính nối tiếp.','Chỉ khóa quyền ghi khi cần; khách vẫn có thể xem và sao lưu theo chính sách.'],
        'rules'=>[
            'Ngày bắt đầu tính phí là mốc bắt đầu chu kỳ sử dụng, không phải ngày khách chuyển tiền.',
            'Ngày thanh toán chỉ lưu lịch sử thu tiền.',
            'Gói 6 tháng giảm tiền 1 tháng nghĩa là dùng 6 tháng nhưng tính tiền 5 tháng; gói 12 tháng dùng 12 tháng nhưng tính tiền 10 tháng.',
            'Các gói được cộng nối tiếp từ ngày hết hạn hiện tại.',
            'Đổi chế độ chỉ thu gọn chức năng, không xóa dữ liệu; hệ thống sẽ chặn nếu còn công nợ hoặc phiếu kiểm kê chờ duyệt.',
        ],
        'example'=>'Khách bắt đầu 01/05, mua gói 6 tháng ngày 19/06: thời hạn vẫn tính từ 01/05 đến hết chu kỳ 6 tháng, tiền thu bằng 5 tháng phí.',
    ],
];

$topics = array_filter($topics, fn($topic)=>in_array($role,$topic['roles'],true));
$topicFeatures=['receivables'=>'receivables','inventory'=>'inventory','reports'=>'reports','cashbook'=>'cashbook','integrity'=>'integrity'];
$topics=array_filter($topics,fn($topic,$key)=>!isset($topicFeatures[$key])||featureEnabled($topicFeatures[$key]),ARRAY_FILTER_USE_BOTH);
if(!featureEnabled('bulk_import') && isset($topics['imports'])){
    $topics['imports']['steps']=array_values(array_filter($topics['imports']['steps'],fn($step)=>!str_contains($step,'file mẫu')));
    $topics['imports']['rules']=array_values(array_filter($topics['imports']['rules'],fn($rule)=>!str_contains($rule,'Bản xem trước')));
}
$selectedKey = (string)($_GET['topic'] ?? '');
$selected = $topics[$selectedKey] ?? null;
$pageTitle = $selected ? 'Hướng Dẫn — '.$selected['title'] : 'Hướng Dẫn Sử Dụng';
include BASE_PATH . '/views/layouts/header.php';
?>

<style>
.help-header{align-items:end;display:flex;gap:16px;justify-content:space-between}.help-search{max-width:360px;position:relative;width:100%}.help-search i{color:#9ca3af;left:12px;position:absolute;top:50%;transform:translateY(-50%)}.help-search input{padding-left:36px}.help-grid{display:grid;gap:12px;grid-template-columns:repeat(3,minmax(0,1fr))}.help-topic{border:1px solid #e5e7eb;border-radius:8px;color:inherit;display:flex;gap:12px;min-height:116px;padding:16px;text-decoration:none;transition:border-color .15s,box-shadow .15s}.help-topic:hover{border-color:#f59e0b;box-shadow:0 3px 12px rgba(17,24,39,.07);color:inherit}.help-topic-icon{align-items:center;background:#fffbeb;border-radius:7px;color:#d97706;display:flex;flex:0 0 38px;font-size:18px;height:38px;justify-content:center}.help-topic h3{font-size:14px;margin:1px 0 5px}.help-topic p{color:#6b7280;font-size:12px;line-height:1.5;margin:0}.help-topic-arrow{color:#9ca3af;margin-left:auto}.help-detail{margin:0 auto;max-width:960px}.help-detail-nav{align-items:center;display:flex;gap:10px;justify-content:space-between;margin-bottom:14px}.help-detail-title{align-items:center;display:flex;gap:12px}.help-detail-title .help-topic-icon{height:44px;width:44px}.help-detail-title h2{font-size:22px;margin:0}.help-detail-title p{color:#6b7280;font-size:12px;margin:3px 0 0}.help-intro{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;line-height:1.6;padding:14px 16px}.help-section{border-bottom:1px solid #e5e7eb;padding:18px 0}.help-section:last-child{border-bottom:0}.help-section h3{font-size:14px;margin:0 0 12px}.help-steps{counter-reset:helpstep;display:grid;gap:10px;list-style:none;margin:0;padding:0}.help-steps li{align-items:flex-start;display:flex;font-size:13px;gap:10px;line-height:1.55}.help-steps li:before{align-items:center;background:#111827;border-radius:50%;color:#fff;content:counter(helpstep);counter-increment:helpstep;display:flex;flex:0 0 23px;font-size:11px;font-weight:800;height:23px;justify-content:center}.help-rules{display:grid;gap:8px;list-style:none;margin:0;padding:0}.help-rules li{background:#fff;border:1px solid #e5e7eb;border-radius:7px;font-size:12.5px;line-height:1.5;padding:10px 12px 10px 34px;position:relative}.help-rules li:before{color:#d97706;content:'\F26A';font-family:'bootstrap-icons';font-size:14px;left:11px;position:absolute;top:10px}.help-example{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;color:#78350f;font-size:12.5px;line-height:1.55;padding:13px 14px}.help-empty{display:none;text-align:center}.help-doc-link{font-size:12px}@media(max-width:900px){.help-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.help-header{align-items:stretch;flex-direction:column}.help-grid{grid-template-columns:1fr}.help-topic{min-height:96px;padding:13px}.help-detail-nav{align-items:flex-start;flex-direction:column}.help-detail-nav>.btn{width:100%}.help-detail-title h2{font-size:19px}}
</style>

<?php if($selected): ?>
<div class="help-detail">
  <div class="help-detail-nav">
    <div class="help-detail-title"><span class="help-topic-icon"><i class="bi <?= $selected['icon'] ?>"></i></span><div><h2><?= htmlspecialchars($selected['title']) ?></h2><p><?= htmlspecialchars($selected['summary']) ?></p></div></div>
    <div class="d-flex gap-2 w-100-mobile"><a class="btn btn-sm btn-outline-secondary" href="index.php?page=help"><i class="bi bi-grid me-1"></i>Tất cả hướng dẫn</a><a class="btn btn-sm btn-primary" href="<?= htmlspecialchars($selected['open_url']) ?>"><i class="bi bi-box-arrow-up-right me-1"></i>Mở chức năng</a></div>
  </div>
  <div class="card"><div class="card-body">
    <div class="help-intro"><strong>Khi nào sử dụng?</strong><br><?= htmlspecialchars($selected['when']) ?></div>
    <section class="help-section"><h3><i class="bi bi-list-ol me-2 text-primary"></i>Quy trình chuẩn</h3><ol class="help-steps"><?php foreach($selected['steps'] as $step): ?><li><?= htmlspecialchars($step) ?></li><?php endforeach; ?></ol></section>
    <section class="help-section"><h3><i class="bi bi-shield-check me-2 text-primary"></i>Logic và nguyên tắc cần nhớ</h3><ul class="help-rules"><?php foreach($selected['rules'] as $rule): ?><li><?= htmlspecialchars($rule) ?></li><?php endforeach; ?></ul></section>
    <section class="help-section"><h3><i class="bi bi-lightbulb me-2 text-primary"></i>Ví dụ thực tế</h3><div class="help-example"><?= htmlspecialchars($selected['example']) ?></div></section>
  </div></div>
</div>
<?php else: ?>
<div class="page-header help-header"><div><h2><i class="bi bi-book-fill me-2 text-primary"></i>Hướng Dẫn Sử Dụng</h2><p>Tra cứu quy trình và logic vận hành theo từng chức năng</p></div><div class="help-search"><i class="bi bi-search"></i><input id="helpSearch" class="form-control" placeholder="Tìm: công nợ, trả hàng, kiểm kê..."></div></div>
<div class="help-grid" id="helpGrid">
<?php foreach($topics as $key=>$topic): ?><a class="help-topic" href="index.php?page=help&topic=<?= urlencode($key) ?>" data-search="<?= htmlspecialchars(mb_strtolower($topic['title'].' '.$topic['summary'],'UTF-8')) ?>"><span class="help-topic-icon"><i class="bi <?= $topic['icon'] ?>"></i></span><div><h3><?= htmlspecialchars($topic['title']) ?></h3><p><?= htmlspecialchars($topic['summary']) ?></p></div><i class="bi bi-chevron-right help-topic-arrow"></i></a><?php endforeach; ?>
</div>
<div class="help-empty empty-state" id="helpEmpty"><i class="bi bi-search"></i><p>Không tìm thấy hướng dẫn phù hợp</p></div>
<?php if($role==='superadmin' && file_exists(BASE_PATH.'/docs/huong_dan_superadmin.docx')): ?><div class="mt-3 text-end"><a class="btn btn-sm btn-outline-secondary help-doc-link" href="docs/huong_dan_superadmin.docx" download><i class="bi bi-file-earmark-word me-1"></i>Tải tài liệu quản trị hệ thống</a></div><?php endif; ?>
<script>document.getElementById('helpSearch')?.addEventListener('input',function(){const q=this.value.trim().toLocaleLowerCase('vi');let visible=0;document.querySelectorAll('#helpGrid .help-topic').forEach(card=>{const show=!q||card.dataset.search.includes(q);card.style.display=show?'':'none';if(show)visible++});document.getElementById('helpEmpty').style.display=visible?'none':'block'});</script>
<?php endif; ?>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
