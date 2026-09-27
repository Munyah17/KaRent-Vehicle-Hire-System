<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Paynow;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\DepositService;
use App\Services\VehicleService;

Auth::requireClient();
$client = Auth::client();
$cid = (int) $client['id'];

$id = (int) ($_GET['id'] ?? 0);
$b = BookingService::find($id);
// A client can only see their own bookings.
if (!$b || (int) $b['client_id'] !== $cid) { http_response_code(404); exit('Booking not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'request_extension') {
        [$eid, $err] = BookingService::requestExtension($id, trim($_POST['new_return'] . ' ' . ($_POST['new_return_time'] ?: '17:00')), Auth::id());
        flash($eid ? 'success' : 'error', $eid ? 'Extension requested — pending approval.' : $err);
    } elseif ($action === 'pay') {
        $amount = (float) $_POST['amount'];
        $method = $_POST['method'];
        $outstanding = BookingService::outstanding($id);
        if ($amount <= 0 || $amount > $outstanding + 0.01) {
            flash('error', 'Invalid payment amount.');
        } elseif ($method === 'wallet') {
            [$pid, $err] = PaymentService::record($cid, $id, $amount, 'wallet', null, 'rental', null);
            flash($pid ? 'success' : 'error', $pid ? 'Paid from wallet.' : $err);
        } elseif ($method === 'paynow') {
            $txn = PaymentService::nextTxnId();
            $res = (new Paynow())->init($txn, $amount, $client['email'] ?? '', 'Booking ' . $b['ref']);
            if ($res['ok']) {
                Database::run(
                    "INSERT INTO payments (txn_id, booking_id, client_id, amount, method, purpose, status, poll_url)
                     VALUES (?,?,?,?,'paynow','rental','pending',?)",
                    [$txn, $id, $cid, $amount, $res['poll_url'] ?? null]
                );
                redirect($res['browser_url']);
            }
            flash('error', 'Paynow initiation failed.');
        }
    }
    redirect('client/booking.php?id=' . $id);
}

$payments = Database::all('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC', [$id]);
$deposit = DepositService::forBooking($id);
$extensions = Database::all('SELECT * FROM booking_extensions WHERE booking_id = ? ORDER BY id DESC', [$id]);
$contracts = Database::all("SELECT * FROM contracts WHERE booking_id = ? AND status != 'void' ORDER BY id DESC", [$id]);
$outstanding = BookingService::outstanding($id);
$paid = BookingService::amountPaid($id);
$photo = VehicleService::photo((int) $b['vehicle_id']);

