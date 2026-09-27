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
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<script src="<?= url('assets/vendor/lucide.min.js') ?>" defer></script>
</head>
<body class="bg-slate-50">
<header class="bg-[#1e3a8a] text-white sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <a href="<?= url('index.php') ?>" class="flex items-center gap-3">
            <span class="w-9 h-9 rounded-lg bg-blue-500/30 flex items-center justify-center"><i data-lucide="car" class="w-5 h-5"></i></span>
            <span class="font-semibold hidden sm:block"><?= e(setting('company_name', 'Vehicle Hire')) ?></span>
        </a>
        <div class="flex items-center gap-4 text-sm">
            <a href="<?= url('client/notifications.php') ?>" class="relative text-blue-200 hover:text-white">
                <i data-lucide="bell" class="w-5 h-5"></i>
                <?php if ($unread): ?><span class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center"><?= $unread ?></span><?php endif; ?>
            </a>
            <span class="text-blue-200 hidden md:block"><?= e($client['full_name'] ?? $user['name']) ?></span>
            <a href="<?= url('logout.php') ?>" class="bg-blue-600 hover:bg-blue-500 px-4 py-1.5 rounded-md font-medium">Sign out</a>
        </div>
    </div>
    <nav class="bg-[#162d6e]">
        <div class="max-w-7xl mx-auto px-6 flex gap-1 overflow-x-auto">
            <?php foreach ($navItems as [$href, $label, $icon, $key]): ?>
            <a href="<?= url('client/' . $href) ?>"
               class="flex items-center gap-2 px-3.5 py-3 text-sm whitespace-nowrap border-b-2 <?= $active === $key ? 'border-white text-white font-medium' : 'border-transparent text-blue-300 hover:text-white' ?>">
                <i data-lucide="<?= $icon ?>" class="w-4 h-4"></i><?= e($label) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
<main class="max-w-7xl mx-auto px-6 py-8">
<?php if ($msg = flash('success')): ?>
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"><?= e($msg) ?></div>
<?php endif; ?>
