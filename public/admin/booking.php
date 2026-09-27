<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\DepositService;
use App\Services\VehicleService;
use App\Services\ContractService;

Auth::requirePermission('bookings');
$id = (int) ($_GET['id'] ?? 0);
$b = BookingService::find($id);
if (!$b) { http_response_code(404); exit('Booking not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['confirm','activate','complete','cancel','overdue'], true)) {
        $map = ['confirm'=>'confirmed','activate'=>'active','complete'=>'completed','cancel'=>'cancelled','overdue'=>'overdue'];
        [$ok, $err] = BookingService::setStatus($id, $map[$action], Auth::id(), $_POST['note'] ?? null);
        flash($ok ? 'success' : 'error', $ok ? 'Booking updated.' : $err);
    } elseif ($action === 'add_charge') {
        BookingService::addCharge($id, trim($_POST['label']), (float) $_POST['amount'], Auth::id());
        flash('success', 'Charge added.');
    } elseif ($action === 'discount') {
        [$ok, $err] = BookingService::applyDiscount($id, (float) $_POST['discount'], Auth::id());
        flash($ok ? 'success' : 'error', $ok ? 'Discount applied.' : $err);
    } elseif ($action === 'record_payment' && Auth::can('payments')) {
        [$pid, $err] = PaymentService::record(
            (int) $b['client_id'], $id,
            (float) $_POST['amount'], $_POST['method'],
            trim($_POST['reference'] ?? '') ?: null,
            $_POST['purpose'] ?? 'rental', Auth::id(),
            trim($_POST['pay_notes'] ?? '') ?: null
        );
        flash($pid ? 'success' : 'error', $pid ? 'Payment recorded.' : $err);
    } elseif ($action === 'deposit_deduct' && Auth::can('deposits')) {
        $dep = DepositService::forBooking($id);
        [$ok, $err] = DepositService::deduct((int) $dep['id'], (float) $_POST['amount'], trim($_POST['reason']), Auth::id());
        flash($ok ? 'success' : 'error', $ok ? 'Deduction recorded.' : $err);
    } elseif ($action === 'deposit_refund' && Auth::can('deposits')) {
        $dep = DepositService::forBooking($id);
        [$ok, $err] = DepositService::refund((int) $dep['id'], (float) $_POST['amount'], trim($_POST['reason']),
            Auth::id(), $_POST['refund_method'] ?? 'external');
        flash($ok ? 'success' : 'error', $ok ? 'Refund recorded.' : $err);
    } elseif ($action === 'gen_contract' && Auth::can('contracts')) {
        [$cid, $err] = ContractService::generate($id, $_POST['template_code'], Auth::id());
        flash($cid ? 'success' : 'error', $cid ? 'Document generated.' : $err);
    } elseif ($action === 'ext_decide') {
        [$ok, $err] = BookingService::decideExtension(
            (int) $_POST['ext_id'], $_POST['decision'] === 'approve', Auth::id(), trim($_POST['ext_note'] ?? ''));
        flash($ok ? 'success' : 'error', $ok ? 'Extension ' . $_POST['decision'] . 'd.' : $err);
    }
    redirect('admin/booking.php?id=' . $id);
}

$tab = $_GET['tab'] ?? 'overview';
$payments = Database::all('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC', [$id]);
$charges = Database::all('SELECT * FROM booking_charges WHERE booking_id = ?', [$id]);
$history = Database::all(
    'SELECT h.*, u.name FROM booking_status_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.booking_id=? ORDER BY h.id', [$id]);
$deposit = DepositService::forBooking($id);
$depTx = $deposit ? Database::all('SELECT * FROM deposit_transactions WHERE deposit_id = ? ORDER BY id', [$deposit['id']]) : [];
$extensions = Database::all('SELECT * FROM booking_extensions WHERE booking_id = ? ORDER BY id DESC', [$id]);
$contracts = Database::all('SELECT * FROM contracts WHERE booking_id = ? ORDER BY id DESC', [$id]);
$templates = Database::all('SELECT code, name FROM contract_templates WHERE is_active = 1 ORDER BY name');
$checklists = Database::all('SELECT * FROM checklists WHERE booking_id = ?', [$id]);

