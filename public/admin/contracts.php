<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Services\ContractService;

Auth::requirePermission('contracts');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $bookingId = (int) $_POST['booking_id'];
    $made = 0;
    foreach ((array) ($_POST['templates'] ?? []) as $code) {
        [$cid, $err] = ContractService::generate($bookingId, (string) $code, Auth::id());
        if ($cid) $made++;
    }
    flash('success', $made . ' document(s) generated.');
    redirect('admin/contract.php?booking=' . $bookingId);
}

$bookingId = (int) ($_GET['booking'] ?? 0);
$templates = Database::all('SELECT * FROM contract_templates WHERE is_active = 1 ORDER BY name');
$bookings = Database::all(
    "SELECT b.id, b.ref, c.full_name client_name, v.make, v.model, v.reg_no, b.pickup_at, b.return_at, b.status
     FROM bookings b JOIN clients c ON c.id=b.client_id JOIN vehicles v ON v.id=b.vehicle_id
     WHERE b.status != 'cancelled' ORDER BY b.id DESC LIMIT 50");
$recent = Database::all(
    "SELECT ct.*, b.ref booking_ref, c.full_name client_name
     FROM contracts ct JOIN bookings b ON b.id=ct.booking_id JOIN clients c ON c.id=ct.client_id
     ORDER BY ct.id DESC LIMIT 15");

$pageTitle = 'Contracts & Documents';
$active = 'contracts';
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Generate Contract</h3>
        <form method="post">
            <?= Csrf::field() ?>
            <label class="label">Select templates</label>
            <div class="space-y-2 mb-5">
                <?php foreach ($templates as $t): ?>
                <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-2.5 hover:bg-gray-50">
                    <input type="checkbox" name="templates[]" value="<?= e($t['code']) ?>" class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm font-medium text-slate-700 flex-1"><?= e($t['name']) ?></span>
                    <span class="text-xs text-slate-400">v<?= (int) $t['version'] ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <label class="label">Booking</label>
            <select name="booking_id" class="input mb-5" required>
                <?php foreach ($bookings as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $bookingId === (int) $b['id'] ? 'selected' : '' ?>>
                    <?= e($b['ref'] . ' — ' . $b['client_name'] . ' — ' . $b['make'] . ' ' . $b['model']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button class="btn-primary w-full justify-center"><i data-lucide="file-plus" class="w-4 h-4"></i> Generate Documents</button>
        </form>
    </div>

    <div class="card" id="summary">
        <h3 class="font-semibold text-slate-800 mb-4">Client & Vehicle Details</h3>
        <?php if ($bookingId):
            $b = \App\Services\BookingService::find($bookingId); ?>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div><dt class="text-slate-400 text-xs">Client</dt><dd class="font-medium"><?= e($b['client_name']) ?></dd></div>
            <div><dt class="text-slate-400 text-xs">Client No</dt><dd class="font-medium"><?= e($b['client_no']) ?></dd></div>
            <div><dt class="text-slate-400 text-xs">Vehicle</dt><dd class="font-medium"><?= e($b['make'] . ' ' . $b['model']) ?></dd></div>
            <div><dt class="text-slate-400 text-xs">Registration</dt><dd class="font-medium"><?= e($b['reg_no']) ?></dd></div>
            <div><dt class="text-slate-400 text-xs">Pickup</dt><dd class="font-medium"><?= e(fmt_datetime($b['pickup_at'])) ?></dd></div>
            <div><dt class="text-slate-400 text-xs">Return</dt><dd class="font-medium"><?= e(fmt_datetime($b['return_at'])) ?></dd></div>
        </dl>
        <?php else: ?>
        <p class="text-sm text-slate-400">Pick a booking above to preview its details here.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card !p-0 overflow-x-auto mt-6">
    <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-slate-800">Generated Documents</h3></div>
    <table class="w-full">
        <thead><tr><th class="th">Document</th><th class="th">Booking</th><th class="th">Client</th><th class="th">Version</th><th class="th">Status</th><th class="th">Created</th><th class="th"></th></tr></thead>
        <tbody>
        <?php foreach ($recent as $c): ?>
        <tr class="table-row">
            <td class="td font-medium"><?= e($c['title']) ?></td>
            <td class="td"><?= e($c['booking_ref']) ?></td>
            <td class="td"><?= e($c['client_name']) ?></td>
            <td class="td">v<?= (int) $c['template_version'] ?></td>
            <td class="td"><?= status_badge($c['status']) ?></td>
            <td class="td"><?= e(fmt_datetime($c['created_at'])) ?></td>
            <td class="td text-right space-x-3">
                <a href="<?= url('admin/contract.php?id=' . $c['id']) ?>" class="text-blue-600 text-sm hover:underline">Open</a>
                <a href="<?= url('admin/contract.php?id=' . $c['id'] . '&print=1') ?>" class="text-blue-600 text-sm hover:underline">Print</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="7" class="td text-center py-8 text-slate-400">No documents generated yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
