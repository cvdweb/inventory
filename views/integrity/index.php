<?php
$reqBranch = $_GET['branch'] ?? firstAccessibleBranchId();
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }
$branchInfo = getBranchInfo($reqBranch);
$result = integrityCheckBranch($reqBranch);
$repairPlan = integrityBuildRepairPlan($reqBranch, $result);
$history = integrityGetHistory($reqBranch, 30);
$allowedTabs = ['overview', 'issues', 'history'];
$activeTab = in_array($_GET['tab'] ?? '', $allowedTabs, true) ? $_GET['tab'] : 'overview';
$selectedRunId = (string)($_GET['run'] ?? '');
$selectedRun = null;
foreach ($history as $run) {
    if (($run['id'] ?? '') === $selectedRunId) { $selectedRun = $run; break; }
}
if (!$selectedRun && $activeTab === 'history' && $history) $selectedRun = $history[0];
$manualIssueCount = count(array_filter($result['issues'], fn($issue) => empty($issue['repairable'])));
$healthState = empty($result['issues']) ? 'healthy' : (($result['counts']['error'] ?? 0) > 0 ? 'error' : 'warning');
$healthLabels = ['healthy'=>'Dữ liệu ổn định', 'error'=>'Cần xử lý', 'warning'=>'Cần kiểm tra'];
$pageTitle = 'Kiểm Tra Toàn Vẹn Dữ Liệu';
include BASE_PATH . '/views/layouts/header.php';
?>

<div class="page-header integrity-page-header">
  <div>
    <h2><i class="bi bi-shield-check me-2 text-primary"></i>Kiểm Tra Toàn Vẹn Dữ Liệu</h2>
    <p><?= htmlspecialchars($branchInfo['name']) ?> · Cập nhật lúc <?= date('H:i d/m/Y', strtotime($result['checked_at'])) ?></p>
  </div>
  <div class="integrity-header-actions">
    <a href="index.php?page=help&topic=integrity" class="btn btn-outline-secondary context-help-btn" title="Hướng dẫn kiểm tra toàn vẹn"><i class="bi bi-question-circle"></i><span class="context-help-label">Hướng dẫn</span></a>
    <form method="GET">
      <input type="hidden" name="page" value="integrity">
      <select class="form-select" name="branch" onchange="this.form.submit()" aria-label="Chọn chi nhánh">
        <?php foreach (getAccessibleBranches() as $id => $branch): ?>
        <option value="<?= htmlspecialchars($id) ?>" <?= $id === $reqBranch ? 'selected' : '' ?>><?= htmlspecialchars($branch['name'] ?? $id) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <form method="POST" action="index.php?page=integrity&branch=<?= urlencode($reqBranch) ?>&action=check">
      <?= csrfField() ?>
      <button class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Kiểm tra lại</button>
    </form>
  </div>
</div>

<section class="integrity-summary integrity-health-<?= $healthState ?>">
  <div class="integrity-health-icon"><i class="bi <?= $healthState === 'healthy' ? 'bi-check2-circle' : ($healthState === 'error' ? 'bi-exclamation-octagon' : 'bi-exclamation-triangle') ?>"></i></div>
  <div class="integrity-health-copy">
    <span>TRẠNG THÁI HIỆN TẠI</span>
    <strong><?= $healthLabels[$healthState] ?></strong>
    <p><?= empty($result['issues']) ? 'Không phát hiện sai lệch giữa kho, hóa đơn, công nợ và sổ thu chi.' : 'Hệ thống phát hiện '.count($result['issues']).' sai lệch; hãy xem chi tiết trước khi khắc phục.' ?></p>
  </div>
  <div class="integrity-metrics">
    <div><strong><?= (int)$result['total_records'] ?></strong><span>Bản ghi đã quét</span></div>
    <div><strong class="text-danger"><?= (int)$result['counts']['error'] ?></strong><span>Lỗi</span></div>
    <div><strong class="text-warning"><?= (int)$result['counts']['warning'] ?></strong><span>Cảnh báo</span></div>
    <div><strong class="text-primary"><?= count($repairPlan) ?></strong><span>Có thể tự sửa</span></div>
  </div>
</section>

<nav class="integrity-tabs" aria-label="Nội dung kiểm tra toàn vẹn">
  <a class="<?= $activeTab === 'overview' ? 'active' : '' ?>" href="index.php?page=integrity&branch=<?= urlencode($reqBranch) ?>&tab=overview"><i class="bi bi-grid"></i>Tổng quan</a>
  <a class="<?= $activeTab === 'issues' ? 'active' : '' ?>" href="index.php?page=integrity&branch=<?= urlencode($reqBranch) ?>&tab=issues"><i class="bi bi-list-check"></i>Sai lệch <span><?= count($result['issues']) ?></span></a>
  <a class="<?= $activeTab === 'history' ? 'active' : '' ?>" href="index.php?page=integrity&branch=<?= urlencode($reqBranch) ?>&tab=history"><i class="bi bi-clock-history"></i>Lịch sử <span><?= count($history) ?></span></a>