$paid = BookingService::amountPaid($id);
$outstanding = BookingService::outstanding($id);
$photo = VehicleService::photo((int) $b['vehicle_id']);
$depBalance = $deposit ? $deposit['received_amount'] - $deposit['deducted_amount'] - $deposit['refunded_amount'] : 0;

$pageTitle = 'Booking ' . $b['ref'];
$active = 'bookings';
$tabs = ['overview' => 'Overview', 'client' => 'Client', 'vehicle' => 'Vehicle', 'payments' => 'Payments', 'contract' => 'Contract'];
require APP_PATH . '/views/admin/header.php';
?>
<div class="card mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <img src="<?= url($photo) ?>" class="w-20 h-14 object-cover rounded-lg border border-gray-200">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-slate-800"><?= e($b['ref']) ?></h2>
                    <?= status_badge($b['status']) ?>
                    <?php if ($b['source'] === 'walk_in'): ?><span class="badge bg-purple-100 text-purple-800">Walk-in</span><?php endif; ?>
                </div>
                <p class="text-sm text-slate-500"><?= e($b['client_name']) ?> · <?= e($b['make'] . ' ' . $b['model']) ?> (<?= e($b['reg_no']) ?>)</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($b['status'] === 'pending'): ?>
            <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="confirm">
                <button class="btn-primary">Confirm</button></form>
            <?php endif; ?>
            <?php if ($b['status'] === 'confirmed'): ?>
            <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="activate">
                <button class="btn-primary"><i data-lucide="key-round" class="w-4 h-4"></i> Hand Over</button></form>
            <?php endif; ?>
            <?php if (in_array($b['status'], ['active','overdue'], true)): ?>
            <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="complete">
                <button class="btn-primary"><i data-lucide="check" class="w-4 h-4"></i> Process Return</button></form>
            <?php endif; ?>
            <?php if (in_array($b['status'], ['pending','confirmed','active'], true)): ?>
            <form method="post" onsubmit="return confirm('Cancel this booking?')"><?= Csrf::field() ?>
                <input type="hidden" name="action" value="cancel">
                <button class="btn-danger">Cancel</button></form>
            <?php endif; ?>
        </div>
    </div>
    <div class="border-t border-gray-100 mt-5 pt-4">
        <nav class="flex gap-1">
            <?php foreach ($tabs as $key => $label): ?>
            <a href="?id=<?= $id ?>&tab=<?= $key ?>"
               class="px-4 py-2 rounded-md text-sm font-medium <?= $tab === $key ? 'bg-blue-50 text-blue-700' : 'text-gray-500 hover:bg-gray-50' ?>">
                <?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<?php if ($tab === 'overview'): ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Hire Details</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div><p class="text-slate-400 text-xs">Pickup</p><p class="font-medium"><?= e(fmt_datetime($b['pickup_at'])) ?></p></div>
                <div><p class="text-slate-400 text-xs">Return</p><p class="font-medium"><?= e(fmt_datetime($b['return_at'])) ?></p></div>
                <div><p class="text-slate-400 text-xs">Duration</p><p class="font-medium"><?= max(1, (int) ceil((strtotime($b['return_at']) - strtotime($b['pickup_at'])) / 86400)) ?> days</p></div>
                <div><p class="text-slate-400 text-xs">Created</p><p class="font-medium"><?= e(fmt_date($b['created_at'])) ?></p></div>
            </div>
            <?php if ($b['notes']): ?><p class="mt-4 text-sm text-slate-600 bg-gray-50 rounded-lg p-3"><?= e($b['notes']) ?></p><?php endif; ?>
        </div>

        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Checklists & Handover</h3>
            <div class="flex flex-wrap gap-3">
                <a href="<?= url('admin/checklist.php?booking=' . $id . '&type=collection') ?>" class="btn-secondary">
                    <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                    Collection checklist <?= in_array('collection', array_column($checklists, 'type'), true) ? '(done)' : '' ?>
                </a>
                <a href="<?= url('admin/checklist.php?booking=' . $id . '&type=return') ?>" class="btn-secondary">
                    <i data-lucide="clipboard-x" class="w-4 h-4"></i>
                    Return checklist <?= in_array('return', array_column($checklists, 'type'), true) ? '(done)' : '' ?>
                </a>
            </div>
        </div>

        <?php if ($extensions): ?>
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Extension Requests</h3>
            <ul class="divide-y divide-gray-100 text-sm">
            <?php foreach ($extensions as $x): ?>
                <li class="py-3 flex items-center justify-between">
                    <div>
                        <p class="font-medium"><?= e(fmt_date($x['old_return_at'])) ?> → <?= e(fmt_date($x['new_return_at'])) ?>
                            <span class="text-slate-400">(+<?= money($x['additional_amount']) ?>)</span></p>
                        <?php if ($x['note']): ?><p class="text-xs text-slate-400"><?= e($x['note']) ?></p><?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <?= status_badge($x['status']) ?>
                        <?php if ($x['status'] === 'pending'): ?>
                        <form method="post" class="flex gap-2"><?= Csrf::field() ?>
                            <input type="hidden" name="action" value="ext_decide">
                            <input type="hidden" name="ext_id" value="<?= $x['id'] ?>">
                            <button name="decision" value="approve" class="text-green-600 text-xs hover:underline">Approve</button>
                            <button name="decision" value="reject" class="text-red-600 text-xs hover:underline">Reject</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Status History</h3>
            <ul class="space-y-3">
                <?php foreach ($history as $h): ?>
                <li class="flex items-center gap-3 text-sm">
                    <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
                    <span class="font-medium w-24"><?= e(ucfirst($h['status'])) ?></span>
                    <span class="text-slate-500 flex-1"><?= e($h['note'] ?? '') ?></span>
                    <span class="text-xs text-slate-400"><?= e($h['name'] ?? '') ?> · <?= e(fmt_datetime($h['created_at'])) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Financial Summary</h3>
            <dl class="text-sm space-y-2.5">
                <div class="flex justify-between"><dt class="text-slate-500">Rental amount</dt><dd class="font-medium"><?= money($b['base_amount']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Additional charges</dt><dd class="font-medium"><?= money($b['additional_amount']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="font-medium text-green-600">-<?= money($b['discount']) ?></dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-2.5"><dt class="font-semibold">Total</dt><dd class="font-semibold"><?= money($b['total']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Amount paid</dt><dd class="font-medium text-green-600"><?= money($paid) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Outstanding</dt><dd class="font-semibold <?= $outstanding > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= money($outstanding) ?></dd></div>
            </dl>
            <a href="<?= url('admin/payment-new.php?booking=' . $id) ?>" class="btn-primary w-full justify-center mt-5">
                <i data-lucide="credit-card" class="w-4 h-4"></i> Process Payment</a>
        </div>

        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Deposit</h3>
            <?php if ($deposit): ?>
            <dl class="text-sm space-y-2.5">
                <div class="flex justify-between"><dt class="text-slate-500">Required</dt><dd class="font-medium"><?= money($deposit['required_amount']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Received</dt><dd class="font-medium"><?= money($deposit['received_amount']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Deducted</dt><dd class="font-medium text-red-600"><?= money($deposit['deducted_amount']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Refunded</dt><dd class="font-medium"><?= money($deposit['refunded_amount']) ?></dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-2.5"><dt class="font-semibold">Held</dt>
                    <dd class="font-semibold"><?= money($depBalance) ?> <?= status_badge($deposit['status']) ?></dd></div>
            </dl>
            <?php if ($depBalance > 0 && Auth::can('deposits')): ?>
            <div class="grid grid-cols-2 gap-3 mt-4">
                <form method="post" class="space-y-2"><?= Csrf::field() ?>
                    <input type="hidden" name="action" value="deposit_deduct">
                    <input name="amount" type="number" step="0.01" min="0.01" max="<?= $depBalance ?>" placeholder="Amount" required class="input !py-1.5 text-xs">
                    <input name="reason" placeholder="Reason" required class="input !py-1.5 text-xs">
                    <button class="btn-secondary w-full justify-center !py-1.5 text-xs">Deduct</button>
                </form>
                <form method="post" class="space-y-2"><?= Csrf::field() ?>
                    <input type="hidden" name="action" value="deposit_refund">
                    <input name="amount" type="number" step="0.01" min="0.01" max="<?= $depBalance ?>" placeholder="Amount" required class="input !py-1.5 text-xs">
                    <input name="reason" placeholder="Reason" required class="input !py-1.5 text-xs">
                    <select name="refund_method" class="input !py-1.5 text-xs">
                        <option value="wallet">Credit client wallet</option>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank transfer</option>
                        <option value="ecocash">EcoCash</option>
                    </select>
                    <button class="btn-secondary w-full justify-center !py-1.5 text-xs">Refund</button>
                </form>
            </div>
            <?php endif; ?>
            <?php else: ?><p class="text-sm text-slate-400">No deposit recorded.</p><?php endif; ?>
        </div>

        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Adjustments</h3>
            <form method="post" class="space-y-2.5 mb-4"><?= Csrf::field() ?>
                <input type="hidden" name="action" value="add_charge">
                <input name="label" placeholder="Charge label (e.g. Fuel)" required class="input !py-1.5">
                <div class="flex gap-2">
                    <input name="amount" type="number" step="0.01" min="0.01" placeholder="Amount" required class="input !py-1.5">
                    <button class="btn-secondary !py-1.5">Add</button>
                </div>
            </form>
            <form method="post" class="flex gap-2"><?= Csrf::field() ?>
                <input type="hidden" name="action" value="discount">
                <input name="discount" type="number" step="0.01" min="0" placeholder="Discount amount" class="input !py-1.5">
                <button class="btn-secondary !py-1.5">Apply</button>
            </form>
            <?php if ($charges): ?>
            <ul class="mt-4 space-y-1.5 text-sm">
                <?php foreach ($charges as $ch): ?>
                <li class="flex justify-between"><span class="text-slate-600"><?= e($ch['label']) ?></span><span class="font-medium"><?= money($ch['amount']) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($tab === 'client'): ?>
<div class="card max-w-3xl">
    <div class="flex items-center gap-4 mb-6">
        <span class="w-14 h-14 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-lg font-semibold"><?= e(strtoupper(substr($b['client_name'],0,1))) ?></span>
        <div>
            <p class="font-semibold text-slate-800"><?= e($b['client_name']) ?></p>
            <p class="text-sm text-slate-500"><?= e($b['client_no']) ?></p>
        </div>
        <a href="<?= url('admin/client-edit.php?id=' . $b['client_id']) ?>" class="btn-secondary ml-auto">View client profile</a>
    </div>
    <dl class="grid grid-cols-2 gap-4 text-sm">
        <div><dt class="text-slate-400 text-xs">Phone</dt><dd class="font-medium"><?= e($b['client_phone'] ?? '—') ?></dd></div>
        <div><dt class="text-slate-400 text-xs">Email</dt><dd class="font-medium"><?= e($b['client_email'] ?? '—') ?></dd></div>
    </dl>
</div>

<?php elseif ($tab === 'vehicle'): ?>
<div class="card max-w-3xl">
    <div class="flex items-center gap-4 mb-6">
        <img src="<?= url($photo) ?>" class="w-24 h-16 object-cover rounded-lg border border-gray-200">
        <div>
            <p class="font-semibold text-slate-800"><?= e($b['make'] . ' ' . $b['model']) ?></p>
            <p class="text-sm text-slate-500"><?= e($b['reg_no']) ?></p>
        </div>
        <a href="<?= url('admin/vehicle-edit.php?id=' . $b['vehicle_id']) ?>" class="btn-secondary ml-auto">Edit vehicle</a>
    </div>
    <dl class="grid grid-cols-3 gap-4 text-sm">
        <div><dt class="text-slate-400 text-xs">Daily rate</dt><dd class="font-medium"><?= money($b['daily_rate']) ?></dd></div>
        <div><dt class="text-slate-400 text-xs">Deposit</dt><dd class="font-medium"><?= money($b['vehicle_deposit']) ?></dd></div>
    </dl>
</div>

<?php elseif ($tab === 'payments'): ?>
<div class="card !p-0 overflow-x-auto">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-slate-800">Payments</h3>
        <a href="<?= url('admin/payment-new.php?booking=' . $id) ?>" class="btn-primary"><i data-lucide="plus" class="w-4 h-4"></i> Record Payment</a>
    </div>
    <table class="w-full">
        <thead><tr><th class="th">Txn ID</th><th class="th">Date</th><th class="th">Method</th><th class="th">Purpose</th><th class="th">Reference</th><th class="th">Status</th><th class="th text-right">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
        <tr class="table-row">
            <td class="td font-medium"><?= e($p['txn_id']) ?></td>
            <td class="td"><?= e(fmt_datetime($p['paid_at'] ?? $p['created_at'])) ?></td>
            <td class="td"><?= e(ucwords(str_replace('_',' ',$p['method']))) ?></td>
            <td class="td"><?= e(ucfirst($p['purpose'])) ?></td>
            <td class="td"><?= e($p['reference'] ?? '—') ?></td>
            <td class="td"><?= status_badge($p['status']) ?></td>
            <td class="td text-right font-medium"><?= money($p['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="7" class="td text-center py-8 text-slate-400">No payments yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php if ($depTx): ?>
    <div class="px-6 py-4 border-t border-gray-100"><h3 class="font-semibold text-slate-800">Deposit Transactions</h3></div>
    <table class="w-full">
        <thead><tr><th class="th">Type</th><th class="th">Reason</th><th class="th">Date</th><th class="th text-right">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($depTx as $t): ?>
        <tr class="table-row">
            <td class="td"><?= status_badge($t['type']) ?></td>
            <td class="td"><?= e($t['reason'] ?? '—') ?></td>
            <td class="td"><?= e(fmt_datetime($t['created_at'])) ?></td>
            <td class="td text-right font-medium"><?= money($t['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'contract'): ?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Generate Document</h3>
        <form method="post" class="space-y-3"><?= Csrf::field() ?>
            <input type="hidden" name="action" value="gen_contract">
            <select name="template_code" class="input">
                <?php foreach ($templates as $t): ?>
                <option value="<?= e($t['code']) ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-primary"><i data-lucide="file-plus" class="w-4 h-4"></i> Generate from template</button>
        </form>
        <p class="text-xs text-slate-400 mt-3">Generated documents preserve the exact template version used — later template edits never change history.</p>
    </div>
    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Documents</h3>
        <ul class="divide-y divide-gray-100 text-sm">
            <?php foreach ($contracts as $c): ?>
            <li class="py-3 flex items-center justify-between">
                <div>
                    <p class="font-medium text-slate-700"><?= e($c['title']) ?> <span class="text-xs text-slate-400">v<?= $c['template_version'] ?></span></p>
                    <p class="text-xs text-slate-400"><?= e(fmt_datetime($c['created_at'])) ?></p>
                </div>
                <div class="flex items-center gap-2">
                    <?= status_badge($c['status']) ?>
                    <a href="<?= url('admin/contract.php?id=' . $c['id']) ?>" class="text-blue-600 text-xs hover:underline">View / Sign</a>
                    <a href="<?= url('admin/contract.php?id=' . $c['id'] . '&print=1') ?>" class="text-blue-600 text-xs hover:underline">Print</a>
                </div>
            </li>
            <?php endforeach; ?>
            <?php if (!$contracts): ?><li class="py-3 text-slate-400">No documents generated yet.</li><?php endif; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
