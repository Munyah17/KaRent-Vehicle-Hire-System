<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Services\BookingService;

Auth::requireAdmin();
BookingService::flagOverdue();

// Vehicle KPIs
$v = Database::one(
    "SELECT COUNT(*) total,
        SUM(status='available') available,
        SUM(status='on_hire') on_hire,
        SUM(status='reserved') reserved,
        SUM(status='maintenance') maintenance
     FROM vehicles"
);
$todayRevenue = (float) Database::value(
    "SELECT COALESCE(SUM(amount),0) FROM payments
     WHERE status='successful' AND DATE(paid_at)=CURDATE()"
);
$monthRevenue = (float) Database::value(
    "SELECT COALESCE(SUM(amount),0) FROM payments
     WHERE status='successful' AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())"
);
$outstanding = (float) Database::value(
    "SELECT COALESCE(SUM(b.total),0) FROM bookings b WHERE b.status IN ('confirmed','active','overdue')"
) - (float) Database::value(
    "SELECT COALESCE(SUM(p.amount),0) FROM payments p
     JOIN bookings b ON b.id=p.booking_id
     WHERE p.status='successful' AND p.purpose IN ('rental','extension')
       AND b.status IN ('confirmed','active','overdue')"
);
$activeClients = (int) Database::value("SELECT COUNT(*) FROM clients WHERE account_status='active'");
$pendingKyc = (int) Database::value("SELECT COUNT(*) FROM clients WHERE kyc_status IN ('pending','under_review')");
$depositsHeld = (float) Database::value(
    "SELECT COALESCE(SUM(received_amount - deducted_amount - refunded_amount),0) FROM deposits WHERE status IN ('held','partial')"
);

$upcoming = Database::all(
    "SELECT b.*, c.full_name client_name, v.make, v.model, v.reg_no
     FROM bookings b JOIN clients c ON c.id=b.client_id JOIN vehicles v ON v.id=b.vehicle_id
     WHERE b.status IN ('pending','confirmed') AND b.pickup_at >= NOW() - INTERVAL 1 DAY
     ORDER BY b.pickup_at ASC LIMIT 8"
);
$activity = Database::all(
    "SELECT a.*, u.name user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id
     ORDER BY a.id DESC LIMIT 10"
);

$pageTitle = 'Dashboard';
$active = 'dashboard';
// CoolAdmin Dashboard-3 style stat cards: label, value, sub-note, fa icon, colour.
$kpis = [
    ['Total Vehicles', $v['total'], ($v['reserved'] ?? 0) . ' reserved', 'fa-car-side', 'c1'],
    ['Available', $v['available'], 'ready to hire', 'fa-circle-check', 'c2'],
    ['On Hire', $v['on_hire'] + 0, $depositsHeld > 0 ? money($depositsHeld) . ' deposits held' : 'no deposits held', 'fa-key', 'c3'],
    ['Maintenance', $v['maintenance'], 'off fleet', 'fa-wrench', 'c4'],
];
$fin = [
    ["Today's Revenue", money($todayRevenue), 'received today', 'fa-sack-dollar', 'c2'],
    ['This Month', money($monthRevenue), date('F Y'), 'fa-arrow-trend-up', 'c1'],
    ['Outstanding', money(max(0, $outstanding)), 'across open bookings', 'fa-circle-exclamation', 'c3'],
    ['Active Clients', $activeClients, $pendingKyc . ' KYC pending', 'fa-users', 'c4'],
];
require APP_PATH . '/views/admin/header.php';
?>
<div class="page-header">
    <div>
        <h1>Welcome back, <?= e(explode(' ', $user['name'])[0]) ?></h1>
        <p class="subtitle">Here's what's happening with your fleet today.</p>
    </div>
    <div class="page-header__actions">
        <a class="m-btn m-btn--ghost" href="<?= url('admin/booking-new.php') ?>"><i class="fa-solid fa-plus"></i> New booking</a>
        <a class="m-btn m-btn--ghost" href="<?= url('admin/reports.php') ?>"><i class="fa-solid fa-download"></i> Reports</a>
    </div>
