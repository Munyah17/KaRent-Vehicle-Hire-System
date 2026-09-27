<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Services\BookingService;

Auth::requirePermission('calendar');
BookingService::flagOverdue();

$month = (int) ($_GET['m'] ?? date('n'));
$year = (int) ($_GET['y'] ?? date('Y'));
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$vehicles = Database::all('SELECT * FROM vehicles ORDER BY make, model');
$bookings = Database::all(
    "SELECT b.*, c.full_name client_name FROM bookings b JOIN clients c ON c.id=b.client_id
     WHERE b.status IN ('pending','confirmed','active','overdue')
       AND b.pickup_at < ? AND b.return_at > ?
     ORDER BY b.pickup_at",
    [$monthEnd . ' 23:59:59', $monthStart . ' 00:00:00']
);

// bookings indexed by vehicle
$byVehicle = [];
foreach ($bookings as $b) $byVehicle[$b['vehicle_id']][] = $b;

$barColor = [
    'pending' => 'bg-amber-400',
    'confirmed' => 'bg-blue-500',
    'active' => 'bg-green-500',
    'overdue' => 'bg-red-500',
];

$prev = mktime(0, 0, 0, $month - 1, 1, $year);
$next = mktime(0, 0, 0, $month + 1, 1, $year);

$pageTitle = 'Bookings — Calendar';
$active = 'calendar';
require APP_PATH . '/views/admin/header.php';
?>
<div class="card !p-0 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div class="flex items-center gap-3">
            <a href="?m=<?= date('n', $prev) ?>&y=<?= date('Y', $prev) ?>" class="btn-secondary !px-2.5"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
            <h2 class="text-lg font-semibold text-slate-800 w-44 text-center"><?= e(date('F Y', strtotime($monthStart))) ?></h2>
            <a href="?m=<?= date('n', $next) ?>&y=<?= date('Y', $next) ?>" class="btn-secondary !px-2.5"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
        </div>
        <div class="flex items-center gap-4 text-xs text-slate-500">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-amber-400"></span>Pending</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-blue-500"></span>Confirmed</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-green-500"></span>On Hire</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-red-500"></span>Overdue / Maint.</span>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-50">
                    <th class="th w-48 sticky left-0 bg-gray-50 z-10">Vehicle</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $isToday = date('Y-m-d') === sprintf('%04d-%02d-%02d', $year, $month, $d); ?>
                    <th class="text-center text-[10px] font-medium py-2 px-0 min-w-[30px] <?= $isToday ? 'text-blue-600' : 'text-gray-500' ?>">
                        <?= $d ?>
                    </th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vehicles as $v): ?>
            <tr class="border-b border-gray-100">
                <td class="py-2 px-4 text-sm sticky left-0 bg-white z-10 border-r border-gray-100">
                    <p class="font-medium text-slate-800 leading-tight"><?= e($v['make'] . ' ' . $v['model']) ?></p>
                    <p class="text-xs text-slate-400"><?= e($v['reg_no']) ?> <?= $v['status'] === 'maintenance' ? '<span class="text-red-500">· Maint.</span>' : '' ?></p>
                </td>
                <td colspan="<?= $daysInMonth ?>" class="p-0 relative h-14">
                    <?php
                    // maintenance block shading
                    if ($v['status'] === 'maintenance'): ?>
                    <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 h-6 bg-red-100 rounded mx-0.5" title="Under maintenance"></div>
                    <?php endif;
                    foreach ($byVehicle[$v['id']] ?? [] as $b):
                        $startDay = max(1, (int) date('j', strtotime($b['pickup_at'])));
                        if (date('Y-m', strtotime($b['pickup_at'])) < sprintf('%04d-%02d', $year, $month)) $startDay = 1;
                        $endDay = min($daysInMonth, (int) date('j', strtotime($b['return_at'])));
                        if (date('Y-m', strtotime($b['return_at'])) > sprintf('%04d-%02d', $year, $month)) $endDay = $daysInMonth;
                        $left = ($startDay - 1) / $daysInMonth * 100;
                        $width = ($endDay - $startDay + 1) / $daysInMonth * 100;
                        $color = $barColor[$b['status']] ?? 'bg-gray-400';
                    ?>
                    <a href="<?= url('admin/booking.php?id=' . $b['id']) ?>"
                       title="<?= e($b['ref'] . ' — ' . $b['client_name']) ?>"
                       class="absolute top-1/2 -translate-y-1/2 h-6 <?= $color ?> rounded text-[10px] text-white flex items-center px-2 truncate hover:opacity-80"
                       style="left: <?= $left ?>%; width: <?= $width ?>%">
                        <?= e($b['client_name']) ?>
                    </a>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
