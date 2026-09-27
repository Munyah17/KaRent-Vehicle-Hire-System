<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Database;
use App\Services\VehicleService;

$featured = Database::all(
    "SELECT * FROM vehicles WHERE is_public = 1 AND status NOT IN ('maintenance','unavailable')
     ORDER BY is_featured DESC, daily_rate LIMIT 6");

// Hero slides — managed from Admin → Documents → Hero slides (max 20)
$slides = Database::all(
    'SELECT * FROM hero_slides WHERE is_active = 1 ORDER BY sort_order, id LIMIT 20');
if (!$slides) {
    $slides = [[
        'image' => 'assets/img/car-placeholder.jpg',
        'title' => config('app_name'),
        'subtitle' => 'Easy Bookings · Safe Journeys · Complete Control',
        'description' => 'Reliable vehicles, transparent pricing and verified payments.',
        'cta1_label' => 'Browse Fleet', 'cta1_url' => 'vehicles.php',
        'cta2_label' => 'Get Started', 'cta2_url' => 'register.php',
        'overlay' => 70,
    ]];
}

$pageTitle = 'Easy Bookings · Safe Journeys · Complete Control';
$navActive = 'home';
require APP_PATH . '/views/site/header.php';
?>
<!-- Hero slider -->
<section class="hero-slider relative overflow-hidden" id="heroSlider">
    <?php foreach ($slides as $i => $s): ?>
    <div class="hero-slide absolute inset-0 transition-opacity duration-700 <?= $i === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none' ?>"
         data-slide="<?= $i ?>">
        <img src="<?= url($s['image']) ?>" alt="<?= e($s['title']) ?>"
             class="absolute inset-0 w-full h-full object-cover" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
        <div class="absolute inset-0 bg-black" style="opacity: <?= (int) $s['overlay'] / 100 ?>"></div>
        <div class="relative z-10 h-full flex items-center">
            <div class="max-w-7xl mx-auto px-6 w-full">
                <div class="max-w-2xl text-white">
                    <?php if (!empty($s['subtitle'])): ?>
                    <p class="text-sm uppercase tracking-widest text-blue-200 mb-2"><?= e($s['subtitle']) ?></p>
                    <?php endif; ?>
                    <h1 class="text-4xl md:text-5xl font-bold mb-3"><?= e($s['title']) ?></h1>
                    <?php if (!empty($s['description'])): ?>
                    <p class="text-lg text-gray-200 mb-6"><?= e($s['description']) ?></p>
                    <?php endif; ?>
                    <div class="flex flex-wrap gap-3">
                        <?php if (!empty($s['cta1_label'])): ?>
                        <a href="<?= url($s['cta1_url'] ?: 'vehicles.php') ?>"
                           class="btn-primary !px-6 !py-3"><?= e($s['cta1_label']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($s['cta2_label'])): ?>
                        <a href="<?= url($s['cta2_url'] ?: 'vehicles.php') ?>"
                           class="inline-flex items-center gap-2 border border-white/60 text-white hover:bg-white/10 px-6 py-3 rounded-md text-sm font-medium transition-colors"><?= e($s['cta2_label']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (count($slides) > 1): ?>
    <button type="button" id="heroPrev" aria-label="Previous slide"
            class="absolute z-20 left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center">
        <i data-lucide="chevron-left" class="w-5 h-5"></i></button>
    <button type="button" id="heroNext" aria-label="Next slide"
            class="absolute z-20 right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center">
        <i data-lucide="chevron-right" class="w-5 h-5"></i></button>
    <div class="absolute z-20 bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5" id="heroDots">
        <?php foreach ($slides as $i => $s): ?>
        <button type="button" data-dot="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"
                class="w-2.5 h-2.5 rounded-full <?= $i === 0 ? 'bg-white' : 'bg-white/40' ?> hover:bg-white transition-colors"></button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<style>
.hero-slider { height: 520px; }
@media (max-width: 768px) { .hero-slider { height: 430px; } }
</style>
<script>
(function () {
    var slides = document.querySelectorAll('.hero-slide');
    if (slides.length < 2) return;
    var dots = document.querySelectorAll('#heroDots [data-dot]');
    var i = 0, timer;
    function show(n) {
        slides[i].classList.remove('opacity-100', 'z-10');
        slides[i].classList.add('opacity-0', 'z-0', 'pointer-events-none');
        dots.forEach(function (d) { d.classList.replace('bg-white', 'bg-white/40'); });
        i = (n + slides.length) % slides.length;
        slides[i].classList.add('opacity-100', 'z-10');
        slides[i].classList.remove('opacity-0', 'z-0', 'pointer-events-none');
        dots[i].classList.replace('bg-white/40', 'bg-white');
    }
    function go(n) { show(n); reset(); }
    function reset() { clearInterval(timer); timer = setInterval(function () { show(i + 1); }, 6000); }
    document.getElementById('heroPrev').addEventListener('click', function () { go(i - 1); });
    document.getElementById('heroNext').addEventListener('click', function () { go(i + 1); });
    dots.forEach(function (d) { d.addEventListener('click', function () { go(+d.dataset.dot); }); });
    var sx = null;
    document.getElementById('heroSlider').addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, {passive: true});
    document.getElementById('heroSlider').addEventListener('touchend', function (e) {
        if (sx === null) return;
        var dx = e.changedTouches[0].clientX - sx;
        if (Math.abs(dx) > 40) go(i + (dx < 0 ? 1 : -1));
        sx = null;
    }, {passive: true});
    reset();
})();
</script>

<!-- Quick book — moved below the hero -->
<section class="bg-gray-50 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-6 py-8">
        <form action="<?= url('vehicles.php') ?>" method="get"
              class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col sm:flex-row gap-3 items-stretch sm:items-end max-w-2xl">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Pickup</label>
                <input type="date" name="pickup" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" class="input">
            </div>
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Return</label>
                <input type="date" name="return" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" class="input">
            </div>
            <button class="btn-primary !px-6 justify-center">
                <i data-lucide="search" class="w-4 h-4"></i> Find a vehicle</button>
        </form>
    </div>
</section>

<!-- Access paths -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 my-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="card !p-3">
            <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"><i data-lucide="globe" class="w-4 h-4"></i></span>
                <div class="min-w-0">
                    <h3 class="font-semibold text-slate-800 text-sm">Browse as guest</h3>
                    <p class="text-xs text-slate-500">No account needed — browse vehicles, check availability and prices. <a href="<?= url('vehicles.php') ?>" class="text-blue-600 font-medium hover:underline whitespace-nowrap">View vehicles →</a></p>
                </div>
            </div>
        </div>
        <div class="card !p-3">
            <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"><i data-lucide="user" class="w-4 h-4"></i></span>
                <div class="min-w-0">
                    <h3 class="font-semibold text-slate-800 text-sm">Client portal</h3>
                    <p class="text-xs text-slate-500">Manage bookings, payments, deposits, wallet and documents. <a href="<?= url('register.php') ?>" class="text-blue-600 font-medium hover:underline whitespace-nowrap">Create account →</a></p>
                </div>
            </div>
        </div>
        <div class="card !p-3">
            <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"><i data-lucide="shield-check" class="w-4 h-4"></i></span>
                <div class="min-w-0">
                    <h3 class="font-semibold text-slate-800 text-sm">Simple & secure</h3>
                    <p class="text-xs text-slate-500">Verified payments via Paynow, transparent pricing, real photos. <a href="<?= url('terms.php') ?>" class="text-blue-600 font-medium hover:underline whitespace-nowrap">Read terms →</a></p>
                </div>
            </div>
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
