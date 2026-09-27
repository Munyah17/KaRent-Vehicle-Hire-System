<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Services\WalletService;
use App\Services\BookingService;

Auth::requireClient();
$client = Auth::client();
$cid = (int) $client['id'];

$current = Database::one(
    "SELECT b.*, v.make, v.model, v.reg_no FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id
     WHERE b.client_id=? AND b.status IN ('active','overdue') ORDER BY b.return_at LIMIT 1", [$cid]);
$upcoming = Database::one(
    "SELECT b.*, v.make, v.model, v.reg_no FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id
     WHERE b.client_id=? AND b.status='confirmed' AND b.pickup_at > NOW() ORDER BY b.pickup_at LIMIT 1", [$cid]);
$outstanding = (float) Database::value(
    "SELECT COALESCE(SUM(b.total),0) FROM bookings b
     WHERE b.client_id=? AND b.status IN ('confirmed','active','overdue')", [$cid])
    - (float) Database::value(
    "SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON b.id=p.booking_id
     WHERE p.client_id=? AND p.status='successful' AND p.purpose IN ('rental','extension')
       AND b.status IN ('confirmed','active','overdue')", [$cid]);
$depositHeld = (float) Database::value(
    "SELECT COALESCE(SUM(received_amount - deducted_amount - refunded_amount),0)
     FROM deposits WHERE client_id=? AND status IN ('held','partial')", [$cid]);
$wallet = WalletService::balance($cid);
$recentTx = WalletService::transactions($cid, 5);

$pageTitle = 'Dashboard';
$active = 'dashboard';
require APP_PATH . '/views/client/header.php';
?>
<h1 class="text-2xl font-semibold text-slate-800 mb-1">Welcome back, <?= e(explode(' ', $client['full_name'])[0]) ?></h1>
<p class="text-slate-500 text-sm mb-6">Account <?= e($client['client_no']) ?> · KYC <?= status_badge($client['kyc_status']) ?></p>

<?php if ($client['kyc_status'] !== 'verified'): ?>
<div class="mb-6 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
    Complete your verification to speed up future bookings. <a href="<?= url('client/kyc.php') ?>" class="underline font-medium">Upload documents</a>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <div class="kpi-card"><div><p class="text-sm text-slate-500">Wallet balance</p>
        <p class="text-2xl font-semibold mt-1"><?= money($wallet) ?></p></div>
        <span class="icon-box bg-blue-50 text-blue-600"><i data-lucide="wallet" class="w-5 h-5"></i></span></div>
    <div class="kpi-card"><div><p class="text-sm text-slate-500">Outstanding balance</p>
        <p class="text-2xl font-semibold mt-1 <?= $outstanding > 0 ? 'text-red-600' : '' ?>"><?= money(max(0, $outstanding)) ?></p></div>
        <span class="icon-box bg-red-50 text-red-600"><i data-lucide="alert-circle" class="w-5 h-5"></i></span></div>
    <div class="kpi-card"><div><p class="text-sm text-slate-500">Deposit held</p>
        <p class="text-2xl font-semibold mt-1"><?= money($depositHeld) ?></p></div>
        <span class="icon-box bg-green-50 text-green-600"><i data-lucide="piggy-bank" class="w-5 h-5"></i></span></div>
    <a href="<?= url('client/browse.php') ?>" class="kpi-card !bg-blue-600 !border-blue-600 hover:!bg-blue-700 transition-colors">
        <div><p class="text-sm text-blue-100">Ready for your next trip?</p>
        <p class="text-xl font-semibold text-white mt-1">Book a vehicle</p></div>
        <span class="icon-box bg-blue-500/40 text-white"><i data-lucide="arrow-right" class="w-5 h-5"></i></span></a>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Current booking</h3>
        <?php if ($current): ?>
        <div class="flex items-center justify-between">
            <div>
                <p class="font-medium"><?= e($current['make'] . ' ' . $current['model']) ?> (<?= e($current['reg_no']) ?>)</p>
                <p class="text-sm text-slate-500">Return due <?= e(fmt_datetime($current['return_at'])) ?></p>
            </div>
            <?= status_badge($current['status']) ?>
        </div>
        <div class="flex gap-3 mt-4">
            <a href="<?= url('client/booking.php?id=' . $current['id']) ?>" class="btn-secondary !py-1.5 text-xs">Details</a>
            <a href="<?= url('client/booking.php?id=' . $current['id'] . '#extend') ?>" class="btn-secondary !py-1.5 text-xs">Request extension</a>
        </div>
        <?php else: ?>
        <p class="text-sm text-slate-400">No vehicle currently on hire.</p>
        <?php endif; ?>

        <h3 class="font-semibold text-slate-800 mt-8 mb-4">Upcoming booking</h3>
        <?php if ($upcoming): ?>
        <div class="flex items-center justify-between">
            <div>
                <p class="font-medium"><?= e($upcoming['make'] . ' ' . $upcoming['model']) ?> (<?= e($upcoming['reg_no']) ?>)</p>
                <p class="text-sm text-slate-500">Pickup <?= e(fmt_datetime($upcoming['pickup_at'])) ?></p>
            </div>
            <?= status_badge($upcoming['status']) ?>
        </div>
        <?php else: ?>
        <p class="text-sm text-slate-400">No upcoming bookings.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Recent wallet activity</h3>
        <ul class="divide-y divide-gray-100 text-sm">
            <?php foreach ($recentTx as $t): ?>
            <li class="py-3 flex items-center justify-between">
                <div><p class="font-medium text-slate-700"><?= e($t['description'] ?? ucfirst($t['type'])) ?></p>
                    <p class="text-xs text-slate-400"><?= e(fmt_datetime($t['created_at'])) ?></p></div>
                <span class="font-semibold <?= $t['amount'] < 0 ? 'text-red-600' : 'text-green-600' ?>">
                    <?= ($t['amount'] < 0 ? '−' : '+') . money(abs($t['amount'])) ?></span>
            </li>
            <?php endforeach; ?>
            <?php if (!$recentTx): ?><li class="py-3 text-slate-400">No transactions yet.</li><?php endif; ?>
        </ul>
        <a href="<?= url('client/wallet.php') ?>" class="btn-secondary w-full justify-center mt-4">View wallet</a>
    </div>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
