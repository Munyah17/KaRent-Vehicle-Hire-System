<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;

Auth::requirePermission('settings');

$tab = $_GET['tab'] ?? 'company';
$tabs = [
    'company' => ['Company', 'building-2'],
    'booking' => ['Booking Rules', 'calendar-check'],
    'pricing' => ['Pricing', 'banknote'],
    'payments' => ['Payments', 'credit-card'],
    'documents' => ['Documents', 'file-text'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $allowed = [
        'company' => ['company_name','company_email','company_phone','company_address'],
        'booking' => ['booking_min_hours','booking_cancellation_fee'],
        'pricing' => ['currency','currency_symbol','tax_rate','default_deposit'],
        'payments' => ['paynow_integration_id','paynow_test_mode'],
        'documents' => ['terms_page','privacy_page'],
    ];
    foreach ($allowed[$tab] ?? [] as $key) {
        set_setting($key, trim((string) ($_POST[$key] ?? '')));
    }
    Audit::log(Auth::id(), 'update_settings', 'settings', null, null, null, $tab);
    flash('success', 'Settings saved.');
    redirect('admin/settings.php?tab=' . $tab);
}

$pageTitle = 'System Settings';
$active = 'settings';
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
    <div class="card !p-2">
        <nav class="space-y-1">
            <?php foreach ($tabs as $key => [$label, $icon]): ?>
            <a href="?tab=<?= $key ?>"
               class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium <?= $tab === $key ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50' ?>">
                <i data-lucide="<?= $icon ?>" class="w-4 h-4"></i> <?= e($label) ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="xl:col-span-3 card">
        <h3 class="font-semibold text-slate-800 mb-6"><?= e($tabs[$tab][0]) ?></h3>
        <form method="post" class="grid grid-cols-2 gap-4 max-w-2xl">
            <?= Csrf::field() ?>
            <?php if ($tab === 'company'): ?>
            <div class="col-span-2"><label class="label">Company Name</label>
                <input name="company_name" class="input" value="<?= e(setting('company_name')) ?>"></div>
            <div><label class="label">Email</label>
                <input name="company_email" type="email" class="input" value="<?= e(setting('company_email')) ?>"></div>
            <div><label class="label">Phone</label>
                <input name="company_phone" class="input" value="<?= e(setting('company_phone')) ?>"></div>
            <div class="col-span-2"><label class="label">Address</label>
                <textarea name="company_address" rows="2" class="input"><?= e(setting('company_address')) ?></textarea></div>

            <?php elseif ($tab === 'booking'): ?>
            <div><label class="label">Minimum hire period (hours)</label>
                <input name="booking_min_hours" type="number" min="1" class="input" value="<?= e(setting('booking_min_hours','24')) ?>"></div>
            <div><label class="label">Cancellation fee</label>
                <input name="booking_cancellation_fee" type="number" step="0.01" min="0" class="input" value="<?= e(setting('booking_cancellation_fee','0')) ?>"></div>

            <?php elseif ($tab === 'pricing'): ?>
            <div><label class="label">Currency code</label>
                <input name="currency" class="input" value="<?= e(setting('currency','USD')) ?>"></div>
            <div><label class="label">Currency symbol</label>
                <input name="currency_symbol" class="input" value="<?= e(setting('currency_symbol','$')) ?>"></div>
            <div><label class="label">Tax rate (%)</label>
                <input name="tax_rate" type="number" step="0.01" min="0" class="input" value="<?= e(setting('tax_rate','0')) ?>"></div>
            <div><label class="label">Default deposit</label>
                <input name="default_deposit" type="number" step="0.01" min="0" class="input" value="<?= e(setting('default_deposit','200')) ?>"></div>

            <?php elseif ($tab === 'payments'): ?>
            <div><label class="label">Paynow Integration ID</label>
                <input name="paynow_integration_id" class="input" value="<?= e(setting('paynow_integration_id','')) ?>"
                       placeholder="Set in config.php for security"></div>
            <div class="flex items-end pb-1">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="paynow_test_mode" value="1" <?= setting('paynow_test_mode','1') === '1' ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600">
                    Test mode
                </label></div>
            <p class="col-span-2 text-xs text-slate-400">Keep the integration key in <code>config/config.php</code> — it never belongs in the database or public files.</p>

            <?php elseif ($tab === 'documents'): ?>
            <div class="col-span-2"><label class="label">Terms & Conditions page content</label>
                <textarea name="terms_page" rows="5" class="input"><?= e(setting('terms_page')) ?></textarea></div>
            <div class="col-span-2"><label class="label">Privacy Policy page content</label>
                <textarea name="privacy_page" rows="5" class="input"><?= e(setting('privacy_page')) ?></textarea></div>
            <p class="col-span-2 text-xs text-slate-400">Contract/printable versions are managed under Documents → Templates.</p>
            <?php endif; ?>
            <div class="col-span-2">
                <button class="btn-primary"><i data-lucide="save" class="w-4 h-4"></i> Save Settings</button>
            </div>
        </form>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
