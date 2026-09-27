<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Services\VehicleService;

$id = (int) ($_GET['id'] ?? 0);
$v = VehicleService::find($id);
if (!$v || !$v['is_public']) { http_response_code(404); exit('Vehicle not found.'); }

$photos = Database::all('SELECT * FROM vehicle_photos WHERE vehicle_id = ? AND is_public = 1 ORDER BY is_primary DESC, sort_order', [$id]);
$pickup = $_GET['pickup'] ?? date('Y-m-d');
$return = $_GET['return'] ?? date('Y-m-d', strtotime('+3 days'));
$quote = VehicleService::quote($v, $pickup . ' 09:00', $return . ' 17:00');
$available = VehicleService::isAvailable($id, $pickup . ' 09:00', $return . ' 17:00');
$bookUrl = Auth::isClient()
    ? url('client/book.php?vehicle=' . $id . "&pickup=$pickup&return=$return")
    : url('login.php');
$mainPhoto = !empty($photos) ? 'uploads/' . $photos[0]['file_path'] : 'assets/img/car-placeholder.jpg';

$pageTitle = $v['make'] . ' ' . $v['model'];
$navActive = 'vehicles';
require APP_PATH . '/views/site/header.php';
?>
<div class="max-w-7xl mx-auto px-6 py-10">
    <a href="<?= url('vehicles.php') ?>" class="text-sm text-blue-600 hover:underline mb-6 inline-flex items-center gap-1"><i data-lucide="arrow-left" class="w-4 h-4"></i> Back to vehicles</a>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mt-4">
        <div class="xl:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <img src="<?= url($mainPhoto) ?>" id="mainimg" class="w-full h-80 object-cover" alt="">
                <?php if (count($photos) > 1): ?>
                <div class="flex gap-2 p-3">
                    <?php foreach ($photos as $p): ?>
                    <img src="<?= url('uploads/' . $p['file_path']) ?>" onclick="document.getElementById('mainimg').src=this.src"
                         class="w-20 h-14 object-cover rounded-md border border-gray-200 cursor-pointer hover:border-blue-400">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 mt-6">
                <h2 class="font-semibold text-slate-800 mb-4">About this vehicle</h2>
                <p class="text-sm text-slate-600 leading-relaxed"><?= e($v['description'] ?? 'No description available.') ?></p>
                <h3 class="font-semibold text-slate-800 mt-6 mb-3">Specifications</h3>
                <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-slate-400">Year</dt><dd class="font-medium"><?= e((string) $v['year']) ?></dd></div>
                    <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-slate-400">Transmission</dt><dd class="font-medium"><?= e(ucfirst($v['transmission'])) ?></dd></div>
                    <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-slate-400">Fuel</dt><dd class="font-medium"><?= e(ucfirst($v['fuel_type'])) ?></dd></div>
                    <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-slate-400">Engine</dt><dd class="font-medium"><?= e($v['engine_capacity'] ?? '—') ?></dd></div>
                    <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-slate-400">Seats</dt><dd class="font-medium"><?= (int) $v['seats'] ?></dd></div>
                    <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-slate-400">Colour</dt><dd class="font-medium"><?= e($v['colour'] ?? '—') ?></dd></div>
                    <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-slate-400">Mileage</dt><dd class="font-medium"><?= number_format($v['mileage']) ?> km</dd></div>
                    <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-slate-400">Registration</dt><dd class="font-medium"><?= e($v['reg_no']) ?></dd></div>
                </dl>
            </div>
        </div>

        <div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 sticky top-24">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-800"><?= e($v['make'] . ' ' . $v['model']) ?></h1>
                        <p class="text-sm text-slate-500"><?= e($v['year']) ?></p>
                    </div>
                    <?= status_badge($v['status'] === 'on_hire' ? 'reserved' : $v['status']) ?>
                </div>
                <div class="space-y-2 text-sm border-y border-gray-100 py-4 mb-4">
                    <div class="flex justify-between"><span class="text-slate-500">Daily</span><span class="font-semibold"><?= money($v['daily_rate']) ?></span></div>
                    <?php if ($v['weekly_rate']): ?><div class="flex justify-between"><span class="text-slate-500">Weekly</span><span class="font-medium"><?= money($v['weekly_rate']) ?></span></div><?php endif; ?>
                    <?php if ($v['monthly_rate']): ?><div class="flex justify-between"><span class="text-slate-500">Monthly</span><span class="font-medium"><?= money($v['monthly_rate']) ?></span></div><?php endif; ?>
                    <div class="flex justify-between"><span class="text-slate-500">Security deposit</span><span class="font-medium"><?= money($v['deposit']) ?></span></div>
                </div>
                <form method="get" class="space-y-3">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div><label class="label">Pickup</label><input type="date" name="pickup" value="<?= e($pickup) ?>" min="<?= date('Y-m-d') ?>" class="input"></div>
                    <div><label class="label">Return</label><input type="date" name="return" value="<?= e($return) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="input"></div>
                    <button class="btn-secondary w-full justify-center">Check availability</button>
                </form>
                <div class="mt-4 rounded-lg <?= $available ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800' ?> border px-4 py-3 text-sm">
                    <?php if ($available): ?>
                    <strong>Available</strong> · <?= $quote['days'] ?> day(s) ≈ <?= money($quote['base']) ?> rental + <?= money($quote['deposit']) ?> deposit
                    <?php else: ?>
                    Not available for the selected dates.
                    <?php endif; ?>
                </div>
                <?php if ($available): ?>
                <a href="<?= $bookUrl ?>" class="btn-primary w-full justify-center mt-4 !py-3 text-base">
                    <i data-lucide="calendar-check" class="w-5 h-5"></i>
                    <?= Auth::isClient() ? 'Book this vehicle' : 'Sign in to book' ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
