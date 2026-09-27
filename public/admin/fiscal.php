<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Audit;
use App\Csrf;
use App\Database;
use App\Services\FdmsService;

Auth::requirePermission('payments');

$dev = null;
if (isset($_GET['device'])) {
    $dev = Database::one('SELECT * FROM fdms_devices WHERE id = ?', [(int) $_GET['device']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'settings') {
        foreach (['fdms_enabled' => isset($_POST['fdms_enabled']) ? '1' : '0',
                  'fdms_scope' => in_array($_POST['fdms_scope'] ?? '', ['all', 'optin'], true) ? $_POST['fdms_scope'] : 'optin',
                  'fdms_device_id' => (string) (int) ($_POST['fdms_device_id'] ?? 0),
                  'fdms_tax_id' => (string) (int) ($_POST['fdms_tax_id'] ?? 0),
                  'fdms_currency' => strtoupper(substr(trim($_POST['fdms_currency'] ?? 'USD'), 0, 8)),
                 ] as $k => $v) {
            Database::run('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?', [$k, $v, $v]);
        }
        Audit::log(Auth::id(), 'update', 'settings', 'fdms', null);
        flash('success', 'Fiscalisation settings saved.');
        redirect('admin/fiscal.php');
    }

    if ($action === 'register') {
        $r = FdmsService::register($_POST);
        Audit::log(Auth::id(), 'fdms_register', 'fiscal', 'fdms_device', $r['id']);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Device registered with FDMS.' : $r['error']);
        redirect('admin/fiscal.php');
    }

    $did = (int) ($_POST['device_pk'] ?? 0);
    $devRow = $did ? Database::one('SELECT * FROM fdms_devices WHERE id=?', [$did]) : null;

    if ($action === 'status' && $devRow) {
        $svcObj = FdmsService::forDevice($did);
        $r = $svcObj ? $svcObj->getStatus() : ['error' => 'Device not found.'];
        flash($r['error'] ? 'error' : 'success',
            $r['error'] ?? ('Day status: ' . ($r['body']['fiscalDayStatus'] ?? '?') .
            ' · last receipt #' . ($r['body']['lastReceiptGlobalNo'] ?? '0') .
            ' · last day #' . ($r['body']['lastFiscalDayNo'] ?? '0')));
        redirect('admin/fiscal.php');
    }

    if ($action === 'syncconfig' && $devRow) {
        $svcObj = FdmsService::forDevice($did);
        $r = $svcObj ? $svcObj->getConfig() : ['error' => 'Device not found.'];
        flash($r['error'] ? 'error' : 'success',
            $r['error'] ?? ('Taxpayer: ' . ($r['body']['taxPayerName'] ?? '?') .
            ' · TIN ' . ($r['body']['taxPayerTIN'] ?? '?') .
            ' · cert valid till ' . substr($r['body']['certificateValidTill'] ?? '?', 0, 10)));
        redirect('admin/fiscal.php');
    }

    if ($action === 'openday' && $devRow) {
        $svcObj = FdmsService::forDevice($did);
        $r = $svcObj ? $svcObj->openDay() : ['ok' => false, 'error' => 'Device not found.'];
        Audit::log(Auth::id(), 'fdms_open_day', 'fiscal', 'fdms_device', $did);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Fiscal day ' . $r['fiscalDayNo'] . ' opened.' : $r['error']);
        redirect('admin/fiscal.php');
    }

    if ($action === 'closeday' && $devRow) {
        $svcObj = FdmsService::forDevice($did);
        $r = $svcObj ? $svcObj->closeDay() : ['ok' => false, 'error' => 'Device not found.'];
        Audit::log(Auth::id(), 'fdms_close_day', 'fiscal', 'fdms_device', $did);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'CloseDay submitted.' : $r['error']);
        redirect('admin/fiscal.php');
    }

    if ($action === 'reconcile' && $devRow) {
        $svcObj = FdmsService::forDevice($did);
        $r = $svcObj ? $svcObj->reconcile() : 'error';
        flash($r === 'error' ? 'error' : 'success', 'Reconcile: ' . $r);
        redirect('admin/fiscal.php');
    }

    if ($action === 'retry_receipt') {
        $r = FdmsService::retry((int) ($_POST['receipt_id'] ?? 0));
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Receipt submitted to FDMS.' : ($r['error'] ?? 'Retry failed.'));
        redirect('admin/fiscal.php');
    }

    if ($action === 'delete_device' && $did) {
        Database::run('DELETE FROM fdms_receipts WHERE fdms_device_id=? AND status!="submitted"', [$did]);
        Database::run('DELETE FROM fdms_devices WHERE id=? AND status!="registered"', [$did]);
        flash('success', 'Device removed.');
        redirect('admin/fiscal.php');
    }
}

