<?php
/** Public website layout header. Pages set $pageTitle and $navActive. */
use App\Auth;
$user = Auth::user();
$navActive = $navActive ?? '';
$navItems = [
    ['index.php', 'Home', 'home'],
    ['vehicles.php', 'Vehicles', 'vehicles'],
    ['about.php', 'About', 'about'],
    ['contact.php', 'Contact', 'contact'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(isset($pageTitle) ? $pageTitle . ' — ' : '') ?><?= e(setting('company_name', 'Vehicle Hire')) ?></title>
<script>try{if((localStorage.getItem('karent.theme')||(document.cookie.match(/theme=(dark)/)||[])[1])==='dark'){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-bs-theme','dark')}}catch(e){}</script>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<script src="<?= asset('assets/vendor/lucide.min.js') ?>" defer></script>
<script src="<?= asset('assets/js/theme.js') ?>" defer></script>
</head>
<body class="bg-white">
<header class="site-header bg-white border-b border-gray-200 text-slate-700 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <button id="drawerBtn" type="button" aria-label="Open menu" aria-controls="siteDrawer"
                class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 text-slate-600 hover:bg-gray-50">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
        <nav class="hidden md:flex items-center gap-6 text-sm">
            <?php foreach ($navItems as [$href, $label, $key]): ?>
            <a href="<?= url($href) ?>" class="<?= $navActive === $key ? 'text-blue-600 font-medium' : 'text-slate-600 hover:text-slate-900' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="flex items-center gap-2 sm:gap-3 text-sm">
            <button class="theme-toggle" type="button" data-theme-toggle title="Toggle dark / light mode">
                <i data-lucide="sun-moon" class="w-4 h-4"></i>
            </button>
            <?php if ($user): ?>
                <?php if (Auth::isAdmin()): ?>
                <a href="<?= url('admin/index.php') ?>" class="hidden sm:inline text-slate-600 hover:text-slate-900">Back Office</a>
                <?php else: ?>
                <a href="<?= url('client/index.php') ?>" class="hidden sm:inline text-slate-600 hover:text-slate-900">My Account</a>
                <?php endif; ?>
                <a href="<?= url('logout.php') ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-md font-medium">Sign out</a>
            <?php else: ?>
                <a href="<?= url('login.php') ?>" class="hidden sm:inline text-slate-600 hover:text-slate-900">Sign in</a>
                <a href="<?= url('register.php') ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-md font-medium">Get Started</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Off-canvas navigation drawer -->
<div id="drawerBackdrop"></div>
<aside id="siteDrawer" aria-label="Site navigation">
    <div class="flex items-center justify-between px-5 h-16 border-b border-gray-200">
        <span class="font-semibold text-slate-800 dark:text-slate-100">Menu</span>
        <button id="drawerClose" type="button" aria-label="Close menu"
                class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 text-slate-600">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
        <?php foreach ($navItems as [$href, $label, $key]): ?>
        <a href="<?= url($href) ?>" class="drawer-link block px-3 py-2.5 rounded-md <?= $navActive === $key ? 'text-blue-600 font-medium bg-blue-50' : 'text-slate-600 hover:bg-gray-50' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="px-5 py-4 border-t border-gray-200 text-sm space-y-3">
        <?php if ($user): ?>
            <a href="<?= url(Auth::isAdmin() ? 'admin/index.php' : 'client/index.php') ?>" class="block text-blue-600 font-medium"><?= Auth::isAdmin() ? 'Back Office' : 'My Account' ?></a>
            <a href="<?= url('logout.php') ?>" class="block text-slate-600">Sign out</a>
        <?php else: ?>
            <a href="<?= url('register.php') ?>" class="block bg-blue-600 text-white text-center rounded-md px-4 py-2 font-medium">Get Started</a>
            <a href="<?= url('login.php') ?>" class="block text-center text-slate-600">Sign in</a>
        <?php endif; ?>
    </div>
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
<?php if ($msg = flash('success')): ?>
<div class="max-w-7xl mx-auto px-6 mt-4"><div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"><?= e($msg) ?></div></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
<div class="max-w-7xl mx-auto px-6 mt-4"><div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"><?= e($msg) ?></div></div>
<?php endif; ?>
