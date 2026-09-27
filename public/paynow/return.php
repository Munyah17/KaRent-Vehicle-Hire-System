<?php
/** Paynow return URL — the customer's browser lands here after paying. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requireLogin();
$ref = $_GET['reference'] ?? '';
$payment = $ref ? Database::one('SELECT * FROM payments WHERE txn_id = ?', [$ref]) : null;

// If still pending, try a server-side poll to pick up the latest status.
if ($payment && $payment['status'] === 'pending' && $payment['poll_url']) {
    $poll = (new \App\Paynow())->pollStatus($payment['poll_url']);
    if (($poll['paid'] ?? false) || in_array($poll['status'] ?? '', ['paid','successful'], true)) {
        Database::run("UPDATE payments SET status='successful', paid_at=NOW() WHERE id=?", [$payment['id']]);
        $payment['status'] = 'successful';
    }
}

$pageTitle = 'Payment Status';
require APP_PATH . '/views/site/header.php';
?>
<div class="min-h-[60vh] flex items-center justify-center px-6 py-16 bg-gray-50">
    <div class="w-full max-w-md card !p-8 text-center">
        <?php if ($payment && $payment['status'] === 'successful'): ?>
        <span class="inline-flex w-14 h-14 rounded-full bg-green-100 text-green-600 items-center justify-center mb-4"><i data-lucide="check" class="w-7 h-7"></i></span>
        <h1 class="text-xl font-semibold text-slate-800">Payment confirmed</h1>
        <p class="text-sm text-slate-500 mt-2"><?= e($payment['txn_id']) ?> · <?= money($payment['amount']) ?></p>
        <?php elseif ($payment): ?>
        <span class="inline-flex w-14 h-14 rounded-full bg-amber-100 text-amber-600 items-center justify-center mb-4"><i data-lucide="clock" class="w-7 h-7"></i></span>
        <h1 class="text-xl font-semibold text-slate-800">Payment <?= e($payment['status']) ?></h1>
        <p class="text-sm text-slate-500 mt-2">We'll update your booking once the payment is verified.</p>
        <?php else: ?>
        <h1 class="text-xl font-semibold text-slate-800">Payment not found</h1>
        <?php endif; ?>
        <a href="<?= url('client/index.php') ?>" class="btn-primary w-full justify-center mt-6">Go to my account</a>
    </div>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
