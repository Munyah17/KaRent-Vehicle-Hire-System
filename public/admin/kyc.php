<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;
use App\Notify;

Auth::requirePermission('kyc');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $id = (int) $_POST['client_id'];
    $status = $_POST['kyc_status'];
    $client = Database::one('SELECT * FROM clients WHERE id = ?', [$id]);
    if ($client && in_array($status, ['verified','rejected','under_review','pending'], true)) {
        Database::run('UPDATE clients SET kyc_status = ? WHERE id = ?', [$status, $id]);
        Audit::log(Auth::id(), 'kyc_' . $status, 'clients', 'client', $id, $client['kyc_status'], $status);
        if ($client['user_id']) {
            Notify::send((int) $client['user_id'], 'kyc',
                'KYC ' . ucwords(str_replace('_', ' ', $status)),
                'Your verification status was updated to ' . $status . '.', 'client/kyc.php');
        }
        flash('success', 'KYC updated for ' . $client['full_name'] . '.');
    }
    redirect('admin/kyc.php');
}

$queue = Database::all(
    "SELECT c.*, (SELECT COUNT(*) FROM client_documents d WHERE d.client_id=c.id) docs,
        (SELECT COUNT(*) FROM client_documents d WHERE d.client_id=c.id AND d.status='verified') verified_docs
     FROM clients c WHERE c.kyc_status IN ('pending','under_review')
     ORDER BY c.kyc_status = 'under_review' DESC, c.id");

$pageTitle = 'KYC Review';
$active = 'kyc';
require APP_PATH . '/views/admin/header.php';
?>
<div class="card !p-0 overflow-x-auto">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-slate-800">Pending Verifications</h3>
        <p class="text-sm text-slate-500">Review documents, then approve or reject each client.</p>
    </div>
    <table class="w-full">
        <thead><tr>
            <th class="th">Client</th><th class="th">National ID</th><th class="th">Licence</th>
            <th class="th">Documents</th><th class="th">Status</th><th class="th">Registered</th><th class="th"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($queue as $c): ?>
        <tr class="table-row">
            <td class="td">
                <p class="font-medium text-slate-800"><?= e($c['full_name']) ?></p>
                <p class="text-xs text-slate-400"><?= e($c['client_no']) ?> · <?= e($c['phone']) ?></p>
            </td>
            <td class="td"><?= e($c['national_id'] ?? '—') ?></td>
            <td class="td"><?= e($c['licence_no'] ?? '—') ?><?= $c['licence_expiry'] ? '<br><span class="text-xs text-slate-400">exp ' . e(fmt_date($c['licence_expiry'])) . '</span>' : '' ?></td>
            <td class="td"><?= (int) $c['verified_docs'] ?>/<?= (int) $c['docs'] ?> verified</td>
            <td class="td"><?= status_badge($c['kyc_status']) ?></td>
            <td class="td"><?= e(fmt_date($c['created_at'])) ?></td>
            <td class="td text-right">
                <div class="flex items-center justify-end gap-2">
                    <a href="<?= url('admin/client-edit.php?id=' . $c['id']) ?>" class="btn-secondary !py-1.5 !px-3 text-xs">Review docs</a>
                    <form method="post"><?= Csrf::field() ?>
                        <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                        <button name="kyc_status" value="verified" class="btn-primary !py-1.5 !px-3 text-xs">Approve</button>
                        <button name="kyc_status" value="rejected" class="btn-danger !py-1.5 !px-3 text-xs">Reject</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$queue): ?>
        <tr><td colspan="7" class="td text-center py-10 text-slate-400">No clients pending verification.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
