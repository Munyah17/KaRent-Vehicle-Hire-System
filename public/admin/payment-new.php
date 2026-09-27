<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\DepositService;
use App\Services\WalletService;

Auth::requirePermission('payments');

$bookingId = (int) ($_GET['booking'] ?? $_POST['booking_id'] ?? 0);
$b = $bookingId ? BookingService::find($bookingId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    if (!$b) { flash('error', 'Booking not found.'); redirect('admin/payments.php'); }
    [$pid, $err] = PaymentService::record(
        (int) $b['client_id'], $bookingId,
        (float) $_POST['amount'], $_POST['method'],
        trim($_POST['reference'] ?? '') ?: null,
        $_POST['purpose'] ?? 'rental', Auth::id(),
        trim($_POST['notes'] ?? '') ?: null
    );
    flash($pid ? 'success' : 'error', $pid ? 'Payment ' . PaymentService::nextTxnId() . ' recorded.' : $err);
    redirect($pid ? 'admin/booking.php?id=' . $bookingId . '&tab=payments' : 'admin/payment-new.php?booking=' . $bookingId);
}

if (!$b) {
    // pick a booking with outstanding balance
    redirect('admin/payments.php');
}

$outstanding = BookingService::outstanding($bookingId);
$deposit = DepositService::forBooking($bookingId);
$depOutstanding = $deposit ? max(0, $deposit['required_amount'] - $deposit['received_amount']) : 0;
$walletBal = WalletService::balance((int) $b['client_id']);

$pageTitle = 'Process Payment';
$active = 'payments';
$methods = [
    ['cash', 'Cash', 'banknote'],
    ['bank_transfer', 'Bank Transfer', 'landmark'],
    ['paynow', 'EcoCash / Paynow', 'smartphone'],
    ['card', 'Card', 'credit-card'],
    ['wallet', 'Client Wallet (' . money($walletBal) . ')', 'wallet'],
    ['other', 'Other', 'circle-dot'],
];
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 max-w-5xl">
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-semibold text-slate-800">Make Payment</h3>
                <p class="text-sm text-slate-500">Booking <?= e($b['ref']) ?> · <?= e($b['client_name']) ?></p>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-400">Outstanding rental</p>
                <p class="text-xl font-semibold text-red-600"><?= money($outstanding) ?></p>
                <?php if ($depOutstanding > 0): ?>
                <p class="text-xs text-slate-400 mt-1">Deposit due <span class="font-medium text-slate-700"><?= money($depOutstanding) ?></span></p>
                <?php endif; ?>
            </div>
        </div>
        <form method="post" id="payform">
            <?= Csrf::field() ?>
            <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
            <label class="label">Payment method</label>
            <div class="grid grid-cols-3 gap-3 mb-5" id="methods">
                <?php foreach ($methods as [$val, $label, $icon]): ?>
                <label class="cursor-pointer">
                    <input type="radio" name="method" value="<?= $val ?>" class="peer sr-only" <?= $val === 'cash' ? 'checked' : '' ?>>
                    <div class="rounded-lg border border-gray-200 p-4 text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-gray-300 transition-colors">
                        <i data-lucide="<?= $icon ?>" class="w-6 h-6 mx-auto mb-2 text-slate-600"></i>
                        <p class="text-xs font-medium text-slate-700"><?= e($label) ?></p>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="label">Amount *</label>
                    <input name="amount" type="number" step="0.01" min="0.01" required class="input" value="<?= e((string) $outstanding) ?>"></div>
                <div><label class="label">Purpose</label>
                    <select name="purpose" class="input">
                        <option value="rental">Rental payment</option>
                        <option value="deposit">Security deposit</option>
                        <option value="topup">Wallet top-up</option>
                        <option value="extension">Extension</option>
                        <option value="other">Other</option>
                    </select></div>
            </div>
        </form>
    </div>

    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Payment Details</h3>
        <div class="space-y-4" >
            <div><label class="label">Reference no.</label>
                <input form="payform" name="reference" class="input" placeholder="Receipt / transfer reference"></div>
            <div><label class="label">Notes</label>
                <textarea form="payform" name="notes" rows="3" class="input" placeholder="Optional notes"></textarea></div>
            <div class="rounded-lg bg-gray-50 p-4 text-sm space-y-1.5">
                <div class="flex justify-between"><span class="text-slate-500">Booking total</span><span class="font-medium"><?= money($b['total']) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Paid so far</span><span class="font-medium text-green-600"><?= money(BookingService::amountPaid($bookingId)) ?></span></div>
                <div class="flex justify-between border-t border-gray-200 pt-1.5"><span class="font-semibold">Outstanding</span><span class="font-semibold text-red-600"><?= money($outstanding) ?></span></div>
            </div>
            <button form="payform" class="btn-primary w-full justify-center !py-3 text-base">
                <i data-lucide="check-circle" class="w-5 h-5"></i> Process Payment
            </button>
            <p class="text-xs text-slate-400 text-center">Payments are recorded against the booking and appear in reports immediately.</p>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
