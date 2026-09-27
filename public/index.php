<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Database;
use App\Services\VehicleService;

$featured = Database::all(
    "SELECT * FROM vehicles WHERE is_public = 1 AND status NOT IN ('maintenance','unavailable')
     ORDER BY is_featured DESC, daily_rate LIMIT 6");

$pageTitle = 'Easy Bookings · Safe Journeys · Complete Control';
$navActive = 'home';
require APP_PATH . '/views/site/header.php';
?>
<!-- Hero -->
<section class="bg-gradient-to-br from-[#1e3a8a] to-blue-700 text-white">
    <div class="max-w-7xl mx-auto px-6 py-20 text-center">
        <h1 class="text-4xl md:text-5xl font-bold mb-4">Vehicle Hire Management System</h1>
        <p class="text-xl text-blue-100 mb-10">Easy Bookings · Safe Journeys · Complete Control</p>
        <form action="<?= url('vehicles.php') ?>" method="get"
              class="bg-white rounded-xl shadow-lg p-4 max-w-2xl mx-auto flex flex-col sm:flex-row gap-3">
            <div class="flex-1 text-left">
                <label class="block text-xs font-medium text-gray-500 mb-1">Pickup</label>
                <input type="date" name="pickup" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" class="input !border-0 !p-0 text-slate-800 !ring-0">
            </div>
            <div class="flex-1 text-left sm:border-l border-gray-200 sm:pl-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">Return</label>
                <input type="date" name="return" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" class="input !border-0 !p-0 text-slate-800 !ring-0">
            </div>
            <button class="btn-primary !px-6 !py-3 self-end sm:self-auto justify-center">
                <i data-lucide="search" class="w-4 h-4"></i> Find a vehicle</button>
        </form>
    </div>
</section>

<!-- Access paths -->
<section class="max-w-7xl mx-auto px-6 -mt-8 mb-16">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <span class="w-11 h-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-4"><i data-lucide="globe" class="w-5 h-5"></i></span>
            <h3 class="font-semibold text-slate-800 mb-1">Browse as guest</h3>
            <p class="text-sm text-slate-500 mb-4">No account needed — browse vehicles, check availability and prices.</p>
            <a href="<?= url('vehicles.php') ?>" class="text-blue-600 text-sm font-medium hover:underline">View vehicles →</a>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <span class="w-11 h-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-4"><i data-lucide="user" class="w-5 h-5"></i></span>
            <h3 class="font-semibold text-slate-800 mb-1">Client portal</h3>
            <p class="text-sm text-slate-500 mb-4">Manage bookings, payments, deposits, wallet and documents in one place.</p>
            <a href="<?= url('register.php') ?>" class="text-blue-600 text-sm font-medium hover:underline">Create account →</a>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <span class="w-11 h-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-4"><i data-lucide="shield-check" class="w-5 h-5"></i></span>
            <h3 class="font-semibold text-slate-800 mb-1">Simple & secure</h3>
            <p class="text-sm text-slate-500 mb-4">Verified payments via Paynow, transparent pricing, real vehicle photos.</p>
            <a href="<?= url('terms.php') ?>" class="text-blue-600 text-sm font-medium hover:underline">Read terms →</a>
        </div>
    </div>
</section>

<!-- Featured vehicles -->
<section class="max-w-7xl mx-auto px-6 pb-16">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-semibold text-slate-800">Our Fleet</h2>
        <a href="<?= url('vehicles.php') ?>" class="text-blue-600 text-sm font-medium hover:underline">View all →</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($featured as $v):
            $photo = VehicleService::photo((int) $v['id']); ?>
        <a href="<?= url('vehicle.php?id=' . $v['id']) ?>" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
            <img src="<?= url($photo) ?>" class="w-full h-44 object-cover" alt="<?= e($v['make'] . ' ' . $v['model']) ?>">
            <div class="p-5">
                <div class="flex items-start justify-between">
                    <h3 class="font-semibold text-slate-800"><?= e($v['make'] . ' ' . $v['model']) ?></h3>
                    <?= status_badge($v['status'] === 'on_hire' ? 'reserved' : $v['status']) ?>
                </div>
                <p class="text-xs text-slate-500 mt-1"><?= e($v['year']) ?> · <?= e(ucfirst($v['transmission'])) ?> · <?= e(ucfirst($v['fuel_type'])) ?> · <?= (int) $v['seats'] ?> seats</p>
                <div class="flex items-center justify-between mt-3">
                    <p class="text-lg font-semibold text-blue-600"><?= money($v['daily_rate']) ?><span class="text-sm font-normal text-slate-400">/day</span></p>
                    <span class="text-sm text-slate-500 flex items-center gap-1">Details <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php require APP_PATH . '/views/site/footer.php'; ?>
