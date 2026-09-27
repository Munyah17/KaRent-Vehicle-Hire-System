<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requireClient();
$client = Auth::client();

$exts = Database::all(
    'SELECT e.*, b.ref booking_ref, v.make, v.model FROM booking_extensions e
     JOIN bookings b ON b.id=e.booking_id JOIN vehicles v ON v.id=b.vehicle_id
     WHERE b.client_id = ? ORDER BY e.id DESC', [$client['id']]);

$eligible = Database::all(
    "SELECT b.id, b.ref, v.make, v.model, b.return_at FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id
     WHERE b.client_id = ? AND b.status IN ('active','confirmed')", [$client['id']]);

$pageTitle = 'Extensions';
$active = 'extensions';
require APP_PATH . '/views/client/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 card !p-0 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-slate-800">Extension Requests</h2></div>
        <table class="w-full">
            <thead><tr><th class="th">Booking</th><th class="th">Vehicle</th><th class="th">New Return</th><th class="th">Extra Cost</th><th class="th">Status</th><th class="th"></th></tr></thead>
            <tbody>
            <?php foreach ($exts as $x): ?>
            <tr class="table-row">
                <td class="td font-medium"><?= e($x['booking_ref']) ?></td>
                <td class="td"><?= e($x['make'] . ' ' . $x['model']) ?></td>
                <td class="td"><?= e(fmt_datetime($x['new_return_at'])) ?></td>
                <td class="td"><?= money($x['additional_amount']) ?></td>
                <td class="td"><?= status_badge($x['status']) ?></td>
                <td class="td text-right"><a href="<?= url('client/booking.php?id=' . $x['booking_id']) ?>" class="text-blue-600 text-sm hover:underline">Booking</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$exts): ?><tr><td colspan="6" class="td text-center py-10 text-slate-400">No extension requests.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-2">Request an extension</h3>
        <p class="text-sm text-slate-500 mb-4">Open an eligible booking and choose a new return date.</p>
        <ul class="space-y-2 text-sm">
            <?php foreach ($eligible as $b): ?>
            <li class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                <span><?= e($b['make'] . ' ' . $b['model']) ?> <span class="text-slate-400">(due <?= e(fmt_date($b['return_at'])) ?>)</span></span>
                <a href="<?= url('client/booking.php?id=' . $b['id'] . '#extend') ?>" class="text-blue-600 hover:underline">Extend</a>
            </li>
            <?php endforeach; ?>
            <?php if (!$eligible): ?><li class="text-slate-400">No active or confirmed bookings.</li><?php endif; ?>
        </ul>
    </div>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
