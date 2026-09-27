<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Notify;

Auth::requirePermission('clients');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $id = (int) $_POST['ticket_id'];
    $reply = trim($_POST['reply'] ?? '');
    $status = $_POST['status'] ?? 'in_progress';
    if ($reply !== '') {
        $t = Database::one('SELECT t.*, c.user_id FROM support_tickets t JOIN clients c ON c.id=t.client_id WHERE t.id = ?', [$id]);
        Database::run(
            'UPDATE support_tickets SET staff_reply=?, status=?, replied_by=?, updated_at=NOW() WHERE id=?',
            [$reply, $status, Auth::id(), $id]
        );
        if ($t && $t['user_id']) {
            Notify::send((int) $t['user_id'], 'support', 'Reply to: ' . $t['subject'], $reply, 'client/support.php');
        }
        flash('success', 'Reply sent.');
    }
    redirect('admin/support.php');
}

$tickets = Database::all(
    'SELECT t.*, c.full_name client_name, c.client_no FROM support_tickets t
     JOIN clients c ON c.id=t.client_id ORDER BY t.status="open" DESC, t.id DESC');

$pageTitle = 'Support Requests';
$active = 'clients';
require APP_PATH . '/views/admin/header.php';
?>
<div class="card !p-0 overflow-hidden max-w-5xl">
    <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-slate-800">Client Support Requests</h3></div>
    <ul class="divide-y divide-gray-100">
        <?php foreach ($tickets as $t): ?>
        <li class="px-6 py-5">
            <div class="flex items-center justify-between">
                <p class="font-medium text-slate-800"><?= e($t['subject']) ?></p>
                <?= status_badge($t['status'] === 'open' ? 'pending' : ($t['status'] === 'resolved' ? 'completed' : $t['status'])) ?>
            </div>
            <p class="text-sm text-slate-600 mt-1"><?= e($t['message']) ?></p>
            <p class="text-xs text-slate-400 mt-1"><?= e($t['client_name']) ?> (<?= e($t['client_no']) ?>) · <?= e(fmt_datetime($t['created_at'])) ?></p>
            <?php if ($t['staff_reply']): ?>
            <p class="text-sm mt-2 rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-blue-800"><strong>Reply:</strong> <?= e($t['staff_reply']) ?></p>
            <?php endif; ?>
            <form method="post" class="mt-3 flex flex-wrap gap-2">
                <?= Csrf::field() ?>
                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                <input name="reply" placeholder="Write a reply..." class="input !py-1.5 flex-1 min-w-56">
                <select name="status" class="input !py-1.5 w-36">
                    <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $t['status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-primary !py-1.5">Reply</button>
            </form>
        </li>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><li class="px-6 py-10 text-center text-slate-400 text-sm">No support requests.</li><?php endif; ?>
    </ul>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
