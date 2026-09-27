<?php
/** Client-facing document view (print-friendly). Clients see only their own docs. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requireClient();
$client = Auth::client();
$id = (int) ($_GET['id'] ?? 0);
$contract = Database::one('SELECT * FROM contracts WHERE id = ?', [$id]);
if (!$contract || (int) $contract['client_id'] !== (int) $client['id']) {
    http_response_code(404);
    exit('Document not found.');
}
$sig = Database::one('SELECT * FROM signatures WHERE contract_id = ? ORDER BY id DESC', [$id]);
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title><?= e($contract['title']) ?></title>
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<style>@media print { .noprint { display:none; } }</style></head>
<body class="bg-gray-100 py-10">
<div class="max-w-3xl mx-auto">
    <div class="noprint mb-4 flex justify-end gap-3">
        <button onclick="window.print()" class="btn-primary">Print / Save as PDF</button>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-10">
        <div class="text-xs text-slate-400 border-b border-gray-100 pb-3 mb-6 flex justify-between">
            <span><?= e(setting('company_name')) ?> · <?= e($contract['title']) ?> · v<?= (int) $contract['template_version'] ?></span>
            <span><?= e(ucfirst($contract['status'])) ?></span>
        </div>
        <div class="text-slate-700 text-sm leading-relaxed"><?= $contract['body'] ?></div>
        <?php if ($sig): ?>
        <div class="mt-10 border-t border-gray-100 pt-4 text-sm">
            <p class="font-semibold">Signed by: <?= e($sig['signer_name']) ?></p>
            <p class="text-xs text-slate-400"><?= e(fmt_datetime($sig['signed_at'])) ?></p>
            <?php if (str_starts_with((string) $sig['signature_data'], 'data:image')): ?>
            <img src="<?= e($sig['signature_data']) ?>" class="mt-2 h-16" alt="signature">
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body></html>
