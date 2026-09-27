<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Services\BookingService;

Auth::requirePermission('bookings');
BookingService::flagOverdue();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $id = (int) ($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['confirm', 'cancel'], true)) {
        [$ok, $err] = BookingService::setStatus($id, $action === 'confirm' ? 'confirmed' : 'cancelled',
            Auth::id(), $_POST['note'] ?? null);
        flash($ok ? 'success' : 'error', $ok ? 'Booking updated.' : $err);
    }
    redirect('admin/bookings.php');
}

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (b.ref LIKE ? OR c.full_name LIKE ? OR v.reg_no LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
if ($status !== '') { $where .= ' AND b.status = ?'; $params[] = $status; }

[$bookings, $total, $pages, $page] = paginate(
    "SELECT b.*, c.full_name client_name, v.make, v.model, v.reg_no
     FROM bookings b JOIN clients c ON c.id=b.client_id JOIN vehicles v ON v.id=b.vehicle_id
     WHERE $where ORDER BY b.id DESC",
    "SELECT COUNT(*) FROM bookings b JOIN clients c ON c.id=b.client_id JOIN vehicles v ON v.id=b.vehicle_id WHERE $where",
    $params, $page, 15
);

$pageTitle = 'Bookings';
$active = 'bookings';
require APP_PATH . '/views/admin/header.php';
?>
<div class="card !p-0 overflow-hidden">
    <div class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100">
        <form method="get" class="flex flex-wrap items-center gap-3 flex-1">
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
                <input name="q" value="<?= e($q) ?>" placeholder="Search ref, client, reg..." class="input !pl-9 w-64">
            </div>
            <select name="status" class="input w-40">
                <option value="">All Statuses</option>
                <?php foreach (['pending','confirmed','active','completed','cancelled','overdue'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-secondary">Filter</button>
        </form>
        <a href="<?= url('admin/calendar.php') ?>" class="btn-secondary"><i data-lucide="calendar-days" class="w-4 h-4"></i> Calendar</a>
        <a href="<?= url('admin/booking-new.php') ?>" class="btn-primary"><i data-lucide="plus" class="w-4 h-4"></i> New Booking</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr>
                <th class="th">Ref</th><th class="th">Client</th><th class="th">Vehicle</th>
                <th class="th">Pickup</th><th class="th">Return</th><th class="th">Status</th>
                <th class="th">Total</th><th class="th">Outstanding</th><th class="th"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($bookings as $b):
                $out = BookingService::outstanding((int) $b['id']); ?>
            <tr class="table-row">
                <td class="td font-medium text-slate-800"><?= e($b['ref']) ?></td>
                <td class="td"><?= e($b['client_name']) ?></td>
                <td class="td"><?= e($b['make'] . ' ' . $b['model']) ?><br><span class="text-xs text-slate-400"><?= e($b['reg_no']) ?></span></td>
                <td class="td"><?= e(fmt_date($b['pickup_at'], 'd M, H:i')) ?></td>
                <td class="td"><?= e(fmt_date($b['return_at'], 'd M, H:i')) ?></td>
                <td class="td"><?= status_badge($b['status']) ?></td>
                <td class="td font-medium"><?= money($b['total']) ?></td>
                <td class="td <?= $out > 0 ? 'text-red-600 font-medium' : 'text-green-600' ?>"><?= money($out) ?></td>
                <td class="td text-right">
                    <a href="<?= url('admin/booking.php?id=' . $b['id']) ?>" class="text-blue-600 text-sm hover:underline">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$bookings): ?>
            <tr><td colspan="9" class="td text-center py-10 text-slate-400">No bookings found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between px-6 py-4">
        <p class="text-sm text-slate-500">Showing <?= count($bookings) ?> of <?= $total ?></p>
        <?= pagination_links($page, $pages, 'bookings.php?x=1' . ($q ? '&q=' . urlencode($q) : '') . ($status ? '&status=' . $status : '')) ?>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
