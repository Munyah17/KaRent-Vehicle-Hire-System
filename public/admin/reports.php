<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requirePermission('reports');

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$vehicleId = (int) ($_GET['vehicle'] ?? 0);
$clientId = (int) ($_GET['client'] ?? 0);

$vWhere = $vehicleId ? ' AND b.vehicle_id = ' . $vehicleId : '';
$cWhere = $clientId ? ' AND b.client_id = ' . $clientId : '';

$revenue = (float) Database::value(
    "SELECT COALESCE(SUM(p.amount),0) FROM payments p
     LEFT JOIN bookings b ON b.id=p.booking_id
     WHERE p.status='successful' AND DATE(p.paid_at) BETWEEN ? AND ? $vWhere $cWhere",
    [$from, $to]);
$bookingCount = (int) Database::value(
    "SELECT COUNT(*) FROM bookings b WHERE DATE(b.created_at) BETWEEN ? AND ? $vWhere $cWhere",
    [$from, $to]);
$activeHires = (int) Database::value(
    "SELECT COUNT(*) FROM bookings b WHERE b.status IN ('active','overdue') $vWhere $cWhere");
$completedCount = (int) Database::value(
    "SELECT COUNT(*) FROM bookings b WHERE b.status='completed' AND DATE(b.created_at) BETWEEN ? AND ? $vWhere $cWhere",
    [$from, $to]);
$utilisation = (float) Database::value(
    "SELECT ROUND(AVG(util),1) FROM (
        SELECT COUNT(DISTINCT b.id) * 100.0 / GREATEST(DATEDIFF(?, ?),1) util
        FROM bookings b WHERE b.status IN ('active','completed')
          AND b.pickup_at < ? AND b.return_at > ? $vWhere
        GROUP BY b.vehicle_id) t",
    [$to, $from, $to . ' 23:59:59', $from . ' 00:00:00']);

// Revenue trend per day
$trend = Database::all(
    "SELECT DATE(p.paid_at) d, SUM(p.amount) total FROM payments p
     LEFT JOIN bookings b ON b.id=p.booking_id
     WHERE p.status='successful' AND DATE(p.paid_at) BETWEEN ? AND ? $vWhere $cWhere
     GROUP BY DATE(p.paid_at) ORDER BY d",
    [$from, $to]);

$topVehicles = Database::all(
    "SELECT v.make, v.model, v.reg_no, COUNT(b.id) hires, COALESCE(SUM(b.total),0) revenue
     FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id
     WHERE b.status != 'cancelled' AND DATE(b.created_at) BETWEEN ? AND ? $cWhere
     GROUP BY b.vehicle_id ORDER BY revenue DESC LIMIT 8",
    [$from, $to]);

$byStatus = Database::all(
    "SELECT b.status, COUNT(*) c FROM bookings b
     WHERE DATE(b.created_at) BETWEEN ? AND ? $vWhere $cWhere GROUP BY b.status",
    [$from, $to]);

$expenseTotal = (float) Database::value(
    'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?', [$from, $to]);

$vehicles = Database::all('SELECT id, make, model, reg_no FROM vehicles ORDER BY make');
$clients = Database::all('SELECT id, full_name FROM clients ORDER BY full_name');

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report-' . $from . '-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Booking', 'Client', 'Vehicle', 'Pickup', 'Return', 'Status', 'Total']);
    $rows = Database::all(
        "SELECT b.ref, c.full_name, CONCAT(v.make,' ',v.model,' (',v.reg_no,')') veh,
                b.pickup_at, b.return_at, b.status, b.total
         FROM bookings b JOIN clients c ON c.id=b.client_id JOIN vehicles v ON v.id=b.vehicle_id
         WHERE DATE(b.created_at) BETWEEN ? AND ? $vWhere $cWhere ORDER BY b.id",
        [$from, $to]);
    foreach ($rows as $r) fputcsv($out, $r);
    exit;
}

// SVG line chart points
$chartData = [];
foreach ($trend as $t) $chartData[date('d M', strtotime($t['d']))] = (float) $t['total'];
$maxY = max(1, max(array_merge([1], array_values($chartData))));
$points = [];
$i = 0;
$n = max(1, count($chartData) - 1);
foreach ($chartData as $label => $val) {
    $x = 40 + ($i / max(1, $n)) * 660;
    $y = 170 - ($val / $maxY) * 150;
    $points[] = "$x,$y";
    $i++;
}
$polyline = implode(' ', $points);

