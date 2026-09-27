<?php
/**
 * Admin layout header — CoolAdmin (Dashboard 3) chrome:
 * light .menu-sidebar, white .header-desktop, mobile header-mobile.
 * Pages set $pageTitle and $active before including.
 */
use App\Auth;
use App\Notify;
use App\Database;

$user = Auth::user();
$unread = $user ? Notify::unreadCount((int) $user['id']) : 0;
$latestNotifs = $user ? Database::all(
    'SELECT title, body, created_at, status FROM notifications
     WHERE user_id = ? ORDER BY id DESC LIMIT 5', [(int) $user['id']]
) : [];
$active = $active ?? '';

// label, href, fa-icon, [children]
$nav = [
    ['dashboard', 'Dashboard', 'admin/index.php', 'fa-gauge', null],
    ['bookings', 'Bookings', null, 'fa-calendar-check', [
        ['bookings', 'All bookings', 'admin/bookings.php'],
        ['bookings', 'New booking', 'admin/booking-new.php'],
        ['calendar', 'Calendar', 'admin/calendar.php'],
    ]],
    ['vehicles', 'Fleet', null, 'fa-car-side', [
        ['vehicles', 'Vehicles', 'admin/vehicles.php'],
        ['maintenance', 'Maintenance', 'admin/maintenance.php'],
        ['damages', 'Damages', 'admin/damages.php'],
    ]],
    ['clients', 'Clients', null, 'fa-users', [
        ['clients', 'Clients', 'admin/clients.php'],
        ['kyc', 'KYC review', 'admin/kyc.php'],
        ['wallets', 'Wallets', 'admin/wallets.php'],
        ['deposits', 'Deposits', 'admin/deposits.php'],
    ]],
    ['payments', 'Finance', null, 'fa-credit-card', [
        ['payments', 'Payments', 'admin/payments.php'],
        ['payments', 'Fiscalisation', 'admin/fiscal.php', 'fiscal'],
        ['expenses', 'Expenses', 'admin/expenses.php'],
    ]],
    ['contracts', 'Documents', null, 'fa-file-lines', [
        ['contracts', 'Contracts', 'admin/contracts.php'],
        ['templates', 'Templates', 'admin/templates.php'],
        ['settings', 'Hero slides', 'admin/hero.php'],
    ]],
    ['reports', 'System', null, 'fa-gear', [
        ['reports', 'Reports', 'admin/reports.php'],
        ['staff', 'Staff', 'admin/staff.php'],
        ['settings', 'Settings', 'admin/settings.php'],
        ['audit', 'Audit log', 'admin/audit.php'],
    ]],
];

