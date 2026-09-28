<?php
/** Client portal layout — lighter, customer-facing design. Pages set $pageTitle, $active. */
use App\Auth;
use App\Notify;
$user = Auth::user();
$client = Auth::client();
$active = $active ?? '';
$unread = $user ? Notify::unreadCount((int) $user['id']) : 0;
$navItems = [
    ['index.php', 'Dashboard', 'layout-dashboard', 'dashboard'],
    ['browse.php', 'Book a Vehicle', 'car', 'browse'],
    ['bookings.php', 'My Bookings', 'calendar-check', 'bookings'],
    ['payments.php', 'Payments', 'credit-card', 'payments'],
    ['wallet.php', 'Wallet', 'wallet', 'wallet'],
    ['deposits.php', 'Deposits', 'piggy-bank', 'deposits'],
    ['documents.php', 'Documents', 'file-text', 'documents'],
    ['kyc.php', 'Profile & KYC', 'shield-check', 'kyc'],
    ['extensions.php', 'Extensions', 'calendar-plus', 'extensions'],
    ['support.php', 'Support', 'life-buoy', 'support'],
    ['settings.php', 'Settings', 'settings', 'settings'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'My Account') ?> — <?= e(setting('company_name', 'Vehicle Hire')) ?></title>
<script>try{if((localStorage.getItem('karent.theme')||(document.cookie.match(/theme=(dark)/)||[])[1])==='dark'){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-bs-theme','dark')}}catch(e){}</script>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<script src="<?= asset('assets/vendor/lucide.min.js') ?>" defer></script>
<script src="<?= asset('assets/js/theme.js') ?>" defer></script>
</head>
<body class="bg-slate-50">
<header class="site-header bg-white border-b border-gray-200 text-slate-700 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <button id="drawerBtn" type="button" aria-label="Open menu" aria-controls="siteDrawer"
                class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 text-slate-600 hover:bg-gray-50">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
        <div class="flex items-center gap-3 sm:gap-4 text-sm">
            <button class="theme-toggle" type="button" data-theme-toggle title="Toggle dark / light mode">
                <i data-lucide="sun-moon" class="w-4 h-4"></i>
            </button>
            <a href="<?= url('client/notifications.php') ?>" class="relative text-slate-500 hover:text-slate-700">
                <i data-lucide="bell" class="w-5 h-5"></i>
                <?php if ($unread): ?><span class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center"><?= $unread ?></span><?php endif; ?>
            </a>
            <span class="text-slate-500 hidden md:block"><?= e($client['full_name'] ?? $user['name']) ?></span>
            <a href="<?= url('logout.php') ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-md font-medium">Sign out</a>
        </div>
    </div>
</header>

<!-- Off-canvas navigation drawer -->
<div id="drawerBackdrop"></div>
<aside id="siteDrawer" aria-label="Portal navigation">
    <div class="flex items-center justify-between px-5 h-16 border-b border-gray-200">
        <span class="font-semibold text-slate-800">Menu</span>
        <button id="drawerClose" type="button" aria-label="Close menu"
                class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 text-slate-600">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
        <?php foreach ($navItems as [$href, $label, $icon, $key]): ?>
        <a href="<?= url('client/' . $href) ?>"
           class="drawer-link flex items-center gap-3 px-3 py-2.5 rounded-md <?= $active === $key ? 'text-blue-600 font-medium bg-blue-50' : 'text-slate-600 hover:bg-gray-50' ?>">
            <i data-lucide="<?= $icon ?>" class="w-4 h-4"></i><?= e($label) ?>
        </a>
        <?php endforeach; ?>
    </nav>
</aside>
<script>
(function () {
    var d = document.getElementById('siteDrawer'), b = document.getElementById('drawerBackdrop');
    function open()  { d.classList.add('open');  b.classList.add('open');  document.body.style.overflow = 'hidden'; }
    function close() { d.classList.remove('open'); b.classList.remove('open'); document.body.style.overflow = ''; }
    document.getElementById('drawerBtn').addEventListener('click', open);
    document.getElementById('drawerClose').addEventListener('click', close);
    b.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
<?php if ($msg = flash('success')): ?>
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"><?= e($msg) ?></div>
<?php endif; ?>