$pageTitle = 'Reports';
$active = 'reports';
require APP_PATH . '/views/admin/header.php';
?>
<div class="card mb-6">
    <form method="get" class="flex flex-wrap items-end gap-3">
        <div><label class="label">From</label><input type="date" name="from" value="<?= e($from) ?>" class="input"></div>
        <div><label class="label">To</label><input type="date" name="to" value="<?= e($to) ?>" class="input"></div>
        <div><label class="label">Vehicle</label>
            <select name="vehicle" class="input w-52">
                <option value="">All Vehicles</option>
                <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $vehicleId === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['make'] . ' ' . $v['model'] . ' ' . $v['reg_no']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div><label class="label">Client</label>
            <select name="client" class="input w-44">
                <option value="">All Clients</option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $clientId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <button class="btn-primary"><i data-lucide="filter" class="w-4 h-4"></i> Apply</button>
        <a href="?from=<?= e($from) ?>&to=<?= e($to) ?>&vehicle=<?= $vehicleId ?>&client=<?= $clientId ?>&export=csv"
           class="btn-secondary"><i data-lucide="download" class="w-4 h-4"></i> Export CSV</a>
    </form>
</div>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
        ['Revenue', money($revenue), 'banknote', 'bg-green-50 text-green-600'],
        ['Bookings', $bookingCount, 'calendar-check', 'bg-blue-50 text-blue-600'],
        ['Active Hires', $activeHires, 'key-round', 'bg-amber-50 text-amber-600'],
        ['Expenses', money($expenseTotal), 'receipt', 'bg-red-50 text-red-600'],
    ] as [$label, $val, $icon, $cls]): ?>
    <div class="kpi-card">
        <div><p class="text-sm text-slate-500"><?= e($label) ?></p>
            <p class="text-2xl font-semibold text-slate-800 mt-1"><?= e((string) $val) ?></p></div>
        <span class="icon-box <?= $cls ?>"><i data-lucide="<?= $icon ?>" class="w-5 h-5"></i></span>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="card xl:col-span-2">
        <h3 class="font-semibold text-slate-800 mb-4">Revenue Trend</h3>
        <?php if ($chartData): ?>
        <svg viewBox="0 0 720 200" class="w-full h-56">
            <line x1="40" y1="170" x2="700" y2="170" stroke="#e2e8f0" />
            <line x1="40" y1="20" x2="40" y2="170" stroke="#e2e8f0" />
            <polyline fill="none" stroke="#2563eb" stroke-width="2.5" points="<?= $polyline ?>" />
            <?php $i = 0; foreach ($chartData as $label => $val):
                $x = 40 + ($i / max(1, $n)) * 660;
                $y = 170 - ($val / $maxY) * 150; ?>
            <circle cx="<?= $x ?>" cy="<?= $y ?>" r="3.5" fill="#2563eb" />
            <?php if (count($chartData) <= 15): ?>
            <text x="<?= $x ?>" y="188" font-size="9" fill="#94a3b8" text-anchor="middle"><?= e($label) ?></text>
            <?php endif; $i++; endforeach; ?>
            <text x="44" y="18" font-size="10" fill="#94a3b8"><?= money($maxY) ?></text>
        </svg>
        <?php else: ?>
        <div class="h-56 flex items-center justify-center text-slate-400 text-sm">No revenue data in this range.</div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Top Vehicles</h3>
        <ul class="space-y-3">
            <?php foreach ($topVehicles as $i => $t): ?>
            <li class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full <?= $i < 3 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500' ?> flex items-center justify-center text-xs font-semibold"><?= $i + 1 ?></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800 truncate"><?= e($t['make'] . ' ' . $t['model']) ?></p>
                    <p class="text-xs text-slate-400"><?= e($t['reg_no']) ?> · <?= (int) $t['hires'] ?> hire(s)</p>
                </div>
                <span class="text-sm font-semibold text-slate-800"><?= money($t['revenue']) ?></span>
            </li>
            <?php endforeach; ?>
            <?php if (!$topVehicles): ?><li class="text-sm text-slate-400">No data in range.</li><?php endif; ?>
        </ul>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-6">
    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Bookings by Status</h3>
        <ul class="space-y-2.5">
            <?php foreach ($byStatus as $s): ?>
            <li class="flex items-center justify-between text-sm">
                <span class="flex items-center gap-2"><?= status_badge($s['status']) ?></span>
                <span class="font-semibold"><?= (int) $s['c'] ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Summary</h3>
        <dl class="text-sm space-y-2.5">
            <div class="flex justify-between"><dt class="text-slate-500">Completed bookings</dt><dd class="font-medium"><?= $completedCount ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Avg. fleet utilisation</dt><dd class="font-medium"><?= $utilisation ?>%</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Gross margin (rev − expenses)</dt>
                <dd class="font-semibold <?= ($revenue - $expenseTotal) < 0 ? 'text-red-600' : 'text-slate-800' ?>"><?= money($revenue - $expenseTotal) ?></dd></div>
        </dl>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
