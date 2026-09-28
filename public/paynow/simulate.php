<?php
/**
 * DEV-ONLY Paynow simulator — lets you exercise the full payment flow locally
 * without real Paynow credentials. Disabled automatically outside test_mode.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Database;
use App\Csrf;

$paynow = new \App\Paynow();
if (!$paynow->testMode || $paynow->configured()) {
    http_response_code(404);
    exit('Simulator is only available in test mode.');
}

$ref = $_GET['ref'] ?? '';
$payment = Database::one('SELECT * FROM payments WHERE txn_id = ?', [$ref]);
if (!$payment) { http_response_code(404); exit('Unknown payment.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $outcome = $_POST['outcome'] ?? 'success';
    // Apply the same fulfilment the real result.php callback performs.
    \App\Services\PaymentService::markGatewayResult(
        $ref, $outcome === 'success' ? 'Paid' : 'Failed');
    redirect('paynow/return.php?reference=' . urlencode($ref));
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Paynow Simulator (dev)</title>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>"></head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8 max-w-md w-full">
    <div class="text-center mb-6">
        <span class="inline-flex px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold mb-3">DEV SIMULATOR — NOT REAL PAYNOW</span>
        <h1 class="text-xl font-semibold">Paynow Checkout</h1>
        <p class="text-sm text-slate-500 mt-1"><?= e($ref) ?> · <?= money($payment['amount']) ?></p>
    </div>
    <form method="post" class="space-y-3">
        <?= Csrf::field() ?>
        <button name="outcome" value="success" class="btn-primary w-full justify-center !py-3">Simulate successful payment</button>
        <button name="outcome" value="fail" class="btn-danger w-full justify-center !py-3">Simulate failed payment</button>
    </form>
</div>
</body></html>
