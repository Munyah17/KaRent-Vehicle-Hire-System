<?php
/**
 * Admin layout header: <html> + sidebar + topbar + opens <main>.
 * Pages set $pageTitle and $active before including.
 */
use App\Auth;
use App\Notify;

$user = Auth::user();
$unread = $user ? Notify::unreadCount((int) $user['id']) : 0;
$active = $active ?? '';

$nav = [
    'MAIN' => [
        ['dashboard', 'Dashboard', 'admin/index.php', 'layout-dashboard'],
        ['bookings', 'Bookings', 'admin/bookings.php', 'calendar-check'],
        ['calendar', 'Calendar', 'admin/calendar.php', 'calendar-days'],
    ],
    'FLEET' => [
        ['vehicles', 'Vehicles', 'admin/vehicles.php', 'car'],
        ['maintenance', 'Maintenance', 'admin/maintenance.php', 'wrench'],
        ['damages', 'Damages', 'admin/damages.php', 'car-front'],
    ],
    'CLIENTS' => [
        ['clients', 'Clients', 'admin/clients.php', 'users'],
        ['kyc', 'KYC Review', 'admin/kyc.php', 'shield-check'],
        ['wallets', 'Wallets', 'admin/wallets.php', 'wallet'],
        ['deposits', 'Deposits', 'admin/deposits.php', 'piggy-bank'],
    ],
    'FINANCE' => [
        ['payments', 'Payments', 'admin/payments.php', 'credit-card'],
        ['expenses', 'Expenses', 'admin/expenses.php', 'receipt'],
    ],
    'DOCUMENTS' => [
        ['contracts', 'Contracts', 'admin/contracts.php', 'file-text'],
        ['templates', 'Templates', 'admin/templates.php', 'files'],
    ],
    'SYSTEM' => [
        ['reports', 'Reports', 'admin/reports.php', 'bar-chart-3'],
        ['staff', 'Staff', 'admin/staff.php', 'user-cog'],
        ['settings', 'Settings', 'admin/settings.php', 'settings'],
        ['audit', 'Audit Log', 'admin/audit.php', 'history'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Admin') ?> — <?= e(config('app_name')) ?></title>
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<script src="<?= url('assets/vendor/lucide.min.js') ?>" defer></script>
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-[#1e3a8a] text-white flex flex-col fixed inset-y-0 z-30 overflow-y-auto">
        <div class="h-16 flex items-center gap-3 px-5 border-b border-blue-800/60">
            <span class="w-9 h-9 rounded-lg bg-blue-500/30 flex items-center justify-center">
                <i data-lucide="car" class="w-5 h-5"></i>
            </span>
            <div>
                <div class="font-semibold text-sm leading-tight">Vehicle Hire</div>
                <div class="text-[11px] text-blue-300 leading-tight">Management System</div>
            </div>
        </div>
        <nav class="flex-1 px-3 py-4 space-y-5">
            <?php foreach ($nav as $section => $items): ?>
            <div>
                <div class="px-4 mb-1.5 text-[10px] font-semibold tracking-widest text-blue-400 uppercase"><?= e($section) ?></div>
                <div class="space-y-0.5">
                <?php foreach ($items as [$perm, $label, $href, $icon]):
                    if ($perm !== 'dashboard' && !Auth::can($perm)) continue;
                    $isActive = $active === $perm; ?>
                    <a href="<?= url($href) ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
                        <i data-lucide="<?= e($icon) ?>" class="w-4 h-4 shrink-0"></i>
                        <span><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </nav>
        <div class="px-5 py-4 border-t border-blue-800/60 text-xs text-blue-300">
            <?= e(setting('company_name', 'Vehicle Hire')) ?>
        </div>
    </aside>

    <!-- Right column -->
    <div class="flex-1 ml-64 flex flex-col min-w-0">
        <!-- Topbar -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 sticky top-0 z-20">
            <h1 class="text-lg font-semibold text-slate-800"><?= e($pageTitle ?? '') ?></h1>
            <div class="flex items-center gap-4">
                <a href="<?= url('admin/notifications.php') ?>" class="relative text-gray-500 hover:text-gray-700">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <?php if ($unread > 0): ?>
                    <span class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center"><?= $unread ?></span>
                    <?php endif; ?>
                </a>
                <div class="relative" x-data>
                    <button onclick="document.getElementById('usermenu').classList.toggle('hidden')"
                            class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900">
                        <span class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-semibold">
                            <?= e(strtoupper(substr($user['name'] ?? 'A', 0, 1))) ?>
                        </span>
                        <span class="font-medium"><?= e($user['name'] ?? 'Admin') ?></span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
                    </button>
                    <div id="usermenu" class="hidden absolute right-0 mt-2 w-44 bg-white rounded-lg shadow-lg border border-gray-200 py-1 text-sm z-40">
                        <div class="px-4 py-2 text-xs text-gray-400 border-b border-gray-100"><?= e($user['role_name'] ?? '') ?></div>
                        <a href="<?= url('change-password.php') ?>" class="block px-4 py-2 hover:bg-gray-50">Change password</a>
                        <a href="<?= url('logout.php') ?>" class="block px-4 py-2 hover:bg-gray-50 text-red-600">Sign out</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-6 lg:p-8">
        <?php if ($msg = flash('success')): ?>
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"><?= e($msg) ?></div>
        <?php endif; ?>
