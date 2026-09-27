<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requirePermission('payments');

$status = $_GET['status'] ?? '';
$method = $_GET['method'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = '1=1';
$params = [];
if ($status !== '') { $where .= ' AND p.status = ?'; $params[] = $status; }
if ($method !== '') { $where .= ' AND p.method = ?'; $params[] = $method; }

[$payments, $total, $pages, $page] = paginate(
    "SELECT p.*, c.full_name client_name, b.ref booking_ref
     FROM payments p JOIN clients c ON c.id=p.client_id LEFT JOIN bookings b ON b.id=p.booking_id
     WHERE $where ORDER BY p.id DESC",
    "SELECT COUNT(*) FROM payments p WHERE $where",
    $params, $page, 15
);

$sumToday = (float) Database::value(
    "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='successful' AND DATE(paid_at)=CURDATE()");

$pageTitle = 'Payments';
$active = 'payments';
require APP_PATH . '/views/admin/header.php';
?>
<div class="card !p-0 overflow-x-auto">
    <div class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100">
        <form method="get" class="flex flex-wrap items-center gap-3 flex-1">
            <select name="status" class="input w-40">
                <option value="">All Statuses</option>
                <?php foreach (['successful','pending','failed','cancelled','refunded'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="method" class="input w-44">
                <option value="">All Methods</option>
                <?php foreach (['cash','bank_transfer','paynow','card','wallet','other'] as $m): ?>
                <option value="<?= $m ?>" <?= $method === $m ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$m)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-secondary">Filter</button>
        </form>
        <div class="text-sm text-slate-500">Today: <span class="font-semibold text-slate-800"><?= money($sumToday) ?></span></div>
        <a href="<?= url('admin/bookings.php') ?>" class="btn-primary"><i data-lucide="plus" class="w-4 h-4"></i> New Payment</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr>
                <th class="th">Txn ID</th><th class="th">Client</th><th class="th">Booking</th>
                <th class="th">Method</th><th class="th">Purpose</th><th class="th">Reference</th>
                <th class="th">Status</th><th class="th">Date</th><th class="th text-right">Amount</th>
            </tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
            <tr class="table-row">
                <td class="td font-medium"><?= e($p['txn_id']) ?></td>
                <td class="td"><?= e($p['client_name']) ?></td>
                <td class="td"><?php if ($p['booking_ref']): ?><a class="text-blue-600 hover:underline" href="<?= url('admin/booking.php?id=' . $p['booking_id']) ?>"><?= e($p['booking_ref']) ?></a><?php else: ?>—<?php endif; ?></td>
                <td class="td"><?= e(ucwords(str_replace('_',' ',$p['method']))) ?></td>
                <td class="td"><?= e(ucfirst($p['purpose'])) ?></td>
                <td class="td"><?= e($p['reference'] ?? '—') ?></td>
                <td class="td"><?= status_badge($p['status']) ?></td>
                <td class="td"><?= e(fmt_datetime($p['paid_at'] ?? $p['created_at'])) ?></td>
                <td class="td text-right font-medium"><?= money($p['amount']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?>
            <tr><td colspan="9" class="td text-center py-10 text-slate-400">No payments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between px-6 py-4">
        <p class="text-sm text-slate-500">Showing <?= count($payments) ?> of <?= $total ?></p>
        <?= pagination_links($page, $pages, 'payments.php?x=1' . ($status ? '&status=' . $status : '') . ($method ? '&method=' . $method : '')) ?>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