$pageTitle = 'Booking ' . $b['ref'];
$active = 'bookings';
require APP_PATH . '/views/client/header.php';
?>
<div class="card mb-6">
    <div class="flex flex-wrap items-center gap-4">
        <img src="<?= url($photo) ?>" class="w-20 h-14 object-cover rounded-lg border border-gray-200">
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-slate-800"><?= e($b['ref']) ?></h2>
                <?= status_badge($b['status']) ?>
            </div>
            <p class="text-sm text-slate-500"><?= e($b['make'] . ' ' . $b['model']) ?> (<?= e($b['reg_no']) ?>) ·
                <?= e(fmt_datetime($b['pickup_at'])) ?> → <?= e(fmt_datetime($b['return_at'])) ?></p>
        </div>
        <?php if ($outstanding > 0 && in_array($b['status'], ['pending','confirmed','active'], true)): ?>
        <div class="text-right">
            <p class="text-xs text-slate-400">Outstanding</p>
            <p class="text-xl font-semibold text-red-600"><?= money($outstanding) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Financial summary</h3>
            <dl class="text-sm space-y-2.5">
                <div class="flex justify-between"><dt class="text-slate-500">Rental</dt><dd class="font-medium"><?= money($b['base_amount']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Additional charges</dt><dd class="font-medium"><?= money($b['additional_amount']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="font-medium text-green-600">−<?= money($b['discount']) ?></dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-2.5"><dt class="font-semibold">Total</dt><dd class="font-semibold"><?= money($b['total']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Paid</dt><dd class="font-medium text-green-600"><?= money($paid) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Outstanding</dt><dd class="font-semibold <?= $outstanding > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= money($outstanding) ?></dd></div>
                <?php if ($deposit): ?>
                <div class="flex justify-between border-t border-gray-100 pt-2.5">
                    <dt class="text-slate-500">Deposit held</dt>
                    <dd class="font-medium"><?= money($deposit['received_amount'] - $deposit['deducted_amount'] - $deposit['refunded_amount']) ?> <?= status_badge($deposit['status']) ?></dd></div>
                <?php endif; ?>
            </dl>
        </div>

        <?php if ($outstanding > 0 && in_array($b['status'], ['pending','confirmed','active'], true)): ?>
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Make a payment</h3>
            <form method="post" class="flex flex-wrap items-end gap-3">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="pay">
                <div><label class="label">Amount</label>
                    <input type="number" name="amount" step="0.01" min="0.01" max="<?= $outstanding ?>" value="<?= $outstanding ?>" class="input w-40"></div>
                <div><label class="label">Method</label>
                    <select name="method" class="input w-44">
                        <option value="paynow">Paynow</option>
                        <option value="wallet">Wallet</option>
                    </select></div>
                <button class="btn-primary"><i data-lucide="credit-card" class="w-4 h-4"></i> Pay</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card" id="extend">
            <h3 class="font-semibold text-slate-800 mb-4">Request extension</h3>
            <?php if (in_array($b['status'], ['active','confirmed'], true)): ?>
            <form method="post" class="flex flex-wrap items-end gap-3">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="request_extension">
                <div><label class="label">New return date</label>
                    <input type="date" name="new_return" min="<?= date('Y-m-d', strtotime($b['return_at'] . ' +1 day')) ?>" class="input" required></div>
                <div><label class="label">Time</label>
                    <input type="time" name="new_return_time" value="17:00" class="input"></div>
                <button class="btn-primary">Request</button>
            </form>
            <?php else: ?>
            <p class="text-sm text-slate-400">Extensions are available for confirmed and active bookings.</p>
            <?php endif; ?>
            <?php if ($extensions): ?>
            <ul class="mt-4 space-y-2 text-sm">
                <?php foreach ($extensions as $x): ?>
                <li class="flex items-center justify-between">
                    <span><?= e(fmt_date($x['new_return_at'])) ?> (+<?= money($x['additional_amount']) ?>)</span>
                    <?= status_badge($x['status']) ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Documents</h3>
            <ul class="space-y-2 text-sm">
                <?php foreach ($contracts as $c): ?>
                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4 text-gray-400"></i><?= e($c['title']) ?></span>
                    <a href="<?= url('client/document.php?id=' . $c['id']) ?>" target="_blank" class="text-blue-600 hover:underline">View</a>
                </li>
                <?php endforeach; ?>
                <?php if (!$contracts): ?><li class="text-slate-400">No documents yet.</li><?php endif; ?>
            </ul>
        </div>
        <div class="card !p-0 overflow-x-auto">
            <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-slate-800">Payments</h3></div>
            <ul class="divide-y divide-gray-100 text-sm">
                <?php foreach ($payments as $p): ?>
                <li class="px-6 py-3 flex items-center justify-between">
                    <div><p class="font-medium"><?= e($p['txn_id']) ?></p>
                        <p class="text-xs text-slate-400"><?= e(ucwords(str_replace('_',' ',$p['method']))) ?> · <?= e(fmt_datetime($p['paid_at'] ?? $p['created_at'])) ?></p></div>
                    <div class="text-right"><p class="font-semibold"><?= money($p['amount']) ?></p><?= status_badge($p['status']) ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (!$payments): ?><li class="px-6 py-6 text-slate-400">No payments yet.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