$initials = strtoupper(substr($user['name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Admin') ?> — <?= e(config('app_name')) ?></title>
<script>try{if((localStorage.getItem('karent.theme')||(document.cookie.match(/theme=(dark)/)||[])[1])==='dark'){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-bs-theme','dark')}}catch(e){}</script>
<link href="<?= url('assets/admin/css/font-face.css') ?>" rel="stylesheet">
<link href="<?= url('assets/admin/vendor/bootstrap-5.3.8.min.css') ?>" rel="stylesheet">
<link href="<?= url('assets/admin/vendor/fontawesome-7.3.1/css/all.min.css') ?>" rel="stylesheet">
<link href="<?= url('assets/admin/vendor/css-hamburgers/hamburgers.min.css') ?>" rel="stylesheet">
<link href="<?= url('assets/admin/css/theme.css') ?>" rel="stylesheet">
<link href="<?= url('assets/admin/css/app.css') ?>" rel="stylesheet">
<link href="<?= url('assets/css/app.css') ?>" rel="stylesheet">
<script src="<?= url('assets/vendor/lucide.min.js') ?>" defer></script>
</head>
<body class="app">
<div class="page-wrapper">

    <!-- Mobile topbar -->
    <header class="header-mobile d-block d-lg-none">
        <div class="header-mobile__bar">
            <div class="header-mobile-inner">
                <a class="logo" href="<?= url('admin/index.php') ?>">
                    <span class="logo-mark">K</span><span class="logo-text">KaRent</span>
                </a>
                <button class="sidebar-toggle js-sidebar-toggle" type="button" aria-label="Open menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="menu-sidebar" id="main-sidebar">
        <div class="logo">
            <a class="logo-link" href="<?= url('admin/index.php') ?>">
                <span class="logo-mark">K</span><span class="logo-text">KaRent</span>
            </a>
            <button class="sidebar-close js-sidebar-toggle" type="button" aria-label="Close navigation">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="menu-sidebar__content js-scrollbar1">
            <nav class="navbar-sidebar">
                <ul class="list-unstyled navbar__list">
                <?php foreach ($nav as [$perm, $label, $href, $icon, $children]):
                    if (!$children) {
                        if ($perm !== 'dashboard' && !Auth::can($perm)) continue;
                        ?>
                        <li class="<?= $active === $perm ? 'active' : '' ?>">
                            <a href="<?= url($href) ?>"><i class="fa-solid <?= $icon ?>"></i><?= e($label) ?></a>
                        </li>
                    <?php } else {
                        $visible = array_filter($children, fn($c) => Auth::can($c[0]) || $c[0] === $perm);
                        if (!$visible) continue;
                        $open = in_array($active, array_map(fn($c) => $c[3] ?? $c[0], $children), true);
                        ?>
                        <li class="has-sub <?= $open ? 'active' : '' ?>">
                            <a class="js-arrow <?= $open ? 'open' : '' ?>" href="#"><i class="fa-solid <?= $icon ?>"></i><?= e($label) ?></a>
                            <ul class="list-unstyled navbar__sub-list js-sub-list" <?= $open ? 'style="display:block"' : '' ?>>
                                <?php foreach ($visible as $c):
                                    [$cp, $clabel, $chref] = $c;
                                    $cactive = $c[3] ?? $cp; ?>
                                <li class="<?= $active === $cactive ? 'active' : '' ?>"><a href="<?= url($chref) ?>"><?= e($clabel) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php } endforeach; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Main column -->
    <div class="page-container">
        <header class="header-desktop">
            <div class="section__content section__content--p30">
                <div class="container-fluid">
                    <div class="header-wrap">
                        <div class="d-flex align-items-center gap-3">
                            <button class="sidebar-toggle js-sidebar-toggle d-none d-lg-flex" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="main-sidebar">
                                <i class="fa-solid fa-bars"></i>
                            </button>
                            <h1 class="page-title m-0" style="font-size:1.05rem;font-weight:600"><?= e($pageTitle ?? '') ?></h1>
                        </div>
                        <div class="header-button">
                            <button class="theme-toggle" type="button" data-theme-toggle title="Toggle dark / light mode">
                                <i class="fa-solid fa-circle-half-stroke"></i>
                            </button>
                            <div class="noti-wrap">
                                <div class="noti__item js-item-menu" role="button" tabindex="0" aria-haspopup="true" aria-label="Notifications">
                                    <i class="fa-solid fa-bell"></i>
                                    <?php if ($unread > 0): ?><span class="quantity"><?= $unread ?></span><?php endif; ?>
                                    <div class="notifi-dropdown js-dropdown">
                                        <div class="notifi__title"><p>You have <?= $unread ?> unread notification<?= $unread == 1 ? '' : 's' ?></p></div>
                                        <?php foreach ($latestNotifs as $n): ?>
                                        <div class="notifi__item">
                                            <div class="bg-c1 img-cir img-40"><i class="fa-solid fa-bell"></i></div>
                                            <div class="content">
                                                <p><?= e(mb_strimwidth($n['title'] ?: $n['body'], 0, 60, '…')) ?></p>
                                                <span class="date"><?= e(fmt_datetime($n['created_at'])) ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (!$latestNotifs): ?>
                                        <div class="notifi__item"><div class="content"><p>No notifications yet.</p></div></div>
                                        <?php endif; ?>
                                        <div class="notifi__footer"><a href="<?= url('admin/notifications.php') ?>">All notifications</a></div>
                                    </div>
                                </div>
                            </div>
                            <div class="account-wrap">
                                <div class="account-item clearfix js-item-menu" role="button" tabindex="0" aria-haspopup="true" aria-label="Account menu">
                                    <div class="image">
                                        <span class="img-cir img-40 bg-c1 d-inline-flex align-items-center justify-content-center fw-semibold text-white"><?= e($initials) ?></span>
                                    </div>
                                    <div class="content"><a class="js-acc-btn" href="#"><?= e($user['name'] ?? 'Admin') ?></a></div>
                                    <div class="account-dropdown js-dropdown">
                                        <div class="info clearfix">
                                            <div class="image">
                                                <span class="img-cir img-40 bg-c1 d-inline-flex align-items-center justify-content-center fw-semibold text-white"><?= e($initials) ?></span>
                                            </div>
                                            <div class="content">
                                                <h5 class="name"><a href="#"><?= e($user['name'] ?? 'Admin') ?></a></h5>
                                                <span class="email"><?= e($user['email'] ?? '') ?></span>
                                                <span class="d-block" style="font-size:.7rem;opacity:.6"><?= e(str_replace('_', ' ', $user['role_name'] ?? '')) ?></span>
                                            </div>
                                        </div>
                                        <div class="account-dropdown__body">
                                            <div class="account-dropdown__item"><a href="<?= url('admin/notifications.php') ?>"><i class="fa-solid fa-bell"></i>Notifications</a></div>
                                            <div class="account-dropdown__item"><a href="<?= url('change-password.php') ?>"><i class="fa-solid fa-key"></i>Change password</a></div>
                                        </div>
                                        <div class="account-dropdown__footer">
                                            <a href="<?= url('logout.php?portal=admin') ?>"><i class="fa-solid fa-power-off"></i>Logout</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="section__content section__content--p30">
                <div class="container-fluid">
        <?php if ($msg = flash('success')): ?>
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"><?= e($msg) ?></div>
        <?php endif; ?>