</div>

<div class="row row-tight">
    <?php foreach ($kpis as [$label, $val, $sub, $icon, $c]): ?>
    <div class="col-sm-6 col-lg-3">
        <article class="stat-card">
            <div class="stat-card__head">
                <p class="stat-card__label"><?= e($label) ?></p>
                <span class="stat-card__icon stat-card__icon--<?= $c ?>"><i class="fa-solid <?= $icon ?>"></i></span>
            </div>
            <p class="stat-card__value"><?= e((string) $val) ?></p>
            <p class="stat-card__delta"><span class="stat-card__delta-period"><?= e($sub) ?></span></p>
        </article>
    </div>
    <?php endforeach; ?>
</div>
<div class="row row-tight" style="margin-top:16px">
    <?php foreach ($fin as [$label, $val, $sub, $icon, $c]): ?>
    <div class="col-sm-6 col-lg-3">
        <article class="stat-card">
            <div class="stat-card__head">
                <p class="stat-card__label"><?= e($label) ?></p>
                <span class="stat-card__icon stat-card__icon--<?= $c ?>"><i class="fa-solid <?= $icon ?>"></i></span>
            </div>
            <p class="stat-card__value"><?= e((string) $val) ?></p>
            <p class="stat-card__delta"><span class="stat-card__delta-period"><?= e($sub) ?></span></p>
        </article>
    </div>
    <?php endforeach; ?>
</div>
<?php if ($pendingKyc > 0): ?>
<div class="mb-6 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm flex items-center gap-2">
    <i data-lucide="shield-alert" class="w-4 h-4"></i>
    <?= $pendingKyc ?> client(s) pending KYC review. <a class="underline font-medium" href="<?= url('admin/kyc.php') ?>">Review now</a>
</div>
<?php endif; ?>

<div class="row row-tight" style="margin-top:16px">
<div class="col-lg-6">
    <div class="card !p-0 overflow-x-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-slate-800">Upcoming Bookings</h3>
            <a href="<?= url('admin/bookings.php') ?>" class="text-sm text-blue-600 hover:underline">View all</a>
        </div>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($upcoming as $b): ?>
            <li class="flex items-center justify-between px-6 py-3.5">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                        <i data-lucide="car" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-slate-800"><?= e($b['client_name']) ?></p>
                        <p class="text-xs text-slate-500"><?= e($b['make'] . ' ' . $b['model']) ?> · <?= e($b['reg_no']) ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <?= status_badge($b['status']) ?>
                    <p class="text-xs text-slate-400 mt-1"><?= e(fmt_date($b['pickup_at'], 'd M')) ?> → <?= e(fmt_date($b['return_at'], 'd M')) ?></p>
                </div>
            </li>
            <?php endforeach; ?>
            <?php if (!$upcoming): ?>
            <li class="px-6 py-10 text-center text-sm text-slate-400">No upcoming bookings.</li>
            <?php endif; ?>
        </ul>
    </div>
</div>
<div class="col-lg-6">
    <div class="card !p-0 overflow-x-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-slate-800">Recent Activity</h3>
            <a href="<?= url('admin/audit.php') ?>" class="text-sm text-blue-600 hover:underline">Audit log</a>
        </div>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($activity as $a): ?>
            <li class="flex items-center justify-between px-6 py-3">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                        <i data-lucide="activity" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <p class="text-sm text-slate-700"><?= e(ucwords(str_replace('_', ' ', $a['action']))) ?> <span class="text-slate-400">· <?= e($a['module']) ?></span></p>
                        <p class="text-xs text-slate-400"><?= e($a['user_name'] ?? 'System') ?></p>
                    </div>
                </div>
                <span class="text-xs text-slate-400"><?= e(fmt_datetime($a['created_at'])) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
