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
$kpis = [
    ['Total Vehicles', $v['total'], 'car', 'bg-blue-50 text-blue-600'],
    ['Available', $v['available'], 'check-circle', 'bg-green-50 text-green-600'],
    ['On Hire', $v['on_hire'] + 0, 'key-round', 'bg-amber-50 text-amber-600'],
    ['Maintenance', $v['maintenance'], 'wrench', 'bg-red-50 text-red-600'],
];
$fin = [
    ["Today's Revenue", money($todayRevenue), 'banknote', 'bg-green-50 text-green-600'],
    ['This Month', money($monthRevenue), 'trending-up', 'bg-blue-50 text-blue-600'],
    ['Outstanding', money(max(0, $outstanding)), 'alert-circle', 'bg-red-50 text-red-600'],
    ['Active Clients', $activeClients, 'users', 'bg-purple-50 text-purple-600'],
];
require APP_PATH . '/views/admin/header.php';
?>
<div class="mb-6">
    <h2 class="text-xl font-semibold text-slate-800">Welcome back, <?= e(explode(' ', $user['name'])[0]) ?></h2>
    <p class="text-sm text-slate-500">Here's what's happening with your fleet today.</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
    <?php foreach ($kpis as [$label, $val, $icon, $cls]): ?>
    <div class="kpi-card">
        <div>
            <p class="text-sm text-slate-500"><?= e($label) ?></p>
            <p class="text-2xl font-semibold text-slate-800 mt-1"><?= e((string) $val) ?></p>
        </div>
        <span class="icon-box <?= $cls ?>"><i data-lucide="<?= $icon ?>" class="w-5 h-5"></i></span>
    </div>
    <?php endforeach; ?>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <?php foreach ($fin as [$label, $val, $icon, $cls]): ?>
    <div class="kpi-card">
        <div>
            <p class="text-sm text-slate-500"><?= e($label) ?></p>
            <p class="text-xl font-semibold text-slate-800 mt-1"><?= e((string) $val) ?></p>
        </div>
        <span class="icon-box <?= $cls ?>"><i data-lucide="<?= $icon ?>" class="w-5 h-5"></i></span>
    </div>
    <?php endforeach; ?>
</div>
<?php if ($pendingKyc > 0): ?>
<div class="mb-6 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm flex items-center gap-2">
    <i data-lucide="shield-alert" class="w-4 h-4"></i>
    <?= $pendingKyc ?> client(s) pending KYC review. <a class="underline font-medium" href="<?= url('admin/kyc.php') ?>">Review now</a>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="card !p-0 overflow-hidden">
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

    <div class="card !p-0 overflow-hidden">
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
<?php require APP_PATH . '/views/admin/footer.php'; ?>
