<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Services\BookingService;

Auth::requireClient();
$client = Auth::client();

$bookings = Database::all(
    "SELECT b.*, v.make, v.model, v.reg_no FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id
     WHERE b.client_id = ? ORDER BY b.id DESC", [$client['id']]);

$pageTitle = 'My Bookings';
$active = 'bookings';
require APP_PATH . '/views/client/header.php';
?>
<div class="card !p-0 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-slate-800">My Bookings</h2>
        <a href="<?= url('client/browse.php') ?>" class="btn-primary"><i data-lucide="plus" class="w-4 h-4"></i> New booking</a>
    </div>
    <table class="w-full">
        <thead><tr>
            <th class="th">Ref</th><th class="th">Vehicle</th><th class="th">Pickup</th>
            <th class="th">Return</th><th class="th">Status</th><th class="th">Total</th><th class="th"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
        <tr class="table-row">
            <td class="td font-medium"><?= e($b['ref']) ?></td>
            <td class="td"><?= e($b['make'] . ' ' . $b['model']) ?> <span class="text-xs text-slate-400">(<?= e($b['reg_no']) ?>)</span></td>
            <td class="td"><?= e(fmt_datetime($b['pickup_at'])) ?></td>
            <td class="td"><?= e(fmt_datetime($b['return_at'])) ?></td>
            <td class="td"><?= status_badge($b['status']) ?></td>
            <td class="td font-medium"><?= money($b['total']) ?></td>
            <td class="td text-right"><a href="<?= url('client/booking.php?id=' . $b['id']) ?>" class="text-blue-600 text-sm hover:underline">View</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$bookings): ?>
        <tr><td colspan="7" class="td text-center py-10 text-slate-400">
            No bookings yet. <a href="<?= url('client/browse.php') ?>" class="text-blue-600">Browse vehicles</a></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