</nav>

<?php if ($activeTab === 'overview'): ?>
<section class="integrity-section">
  <div class="integrity-section-heading"><div><h3>Phạm vi đã kiểm tra</h3><p>Mỗi nhóm dữ liệu được đọc và đối chiếu với chứng từ liên quan.</p></div></div>
  <div class="integrity-scope-grid">
    <?php foreach ($result['scopes'] as $key => $scope): ?>
    <div class="integrity-scope-item">
      <i class="bi <?= ['products'=>'bi-box-seam','invoices'=>'bi-receipt','invoice_items'=>'bi-list-ul','returns'=>'bi-arrow-return-left','adjustments'=>'bi-clipboard-check','payments'=>'bi-cash-coin','cashbook'=>'bi-journal-check'][$key] ?? 'bi-database-check' ?>"></i>
      <div><strong><?= number_format((int)$scope['count'], 0, ',', '.') ?></strong><span><?= htmlspecialchars($scope['label']) ?></span></div>
      <i class="bi bi-check2 integrity-scope-check"></i>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="integrity-section integrity-repair-section">
  <div class="integrity-section-heading">
    <div><h3>Kế hoạch khắc phục</h3><p>Chỉ đồng bộ các liên kết tài chính có thể xác định chắc chắn từ chứng từ nguồn.</p></div>
    <?php if ($repairPlan): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#integrityRepairModal"><i class="bi bi-tools me-1"></i>Xem và khắc phục</button><?php endif; ?>
  </div>
  <div class="integrity-repair-summary">
    <div class="repair-summary-safe"><i class="bi bi-check2-shield"></i><div><strong><?= count($repairPlan) ?> sai lệch có thể tự sửa</strong><span>Tạo, cập nhật hoặc vô hiệu hóa liên kết sổ thu chi theo chứng từ gốc.</span></div></div>
    <div class="repair-summary-manual"><i class="bi bi-person-check"></i><div><strong><?= $manualIssueCount ?> sai lệch cần kiểm tra thủ công</strong><span>Hệ thống không tự đổi tồn kho, mã sản phẩm hay chứng từ bán hàng khi chưa đủ căn cứ.</span></div></div>
  </div>
  <?php if (!$repairPlan && empty($result['issues'])): ?><div class="integrity-empty"><i class="bi bi-check-circle"></i><strong>Không cần khắc phục</strong><span>Tất cả liên kết đang nhất quán.</span></div><?php elseif (!$repairPlan): ?><div class="integrity-empty warning"><i class="bi bi-eye"></i><strong>Không có lỗi phù hợp để tự sửa</strong><span>Hãy xem tab Sai lệch để xử lý các cảnh báo cần quyết định của người quản lý.</span></div><?php endif; ?>
</section>

