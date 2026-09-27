<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;
use App\Services\VehicleService;

Auth::requirePermission('vehicles');

$id = (int) ($_GET['id'] ?? 0);
$vehicle = $id ? VehicleService::find($id) : null;
if ($id && !$vehicle) { http_response_code(404); exit('Vehicle not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $data = [
            'reg_no' => strtoupper(trim($_POST['reg_no'] ?? '')),
            'make' => trim($_POST['make'] ?? ''),
            'model' => trim($_POST['model'] ?? ''),
            'year' => (int) ($_POST['year'] ?? 0) ?: null,
            'colour' => trim($_POST['colour'] ?? ''),
            'transmission' => $_POST['transmission'] ?? 'automatic',
            'fuel_type' => $_POST['fuel_type'] ?? 'petrol',
            'engine_capacity' => trim($_POST['engine_capacity'] ?? ''),
            'seats' => (int) ($_POST['seats'] ?? 5),
            'mileage' => (int) ($_POST['mileage'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'daily_rate' => (float) ($_POST['daily_rate'] ?? 0),
            'weekly_rate' => ($_POST['weekly_rate'] ?? '') !== '' ? (float) $_POST['weekly_rate'] : null,
            'monthly_rate' => ($_POST['monthly_rate'] ?? '') !== '' ? (float) $_POST['monthly_rate'] : null,
            'deposit' => (float) ($_POST['deposit'] ?? 0),
            'excess_mileage_rate' => ($_POST['excess_mileage_rate'] ?? '') !== '' ? (float) $_POST['excess_mileage_rate'] : null,
            'status' => $_POST['status'] ?? 'available',
            'is_public' => isset($_POST['is_public']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        ];
        if ($data['reg_no'] === '' || $data['make'] === '' || $data['model'] === '' || $data['daily_rate'] <= 0) {
            flash('error', 'Registration, make, model and a positive daily rate are required.');
            remember_old($_POST);
            redirect('admin/vehicle-edit.php' . ($id ? "?id=$id" : ''));
        }
        $dup = Database::value('SELECT id FROM vehicles WHERE reg_no = ? AND id != ?', [$data['reg_no'], $id]);
        if ($dup) {
            flash('error', 'Another vehicle already uses this registration number.');
            remember_old($_POST);
            redirect('admin/vehicle-edit.php' . ($id ? "?id=$id" : ''));
        }

        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
            Database::run("UPDATE vehicles SET $sets WHERE id = :id", $data + ['id' => $id]);
            Audit::log(Auth::id(), 'update_vehicle', 'vehicles', 'vehicle', $id, $vehicle, $data);
            flash('success', 'Vehicle updated.');
        } else {
            $cols = implode(',', array_keys($data));
            $marks = ':' . implode(',:', array_keys($data));
            $id = Database::insert("INSERT INTO vehicles ($cols) VALUES ($marks)", $data);
            Audit::log(Auth::id(), 'create_vehicle', 'vehicles', 'vehicle', $id, null, $data);
            flash('success', 'Vehicle added.');
        }
        clear_old();
        redirect('admin/vehicle-edit.php?id=' . $id);
    }

    if ($id && $action === 'upload_photo') {
        [$name, $err] = upload_file($_FILES['photo'] ?? [], PUBLIC_UPLOAD_PATH . '/vehicles',
            config('uploads.image_types'));
        if ($err) {
            flash('error', $err);
        } else {
            $hasPrimary = Database::value('SELECT COUNT(*) FROM vehicle_photos WHERE vehicle_id = ?', [$id]);
            Database::run(
                'INSERT INTO vehicle_photos (vehicle_id, file_path, is_primary, is_public, sort_order)
                 VALUES (?,?,?,?,?)',
                [$id, 'vehicles/' . $name, $hasPrimary ? 0 : 1, isset($_POST['photo_public']) ? 1 : 0, 99]
            );
            Audit::log(Auth::id(), 'upload_vehicle_photo', 'vehicles', 'vehicle', $id);
            flash('success', 'Photo uploaded.');
        }
        redirect('admin/vehicle-edit.php?id=' . $id);
    }

    if ($id && $action === 'set_primary') {
        $pid = (int) $_POST['photo_id'];
        Database::run('UPDATE vehicle_photos SET is_primary = 0 WHERE vehicle_id = ?', [$id]);
        Database::run('UPDATE vehicle_photos SET is_primary = 1 WHERE id = ? AND vehicle_id = ?', [$pid, $id]);
        redirect('admin/vehicle-edit.php?id=' . $id);
    }

    if ($id && $action === 'delete_photo') {
        $pid = (int) $_POST['photo_id'];
        $p = Database::one('SELECT * FROM vehicle_photos WHERE id = ? AND vehicle_id = ?', [$pid, $id]);
        if ($p) {
            @unlink(PUBLIC_UPLOAD_PATH . '/' . $p['file_path']);
            Database::run('DELETE FROM vehicle_photos WHERE id = ?', [$pid]);
        }
        redirect('admin/vehicle-edit.php?id=' . $id);
    }

    if ($id && $action === 'upload_doc') {
        [$name, $err] = upload_file($_FILES['doc'] ?? [], UPLOAD_PATH . '/docs', config('uploads.doc_types'));
        if ($err) {
            flash('error', $err);
        } else {
            Database::run(
                'INSERT INTO vehicle_documents (vehicle_id, doc_type, title, file_path, expiry_date)
                 VALUES (?,?,?,?,?)',
                [$id, $_POST['doc_type'] ?? 'other', trim($_POST['doc_title'] ?? '') ?: null,
                 'docs/' . $name, ($_POST['doc_expiry'] ?? '') ?: null]
            );
            flash('success', 'Document uploaded.');
        }
        redirect('admin/vehicle-edit.php?id=' . $id . '#docs');
    }
}

$photos = $id ? Database::all('SELECT * FROM vehicle_photos WHERE vehicle_id = ? ORDER BY is_primary DESC, sort_order', [$id]) : [];
$docs = $id ? Database::all('SELECT * FROM vehicle_documents WHERE vehicle_id = ? ORDER BY id DESC', [$id]) : [];
$v = array_merge($vehicle ?? [], $_SESSION['_old'] ?? []);
clear_old();

$pageTitle = $id ? 'Vehicle Details' : 'Add Vehicle';
$active = 'vehicles';
require APP_PATH . '/views/admin/header.php';
?>
<form method="post" class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save">

    <!-- Left column -->
    <div class="space-y-6">
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Photos</h3>
            <?php if ($photos): ?>
            <div class="grid grid-cols-3 gap-3 mb-4">
                <?php foreach ($photos as $p): ?>
                <div class="relative group">
                    <img src="<?= url('uploads/' . $p['file_path']) ?>" class="w-full h-24 object-cover rounded-lg border <?= $p['is_primary'] ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200' ?>">
                    <?php if ($p['is_primary']): ?>
                    <span class="absolute top-1 left-1 badge bg-blue-600 text-white">Primary</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-2 text-xs">
                <?php foreach ($photos as $p): ?>
                <div class="flex flex-col items-center gap-1">
                    <form method="post"><?= Csrf::field() ?>
                        <input type="hidden" name="action" value="set_primary">
                        <input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
                        <button class="text-blue-600 hover:underline">Set primary</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Delete this photo?')"><?= Csrf::field() ?>
                        <input type="hidden" name="action" value="delete_photo">
                        <input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
                        <button class="text-red-600 hover:underline">Delete</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="h-32 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 text-sm mb-4">
                No photos yet <?= $id ? '' : '— save the vehicle first' ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Registration</h3>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="label">Registration No *</label>
                    <input name="reg_no" class="input" required value="<?= e($v['reg_no'] ?? '') ?>"></div>
                <div><label class="label">Colour</label>
                    <input name="colour" class="input" value="<?= e($v['colour'] ?? '') ?>"></div>
                <div><label class="label">Make *</label>
                    <input name="make" class="input" required value="<?= e($v['make'] ?? '') ?>"></div>
                <div><label class="label">Model *</label>
                    <input name="model" class="input" required value="<?= e($v['model'] ?? '') ?>"></div>
                <div><label class="label">Year</label>
                    <input name="year" type="number" class="input" value="<?= e((string) ($v['year'] ?? '')) ?>"></div>
                <div><label class="label">Mileage (km)</label>
                    <input name="mileage" type="number" class="input" value="<?= e((string) ($v['mileage'] ?? 0)) ?>"></div>
            </div>
        </div>

        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Specifications</h3>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="label">Transmission</label>
                    <select name="transmission" class="input">
                        <?php foreach (['automatic','manual'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($v['transmission'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div><label class="label">Fuel Type</label>
                    <select name="fuel_type" class="input">
                        <?php foreach (['petrol','diesel','hybrid','electric'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($v['fuel_type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div><label class="label">Engine Capacity</label>
                    <input name="engine_capacity" class="input" placeholder="e.g. 1.8L" value="<?= e($v['engine_capacity'] ?? '') ?>"></div>
                <div><label class="label">Seats</label>
                    <input name="seats" type="number" min="1" max="60" class="input" value="<?= e((string) ($v['seats'] ?? 5)) ?>"></div>
            </div>
            <div class="mt-4"><label class="label">Description</label>
                <textarea name="description" rows="3" class="input"><?= e($v['description'] ?? '') ?></textarea></div>
        </div>
    </div>

    <!-- Right column -->
    <div class="space-y-6">
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Pricing</h3>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="label">Daily Rate *</label>
                    <input name="daily_rate" type="number" step="0.01" min="0" class="input" required value="<?= e((string) ($v['daily_rate'] ?? '')) ?>"></div>
                <div><label class="label">Weekly Rate</label>
                    <input name="weekly_rate" type="number" step="0.01" min="0" class="input" value="<?= e((string) ($v['weekly_rate'] ?? '')) ?>"></div>
                <div><label class="label">Monthly Rate</label>
                    <input name="monthly_rate" type="number" step="0.01" min="0" class="input" value="<?= e((string) ($v['monthly_rate'] ?? '')) ?>"></div>
                <div><label class="label">Security Deposit</label>
                    <input name="deposit" type="number" step="0.01" min="0" class="input" value="<?= e((string) ($v['deposit'] ?? '')) ?>"></div>
                <div class="col-span-2"><label class="label">Excess Mileage Rate / km</label>
                    <input name="excess_mileage_rate" type="number" step="0.01" min="0" class="input" value="<?= e((string) ($v['excess_mileage_rate'] ?? '')) ?>"></div>
            </div>
        </div>

        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Status & Visibility</h3>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="label">Status</label>
                    <select name="status" class="input">
                        <?php foreach (['available','reserved','on_hire','maintenance','unavailable'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($v['status'] ?? 'available') === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>
            <label class="flex items-center gap-2 mt-4 text-sm text-gray-700">
                <input type="checkbox" name="is_public" <?= !empty($v['is_public']) || !$id ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600">
                Visible on public website
            </label>
            <label class="flex items-center gap-2 mt-2 text-sm text-gray-700">
                <input type="checkbox" name="is_featured" <?= !empty($v['is_featured']) ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600">
                Featured vehicle
            </label>
        </div>

        <button class="btn-primary w-full justify-center !py-2.5">
            <i data-lucide="save" class="w-4 h-4"></i> <?= $id ? 'Save Changes' : 'Add Vehicle' ?>
        </button>
    </div>
</form>

<?php if ($id): ?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-6">
    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Upload Photo</h3>
        <form method="post" enctype="multipart/form-data" class="space-y-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="upload_photo">
            <input type="file" name="photo" accept="image/*" required class="input !py-1.5">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="photo_public" checked class="rounded border-gray-300 text-blue-600">
                Show on public website
            </label>
            <button class="btn-secondary"><i data-lucide="upload" class="w-4 h-4"></i> Upload</button>
        </form>
    </div>

    <div class="card" id="docs">
        <h3 class="font-semibold text-slate-800 mb-4">Vehicle Documents</h3>
        <ul class="divide-y divide-gray-100 mb-4 text-sm">
            <?php foreach ($docs as $d): ?>
            <li class="py-2.5 flex items-center justify-between">
                <div>
                    <p class="font-medium text-slate-700"><?= e($d['title'] ?: ucfirst($d['doc_type'])) ?></p>
                    <p class="text-xs text-slate-400"><?= e(ucfirst($d['doc_type'])) ?>
                        <?= $d['expiry_date'] ? '· expires ' . e(fmt_date($d['expiry_date'])) : '' ?></p>
                </div>
                <a href="<?= url('admin/doc.php?id=' . $d['id'] . '&t=vehicle') ?>" class="text-blue-600 hover:underline text-xs">View</a>
            </li>
            <?php endforeach; ?>
            <?php if (!$docs): ?><li class="py-3 text-slate-400">No documents uploaded.</li><?php endif; ?>
        </ul>
        <form method="post" enctype="multipart/form-data" class="grid grid-cols-2 gap-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="upload_doc">
            <select name="doc_type" class="input">
                <option value="registration">Registration</option>
                <option value="insurance">Insurance</option>
                <option value="licence">Licence</option>
                <option value="other">Other</option>
            </select>
            <input name="doc_title" class="input" placeholder="Title (optional)">
            <input type="file" name="doc" required class="input !py-1.5 col-span-2">
            <div class="col-span-2"><label class="label">Expiry date (if applicable)</label>
                <input type="date" name="doc_expiry" class="input"></div>
            <button class="btn-secondary col-span-2 justify-center"><i data-lucide="upload" class="w-4 h-4"></i> Upload Document</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
