<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;
use App\Services\WalletService;
use App\Services\PaymentService;

Auth::requirePermission('wallets');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $clientId = (int) $_POST['client_id'];
    $action = $_POST['action'];
    $amount = (float) $_POST['amount'];
    $desc = trim($_POST['description'] ?? '') ?: ucfirst($action);
    if ($amount <= 0) {
        flash('error', 'Amount must be positive.');
    } elseif ($action === 'topup') {
        // top-ups come in via a real payment method
        [$pid, $err] = PaymentService::record($clientId, null, $amount,
            $_POST['method'] ?? 'cash', null, 'topup', Auth::id(), $desc);
        flash($pid ? 'success' : 'error', $pid ? 'Top-up recorded.' : $err);
    } elseif ($action === 'adjust') {
        $sign = $_POST['direction'] === 'debit' ? -1 : 1;
        WalletService::post($clientId, 'adjustment', $sign * $amount, $desc, null, null, Auth::id());
        Audit::log(Auth::id(), 'wallet_adjust', 'wallets', null, null, null, $sign * $amount);
        flash('success', 'Adjustment posted.');
    }
    redirect('admin/wallets.php' . ($clientId ? '?client=' . $clientId : ''));
}

$selected = (int) ($_GET['client'] ?? 0);
$clients = Database::all(
    "SELECT c.id, c.client_no, c.full_name,
        (SELECT COALESCE(SUM(t.amount),0) FROM wallet_transactions t
         JOIN wallets w ON w.id=t.wallet_id WHERE w.client_id=c.id) balance
     FROM clients c ORDER BY c.full_name");
$txns = $selected ? WalletService::transactions($selected, 100) : [];

$pageTitle = 'Wallets';
$active = 'wallets';
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="card !p-0 overflow-x-auto">
        <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-slate-800">Client Wallets</h3></div>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($clients as $c): ?>
            <li>
                <a href="?client=<?= $c['id'] ?>" class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 <?= $selected === (int) $c['id'] ? 'bg-blue-50' : '' ?>">
                    <div><p class="text-sm font-medium text-slate-800"><?= e($c['full_name']) ?></p>
                        <p class="text-xs text-slate-400"><?= e($c['client_no']) ?></p></div>
                    <span class="text-sm font-semibold <?= $c['balance'] < 0 ? 'text-red-600' : 'text-slate-800' ?>"><?= money($c['balance']) ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="xl:col-span-2 space-y-6">
        <?php if ($selected): ?>
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Post Transaction</h3>
            <div class="grid grid-cols-2 gap-4">
                <form method="post" class="space-y-3"><?= Csrf::field() ?>
                    <input type="hidden" name="action" value="topup">
                    <input type="hidden" name="client_id" value="<?= $selected ?>">
                    <p class="text-sm font-medium text-slate-700">Top-up</p>
                    <input name="amount" type="number" step="0.01" min="0.01" placeholder="Amount" required class="input !py-1.5">
                    <select name="method" class="input !py-1.5">
                        <option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option>
                        <option value="paynow">Paynow</option><option value="card">Card</option>
                    </select>
                    <input name="description" placeholder="Note" class="input !py-1.5">
                    <button class="btn-primary !py-1.5 w-full justify-center">Record Top-up</button>
                </form>
                <form method="post" class="space-y-3"><?= Csrf::field() ?>
                    <input type="hidden" name="action" value="adjust">
                    <input type="hidden" name="client_id" value="<?= $selected ?>">
                    <p class="text-sm font-medium text-slate-700">Manual adjustment</p>
                    <input name="amount" type="number" step="0.01" min="0.01" placeholder="Amount" required class="input !py-1.5">
                    <select name="direction" class="input !py-1.5">
                        <option value="credit">Credit (+)</option><option value="debit">Debit (−)</option>
                    </select>
                    <input name="description" placeholder="Reason" required class="input !py-1.5">
                    <button class="btn-secondary !py-1.5 w-full justify-center">Post Adjustment</button>
                </form>
            </div>
        </div>

        <div class="card !p-0 overflow-x-auto">
            <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-slate-800">Ledger</h3></div>
            <table class="w-full">
                <thead><tr><th class="th">Ref</th><th class="th">Type</th><th class="th">Description</th><th class="th">Date</th><th class="th text-right">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($txns as $t): ?>
                <tr class="table-row">
                    <td class="td font-medium"><?= e($t['ref']) ?></td>
                    <td class="td"><?= e(ucwords(str_replace('_',' ',$t['type']))) ?></td>
                    <td class="td"><?= e($t['description'] ?? '—') ?></td>
                    <td class="td"><?= e(fmt_datetime($t['created_at'])) ?></td>
                    <td class="td text-right font-medium <?= $t['amount'] < 0 ? 'text-red-600' : 'text-green-600' ?>">
                        <?= ($t['amount'] < 0 ? '−' : '+') . money(abs($t['amount'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$txns): ?><tr><td colspan="5" class="td text-center py-8 text-slate-400">No transactions.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card text-center py-16 text-slate-400">Select a client to view their wallet ledger.</div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
