<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requireClient();
$client = Auth::client();

$deposits = Database::all(
    'SELECT d.*, b.ref booking_ref, v.make, v.model, v.reg_no FROM deposits d
     JOIN bookings b ON b.id=d.booking_id JOIN vehicles v ON v.id=b.vehicle_id
     WHERE d.client_id = ? ORDER BY d.id DESC', [$client['id']]);

$pageTitle = 'My Deposits';
$active = 'deposits';
require APP_PATH . '/views/client/header.php';
?>
<div class="card !p-0 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-slate-800">Security Deposits</h2></div>
    <table class="w-full">
        <thead><tr>
            <th class="th">Booking</th><th class="th">Vehicle</th><th class="th">Required</th>
            <th class="th">Received</th><th class="th">Deductions</th><th class="th">Refunded</th>
            <th class="th">Held</th><th class="th">Status</th>
        </tr></thead>
        <tbody>
        <?php foreach ($deposits as $d):
            $held = $d['received_amount'] - $d['deducted_amount'] - $d['refunded_amount']; ?>
        <tr class="table-row">
            <td class="td font-medium"><?= e($d['booking_ref']) ?></td>
            <td class="td"><?= e($d['make'] . ' ' . $d['model']) ?></td>
            <td class="td"><?= money($d['required_amount']) ?></td>
            <td class="td"><?= money($d['received_amount']) ?></td>
            <td class="td text-red-600"><?= money($d['deducted_amount']) ?></td>
            <td class="td"><?= money($d['refunded_amount']) ?></td>
            <td class="td font-medium"><?= money($held) ?></td>
            <td class="td"><?= status_badge($d['status']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$deposits): ?><tr><td colspan="8" class="td text-center py-10 text-slate-400">No deposits yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
