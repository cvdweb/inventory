<?php
$reqBranch = $_GET['branch'] ?? firstAccessibleBranchId();
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }

$branchInfo = getBranchInfo($reqBranch);
$yearMonth = $_GET['ym'] ?? date('Y_m');
if (!preg_match('/^\d{4}_\d{2}$/', $yearMonth)) $yearMonth = date('Y_m');

cashbookSyncReceivablePayments($reqBranch, $yearMonth);
$entries = getCashbookEntries($reqBranch, $yearMonth);
$summary = cashbookSummary($entries);
$categories = cashbookCategories();
$methods = cashbookMethods();
$currentRole = currentUser()['role'] ?? '';
$canIncome = cashbookCanCreateType('income');
$canExpense = cashbookCanCreateType('expense');
$canDelete = cashbookCanDelete();
$monthIso = str_replace('_', '-', $yearMonth) . '-01';
$pageTitle = 'Quản Lý Thu Chi — ' . $branchInfo['name'];
include BASE_PATH . '/views/layouts/header.php';
?>

<style>
.cashbook-toolbar {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 10px;
  align-items: end;
}
.cashbook-type {
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  font-weight: 800;
  padding: 5px 9px;
}
.cashbook-income { background: rgba(16,185,129,.12); color: #047857; }
.cashbook-expense { background: rgba(239,68,68,.1); color: #b91c1c; }
.cashbook-source {
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  color: #6b7280;
  display: inline-flex;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
}
.cashbook-money { font-family: 'JetBrains Mono', monospace; font-weight: 800; }
.cashbook-card-list { display: none; }
.cashbook-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 10px;
  display: grid;
  gap: 10px;
  padding: 13px;
}
.cashbook-card-top {
  align-items: flex-start;
  display: flex;
  gap: 10px;
  justify-content: space-between;
}
.cashbook-actions {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
}
@media (max-width: 768px) {
  .content-body { padding: 10px; }
  .cashbook-toolbar { grid-template-columns: 1fr; }
  .cashbook-toolbar .btn { min-height: 42px; }
  .cashbook-desktop { display: none; }
  .cashbook-card-list { display: grid; gap: 8px; }
  .cashbook-actions { display: grid; grid-template-columns: 1fr 1fr; }
  .cashbook-actions .btn { min-height: 40px; }
  .page-header h2 { font-size: 18px; }
}
</style>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-cash-stack me-2 text-<?= htmlspecialchars($branchInfo['color']) ?>"></i>Quản Lý Thu Chi</h2>
    <p><?= htmlspecialchars($branchInfo['name']) ?> — sổ quỹ theo tháng, có in phiếu thu/chi</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="index.php?page=help&topic=cashbook" class="btn btn-sm btn-outline-secondary context-help-btn" title="Hướng dẫn quản lý thu chi"><i class="bi bi-question-circle"></i><span class="context-help-label">Hướng dẫn</span></a>
    <?php foreach (getAccessibleBranches() as $bId => $b): ?>
      <a href="index.php?page=cashbook&branch=<?= urlencode($bId) ?>&ym=<?= urlencode($yearMonth) ?>"
         class="btn btn-sm <?= $reqBranch === $bId ? 'btn-primary' : 'btn-outline-secondary' ?>">
        <?= htmlspecialchars($b['short']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-arrow-down-circle"></i></div>
      <div class="stat-value" style="font-size:17px"><?= formatMoney($summary['income']) ?></div>
      <div class="stat-label">Tổng thu</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-red">
      <div class="stat-icon"><i class="bi bi-arrow-up-circle"></i></div>
      <div class="stat-value" style="font-size:17px"><?= formatMoney($summary['expense']) ?></div>
      <div class="stat-label">Tổng chi</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-amber">
      <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
      <div class="stat-value" style="font-size:17px"><?= formatMoney($summary['balance']) ?></div>
      <div class="stat-label">Chênh lệch</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-blue">
      <div class="stat-icon"><i class="bi bi-list-check"></i></div>
      <div class="stat-value"><?= (int)$summary['count'] ?></div>
      <div class="stat-label">Số phiếu</div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="cashbook-toolbar" method="GET">
      <div>
        <label class="form-label">Tháng xem sổ</label>
        <input type="hidden" name="page" value="cashbook">
        <input type="hidden" name="branch" value="<?= htmlspecialchars($reqBranch) ?>">
        <input type="month" class="form-control" name="ym_picker" value="<?= htmlspecialchars(str_replace('_', '-', $yearMonth)) ?>" onchange="this.form.ym.value=this.value.replace('-', '_')">
        <input type="hidden" name="ym" value="<?= htmlspecialchars($yearMonth) ?>">
      </div>
      <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel me-1"></i>Xem tháng</button>
      <div class="d-flex gap-2">
        <?php if ($canIncome): ?>
        <button type="button" class="btn btn-primary" onclick="openCashbookModal('income')"><i class="bi bi-plus-circle me-1"></i>Thêm thu</button>
        <?php endif; ?>
        <?php if ($canExpense): ?>
        <button type="button" class="btn btn-outline-primary" onclick="openCashbookModal('expense')"><i class="bi bi-dash-circle me-1"></i>Thêm chi</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if (empty($entries)): ?>
<div class="card">
  <div class="empty-state">
    <i class="bi bi-cash-stack"></i>
    <p>Chưa có phiếu thu chi trong tháng này.</p>
  </div>
</div>
<?php else: ?>

<div class="cashbook-card-list" data-progressive-list data-progressive-initial="15" data-progressive-batch="15" data-progressive-controls="cashbookMobileMore">
  <?php foreach ($entries as $entry):
    $payload = htmlspecialchars(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
    $isExpense = ($entry['type'] ?? '') === 'expense';
    $isManual = ($entry['source_type'] ?? 'manual') === 'manual';
  ?>
  <div class="cashbook-card" data-progressive-item>
    <div class="cashbook-card-top">
      <div>
        <span class="cashbook-type <?= $isExpense ? 'cashbook-expense' : 'cashbook-income' ?>">
          <i class="bi <?= $isExpense ? 'bi-arrow-up-circle' : 'bi-arrow-down-circle' ?>"></i>
          <?= $isExpense ? 'Chi' : 'Thu' ?>
        </span>
        <div class="fw-800 mt-2"><?= htmlspecialchars(cashbookCategoryLabel($entry['type'], $entry['category'])) ?></div>
        <div class="text-muted" style="font-size:12px"><?= date('d/m/Y', strtotime($entry['entry_date'])) ?> · <?= htmlspecialchars(cashbookMethodLabel($entry['method'] ?? 'cash')) ?></div>
      </div>
      <div class="cashbook-money <?= $isExpense ? 'text-danger' : 'text-success' ?>"><?= ($isExpense ? '-' : '+') . formatMoney((float)$entry['amount']) ?></div>
    </div>
    <div style="font-size:13px">
      <div><b>Người nộp/nhận:</b> <?= htmlspecialchars($entry['person'] ?: '-') ?></div>
      <div><b>Nội dung:</b> <?= htmlspecialchars($entry['description'] ?: '-') ?></div>
      <?php if (!$isManual): ?><span class="cashbook-source mt-1">Tự động từ công nợ</span><?php endif; ?>
    </div>
    <div class="cashbook-actions">
      <button type="button" class="btn btn-outline-primary" onclick="printCashbook(this)" data-entry="<?= $payload ?>"><i class="bi bi-printer me-1"></i>In</button>
      <?php if ($isManual && cashbookCanManageEntry($entry)): ?>
      <button type="button" class="btn btn-outline-secondary" onclick="openCashbookModal('<?= htmlspecialchars($entry['type']) ?>', this)" data-entry="<?= $payload ?>"><i class="bi bi-pencil me-1"></i>Sửa</button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<div id="cashbookMobileMore" class="mobile-progressive-control"></div>

<div class="card cashbook-desktop">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Ngày</th>
            <th>Loại</th>
            <th>Khoản mục / Nội dung</th>
            <th>Người nộp/nhận</th>
            <th>Hình thức</th>
            <th class="text-end">Số tiền</th>
            <th class="text-center">Thao tác</th>
          </tr>
        </thead>
        <tbody data-progressive-list data-progressive-initial="20" data-progressive-batch="20" data-progressive-controls="cashbookDesktopMore">
        <?php foreach ($entries as $entry):
          $payload = htmlspecialchars(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
          $isExpense = ($entry['type'] ?? '') === 'expense';
          $isManual = ($entry['source_type'] ?? 'manual') === 'manual';
        ?>
          <tr data-progressive-item>
            <td style="white-space:nowrap">
              <div class="fw-700"><?= date('d/m/Y', strtotime($entry['entry_date'])) ?></div>
              <div class="text-muted" style="font-size:11px"><?= htmlspecialchars(substr($entry['created_at'] ?? '', 0, 16)) ?></div>
            </td>
            <td>
              <span class="cashbook-type <?= $isExpense ? 'cashbook-expense' : 'cashbook-income' ?>">
                <i class="bi <?= $isExpense ? 'bi-arrow-up-circle' : 'bi-arrow-down-circle' ?>"></i>
                <?= $isExpense ? 'Chi' : 'Thu' ?>
              </span>
            </td>
            <td>
              <div class="fw-800"><?= htmlspecialchars(cashbookCategoryLabel($entry['type'], $entry['category'])) ?></div>
              <div class="text-muted" style="font-size:12px"><?= htmlspecialchars($entry['description'] ?: '-') ?></div>
              <?php if (!$isManual): ?><span class="cashbook-source mt-1">Tự động từ công nợ</span><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($entry['person'] ?: '-') ?></td>
            <td><?= htmlspecialchars(cashbookMethodLabel($entry['method'] ?? 'cash')) ?></td>
            <td class="text-end cashbook-money <?= $isExpense ? 'text-danger' : 'text-success' ?>">
              <?= ($isExpense ? '-' : '+') . formatMoney((float)$entry['amount']) ?>
            </td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-content-center">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="printCashbook(this)" data-entry="<?= $payload ?>"><i class="bi bi-printer"></i></button>
                <?php if ($isManual && cashbookCanManageEntry($entry)): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openCashbookModal('<?= htmlspecialchars($entry['type']) ?>', this)" data-entry="<?= $payload ?>"><i class="bi bi-pencil"></i></button>
                <?php endif; ?>
                <?php if ($isManual && $canDelete): ?>
                <form method="POST" action="index.php?page=cashbook&branch=<?= urlencode($reqBranch) ?>&ym=<?= urlencode($yearMonth) ?>&action=delete" onsubmit="return confirm('Xóa phiếu này?')" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= htmlspecialchars($entry['id']) ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div id="cashbookDesktopMore" class="desktop-progressive-control"></div>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="cashbookModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="index.php?page=cashbook&branch=<?= urlencode($reqBranch) ?>&action=save">
      <?= csrfField() ?>
      <input type="hidden" name="id" id="cbId">
      <input type="hidden" name="type" id="cbType" value="income">
      <div class="modal-header">
        <h5 class="modal-title" id="cbTitle"><i class="bi bi-cash-coin me-2 text-success"></i>Thêm khoản thu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Khoản mục *</label>
            <select class="form-select" name="category" id="cbCategory" required></select>
          </div>
          <div class="col-12">
            <label class="form-label">Số tiền *</label>
            <input type="number" class="form-control" name="amount" id="cbAmount" min="1" step="1" inputmode="numeric" required>
          </div>
          <div class="col-6">
            <label class="form-label">Ngày ghi nhận *</label>
            <input type="hidden" name="entry_date" id="cashbookEntryDateIso" value="<?= date('Y-m-d') ?>">
            <input type="text" class="form-control" id="cashbookEntryDateDisplay" data-vn-date-target="cashbookEntryDateIso" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label">Hình thức</label>
            <select class="form-select" name="method" id="cbMethod">
              <?php foreach ($methods as $mKey => $mLabel): ?>
              <option value="<?= htmlspecialchars($mKey) ?>"><?= htmlspecialchars($mLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Người nộp / nhận tiền</label>
            <input type="text" class="form-control" name="person" id="cbPerson" placeholder="VD: Khách A, nhà cung cấp B, nhân viên C...">
          </div>
          <div class="col-12">
            <label class="form-label">Nội dung</label>
            <textarea class="form-control" name="description" id="cbDescription" rows="2" placeholder="Ghi rõ lý do thu/chi để dễ đối chiếu"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
        <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Lưu phiếu</button>
      </div>
    </form>
  </div>
</div>

<script>
const CASHBOOK_CATEGORIES = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;
const CASHBOOK_METHODS = <?= json_encode($methods, JSON_UNESCAPED_UNICODE) ?>;
const CASHBOOK_BRANCH = <?= json_encode($branchInfo, JSON_UNESCAPED_UNICODE) ?>;
const CASHBOOK_BUSINESS = <?= json_encode([
  'name' => BUSINESS['name'],
  'address' => BUSINESS['address'],
  'phone' => BUSINESS['phone'],
], JSON_UNESCAPED_UNICODE) ?>;

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function money(n) {
  return new Intl.NumberFormat('vi-VN', {style:'currency', currency:'VND'}).format(Number(n) || 0);
}
function isoToVn(value) {
  if (!value) return '';
  const parts = value.split('-');
  return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : value;
}
function entryFromButton(btn) {
  return JSON.parse(btn.dataset.entry || '{}');
}
function fillCategory(type, selected = '') {
  const select = document.getElementById('cbCategory');
  select.innerHTML = '';
  Object.entries(CASHBOOK_CATEGORIES[type] || {}).forEach(([key, label]) => {
    const opt = document.createElement('option');
    opt.value = key;
    opt.textContent = label;
    select.appendChild(opt);
  });
  if (selected) select.value = selected;
}
function openCashbookModal(type, btn = null) {
  const entry = btn ? entryFromButton(btn) : {};
  const form = document.querySelector('#cashbookModal form');
  form.reset();
  document.getElementById('cbType').value = type;
  document.getElementById('cbId').value = entry.id || '';
  document.getElementById('cbTitle').innerHTML = type === 'expense'
    ? '<i class="bi bi-dash-circle me-2 text-danger"></i>' + (entry.id ? 'Sửa khoản chi' : 'Thêm khoản chi')
    : '<i class="bi bi-plus-circle me-2 text-success"></i>' + (entry.id ? 'Sửa khoản thu' : 'Thêm khoản thu');
  fillCategory(type, entry.category || '');
  document.getElementById('cbAmount').value = entry.amount || '';
  document.getElementById('cbMethod').value = entry.method || 'cash';
  document.getElementById('cbPerson').value = entry.person || '';
  document.getElementById('cbDescription').value = entry.description || '';
  document.getElementById('cashbookEntryDateIso').value = entry.entry_date || '<?= date('Y-m-d') ?>';
  document.getElementById('cashbookEntryDateDisplay').value = isoToVn(entry.entry_date || '<?= date('Y-m-d') ?>');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('cashbookModal')).show();
}
function printCashbook(btn) {
  const entry = entryFromButton(btn);
  const isExpense = entry.type === 'expense';
  const title = isExpense ? 'Phiếu Chi' : 'Phiếu Thu';
  const personLabel = isExpense ? 'Người nhận tiền' : 'Người nộp tiền';
  const win = window.open('', '_blank', 'width=760,height=720');
  win.document.write(`<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>${esc(title)}</title>
<style>
  @page { size: A5 portrait; margin: 12mm; }
  * { box-sizing: border-box; }
  body { font-family: "Times New Roman", serif; color: #000; font-size: 13pt; line-height: 1.25; margin: 0; }
  .biz { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 12px; }
  .biz-name { font-weight: bold; font-size: 15pt; text-transform: uppercase; }
  .biz-line { font-size: 11pt; margin-top: 2px; }
  .title { text-align: center; font-weight: bold; font-size: 20pt; letter-spacing: 1px; margin: 12px 0 4px; text-transform: uppercase; }
  .code { text-align: center; font-size: 11pt; margin-bottom: 14px; }
  .row { display: grid; grid-template-columns: 140px 1fr; gap: 10px; padding: 5px 0; border-bottom: 1px dotted #999; }
  .label { color: #333; }
  .value { font-weight: bold; }
  .amount { font-size: 17pt; font-weight: bold; }
  .summary { margin-top: 12px; padding: 8px 10px; border: 1px solid #000; font-weight: bold; text-align: center; }
  .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 26px; text-align: center; }
  .sign-title { font-weight: bold; }
  .sign-hint { font-size: 10.5pt; font-style: italic; margin-top: 2px; }
  .sign-space { height: 58px; }
</style>
</head>
<body>
  <div class="biz">
    <div class="biz-name">${esc(CASHBOOK_BUSINESS.name)}</div>
    <div class="biz-line">${esc(CASHBOOK_BRANCH.name || '')}</div>
    <div class="biz-line">Địa chỉ: ${esc(CASHBOOK_BUSINESS.address)}</div>
    <div class="biz-line">Điện thoại: ${esc(CASHBOOK_BUSINESS.phone)}</div>
  </div>
  <div class="title">${esc(title)}</div>
  <div class="code">Mã phiếu: <b>${esc(entry.id || '')}</b></div>
  <div class="row"><div class="label">Ngày ghi nhận</div><div class="value">${esc(isoToVn(entry.entry_date || ''))}</div></div>
  <div class="row"><div class="label">${esc(personLabel)}</div><div class="value">${esc(entry.person || '-')}</div></div>
  <div class="row"><div class="label">Khoản mục</div><div class="value">${esc((CASHBOOK_CATEGORIES[entry.type] || {})[entry.category] || entry.category || '')}</div></div>
  <div class="row"><div class="label">Số tiền</div><div class="value amount">${money(entry.amount || 0)}</div></div>
  <div class="row"><div class="label">Hình thức</div><div class="value">${esc(CASHBOOK_METHODS[entry.method] || entry.method || '')}</div></div>
  <div class="row"><div class="label">Nội dung</div><div class="value">${esc(entry.description || '')}</div></div>
  <div class="summary">${esc(title)}: ${money(entry.amount || 0)}</div>
  <div class="signatures">
    <div><div class="sign-title">${esc(personLabel)}</div><div class="sign-hint">(Ký, ghi rõ họ tên)</div><div class="sign-space"></div></div>
    <div><div class="sign-title">Người lập phiếu</div><div class="sign-hint">(Ký, ghi rõ họ tên)</div><div class="sign-space"></div><div><b>${esc(entry.created_by || '')}</b></div></div>
  </div>
  <script>window.onload = function(){ window.print(); window.close(); }<\/script>
</body>
</html>`);
  win.document.close();
}
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
