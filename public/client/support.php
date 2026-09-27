<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Notify;

Auth::requireClient();
$client = Auth::client();
$cid = (int) $client['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($subject && $message) {
        Database::run(
            'INSERT INTO support_tickets (client_id, subject, message) VALUES (?,?,?)',
            [$cid, $subject, $message]
        );
        Notify::staff('support', 'New support request', $client['full_name'] . ': ' . $subject, 'admin/support.php');
        flash('success', 'Request submitted — we\'ll get back to you soon.');
    } else {
        flash('error', 'Subject and message are required.');
    }
    redirect('client/support.php');
}

$tickets = Database::all('SELECT * FROM support_tickets WHERE client_id = ? ORDER BY id DESC', [$cid]);

$pageTitle = 'Support';
$active = 'support';
require APP_PATH . '/views/client/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="card">
        <h2 class="font-semibold text-slate-800 mb-4">Raise a request</h2>
        <form method="post" class="space-y-4">
            <?= Csrf::field() ?>
            <div><label class="label">Subject</label>
                <input name="subject" class="input" required></div>
            <div><label class="label">How can we help?</label>
                <textarea name="message" rows="5" class="input" required></textarea></div>
            <button class="btn-primary w-full justify-center"><i data-lucide="send" class="w-4 h-4"></i> Submit Request</button>
        </form>
        <div class="mt-6 text-sm text-slate-500 space-y-1">
            <p class="font-medium text-slate-700">Prefer to reach us directly?</p>
            <p><?= e(setting('company_phone')) ?></p>
            <p><?= e(setting('company_email')) ?></p>
        </div>
    </div>
    <div class="card !p-0 overflow-x-auto">
        <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-slate-800">My Requests</h3></div>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($tickets as $t): ?>
            <li class="px-6 py-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-800"><?= e($t['subject']) ?></p>
                    <?= status_badge($t['status'] === 'open' ? 'pending' : ($t['status'] === 'resolved' ? 'completed' : $t['status'])) ?>
                </div>
                <p class="text-sm text-slate-500 mt-1"><?= e($t['message']) ?></p>
                <?php if ($t['staff_reply']): ?>
                <p class="text-sm mt-2 rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-blue-800">
                    <strong>Reply:</strong> <?= e($t['staff_reply']) ?></p>
                <?php endif; ?>
                <p class="text-xs text-slate-400 mt-2"><?= e(fmt_datetime($t['created_at'])) ?></p>
            </li>
            <?php endforeach; ?>
            <?php if (!$tickets): ?><li class="px-6 py-10 text-center text-slate-400 text-sm">No requests yet.</li><?php endif; ?>
        </ul>
    </div>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
