<?php
/** Public website layout header. Pages set $pageTitle. */
use App\Auth;
$user = Auth::user();
$navActive = $navActive ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(isset($pageTitle) ? $pageTitle . ' — ' : '') ?><?= e(setting('company_name', 'Vehicle Hire')) ?></title>
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<script src="<?= url('assets/vendor/lucide.min.js') ?>" defer></script>
</head>
<body class="bg-white">
<header class="bg-[#1e3a8a] text-white sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <a href="<?= url('index.php') ?>" class="flex items-center gap-3">
            <span class="w-9 h-9 rounded-lg bg-blue-500/30 flex items-center justify-center">
                <i data-lucide="car" class="w-5 h-5"></i>
            </span>
            <span class="font-semibold"><?= e(setting('company_name', 'Vehicle Hire')) ?></span>
        </a>
        <nav class="hidden md:flex items-center gap-6 text-sm">
            <?php foreach ([
                ['index.php', 'Home', 'home'],
                ['vehicles.php', 'Vehicles', 'vehicles'],
                ['about.php', 'About', 'about'],
                ['contact.php', 'Contact', 'contact'],
            ] as [$href, $label, $key]): ?>
            <a href="<?= url($href) ?>" class="<?= $navActive === $key ? 'text-white font-medium' : 'text-blue-200 hover:text-white' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="flex items-center gap-3 text-sm">
            <?php if ($user): ?>
                <?php if (Auth::isAdmin()): ?>
                <a href="<?= url('admin/index.php') ?>" class="text-blue-200 hover:text-white">Back Office</a>
                <?php else: ?>
                <a href="<?= url('client/index.php') ?>" class="text-blue-200 hover:text-white">My Account</a>
                <?php endif; ?>
                <a href="<?= url('logout.php') ?>" class="bg-blue-600 hover:bg-blue-500 px-4 py-1.5 rounded-md font-medium">Sign out</a>
            <?php else: ?>
                <a href="<?= url('login.php') ?>" class="text-blue-200 hover:text-white">Sign in</a>
                <a href="<?= url('register.php') ?>" class="bg-blue-600 hover:bg-blue-500 px-4 py-1.5 rounded-md font-medium">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<?php if ($msg = flash('success')): ?>
<div class="max-w-7xl mx-auto px-6 mt-4"><div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"><?= e($msg) ?></div></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
<div class="max-w-7xl mx-auto px-6 mt-4"><div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"><?= e($msg) ?></div></div>
<?php endif; ?>
