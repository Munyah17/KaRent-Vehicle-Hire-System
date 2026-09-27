<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;

Auth::requirePermission('maintenance');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    if ($_POST['action'] === 'add') {
        $id = Database::insert(
            'INSERT INTO maintenance
             (vehicle_id, maint_type, maint_date, mileage, description, cost, supplier_id,
              next_service_date, next_service_mileage, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                (int) $_POST['vehicle_id'], trim($_POST['maint_type']),
                $_POST['maint_date'] ?: date('Y-m-d'),
                ($_POST['mileage'] ?? '') !== '' ? (int) $_POST['mileage'] : null,
                trim($_POST['description'] ?? ''),
                (float) $_POST['cost'],
                ($_POST['supplier_id'] ?? '') ?: null,
                ($_POST['next_service_date'] ?? '') ?: null,
                ($_POST['next_service_mileage'] ?? '') !== '' ? (int) $_POST['next_service_mileage'] : null,
                Auth::id(),
            ]
        );
        Audit::log(Auth::id(), 'add_maintenance', 'maintenance', 'maintenance', $id);
        flash('success', 'Maintenance record added.');
    }
    redirect('admin/maintenance.php');
}

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$vehicleId = (int) ($_GET['vehicle'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = '1=1';
$params = [];
if ($from !== '') { $where .= ' AND m.maint_date >= ?'; $params[] = $from; }
if ($to !== '') { $where .= ' AND m.maint_date <= ?'; $params[] = $to; }
if ($vehicleId) { $where .= ' AND m.vehicle_id = ?'; $params[] = $vehicleId; }

[$records, $total, $pages, $page] = paginate(
    "SELECT m.*, v.make, v.model, v.reg_no, s.name supplier_name
     FROM maintenance m JOIN vehicles v ON v.id=m.vehicle_id
     LEFT JOIN suppliers s ON s.id=m.supplier_id
     WHERE $where ORDER BY m.maint_date DESC",
    "SELECT COUNT(*) FROM maintenance m WHERE $where",
    $params, $page, 15
);

$upcoming = Database::all(
    "SELECT m.*, v.make, v.model, v.reg_no FROM maintenance m JOIN vehicles v ON v.id=m.vehicle_id
     WHERE m.next_service_date IS NOT NULL AND m.next_service_date <= CURDATE() + INTERVAL 30 DAY
     ORDER BY m.next_service_date LIMIT 5");

$vehicles = Database::all('SELECT id, make, model, reg_no, mileage FROM vehicles ORDER BY make');
$suppliers = Database::all('SELECT * FROM suppliers ORDER BY name');
$totalCost = (float) Database::value("SELECT COALESCE(SUM(cost),0) FROM maintenance m WHERE $where", $params);

$pageTitle = 'Maintenance Records';
$active = 'maintenance';
require APP_PATH . '/views/admin/header.php';
?>
<?php if ($upcoming): ?>
<div class="mb-6 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
    <strong>Upcoming services:</strong>
    <?php foreach ($upcoming as $u): ?>
    <span class="inline-block mr-4"><?= e($u['make'] . ' ' . $u['model'] . ' (' . $u['reg_no'] . ')') ?> — <?= e(fmt_date($u['next_service_date'])) ?></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
    <div class="xl:col-span-3 card !p-0 overflow-x-auto">
        <div class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100">
            <form method="get" class="flex flex-wrap items-center gap-3 flex-1">
                <input type="date" name="from" value="<?= e($from) ?>" class="input w-36">
                <input type="date" name="to" value="<?= e($to) ?>" class="input w-36">
                <select name="vehicle" class="input w-52">
                    <option value="">All Vehicles</option>
                    <?php foreach ($vehicles as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $vehicleId === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['make'] . ' ' . $v['model'] . ' ' . $v['reg_no']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-secondary">Filter</button>
            </form>
            <span class="text-sm text-slate-500">Total: <span class="font-semibold text-slate-800"><?= money($totalCost) ?></span></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr>
                    <th class="th">Date</th><th class="th">Vehicle</th><th class="th">Type</th>
                    <th class="th">Description</th><th class="th">Supplier</th>
                    <th class="th">Next Service</th><th class="th text-right">Cost</th>
                </tr></thead>
                <tbody>
                <?php foreach ($records as $m): ?>
                <tr class="table-row">
                    <td class="td"><?= e(fmt_date($m['maint_date'])) ?></td>
                    <td class="td font-medium"><?= e($m['make'] . ' ' . $m['model']) ?><br><span class="text-xs text-slate-400"><?= e($m['reg_no']) ?></span></td>
                    <td class="td"><?= e($m['maint_type']) ?></td>
                    <td class="td max-w-xs"><span class="line-clamp-2"><?= e($m['description'] ?? '—') ?></span></td>
                    <td class="td"><?= e($m['supplier_name'] ?? '—') ?></td>
                    <td class="td"><?= e($m['next_service_date'] ? fmt_date($m['next_service_date']) : ($m['next_service_mileage'] ? number_format($m['next_service_mileage']) . ' km' : '—')) ?></td>
                    <td class="td text-right font-medium"><?= money($m['cost']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$records): ?><tr><td colspan="7" class="td text-center py-10 text-slate-400">No maintenance records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-6 py-4">
            <p class="text-sm text-slate-500">Showing <?= count($records) ?> of <?= $total ?></p>
            <?= pagination_links($page, $pages, 'maintenance.php?x=1' . ($vehicleId ? "&vehicle=$vehicleId" : '') . ($from ? "&from=$from" : '') . ($to ? "&to=$to" : '')) ?>
        </div>
    </div>

    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Add Record</h3>
        <form method="post" class="space-y-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="add">
            <select name="vehicle_id" class="input" required>
                <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id'] ?>"><?= e($v['make'] . ' ' . $v['model'] . ' ' . $v['reg_no']) ?></option>
                <?php endforeach; ?>
            </select>
            <input name="maint_type" placeholder="Type (Service, Tyres, Repair...)" class="input" required>
            <input type="date" name="maint_date" class="input" value="<?= date('Y-m-d') ?>" required>
            <input name="mileage" type="number" placeholder="Mileage (km)" class="input">
            <textarea name="description" rows="2" placeholder="Description" class="input"></textarea>
            <input name="cost" type="number" step="0.01" min="0" placeholder="Cost" class="input" required>
            <select name="supplier_id" class="input">
                <option value="">No supplier</option>
                <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label !text-xs">Next service date</label>
                    <input type="date" name="next_service_date" class="input"></div>
                <div><label class="label !text-xs">Next mileage</label>
                    <input name="next_service_mileage" type="number" class="input"></div>
            </div>
            <button class="btn-primary w-full justify-center"><i data-lucide="plus" class="w-4 h-4"></i> Add Record</button>
        </form>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
