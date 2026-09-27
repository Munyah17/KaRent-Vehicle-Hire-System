<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Services\ContractService;

Auth::requirePermission('contracts');

$id = (int) ($_GET['id'] ?? 0);
$contract = Database::one('SELECT * FROM contracts WHERE id = ?', [$id]);
if (!$contract) { http_response_code(404); exit('Document not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    if ($_POST['action'] === 'sign') {
        [$ok, $err] = ContractService::sign(
            $id,
            trim($_POST['signer_name'] ?? ''),
            $_POST['signature_data'] ?? '',
            Auth::id()
        );
        flash($ok ? 'success' : 'error', $ok ? 'Signature recorded.' : $err);
    } elseif ($_POST['action'] === 'void' && $contract['status'] !== 'signed') {
        Database::run("UPDATE contracts SET status='void' WHERE id=?", [$id]);
        flash('success', 'Document voided.');
    }
    redirect('admin/contract.php?id=' . $id);
}

$sig = Database::one('SELECT * FROM signatures WHERE contract_id = ? ORDER BY id DESC', [$id]);

// Print view: standalone minimal page.
if (isset($_GET['print'])): ?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title><?= e($contract['title']) ?></title>
<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; color: #1e293b; line-height: 1.6; }
    h2 { font-size: 18px; } h3 { font-size: 15px; } ol { padding-left: 20px; }
    .meta { color: #64748b; font-size: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 20px; }
    .sig { margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 12px; font-size: 13px; }
    img.sig-img { max-height: 80px; }
    @media print { .noprint { display: none; } }
</style></head>
<body>
<div class="noprint" style="margin-bottom:16px"><button onclick="window.print()">Print / Save as PDF</button></div>
<div class="meta">
    <?= e(setting('company_name')) ?> · <?= e($contract['title']) ?> · v<?= (int) $contract['template_version'] ?> ·
    Generated <?= e(fmt_datetime($contract['created_at'])) ?> · <?= e(ucfirst($contract['status'])) ?>
</div>
<?= $contract['body'] /* trusted: rendered from admin-edited templates with escaped data */ ?>
<?php if ($sig): ?>
<div class="sig">
    <strong>Signed by:</strong> <?= e($sig['signer_name']) ?> on <?= e(fmt_datetime($sig['signed_at'])) ?>
    <?php if (str_starts_with((string) $sig['signature_data'], 'data:image')): ?>
    <br><img class="sig-img" src="<?= e($sig['signature_data']) ?>" alt="signature">
    <?php endif; ?>
</div>
<?php endif; ?>
</body></html>
<?php exit; endif;

$pageTitle = $contract['title'];
$active = 'contracts';
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-10 max-w-3xl">
            <div class="text-xs text-slate-400 border-b border-gray-100 pb-3 mb-6 flex items-center justify-between">
                <span><?= e(setting('company_name')) ?> · Template v<?= (int) $contract['template_version'] ?></span>
                <?= status_badge($contract['status']) ?>
            </div>
            <div class="prose prose-sm max-w-none text-slate-700">
                <?= $contract['body'] ?>
            </div>
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

    <div class="space-y-6">
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Actions</h3>
            <a href="?id=<?= $id ?>&print=1" target="_blank" class="btn-secondary w-full justify-center mb-3">
                <i data-lucide="printer" class="w-4 h-4"></i> Print / Save as PDF</a>
            <?php if ($contract['status'] === 'generated'): ?>
            <form method="post" onsubmit="return confirm('Void this document?')"><?= Csrf::field() ?>
                <input type="hidden" name="action" value="void">
                <button class="btn-secondary w-full justify-center text-red-600">Void Document</button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($contract['status'] !== 'signed'): ?>
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Sign / Acknowledge</h3>
            <form method="post" id="signform">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="sign">
                <input type="hidden" name="signature_data" id="sigdata">
                <div class="mb-3"><label class="label">Signer name</label>
                    <input name="signer_name" class="input" required placeholder="Full name"></div>
                <label class="label">Signature (draw below)</label>
                <canvas id="pad" width="320" height="140" class="border border-gray-300 rounded-lg w-full bg-white cursor-crosshair"></canvas>
                <div class="flex gap-2 mt-3">
                    <button type="button" class="btn-secondary !py-1.5 text-xs" onclick="clearPad()">Clear</button>
                    <button class="btn-primary !py-1.5 text-xs flex-1 justify-center">Record Signature</button>
                </div>
            </form>
        </div>
        <script>
        const pad = document.getElementById('pad');
        const ctx = pad.getContext('2d');
        let drawing = false;
        pad.addEventListener('mousedown', e => { drawing = true; ctx.beginPath(); ctx.moveTo(e.offsetX, e.offsetY); });
        pad.addEventListener('mousemove', e => { if (drawing) { ctx.lineTo(e.offsetX, e.offsetY); ctx.stroke(); } });
        ['mouseup','mouseleave'].forEach(ev => pad.addEventListener(ev, () => drawing = false));
        pad.addEventListener('touchstart', e => { e.preventDefault(); const t = e.touches[0], r = pad.getBoundingClientRect(); drawing = true; ctx.beginPath(); ctx.moveTo(t.clientX - r.left, t.clientY - r.top); });
        pad.addEventListener('touchmove', e => { e.preventDefault(); if (!drawing) return; const t = e.touches[0], r = pad.getBoundingClientRect(); ctx.lineTo(t.clientX - r.left, t.clientY - r.top); ctx.stroke(); });
        pad.addEventListener('touchend', () => drawing = false);
        function clearPad() { ctx.clearRect(0, 0, pad.width, pad.height); }
        document.getElementById('signform').addEventListener('submit', e => {
            document.getElementById('sigdata').value = pad.toDataURL('image/png');
        });
        </script>
        <?php endif; ?>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