<?php elseif ($activeTab === 'issues'): ?>
<section class="integrity-section">
  <div class="integrity-section-heading integrity-issues-heading">
    <div><h3>Danh sách sai lệch</h3><p>Giá trị hiện tại và trạng thái đúng dự kiến cho từng bản ghi.</p></div>
    <?php if ($result['issues']): ?><div class="integrity-filters"><select id="integritySeverityFilter" class="form-select"><option value="">Tất cả mức độ</option><option value="error">Lỗi</option><option value="warning">Cảnh báo</option></select><select id="integrityAreaFilter" class="form-select"><option value="">Tất cả nghiệp vụ</option><?php $areas=[]; foreach($result['issues'] as $issue) $areas[$issue['area']]= $issue['area_label']; foreach($areas as $key=>$label): ?><option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  </div>
  <?php if (!$result['issues']): ?>
  <div class="integrity-empty"><i class="bi bi-check-circle"></i><strong>Không phát hiện sai lệch</strong><span>Dữ liệu hiện tại đã vượt qua toàn bộ quy tắc kiểm tra.</span></div>
  <?php else: ?>
  <div class="table-responsive integrity-issues-table"><table class="table mb-0"><thead><tr><th>Mức độ</th><th>Nghiệp vụ</th><th>Đối tượng</th><th>Thông tin phát hiện</th><th>Trạng thái đúng</th><th>Xử lý</th></tr></thead><tbody>
    <?php foreach ($result['issues'] as $issue): ?><tr data-integrity-issue data-severity="<?= htmlspecialchars($issue['severity']) ?>" data-area="<?= htmlspecialchars($issue['area']) ?>"><td><span class="integrity-severity <?= $issue['severity'] ?>"><?= $issue['severity']==='error'?'Lỗi':'Cảnh báo' ?></span></td><td><?= htmlspecialchars($issue['area_label']) ?></td><td><strong><?= htmlspecialchars($issue['entity']) ?></strong><code><?= htmlspecialchars($issue['sourceId']) ?></code></td><td><?= htmlspecialchars($issue['message']) ?></td><td><?= htmlspecialchars($issue['expected']) ?></td><td><span class="integrity-action-type <?= !empty($issue['repairable'])?'automatic':'manual' ?>"><i class="bi <?= !empty($issue['repairable'])?'bi-magic':'bi-person' ?>"></i><?= !empty($issue['repairable'])?'Tự động':'Thủ công' ?></span></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="integrity-issue-cards"><?php foreach ($result['issues'] as $issue): ?><article data-integrity-issue data-severity="<?= htmlspecialchars($issue['severity']) ?>" data-area="<?= htmlspecialchars($issue['area']) ?>"><div class="integrity-issue-card-head"><span class="integrity-severity <?= $issue['severity'] ?>"><?= $issue['severity']==='error'?'Lỗi':'Cảnh báo' ?></span><span><?= htmlspecialchars($issue['area_label']) ?></span></div><h4><?= htmlspecialchars($issue['entity']) ?> <code><?= htmlspecialchars($issue['sourceId']) ?></code></h4><p><?= htmlspecialchars($issue['message']) ?></p><div><span>Trạng thái đúng</span><strong><?= htmlspecialchars($issue['expected']) ?></strong></div><span class="integrity-action-type <?= !empty($issue['repairable'])?'automatic':'manual' ?>"><i class="bi <?= !empty($issue['repairable'])?'bi-magic':'bi-person' ?>"></i><?= !empty($issue['repairable'])?'Có thể tự sửa':'Cần kiểm tra thủ công' ?></span></article><?php endforeach; ?></div>
  <div id="integrityNoFilterResult" class="integrity-empty compact" hidden><i class="bi bi-funnel"></i><strong>Không có kết quả phù hợp</strong></div>
  <?php endif; ?>
</section>

