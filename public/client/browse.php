<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Services\VehicleService;

Auth::requireClient();

$pickup = $_GET['pickup'] ?? '';
$return = $_GET['return'] ?? '';
$fuel = $_GET['fuel'] ?? '';
$trans = $_GET['transmission'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';

$where = "is_public = 1 AND status NOT IN ('maintenance','unavailable')";
$params = [];
if ($fuel !== '') { $where .= ' AND fuel_type = ?'; $params[] = $fuel; }
if ($trans !== '') { $where .= ' AND transmission = ?'; $params[] = $trans; }
if ($maxPrice !== '') { $where .= ' AND daily_rate <= ?'; $params[] = (float) $maxPrice; }

$vehicles = Database::all("SELECT * FROM vehicles WHERE $where ORDER BY daily_rate", $params);

// Filter by date availability server-side when dates chosen.
if ($pickup !== '' && $return !== '' && strtotime($return) > strtotime($pickup)) {
    $vehicles = array_values(array_filter($vehicles, fn($v) =>
        VehicleService::isAvailable((int) $v['id'], $pickup . ' 09:00', $return . ' 17:00')));
}

$pageTitle = 'Book a Vehicle';
$active = 'browse';
require APP_PATH . '/views/client/header.php';
?>
<div class="card mb-6">
    <form method="get" class="flex flex-wrap items-end gap-3">
        <div><label class="label">Pickup date</label><input type="date" name="pickup" value="<?= e($pickup) ?>" min="<?= date('Y-m-d') ?>" class="input"></div>
        <div><label class="label">Return date</label><input type="date" name="return" value="<?= e($return) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="input"></div>
        <div><label class="label">Fuel</label>
            <select name="fuel" class="input w-32"><option value="">Any</option>
            <?php foreach (['petrol','diesel','hybrid','electric'] as $f): ?>
            <option value="<?= $f ?>" <?= $fuel === $f ? 'selected' : '' ?>><?= ucfirst($f) ?></option><?php endforeach; ?></select></div>
        <div><label class="label">Transmission</label>
            <select name="transmission" class="input w-36"><option value="">Any</option>
            <option value="automatic" <?= $trans === 'automatic' ? 'selected' : '' ?>>Automatic</option>
            <option value="manual" <?= $trans === 'manual' ? 'selected' : '' ?>>Manual</option></select></div>
        <div><label class="label">Max daily rate</label><input type="number" name="max_price" value="<?= e($maxPrice) ?>" class="input w-32" min="0"></div>
        <button class="btn-primary"><i data-lucide="search" class="w-4 h-4"></i> Check availability</button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
    <?php foreach ($vehicles as $v):
        $photo = VehicleService::photo((int) $v['id']);
        $quote = ($pickup && $return && strtotime($return) > strtotime($pickup))
            ? VehicleService::quote($v, $pickup . ' 09:00', $return . ' 17:00') : null; ?>
    <div class="card !p-0 overflow-hidden flex flex-col">
        <img src="<?= url($photo) ?>" class="w-full h-44 object-cover" alt="<?= e($v['make'] . ' ' . $v['model']) ?>">
        <div class="p-5 flex-1 flex flex-col">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-semibold text-slate-800"><?= e($v['make'] . ' ' . $v['model']) ?></h3>
                    <p class="text-xs text-slate-500"><?= e($v['year']) ?> · <?= e(ucfirst($v['transmission'])) ?> · <?= e(ucfirst($v['fuel_type'])) ?> · <?= (int) $v['seats'] ?> seats</p>
                </div>
                <?= status_badge($v['status'] === 'on_hire' ? 'reserved' : $v['status']) ?>
            </div>
            <p class="text-sm text-slate-500 mt-2 line-clamp-2 flex-1"><?= e($v['description']) ?></p>
            <div class="flex items-end justify-between mt-4">
                <div>
                    <p class="text-xl font-semibold text-slate-800"><?= money($v['daily_rate']) ?><span class="text-sm font-normal text-slate-400">/day</span></p>
                    <?php if ($quote): ?>
                    <p class="text-xs text-blue-600 font-medium"><?= $quote['days'] ?> days ≈ <?= money($quote['base']) ?> + <?= money($quote['deposit']) ?> deposit</p>
                    <?php endif; ?>
                </div>
                <a href="<?= url('client/book.php?vehicle=' . $v['id'] . ($pickup ? '&pickup=' . $pickup : '') . ($return ? '&return=' . $return : '')) ?>"
                   class="btn-primary !py-1.5 text-xs">Book now</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php if (!$vehicles): ?>
<div class="card text-center py-16 text-slate-400">No vehicles match your criteria — try different dates or filters.</div>
<?php endif; ?>
<?php require APP_PATH . '/views/client/footer.php'; ?>