$devices = Database::all(
    'SELECT d.*, c.full_name client_name FROM fdms_devices d LEFT JOIN clients c ON c.id=d.client_id ORDER BY d.id DESC'
);
$receipts = Database::all(
    'SELECT r.*, p.txn_id, p.amount, c.full_name client_name
     FROM fdms_receipts r
     LEFT JOIN payments p ON p.id=r.payment_id
     LEFT JOIN clients c ON c.id=p.client_id
     ORDER BY r.id DESC LIMIT 50'
);
$clients = Database::all('SELECT id, full_name, client_no FROM clients ORDER BY full_name LIMIT 300');

$pageTitle = 'Fiscalisation (ZIMRA FDMS)';
$active = 'fiscal';
require APP_PATH . '/views/admin/header.php';
?>
<div class="page-header">
    <div>
        <h1>Fiscalisation</h1>
        <p class="subtitle">ZIMRA FDMS devices, fiscal days and fiscalised receipts</p>
    </div>
</div>

<div class="row row-tight">
    <div class="col-lg-5">
        <!-- Global settings -->
        <div class="card">
            <h2 class="font-semibold text-slate-800 mb-1">Global settings</h2>
            <p class="text-xs text-slate-500 mb-4">Fiscalisation is optional — turn it on once a device is registered.</p>
            <form method="post" class="space-y-3">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="settings">
                <label class="flex items-center gap-2 text-sm text-slate-700 font-medium">
                    <input type="checkbox" name="fdms_enabled" <?= setting('fdms_enabled') === '1' ? 'checked' : '' ?>>
                    Enable fiscalisation (issue fiscal receipts for payments)
                </label>
                <div>
                    <label class="label">Apply to</label>
                    <select name="fdms_scope" class="input">
                        <option value="optin" <?= setting('fdms_scope','optin') === 'optin' ? 'selected' : '' ?>>Clients who opted in only</option>
                        <option value="all" <?= setting('fdms_scope') === 'all' ? 'selected' : '' ?>>All payments</option>
                    </select>
                </div>
                <div>
                    <label class="label">Default device</label>
                    <select name="fdms_device_id" class="input">
                        <option value="0">— first registered device —</option>
                        <?php foreach ($devices as $d): if ($d['status'] !== 'registered') continue; ?>
                        <option value="<?= $d['id'] ?>" <?= (int) setting('fdms_device_id') === (int) $d['id'] ? 'selected' : '' ?>>
                            <?= e($d['label'] ?: '#' . $d['device_id'] . ' ' . $d['serial_number']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Tax ID (from Get Config)</label>
                        <input type="number" name="fdms_tax_id" class="input" value="<?= e(setting('fdms_tax_id', '0')) ?>">
                    </div>
                    <div>
                        <label class="label">Currency</label>
                        <input name="fdms_currency" class="input" value="<?= e(setting('fdms_currency', config('currency','USD'))) ?>" maxlength="8">
                    </div>
                </div>
                <button class="btn-primary">Save settings</button>
            </form>
        </div>

        <!-- Register device -->
        <div class="card mt-4">
            <h2 class="font-semibold text-slate-800 mb-1">Register a fiscal device</h2>
            <p class="text-xs text-slate-500 mb-4">From the FDMS portal: device ID, serial and 8-symbol activation key. Leave <em>Client</em> empty for the company device.</p>
            <form method="post" class="space-y-3">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="register">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Device ID</label>
                        <input type="number" name="device_id" class="input" required></div>
                    <div><label class="label">Serial number</label>
                        <input name="serial_number" class="input" required maxlength="60"></div>
                </div>
                <div><label class="label">Activation key</label>
                    <input name="activation_key" class="input" required maxlength="8" placeholder="AAAABBBB"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Label (optional)</label>
                        <input name="label" class="input" placeholder="Main till"></div>
                    <div><label class="label">Environment</label>
                        <select name="environment" class="input">
                            <option value="test">Test</option>
                            <option value="production">Production</option>
                        </select></div>
                </div>
                <div>
                    <label class="label">Client device (optional)</label>
                    <select name="client_id" class="input">
                        <option value="">— company device —</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['full_name']) ?> (<?= e($c['client_no']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="model_name" value="Server">
                <input type="hidden" name="model_version" value="v1">
                <button class="btn-primary">Register device</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <!-- Devices -->
        <div class="card !p-0 overflow-x-auto">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="font-semibold text-slate-800">Fiscal devices</h2>
            </div>
            <table class="w-full">
                <thead><tr><th class="th">Device</th><th class="th">Owner</th><th class="th">Env</th><th class="th">Status</th><th class="th text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($devices as $d): ?>
                <tr class="table-row">
                    <td class="td">
                        <p class="font-medium text-slate-800"><?= e($d['label'] ?: '#' . $d['device_id']) ?></p>
                        <p class="text-xs text-slate-500"><?= e($d['serial_number']) ?> · ID <?= (int) $d['device_id'] ?></p>
                    </td>
                    <td class="td"><?= e($d['client_name'] ?? 'Company') ?></td>
                    <td class="td"><?= status_badge($d['environment']) ?></td>
                    <td class="td"><?= status_badge($d['status']) ?></td>
                    <td class="td text-right">
                        <form method="post" class="flex flex-wrap gap-2 justify-end">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="device_pk" value="<?= $d['id'] ?>">
                            <?php if ($d['status'] === 'registered'): ?>
                            <button name="action" value="status" class="btn-secondary !px-3 !py-1.5 text-xs">Status</button>
                            <button name="action" value="syncconfig" class="btn-secondary !px-3 !py-1.5 text-xs">Config</button>
                            <button name="action" value="openday" class="btn-secondary !px-3 !py-1.5 text-xs">Open day</button>
                            <button name="action" value="closeday" class="btn-secondary !px-3 !py-1.5 text-xs" onclick="return confirm('Submit CloseDay to FDMS?')">Close day</button>
                            <?php if ($d['pending_receipt']): ?>
                            <button name="action" value="reconcile" class="btn-secondary !px-3 !py-1.5 text-xs !text-amber-700">Reconcile</button>
                            <?php endif; ?>
                            <?php else: ?>
                            <button name="action" value="delete_device" class="text-red-600 text-xs hover:underline" onclick="return confirm('Remove this device record?')">Remove</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$devices): ?>
                <tr><td colspan="5" class="td text-center text-slate-400 py-8">No devices yet — register one on the left.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Receipts -->
        <div class="card !p-0 overflow-x-auto mt-4">
            <div class="px-5 py-4 border-b border-gray-200"><h2 class="font-semibold text-slate-800">Fiscal receipts</h2></div>
            <table class="w-full">
                <thead><tr><th class="th">Invoice</th><th class="th">Client</th><th class="th">Amount</th><th class="th">Receipt #</th><th class="th">Status</th><th class="th text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($receipts as $r): ?>
                <tr class="table-row">
                    <td class="td">
                        <p class="font-medium"><?= e($r['invoice_no'] ?? '—') ?></p>
                        <p class="text-xs text-slate-500"><?= e($r['txn_id']) ?></p>
                    </td>
                    <td class="td"><?= e($r['client_name'] ?? '—') ?></td>
                    <td class="td"><?= $r['amount'] !== null ? money($r['amount']) : '—' ?></td>
                    <td class="td"><?= $r['receipt_global_no'] ? 'G' . $r['receipt_global_no'] . ' / D' . $r['receipt_counter'] : '—' ?></td>
                    <td class="td">
                        <?= status_badge($r['status']) ?>
                        <?php if ($r['error']): ?><p class="text-xs text-red-500 mt-0.5"><?= e(mb_strimwidth($r['error'], 0, 60, '…')) ?></p><?php endif; ?>
                        <?php if ($r['qr_data']): ?><p class="text-xs text-slate-400 mt-0.5 break-all"><?= e($r['qr_data']) ?></p><?php endif; ?>
                    </td>
                    <td class="td text-right">
                        <?php if ($r['status'] !== 'submitted'): ?>
                        <form method="post" class="inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="retry_receipt">
                            <input type="hidden" name="receipt_id" value="<?= $r['id'] ?>">
                            <button class="text-blue-600 text-sm hover:underline">Retry</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$receipts): ?>
                <tr><td colspan="6" class="td text-center text-slate-400 py-8">No fiscal receipts yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
