<?php
$reqBranch = $_GET['branch'] ?? firstAccessibleBranchId();
if (!canAccessBranch($reqBranch)) { header('Location: index.php'); exit; }

$branchInfo = getBranchInfo($reqBranch);
$summary = getReceivableSummary($reqBranch);
$customers = $summary['customers'];
$query = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'open';

$filtered = array_values(array_filter($customers, function ($c) use ($query, $status) {
    $balance = (float)($c['balance'] ?? 0);
    if ($status === 'open' && $balance <= 0) return false;
    if ($status === 'paid' && $balance != 0) return false;
    if ($status === 'overpaid' && $balance >= 0) return false;
    if ($query === '') return true;

    $haystack = mb_strtolower(($c['name'] ?? '') . ' ' . ($c['phone'] ?? '') . ' ' . ($c['address'] ?? ''), 'UTF-8');
    return str_contains($haystack, mb_strtolower($query, 'UTF-8'));
}));

$openCustomers = count(array_filter($customers, fn($c) => (float)($c['balance'] ?? 0) > 0));
$paidCustomers = count(array_filter($customers, fn($c) => (float)($c['balance'] ?? 0) == 0 && ((float)($c['debt'] ?? 0) > 0 || (float)($c['paid'] ?? 0) > 0)));
$overpaidCustomers = count(array_filter($customers, fn($c) => (float)($c['balance'] ?? 0) < 0));
$pageTitle = 'Quản Lý Công Nợ — ' . $branchInfo['name'];
include BASE_PATH . '/views/layouts/header.php';
?>

