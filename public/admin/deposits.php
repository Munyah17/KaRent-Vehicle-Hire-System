<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requirePermission('deposits');

$status = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$where = '1=1';
$params = [];
if ($status !== '') { $where .= ' AND d.status = ?'; $params[] = $status; }

[$deposits, $total, $pages, $page] = paginate(
    "SELECT d.*, c.full_name client_name, b.ref booking_ref
     FROM deposits d JOIN clients c ON c.id=d.client_id JOIN bookings b ON b.id=d.booking_id
     WHERE $where ORDER BY d.id DESC",
    "SELECT COUNT(*) FROM deposits d WHERE $where",
    $params, $page, 15
);

$held = (float) Database::value(
    "SELECT COALESCE(SUM(received_amount - deducted_amount - refunded_amount),0)
     FROM deposits WHERE status IN ('held','partial')");

$pageTitle = 'Deposits';
$active = 'deposits';
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="kpi-card"><div><p class="text-sm text-slate-500">Deposits held</p><p class="text-2xl font-semibold mt-1"><?= money($held) ?></p></div>
        <span class="icon-box bg-blue-50 text-blue-600"><i data-lucide="piggy-bank" class="w-5 h-5"></i></span></div>
    <div class="kpi-card"><div><p class="text-sm text-slate-500">Pending collection</p>
        <p class="text-2xl font-semibold mt-1"><?= (int) Database::value("SELECT COUNT(*) FROM deposits WHERE status='pending'") ?></p></div>
        <span class="icon-box bg-amber-50 text-amber-600"><i data-lucide="clock" class="w-5 h-5"></i></span></div>
    <div class="kpi-card"><div><p class="text-sm text-slate-500">Released this month</p>
        <p class="text-2xl font-semibold mt-1"><?= (int) Database::value("SELECT COUNT(*) FROM deposits WHERE status='released' AND MONTH(updated_at)=MONTH(NOW())") ?></p></div>
        <span class="icon-box bg-green-50 text-green-600"><i data-lucide="check-circle" class="w-5 h-5"></i></span></div>
</div>

<div class="card !p-0 overflow-x-auto">
    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
        <form method="get" class="flex items-center gap-3 flex-1">
            <select name="status" class="input w-44">
                <option value="">All Statuses</option>
                <?php foreach (['pending','partial','held','released','forfeited'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-secondary">Filter</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr>
                <th class="th">Booking</th><th class="th">Client</th><th class="th">Required</th>
                <th class="th">Received</th><th class="th">Deducted</th><th class="th">Refunded</th>
                <th class="th">Held</th><th class="th">Status</th><th class="th"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($deposits as $d):
                $bal = $d['received_amount'] - $d['deducted_amount'] - $d['refunded_amount']; ?>
            <tr class="table-row">
                <td class="td font-medium"><a class="text-blue-600 hover:underline" href="<?= url('admin/booking.php?id=' . $d['booking_id']) ?>"><?= e($d['booking_ref']) ?></a></td>
                <td class="td"><?= e($d['client_name']) ?></td>
                <td class="td"><?= money($d['required_amount']) ?></td>
                <td class="td"><?= money($d['received_amount']) ?></td>
                <td class="td text-red-600"><?= money($d['deducted_amount']) ?></td>
                <td class="td"><?= money($d['refunded_amount']) ?></td>
                <td class="td font-medium"><?= money($bal) ?></td>
                <td class="td"><?= status_badge($d['status']) ?></td>
                <td class="td text-right"><a href="<?= url('admin/booking.php?id=' . $d['booking_id']) ?>" class="text-blue-600 text-sm hover:underline">Manage</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$deposits): ?><tr><td colspan="9" class="td text-center py-10 text-slate-400">No deposits found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between px-6 py-4">
        <p class="text-sm text-slate-500">Showing <?= count($deposits) ?> of <?= $total ?></p>
        <?= pagination_links($page, $pages, 'deposits.php?x=1' . ($status ? '&status=' . $status : '')) ?>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