<?php else: ?>
<section class="integrity-history-layout">
  <div class="integrity-history-list">
    <div class="integrity-section-heading"><div><h3>Lịch sử kiểm tra</h3><p>Lưu tối đa 60 lần gần nhất.</p></div></div>
    <?php if (!$history): ?><div class="integrity-empty compact"><i class="bi bi-clock-history"></i><strong>Chưa có lịch sử</strong><span>Bấm Kiểm tra lại để lưu kết quả đầu tiên.</span></div><?php endif; ?>
    <?php foreach ($history as $run): $isSelected=($selectedRun['id']??'')===($run['id']??''); ?><a class="integrity-history-item <?= $isSelected?'active':'' ?>" href="index.php?page=integrity&branch=<?= urlencode($reqBranch) ?>&tab=history&run=<?= urlencode($run['id']) ?>"><i class="bi <?= ($run['type']??'check')==='repair'?'bi-tools':'bi-shield-check' ?>"></i><div><strong><?= ($run['type']??'check')==='repair'?'Khắc phục dữ liệu':'Kiểm tra dữ liệu' ?></strong><span><?= date('H:i d/m/Y', strtotime($run['created_at'])) ?> · <?= htmlspecialchars($run['created_by']??'System') ?></span></div><span class="integrity-history-count <?= (($run['counts_after']['error']??0)>0)?'has-error':'' ?>"><?= (int)($run['counts_after']['error']??0) ?> lỗi</span></a><?php endforeach; ?>
  </div>
  <div class="integrity-history-detail">
    <?php if ($selectedRun): $before=$selectedRun['counts_before']??['error'=>0,'warning'=>0]; $after=$selectedRun['counts_after']??$before; ?>
    <div class="integrity-history-title"><div><span><?= ($selectedRun['type']??'check')==='repair'?'KẾT QUẢ KHẮC PHỤC':'KẾT QUẢ KIỂM TRA' ?></span><h3><?= htmlspecialchars($selectedRun['summary']??'Kết quả kiểm tra') ?></h3><p>Mã lần chạy: <code><?= htmlspecialchars($selectedRun['id']) ?></code></p></div><span class="integrity-run-status <?= ($after['error']??0)>0?'warning':'success' ?>"><i class="bi <?= ($after['error']??0)>0?'bi-exclamation-triangle':'bi-check-circle' ?>"></i><?= ($after['error']??0)>0?'Còn sai lệch':'Hoàn tất' ?></span></div>
    <div class="integrity-before-after"><div><span>Trước xử lý</span><strong><?= (int)$before['error'] ?> lỗi · <?= (int)$before['warning'] ?> cảnh báo</strong></div><i class="bi bi-arrow-right"></i><div><span>Sau xử lý</span><strong><?= (int)$after['error'] ?> lỗi · <?= (int)$after['warning'] ?> cảnh báo</strong></div></div>
    <?php if (!empty($selectedRun['actions'])): ?><h4 class="integrity-detail-subtitle">Các thay đổi đã thực hiện</h4><div class="integrity-action-list"><?php foreach($selectedRun['actions'] as $action): ?><div><span class="integrity-action-status <?= htmlspecialchars($action['status']??'failed') ?>"><i class="bi <?= ($action['status']??'')==='resolved'?'bi-check2':'bi-x-lg' ?>"></i></span><div><strong><?= htmlspecialchars($action['operation_label']??'Đồng bộ') ?> · <?= htmlspecialchars($action['source_id']??'') ?></strong><span><?= htmlspecialchars($action['before']??'') ?></span><small><?= htmlspecialchars($action['result_message']??'') ?></small></div></div><?php endforeach; ?></div><?php endif; ?>
    <h4 class="integrity-detail-subtitle">Sai lệch được ghi nhận (<?= count($selectedRun['issues']??[]) ?>)</h4>
    <?php if (empty($selectedRun['issues'])): ?><div class="integrity-empty compact"><i class="bi bi-check-circle"></i><strong>Không phát hiện sai lệch</strong></div><?php else: ?><div class="integrity-run-issues"><?php foreach($selectedRun['issues'] as $issue): ?><div><span class="integrity-severity <?= htmlspecialchars($issue['severity']) ?>"><?= $issue['severity']==='error'?'Lỗi':'Cảnh báo' ?></span><div><strong><?= htmlspecialchars($issue['entity']??$issue['area_label']??'Dữ liệu') ?> · <?= htmlspecialchars($issue['sourceId']??'') ?></strong><span><?= htmlspecialchars($issue['message']??'') ?></span></div></div><?php endforeach; ?></div><?php endif; ?>
    <?php else: ?><div class="integrity-empty"><i class="bi bi-clock-history"></i><strong>Chọn một lần kiểm tra</strong><span>Chi tiết trước, sau và các thao tác khắc phục sẽ xuất hiện tại đây.</span></div><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($repairPlan): ?>
<div class="modal fade" id="integrityRepairModal" tabindex="-1" aria-labelledby="integrityRepairModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><div><div class="modal-kicker">XEM TRƯỚC THAY ĐỔI</div><h5 class="modal-title" id="integrityRepairModalLabel"><i class="bi bi-tools me-2"></i>Khắc phục liên kết dữ liệu</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
      <div class="modal-body"><div class="integrity-modal-note"><i class="bi bi-shield-check"></i><div><strong><?= count($repairPlan) ?> thay đổi an toàn đã được xác định</strong><span>Hệ thống chỉ cập nhật sổ thu chi theo hóa đơn, phiếu thu hoặc phiếu trả hàng gốc; không thay đổi tồn kho và chứng từ nguồn.</span></div></div><div class="integrity-plan-list"><?php foreach($repairPlan as $action): ?><div><span class="integrity-plan-operation <?= htmlspecialchars($action['operation']) ?>"><?= htmlspecialchars($action['operation_label']) ?></span><div><strong><?= htmlspecialchars($action['source_id']) ?></strong><span><?= htmlspecialchars($action['before']) ?></span><small>Sau xử lý: <?= htmlspecialchars($action['after']) ?></small></div></div><?php endforeach; ?></div></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Quay lại</button><form method="POST" action="index.php?page=integrity&branch=<?= urlencode($reqBranch) ?>&action=repair"><?= csrfField() ?><button class="btn btn-primary"><i class="bi bi-check2-shield me-1"></i>Xác nhận khắc phục</button></form></div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
(() => {
  const severity = document.getElementById('integritySeverityFilter');
  const area = document.getElementById('integrityAreaFilter');
  if (!severity || !area) return;
  const filter = () => {
    let visible = 0;
    document.querySelectorAll('[data-integrity-issue]').forEach(item => {
      const show = (!severity.value || item.dataset.severity === severity.value) && (!area.value || item.dataset.area === area.value);
      item.hidden = !show;
      if (show) visible++;
    });
    const empty = document.getElementById('integrityNoFilterResult');
    if (empty) empty.hidden = visible > 0;
  };
  severity.addEventListener('change', filter);
  area.addEventListener('change', filter);
})();
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