<style>
.debt-toolbar {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  align-items: center;
}
.debt-card {
  border: 1px solid var(--border);
  border-radius: 10px;
  background: #fff;
  padding: 14px;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  align-items: center;
}
.debt-name { font-weight: 800; font-size: 14.5px; color: #111827; }
.debt-meta { font-size: 12px; color: #6b7280; margin-top: 3px; }
.debt-money { font-family: 'JetBrains Mono', monospace; font-weight: 800; }
.debt-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
.debt-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  padding: 4px 9px;
  font-size: 11.5px;
  font-weight: 700;
  color: #4b5563;
  background: #fff;
}
.debt-mobile-list { display: none; }

@media (max-width: 768px) {
  .content-body { padding: 10px; }
  .page-header { margin-bottom: 12px; }
  .page-header h2 { font-size: 18px; }
  .debt-toolbar { grid-template-columns: 1fr; }
  .debt-toolbar .btn { min-height: 42px; }
  .debt-desktop-table { display: none; }
  .debt-mobile-list { display: grid; gap: 8px; }
  .debt-card {
    grid-template-columns: 1fr;
    padding: 13px;
    border-radius: 9px;
  }
  .debt-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    width: 100%;
  }
  .debt-actions .btn { min-height: 42px; }
  .stat-card { padding: 14px; }
  .stat-card .stat-icon { width: 38px; height: 38px; font-size: 18px; margin-bottom: 8px; }
  .stat-card .stat-value { font-size: 18px; }
  .filter-scroll {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 2px;
  }
  .filter-scroll .btn { white-space: nowrap; }
}
</style>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h2><i class="bi bi-wallet2 me-2 text-<?= $branchInfo['color'] ?>"></i>Quản Lý Công Nợ</h2>
    <p><?= htmlspecialchars($branchInfo['name']) ?> — tự tổng hợp từ hóa đơn thanh toán công nợ</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="index.php?page=help&topic=receivables" class="btn btn-sm btn-outline-secondary context-help-btn" title="Hướng dẫn quản lý công nợ"><i class="bi bi-question-circle"></i><span class="context-help-label">Hướng dẫn</span></a>
    <?php foreach (getAccessibleBranches() as $bId => $b): ?>
      <a href="index.php?page=receivables&branch=<?= urlencode($bId) ?>"
         class="btn btn-sm <?= $reqBranch === $bId ? 'btn-primary' : 'btn-outline-secondary' ?>">
        <?= htmlspecialchars($b['short']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-card stat-red">
      <div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div>
      <div class="stat-value" style="font-size:17px"><?= formatMoney((float)$summary['total_balance']) ?></div>
      <div class="stat-label">Còn phải thu</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-amber">
      <div class="stat-icon"><i class="bi bi-receipt"></i></div>
      <div class="stat-value" style="font-size:17px"><?= formatMoney((float)$summary['total_debt']) ?></div>
      <div class="stat-label">Tổng phát sinh nợ</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-value" style="font-size:17px"><?= formatMoney((float)$summary['total_paid']) ?></div>
      <div class="stat-label">Đã thu</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-blue">
      <div class="stat-icon"><i class="bi bi-people"></i></div>
      <div class="stat-value"><?= $openCustomers ?></div>
      <div class="stat-label">Khách còn nợ</div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="debt-toolbar" method="GET">
      <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="hidden" name="page" value="receivables">
        <input type="hidden" name="branch" value="<?= htmlspecialchars($reqBranch) ?>">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <input class="form-control" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Tìm theo tên khách, số điện thoại, địa chỉ...">
      </div>
      <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Tìm</button>
    </form>
    <div class="filter-scroll mt-3">
      <?php
      $tabs = [
        'open' => ['label' => 'Còn nợ', 'count' => $openCustomers],
        'all' => ['label' => 'Tất cả', 'count' => count($customers)],
        'paid' => ['label' => 'Đã tất toán', 'count' => $paidCustomers],
        'overpaid' => ['label' => 'Thu dư', 'count' => $overpaidCustomers],
      ];
      foreach ($tabs as $key => $tab):
      ?>
      <a class="btn btn-sm <?= $status === $key ? 'btn-primary' : 'btn-outline-secondary' ?>"
         href="index.php?page=receivables&branch=<?= urlencode($reqBranch) ?>&status=<?= $key ?>&q=<?= urlencode($query) ?>">
        <?= htmlspecialchars($tab['label']) ?> <span class="badge bg-light text-dark ms-1"><?= $tab['count'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if (empty($filtered)): ?>
<div class="card">
  <div class="empty-state">
    <i class="bi bi-wallet2"></i>
    <p>Chưa có khách hàng phù hợp với bộ lọc này.</p>
  </div>
</div>
<?php else: ?>

<div class="debt-mobile-list" data-progressive-list data-progressive-initial="12" data-progressive-batch="12" data-progressive-controls="debtMobileMore">
  <?php foreach ($filtered as $c):
    $balance = (float)$c['balance'];
    $payload = htmlspecialchars(json_encode($c, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
  ?>
  <div class="debt-card" data-progressive-item>
    <div>
      <div class="d-flex justify-content-between gap-2">
        <div class="debt-name"><?= htmlspecialchars($c['name']) ?></div>
        <div class="debt-money <?= $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-primary' : 'text-success') ?>">
          <?= formatMoney(abs($balance)) ?>
        </div>
      </div>
      <div class="debt-meta">
        <?= htmlspecialchars($c['phone'] ?: 'Chưa có SĐT') ?>
        <?php if (!empty($c['address'])): ?> · <?= htmlspecialchars($c['address']) ?><?php endif; ?>
      </div>
      <div class="d-flex gap-1 flex-wrap mt-2">
        <span class="debt-chip"><i class="bi bi-receipt"></i><?= (int)$c['invoice_count'] ?> hóa đơn</span>
        <span class="debt-chip"><i class="bi bi-cash"></i><?= (int)$c['payment_count'] ?> lần thu</span>
      </div>
    </div>
    <div class="debt-actions">
      <button type="button" class="btn btn-primary" onclick="openPaymentModal(this)" data-customer="<?= $payload ?>">
        <i class="bi bi-cash-coin me-1"></i>Thu tiền
      </button>
      <button type="button" class="btn btn-outline-secondary" onclick="openDetailModal(this)" data-customer="<?= $payload ?>">
        <i class="bi bi-list-ul me-1"></i>Chi tiết
      </button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<div id="debtMobileMore" class="mobile-progressive-control"></div>

<div class="card debt-desktop-table">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Khách hàng</th>
            <th class="text-end">Phát sinh nợ</th>
            <th class="text-end">Đã thu</th>
            <th class="text-end">Còn lại</th>
            <th>Lần cuối</th>
            <th class="text-center">Thao tác</th>
          </tr>
        </thead>
        <tbody data-progressive-list data-progressive-initial="20" data-progressive-batch="20" data-progressive-controls="debtDesktopMore">
        <?php foreach ($filtered as $c):
          $balance = (float)$c['balance'];
          $payload = htmlspecialchars(json_encode($c, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
        ?>
          <tr data-progressive-item>
            <td>
              <div class="fw-800"><?= htmlspecialchars($c['name']) ?></div>
              <div class="text-muted" style="font-size:12px">
                <?= htmlspecialchars($c['phone'] ?: 'Chưa có SĐT') ?>
                <?php if (!empty($c['address'])): ?> · <?= htmlspecialchars($c['address']) ?><?php endif; ?>
              </div>
              <div class="d-flex gap-1 flex-wrap mt-1">
                <span class="debt-chip"><i class="bi bi-receipt"></i><?= (int)$c['invoice_count'] ?> hóa đơn</span>
                <span class="debt-chip"><i class="bi bi-cash"></i><?= (int)$c['payment_count'] ?> lần thu</span>
              </div>
            </td>
            <td class="text-end debt-money"><?= formatMoney((float)$c['debt']) ?></td>
            <td class="text-end debt-money text-success"><?= formatMoney((float)$c['paid']) ?></td>
            <td class="text-end debt-money <?= $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-primary' : 'text-success') ?>">
              <?= $balance < 0 ? 'Dư ' : '' ?><?= formatMoney(abs($balance)) ?>
            </td>
            <td style="font-size:12px;color:#6b7280">
              Nợ: <?= htmlspecialchars(substr($c['last_invoice_at'] ?: '-', 0, 10)) ?><br>
              Thu: <?= htmlspecialchars(substr($c['last_payment_at'] ?: '-', 0, 10)) ?>
            </td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-content-center">
                <button type="button" class="btn btn-sm btn-primary" onclick="openPaymentModal(this)" data-customer="<?= $payload ?>">
                  <i class="bi bi-cash-coin"></i> Thu
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openDetailModal(this)" data-customer="<?= $payload ?>">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div id="debtDesktopMore" class="desktop-progressive-control"></div>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="index.php?page=receivables&branch=<?= urlencode($reqBranch) ?>&action=payment_save">
      <?= csrfField() ?>
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cash-coin me-2 text-success"></i>Thu Công Nợ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="customer_key" id="payCustomerKey">
        <input type="hidden" name="customer_name" id="payCustomerNameRaw">
        <input type="hidden" name="phone" id="payPhoneRaw">
        <div class="p-3 rounded-3 mb-3" style="background:#f9fafb;border:1px solid #e5e7eb">
          <div class="fw-800" id="payCustomerName"></div>
          <div class="text-muted" style="font-size:12px" id="payCustomerMeta"></div>
          <div class="mt-2">Còn nợ: <span class="debt-money text-danger" id="payBalance"></span></div>
        </div>
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label">Số tiền thu</label>
            <input type="number" class="form-control" name="amount" id="payAmount" min="1" step="1" inputmode="numeric" required>
          </div>
          <div class="col-6">
            <label class="form-label">Ngày thu</label>
            <input type="hidden" name="paid_at" id="receivablePaidAtIso" value="<?= date('Y-m-d') ?>">
            <input type="text" class="form-control" data-vn-date-target="receivablePaidAtIso" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label">Hình thức</label>
            <select class="form-select" name="method">
              <option value="cash">Tiền mặt</option>
              <option value="transfer">Chuyển khoản</option>
              <option value="other">Khác</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Ghi chú</label>
            <input type="text" class="form-control" name="note" placeholder="VD: Khách trả một phần, chuyển khoản...">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Ghi nhận thu</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-list-ul me-2 text-primary"></i>Chi Tiết Công Nợ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailBody"></div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>

<script>
const CURRENT_ROLE = <?= json_encode(currentUser()['role'] ?? '') ?>;
const CAN_DELETE_PAYMENT = ['superadmin','admin'].includes(CURRENT_ROLE);

function money(n) {
  return new Intl.NumberFormat('vi-VN', {style: 'currency', currency: 'VND'}).format(Number(n) || 0);
}
function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function customerFromButton(btn) {
  return JSON.parse(btn.dataset.customer || '{}');
}
function openPaymentModal(btn) {
  const c = customerFromButton(btn);
  document.getElementById('payCustomerKey').value = c.key || '';
  document.getElementById('payCustomerNameRaw').value = c.name || '';
  document.getElementById('payPhoneRaw').value = c.phone || '';
  document.getElementById('payCustomerName').textContent = c.name || '';
  document.getElementById('payCustomerMeta').textContent = [c.phone, c.address].filter(Boolean).join(' · ') || 'Chưa có thông tin liên hệ';
  document.getElementById('payBalance').textContent = money(Math.max(Number(c.balance) || 0, 0));
  document.getElementById('payAmount').value = Math.max(Number(c.balance) || 0, 0);
  bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal')).show();
}
function methodLabel(method) {
  return {cash: 'Tiền mặt', transfer: 'Chuyển khoản', other: 'Khác'}[method] || method || '';
}
function cancelPayment(form) {
  const reason = window.prompt('Lý do hủy phiếu thu:');
  if (reason === null) return false;
  if (!reason.trim()) {
    showToast('Vui lòng nhập lý do hủy để lưu lịch sử đối soát.', 'warning');
    return false;
  }
  form.querySelector('[name="delete_reason"]').value = reason.trim();
  return confirm('Xác nhận hủy phiếu thu? Số tiền sẽ được cộng lại vào công nợ và bút toán thu liên quan sẽ bị hủy.');
}
function openDetailModal(btn) {
  const c = customerFromButton(btn);
  const invoiceRows = (c.invoices || []).map(inv => `
    <tr>
      <td><code>${esc(inv.id)}</code><div class="text-muted" style="font-size:11px">${esc((inv.created_at || '').slice(0,16))}</div></td>
      <td>${esc(inv.created_by || '')}</td>
      <td class="text-end debt-money">${money(inv.total || 0)}</td>
      <td><a class="btn btn-sm btn-outline-secondary" href="index.php?page=invoices&branch=<?= urlencode($reqBranch) ?>&ym=${esc(inv._ym || '')}">Mở tháng</a></td>
    </tr>
  `).join('');

  const paymentRows = (c.payments || []).map(p => `
    <tr>
      <td><code>${esc(p.id)}</code><div class="text-muted" style="font-size:11px">${esc(p.created_at || '')}</div></td>
      <td>${esc(p.paid_at || '')}</td>
      <td>${esc(methodLabel(p.method))}</td>
      <td>${esc(p.note || '')}</td>
      <td class="text-end debt-money text-success">${money(p.amount || 0)}</td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick='printReceipt(${JSON.stringify(p)}, ${JSON.stringify(c)})'>
          <i class="bi bi-printer"></i>
        </button>
        ${CAN_DELETE_PAYMENT ? `
        <form method="POST" action="index.php?page=receivables&branch=<?= urlencode($reqBranch) ?>&action=payment_delete" onsubmit="return cancelPayment(this)" class="d-inline">
          <?= str_replace(["\n", "\r"], '', csrfField()) ?>
          <input type="hidden" name="payment_id" value="${esc(p.id)}">
          <input type="hidden" name="delete_reason" value="">
          <button class="btn btn-sm btn-outline-danger" title="Hủy phiếu thu"><i class="bi bi-x-circle"></i></button>
        </form>` : ''}
      </td>
    </tr>
  `).join('');

  document.getElementById('detailBody').innerHTML = `
    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <div class="p-3 rounded-3" style="background:#f9fafb;border:1px solid #e5e7eb">
          <div class="fw-800">${esc(c.name)}</div>
          <div class="text-muted" style="font-size:12px">${esc([c.phone, c.address].filter(Boolean).join(' · ') || 'Chưa có thông tin liên hệ')}</div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="p-3 rounded-3 text-end" style="background:#fff7ed;border:1px solid #fed7aa">
          <div class="text-muted" style="font-size:12px">Số dư hiện tại</div>
          <div class="debt-money ${Number(c.balance) > 0 ? 'text-danger' : 'text-success'}" style="font-size:20px">${Number(c.balance) < 0 ? 'Dư ' : ''}${money(Math.abs(Number(c.balance) || 0))}</div>
        </div>
      </div>
    </div>
    <h6 class="fw-800 mt-2">Hóa đơn công nợ</h6>
    <div class="table-responsive mb-3">
      <table class="table table-sm table-bordered">
        <thead><tr><th>Mã hóa đơn</th><th>Người lập</th><th class="text-end">Số tiền</th><th></th></tr></thead>
        <tbody>${invoiceRows || '<tr><td colspan="4" class="text-muted text-center">Chưa có hóa đơn công nợ</td></tr>'}</tbody>
      </table>
    </div>
    <h6 class="fw-800">Lịch sử thu tiền</h6>
    <div class="table-responsive">
      <table class="table table-sm table-bordered">
        <thead><tr><th>Mã phiếu</th><th>Ngày thu</th><th>Hình thức</th><th>Ghi chú</th><th class="text-end">Số tiền</th><th></th></tr></thead>
        <tbody>${paymentRows || '<tr><td colspan="6" class="text-muted text-center">Chưa có phiếu thu</td></tr>'}</tbody>
      </table>
    </div>
  `;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('detailModal')).show();
}

function formatDateVN(value) {
  if (!value) return '';
  const date = new Date(value + (String(value).length === 10 ? 'T00:00:00' : ''));
  if (Number.isNaN(date.getTime())) return value;
  const d = String(date.getDate()).padStart(2, '0');
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const y = date.getFullYear();
  return `Ngày ${d} tháng ${m} năm ${y}`;
}

function printReceipt(payment, customer) {
  const business = <?= json_encode([
    'name' => BUSINESS['name'],
    'address' => BUSINESS['address'],
    'phone' => BUSINESS['phone'],
    'branch' => $branchInfo['name'],
  ], JSON_UNESCAPED_UNICODE) ?>;
  const amount = Number(payment.amount || 0);
  const win = window.open('', '_blank', 'width=760,height=720');
  win.document.write(`<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Phiếu thu công nợ</title>
<style>
  @page { size: A5 portrait; margin: 12mm; }
  * { box-sizing: border-box; }
  body { font-family: "Times New Roman", serif; color: #000; font-size: 13pt; line-height: 1.25; margin: 0; }
  .biz { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 12px; }
  .biz-name { font-weight: bold; font-size: 15pt; text-transform: uppercase; }
  .biz-line { font-size: 11pt; margin-top: 2px; }
  .title { text-align: center; font-weight: bold; font-size: 20pt; letter-spacing: 1px; margin: 12px 0 4px; text-transform: uppercase; }
  .code { text-align: center; font-size: 11pt; margin-bottom: 14px; }
  .row { display: grid; grid-template-columns: 130px 1fr; gap: 10px; padding: 5px 0; border-bottom: 1px dotted #999; }
  .label { color: #333; }
  .value { font-weight: bold; }
  .amount { font-size: 17pt; font-weight: bold; }
  .note { min-height: 36px; }
  .summary { margin-top: 12px; padding: 8px 10px; border: 1px solid #000; font-weight: bold; text-align: center; }
  .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 26px; text-align: center; }
  .sign-title { font-weight: bold; }
  .sign-hint { font-size: 10.5pt; font-style: italic; margin-top: 2px; }
  .sign-space { height: 58px; }
  .footer { margin-top: 12px; font-size: 10.5pt; color: #333; text-align: center; }
</style>
</head>
<body>
  <div class="biz">
    <div class="biz-name">${esc(business.name)}</div>
    <div class="biz-line">${esc(business.branch)}</div>
    <div class="biz-line">Địa chỉ: ${esc(business.address)}</div>
    <div class="biz-line">Điện thoại: ${esc(business.phone)}</div>
  </div>

  <div class="title">Phiếu Thu</div>
  <div class="code">Mã phiếu: <b>${esc(payment.id || '')}</b></div>

  <div class="row"><div class="label">Ngày thu</div><div class="value">${esc(formatDateVN(payment.paid_at || ''))}</div></div>
  <div class="row"><div class="label">Khách hàng</div><div class="value">${esc(payment.customer_name || customer.name || '')}</div></div>
  <div class="row"><div class="label">Số điện thoại</div><div class="value">${esc(payment.phone || customer.phone || '-')}</div></div>
  <div class="row"><div class="label">Số tiền thu</div><div class="value amount">${money(amount)}</div></div>
  <div class="row"><div class="label">Hình thức</div><div class="value">${esc(methodLabel(payment.method))}</div></div>
  <div class="row"><div class="label">Nội dung</div><div class="value">Thu tiền công nợ</div></div>
  <div class="row note"><div class="label">Ghi chú</div><div class="value">${esc(payment.note || '')}</div></div>

  <div class="summary">Khách hàng đã thanh toán: ${money(amount)}</div>

  <div class="signatures">
    <div>
      <div class="sign-title">Người nộp tiền</div>
      <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
      <div class="sign-space"></div>
    </div>
    <div>
      <div class="sign-title">Người thu tiền</div>
      <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
      <div class="sign-space"></div>
      <div><b>${esc(payment.created_by || '')}</b></div>
    </div>
  </div>

  <div class="footer">Phiếu thu được in từ hệ thống quản lý công nợ.</div>
  <script>window.onload = function(){ window.print(); window.close(); }<\/script>
</body>
</html>`);
  win.document.close();
}
</script>

<?php include BASE_PATH . '/views/layouts/footer.php'; ?>
