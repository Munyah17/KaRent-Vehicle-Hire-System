<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requirePermission('clients');

$q = trim($_GET['q'] ?? '');
$kyc = $_GET['kyc'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR national_id LIKE ? OR client_no LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($kyc !== '') { $where .= ' AND kyc_status = ?'; $params[] = $kyc; }

[$clients, $total, $pages, $page] = paginate(
    "SELECT * FROM clients WHERE $where ORDER BY id DESC",
    "SELECT COUNT(*) FROM clients WHERE $where",
    $params, $page, 15
);

$pageTitle = 'Clients';
$active = 'clients';
require APP_PATH . '/views/admin/header.php';
?>
<div class="card !p-0 overflow-x-auto">
    <div class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100">
        <form method="get" class="flex flex-wrap items-center gap-3 flex-1">
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
                <input name="q" value="<?= e($q) ?>" placeholder="Search name, phone, ID..."
                       class="input !pl-9 w-64">
            </div>
            <select name="kyc" class="input w-44">
                <option value="">All KYC statuses</option>
                <?php foreach (['pending','under_review','verified','rejected'] as $s): ?>
                <option value="<?= $s ?>" <?= $kyc === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-secondary">Filter</button>
        </form>
        <a href="<?= url('admin/client-edit.php') ?>" class="btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Client
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr>
                <th class="th">Client</th><th class="th">Client No</th><th class="th">Phone</th>
                <th class="th">National ID</th><th class="th">KYC</th><th class="th">Source</th>
                <th class="th">Bookings</th><th class="th"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($clients as $c):
                $bookingCount = Database::value('SELECT COUNT(*) FROM bookings WHERE client_id = ?', [$c['id']]); ?>
            <tr class="table-row">
                <td class="td">
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-semibold">
                            <?= e(strtoupper(substr($c['full_name'], 0, 1))) ?>
                        </span>
                        <div>
                            <p class="font-medium text-slate-800"><?= e($c['full_name']) ?></p>
                            <p class="text-xs text-slate-400"><?= e($c['email']) ?></p>
                        </div>
                    </div>
                </td>
                <td class="td"><?= e($c['client_no']) ?></td>
                <td class="td"><?= e($c['phone']) ?></td>
                <td class="td"><?= e($c['national_id'] ?? '—') ?></td>
                <td class="td"><?= status_badge($c['kyc_status']) ?></td>
                <td class="td"><?= e(ucwords(str_replace('_', ' ', $c['source']))) ?></td>
                <td class="td"><?= (int) $bookingCount ?></td>
                <td class="td text-right">
                    <a href="<?= url('admin/client-edit.php?id=' . $c['id']) ?>" class="text-blue-600 hover:underline text-sm">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$clients): ?>
            <tr><td colspan="8" class="td text-center py-10 text-slate-400">No clients found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between px-6 py-4">
        <p class="text-sm text-slate-500">Showing <?= count($clients) ?> of <?= $total ?></p>
        <?= pagination_links($page, $pages, 'clients.php?x=1' . ($q ? '&q=' . urlencode($q) : '') . ($kyc ? '&kyc=' . $kyc : '')) ?>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
