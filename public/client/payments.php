<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requireClient();
$client = Auth::client();

$payments = Database::all(
    'SELECT p.*, b.ref booking_ref FROM payments p LEFT JOIN bookings b ON b.id=p.booking_id
     WHERE p.client_id = ? ORDER BY p.id DESC', [$client['id']]);

$pageTitle = 'Payments';
$active = 'payments';
require APP_PATH . '/views/client/header.php';
?>
<div class="card !p-0 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-slate-800">Payment History</h2></div>
    <table class="w-full">
        <thead><tr>
            <th class="th">Txn ID</th><th class="th">Booking</th><th class="th">Method</th>
            <th class="th">Purpose</th><th class="th">Status</th><th class="th">Date</th><th class="th text-right">Amount</th>
        </tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
        <tr class="table-row">
            <td class="td font-medium"><?= e($p['txn_id']) ?></td>
            <td class="td"><?= e($p['booking_ref'] ?? '—') ?></td>
            <td class="td"><?= e(ucwords(str_replace('_',' ',$p['method']))) ?></td>
            <td class="td"><?= e(ucfirst($p['purpose'])) ?></td>
            <td class="td"><?= status_badge($p['status']) ?></td>
            <td class="td"><?= e(fmt_datetime($p['paid_at'] ?? $p['created_at'])) ?></td>
            <td class="td text-right font-medium"><?= money($p['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="7" class="td text-center py-10 text-slate-400">No payments yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
