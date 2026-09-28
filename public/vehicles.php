<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Database;
use App\Services\VehicleService;

$pickup = $_GET['pickup'] ?? '';
$return = $_GET['return'] ?? '';
$fuel = $_GET['fuel'] ?? '';
$trans = $_GET['transmission'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$q = trim($_GET['q'] ?? '');

$where = "is_public = 1 AND status NOT IN ('maintenance','unavailable')";
$params = [];
if ($fuel !== '') { $where .= ' AND fuel_type = ?'; $params[] = $fuel; }
if ($trans !== '') { $where .= ' AND transmission = ?'; $params[] = $trans; }
if ($maxPrice !== '') { $where .= ' AND daily_rate <= ?'; $params[] = (float) $maxPrice; }
if ($q !== '') { $where .= ' AND (make LIKE ? OR model LIKE ?)'; $like = "%$q%"; array_push($params, $like, $like); }

$vehicles = Database::all("SELECT * FROM vehicles WHERE $where ORDER BY daily_rate", $params);
if ($pickup !== '' && $return !== '' && strtotime($return) > strtotime($pickup)) {
    $vehicles = array_values(array_filter($vehicles, fn($v) =>
        VehicleService::isAvailable((int) $v['id'], $pickup . ' 09:00', $return . ' 17:00')));
}

$pageTitle = 'Vehicles';
$navActive = 'vehicles';
require APP_PATH . '/views/site/header.php';
?>
<div class="bg-gray-50 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-6 py-8">
        <h1 class="text-2xl font-semibold text-slate-800 mb-5">Available Vehicles</h1>
        <form method="get" class="flex flex-wrap items-end gap-3">
            <div class="relative">
                <label class="label">Search</label>
                <input name="q" value="<?= e($q) ?>" placeholder="Make or model" class="input w-44">
            </div>
            <div><label class="label">Pickup</label><input type="date" name="pickup" value="<?= e($pickup) ?>" class="input"></div>
            <div><label class="label">Return</label><input type="date" name="return" value="<?= e($return) ?>" class="input"></div>
            <div><label class="label">Fuel</label>
                <select name="fuel" class="input w-32"><option value="">Any</option>
                <?php foreach (['petrol','diesel','hybrid','electric'] as $f): ?>
                <option value="<?= $f ?>" <?= $fuel === $f ? 'selected' : '' ?>><?= ucfirst($f) ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Transmission</label>
                <select name="transmission" class="input w-36"><option value="">Any</option>
                <option value="automatic" <?= $trans === 'automatic' ? 'selected' : '' ?>>Automatic</option>
                <option value="manual" <?= $trans === 'manual' ? 'selected' : '' ?>>Manual</option></select></div>
            <div><label class="label">Max /day</label><input type="number" name="max_price" value="<?= e($maxPrice) ?>" class="input w-28" min="0"></div>
            <button class="btn-primary"><i data-lucide="search" class="w-4 h-4"></i> Search</button>
        </form>
    </div>
</div>

<div class="max-w-7xl mx-auto px-6 py-10">
    <p class="text-sm text-slate-500 mb-6"><?= count($vehicles) ?> vehicle(s) <?= $pickup && $return ? 'available for your dates' : 'in our fleet' ?></p>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($vehicles as $v):
            $photo = VehicleService::photo((int) $v['id']);
            $vehUrl = url('vehicle.php?id=' . $v['id'] . ($pickup ? "&pickup=$pickup&return=$return" : ''));
            $inquireUrl = url('contact.php?subject=' . rawurlencode('Inquiry: ' . $v['make'] . ' ' . $v['model'] . ' (' . $v['reg_no'] . ')')); ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
            <a href="<?= $vehUrl ?>"><img src="<?= url($photo) ?>" class="w-full h-48 object-cover" alt="<?= e($v['make'] . ' ' . $v['model']) ?>"></a>
            <div class="p-5">
                <div class="flex items-start justify-between">
                    <h3 class="font-semibold text-slate-800"><a href="<?= $vehUrl ?>" class="hover:text-blue-600"><?= e($v['make'] . ' ' . $v['model']) ?></a></h3>
                    <?= status_badge($v['status'] === 'on_hire' ? 'reserved' : $v['status']) ?>
                </div>
                <p class="text-xs text-slate-500 mt-1"><?= e($v['year']) ?> · <?= e(ucfirst($v['transmission'])) ?> · <?= e(ucfirst($v['fuel_type'])) ?> · <?= (int) $v['seats'] ?> seats</p>
                <p class="text-sm text-slate-500 mt-2 line-clamp-2"><?= e($v['description']) ?></p>
                <div class="flex items-center justify-between mt-3">
                    <p class="text-lg font-semibold text-blue-600"><?= money($v['daily_rate']) ?><span class="text-sm font-normal text-slate-400">/day</span></p>
                </div>
                <div class="flex gap-2 mt-3">
                    <a href="<?= $inquireUrl ?>" class="flex-1 text-center border border-gray-300 text-slate-700 hover:bg-gray-50 rounded-md px-3 py-2 text-xs font-medium">Inquire</a>
                    <a href="<?= $vehUrl ?>" class="flex-1 text-center bg-blue-600 hover:bg-blue-700 text-white rounded-md px-3 py-2 text-xs font-medium">Book Now</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (!$vehicles): ?>
    <div class="bg-white rounded-xl border border-gray-200 text-center py-20 text-slate-400">
        No vehicles match your search — try different dates or filters.</div>
    <?php endif; ?>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
