<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Paynow;
use App\Services\PaymentService;
use App\Services\WalletService;

Auth::requireClient();
$client = Auth::client();
$cid = (int) $client['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $amount = (float) $_POST['amount'];
    $method = $_POST['method'] ?? 'paynow';
    if ($amount <= 0) {
        flash('error', 'Enter a valid amount.');
    } elseif ($method === 'paynow') {
        $txn = PaymentService::nextTxnId();
        $res = (new Paynow())->init($txn, $amount, $client['email'] ?? '', 'Wallet top-up');
        if ($res['ok']) {
            Database::run(
                "INSERT INTO payments (txn_id, client_id, amount, method, purpose, status, poll_url)
                 VALUES (?,?,?,'paynow','topup','pending',?)",
                [$txn, $cid, $amount, $res['poll_url'] ?? null]
            );
            redirect($res['browser_url']);
        }
        flash('error', 'Paynow initiation failed.');
    }
    redirect('client/wallet.php');
}

$balance = WalletService::balance($cid);
$txns = WalletService::transactions($cid, 100);

$pageTitle = 'My Wallet';
$active = 'wallet';
require APP_PATH . '/views/client/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="card h-fit">
        <p class="text-sm text-slate-500">Current balance</p>
        <p class="text-3xl font-semibold text-slate-800 mt-1"><?= money($balance) ?></p>
        <form method="post" class="mt-6 space-y-3">
            <?= Csrf::field() ?>
            <label class="label">Top up via Paynow</label>
            <input type="number" name="amount" step="0.01" min="1" placeholder="Amount" class="input" required>
            <input type="hidden" name="method" value="paynow">
            <button class="btn-primary w-full justify-center"><i data-lucide="plus-circle" class="w-4 h-4"></i> Top Up</button>
        </form>
    </div>
    <div class="xl:col-span-2 card !p-0 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-slate-800">Transaction History</h3></div>
        <table class="w-full">
            <thead><tr><th class="th">Ref</th><th class="th">Type</th><th class="th">Description</th><th class="th">Date</th><th class="th text-right">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($txns as $t): ?>
            <tr class="table-row">
                <td class="td font-medium"><?= e($t['ref']) ?></td>
                <td class="td"><?= e(ucwords(str_replace('_',' ',$t['type']))) ?></td>
                <td class="td"><?= e($t['description'] ?? '—') ?></td>
                <td class="td"><?= e(fmt_datetime($t['created_at'])) ?></td>
                <td class="td text-right font-semibold <?= $t['amount'] < 0 ? 'text-red-600' : 'text-green-600' ?>">
                    <?= ($t['amount'] < 0 ? '−' : '+') . money(abs($t['amount'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$txns): ?><tr><td colspan="5" class="td text-center py-10 text-slate-400">No transactions yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
