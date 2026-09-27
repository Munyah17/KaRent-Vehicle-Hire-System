<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;

Auth::requirePermission('vehicles');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    if ($_POST['action'] === 'add') {
        $id = Database::insert(
            'INSERT INTO damages (vehicle_id, client_id, booking_id, incident_date, description,
                estimated_cost, actual_cost, client_charge, status, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                (int) $_POST['vehicle_id'],
                ($_POST['client_id'] ?? '') ?: null,
                ($_POST['booking_id'] ?? '') ?: null,
                $_POST['incident_date'] ?: date('Y-m-d'),
                trim($_POST['description']),
                ($_POST['estimated_cost'] ?? '') !== '' ? (float) $_POST['estimated_cost'] : null,
                ($_POST['actual_cost'] ?? '') !== '' ? (float) $_POST['actual_cost'] : null,
                ($_POST['client_charge'] ?? '') !== '' ? (float) $_POST['client_charge'] : null,
                $_POST['status'] ?? 'reported',
                trim($_POST['notes'] ?? ''),
                Auth::id(),
            ]
        );
        Audit::log(Auth::id(), 'add_damage', 'damages', 'damage', $id);
        flash('success', 'Damage/incident recorded.');
    } elseif ($_POST['action'] === 'status') {
        $id = (int) $_POST['damage_id'];
        $status = $_POST['status'];
        if (in_array($status, ['reported','assessed','charged','resolved'], true)) {
            Database::run('UPDATE damages SET status = ? WHERE id = ?', [$status, $id]);
            flash('success', 'Status updated.');
        }
    }
    redirect('admin/damages.php');
}

$status = $_GET['status'] ?? '';
$where = '1=1';
$params = [];
if ($status !== '') { $where .= ' AND d.status = ?'; $params[] = $status; }

$records = Database::all(
    "SELECT d.*, v.make, v.model, v.reg_no, c.full_name client_name, b.ref booking_ref
     FROM damages d JOIN vehicles v ON v.id=d.vehicle_id
     LEFT JOIN clients c ON c.id=d.client_id LEFT JOIN bookings b ON b.id=d.booking_id
     WHERE $where ORDER BY d.id DESC LIMIT 100", $params);
$vehicles = Database::all('SELECT id, make, model, reg_no FROM vehicles ORDER BY make');
$clients = Database::all('SELECT id, full_name, client_no FROM clients ORDER BY full_name');

$pageTitle = 'Damage / Incidents';
$active = 'damages';
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
    <div class="xl:col-span-3 card !p-0 overflow-hidden">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
            <form method="get" class="flex items-center gap-3 flex-1">
                <select name="status" class="input w-40">
                    <option value="">All Statuses</option>
                    <?php foreach (['reported','assessed','charged','resolved'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-secondary">Filter</button>
            </form>
        </div>
        <table class="w-full">
            <thead><tr>
                <th class="th">Date</th><th class="th">Vehicle</th><th class="th">Client / Booking</th>
                <th class="th">Description</th><th class="th">Est / Actual</th><th class="th">Client Charge</th>
                <th class="th">Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($records as $d): ?>
            <tr class="table-row">
                <td class="td"><?= e(fmt_date($d['incident_date'])) ?></td>
                <td class="td font-medium"><?= e($d['make'] . ' ' . $d['model']) ?><br><span class="text-xs text-slate-400"><?= e($d['reg_no']) ?></span></td>
                <td class="td"><?= e($d['client_name'] ?? '—') ?><br><span class="text-xs text-slate-400"><?= e($d['booking_ref'] ?? '') ?></span></td>
                <td class="td max-w-xs"><span class="line-clamp-2"><?= e($d['description']) ?></span></td>
                <td class="td"><?= $d['estimated_cost'] !== null ? money($d['estimated_cost']) : '—' ?> / <?= $d['actual_cost'] !== null ? money($d['actual_cost']) : '—' ?></td>
                <td class="td"><?= $d['client_charge'] !== null ? money($d['client_charge']) : '—' ?></td>
                <td class="td">
                    <form method="post" class="flex items-center gap-1"><?= Csrf::field() ?>
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="damage_id" value="<?= $d['id'] ?>">
                        <select name="status" class="input !py-1 !px-2 text-xs w-28" onchange="this.form.submit()">
                            <?php foreach (['reported','assessed','charged','resolved'] as $s): ?>
                            <option value="<?= $s ?>" <?= $d['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?><tr><td colspan="7" class="td text-center py-10 text-slate-400">No incidents recorded.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Report Damage / Incident</h3>
        <form method="post" class="space-y-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="add">
            <select name="vehicle_id" class="input" required>
                <?php foreach ($vehicles as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['make'] . ' ' . $v['model'] . ' ' . $v['reg_no']) ?></option><?php endforeach; ?>
            </select>
            <select name="client_id" class="input">
                <option value="">No client</option>
                <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['full_name'] . ' (' . $c['client_no'] . ')') ?></option><?php endforeach; ?>
            </select>
            <input type="date" name="incident_date" class="input" value="<?= date('Y-m-d') ?>" required>
            <textarea name="description" rows="3" placeholder="Describe the damage/incident" class="input" required></textarea>
            <div class="grid grid-cols-2 gap-3">
                <input name="estimated_cost" type="number" step="0.01" placeholder="Est. cost" class="input">
                <input name="actual_cost" type="number" step="0.01" placeholder="Actual cost" class="input">
            </div>
            <input name="client_charge" type="number" step="0.01" placeholder="Charge to client" class="input">
            <input name="notes" placeholder="Notes" class="input">
            <button class="btn-primary w-full justify-center"><i data-lucide="plus" class="w-4 h-4"></i> Record Incident</button>
        </form>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
