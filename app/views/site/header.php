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
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<script src="<?= url('assets/vendor/lucide.min.js') ?>" defer></script>
<script src="<?= url('assets/js/theme.js') ?>" defer></script>
</head>
<body class="bg-white">
<header class="site-header bg-white border-b border-gray-200 text-slate-700 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <a href="<?= url('index.php') ?>" class="flex items-center gap-3 shrink-0" aria-label="Home">
            <span class="w-9 h-9 rounded-lg bg-blue-600 text-white flex items-center justify-center">
                <i data-lucide="car" class="w-5 h-5"></i>
            </span>
        </a>
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
            <button id="siteMenuBtn" class="md:hidden inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 text-slate-600" aria-label="Open menu">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
        </div>
    </div>
    <nav id="siteMenu" class="hidden md:hidden border-t border-gray-200 px-4 py-2 space-y-1 text-sm bg-white">
        <?php foreach ($navItems as [$href, $label, $key]): ?>
        <a href="<?= url($href) ?>" class="block px-3 py-2 rounded-md <?= $navActive === $key ? 'text-blue-600 font-medium bg-blue-50' : 'text-slate-600' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <script>
    document.getElementById('siteMenuBtn').addEventListener('click', function () {
        document.getElementById('siteMenu').classList.toggle('hidden');
    });
    </script>
</header>
<?php if ($msg = flash('success')): ?>
<div class="max-w-7xl mx-auto px-6 mt-4"><div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"><?= e($msg) ?></div></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
<div class="max-w-7xl mx-auto px-6 mt-4"><div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"><?= e($msg) ?></div></div>
<?php endif; ?>
