<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;
use App\Services\VehicleService;

Auth::requirePermission('vehicles');

// Actions: change status, toggle visibility, delete photo etc. handled on edit page.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';
    $vid = (int) ($_POST['vehicle_id'] ?? 0);
    $veh = VehicleService::find($vid);
    if ($veh && $action === 'set_status') {
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['available', 'maintenance', 'unavailable'], true)) {
            Database::run('UPDATE vehicles SET status = ? WHERE id = ?', [$status, $vid]);
            Audit::log(Auth::id(), 'vehicle_status', 'vehicles', 'vehicle', $vid, $veh['status'], $status);
            flash('success', 'Vehicle status updated.');
        }
    } elseif ($veh && $action === 'toggle_public') {
        Database::run('UPDATE vehicles SET is_public = 1 - is_public WHERE id = ?', [$vid]);
        Audit::log(Auth::id(), 'vehicle_visibility', 'vehicles', 'vehicle', $vid, $veh['is_public'], 1 - (int) $veh['is_public']);
        flash('success', 'Visibility updated.');
    }
    redirect('admin/vehicles.php');
}

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (v.reg_no LIKE ? OR v.make LIKE ? OR v.model LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
if ($status !== '') { $where .= ' AND v.status = ?'; $params[] = $status; }
if ($type !== '') { $where .= ' AND v.fuel_type = ?'; $params[] = $type; }

[$vehicles, $total, $pages, $page] = paginate(
    "SELECT v.*, (SELECT file_path FROM vehicle_photos p WHERE p.vehicle_id=v.id
        ORDER BY p.is_primary DESC, p.sort_order LIMIT 1) photo
     FROM vehicles v WHERE $where ORDER BY v.id",
    "SELECT COUNT(*) FROM vehicles v WHERE $where",
    $params, $page, 10
);

$pageTitle = 'Vehicles';
$active = 'vehicles';
require APP_PATH . '/views/admin/header.php';
?>
<div class="card !p-0 overflow-x-auto">
    <div class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100">
        <form method="get" class="flex flex-wrap items-center gap-3 flex-1">
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
                <input name="q" value="<?= e($q) ?>" placeholder="Search vehicles..."
                       class="input !pl-9 w-56">
            </div>
            <select name="status" class="input w-40">
                <option value="">All Statuses</option>
                <?php foreach (['available','reserved','on_hire','maintenance','unavailable'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type" class="input w-36">
                <option value="">All Types</option>
                <?php foreach (['petrol','diesel','hybrid','electric'] as $t): ?>
                <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-secondary">Filter</button>
        </form>
        <a href="<?= url('admin/vehicle-edit.php') ?>" class="btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Vehicle
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr>
                <th class="th">Photo</th><th class="th">Reg No</th><th class="th">Make / Model</th>
                <th class="th">Year</th><th class="th">Type</th><th class="th">Status</th>
                <th class="th">Daily Rate</th><th class="th">Visible</th><th class="th"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($vehicles as $veh): ?>
            <tr class="table-row">
                <td class="td">
                    <img src="<?= url($veh['photo'] && file_exists(PUBLIC_UPLOAD_PATH.'/'.$veh['photo']) ? 'uploads/'.$veh['photo'] : 'assets/img/car-placeholder.jpg') ?>"
                         class="w-14 h-10 object-cover rounded-md border border-gray-200" alt="">
                </td>
                <td class="td font-medium text-slate-800"><?= e($veh['reg_no']) ?></td>
                <td class="td"><?= e($veh['make'] . ' ' . $veh['model']) ?></td>
                <td class="td"><?= e((string) $veh['year']) ?></td>
                <td class="td"><?= e(ucfirst($veh['fuel_type'])) ?> · <?= e(ucfirst($veh['transmission'])) ?></td>
                <td class="td"><?= status_badge($veh['status']) ?></td>
                <td class="td font-medium"><?= money($veh['daily_rate']) ?></td>
                <td class="td">
                    <form method="post" class="inline"><?= Csrf::field() ?>
                        <input type="hidden" name="action" value="toggle_public">
                        <input type="hidden" name="vehicle_id" value="<?= $veh['id'] ?>">
                        <button title="Toggle public visibility"
                            class="<?= $veh['is_public'] ? 'text-green-600' : 'text-gray-400' ?>">
                            <i data-lucide="<?= $veh['is_public'] ? 'eye' : 'eye-off' ?>" class="w-4 h-4"></i>
                        </button>
                    </form>
                </td>
                <td class="td text-right">
                    <div class="relative inline-block text-left">
                        <button onclick="this.nextElementSibling.classList.toggle('hidden')"
                                class="p-1.5 rounded hover:bg-gray-100 text-gray-500">
                            <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                        </button>
                        <div class="hidden absolute right-0 mt-1 w-44 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-30 text-sm text-left">
                            <a class="block px-4 py-2 hover:bg-gray-50" href="<?= url('admin/vehicle-edit.php?id=' . $veh['id']) ?>">Edit details</a>
                            <?php foreach (['available' => 'Set Available', 'maintenance' => 'Set Maintenance', 'unavailable' => 'Set Unavailable'] as $s => $lbl): ?>
                            <form method="post"><?= Csrf::field() ?>
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="vehicle_id" value="<?= $veh['id'] ?>">
                                <input type="hidden" name="status" value="<?= $s ?>">
                                <button class="block w-full text-left px-4 py-2 hover:bg-gray-50"><?= $lbl ?></button>
                            </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$vehicles): ?>
            <tr><td colspan="9" class="td text-center py-10 text-slate-400">No vehicles found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between px-6 py-4">
        <p class="text-sm text-slate-500">Showing <?= count($vehicles) ?> of <?= $total ?></p>
        <?= pagination_links($page, $pages, 'vehicles.php?x=1' . ($q ? '&q=' . urlencode($q) : '') . ($status ? '&status=' . $status : '') . ($type ? '&type=' . $type : '')) ?>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
