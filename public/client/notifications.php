<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;

Auth::requireClient();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    Database::run("UPDATE notifications SET status='read' WHERE user_id=? AND status='unread'", [Auth::id()]);
    redirect('client/notifications.php');
}
$notifs = Database::all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50', [Auth::id()]);

$pageTitle = 'Notifications';
$active = '';
require APP_PATH . '/views/client/header.php';
?>
<div class="card !p-0 overflow-x-auto max-w-3xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-slate-800">Notifications</h2>
        <form method="post"><?= Csrf::field() ?><button class="btn-secondary !py-1.5 text-xs">Mark all read</button></form>
    </div>
    <ul class="divide-y divide-gray-100">
        <?php foreach ($notifs as $n): ?>
        <li class="flex items-start gap-4 px-6 py-4 <?= $n['status'] === 'unread' ? 'bg-blue-50/40' : '' ?>">
            <span class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"><i data-lucide="bell" class="w-4 h-4"></i></span>
            <div class="flex-1">
                <p class="text-sm font-medium text-slate-800"><?= e($n['title']) ?></p>
                <p class="text-sm text-slate-500"><?= e($n['body'] ?? '') ?></p>
                <p class="text-xs text-slate-400 mt-1"><?= e(fmt_datetime($n['created_at'])) ?></p>
            </div>
        </li>
        <?php endforeach; ?>
        <?php if (!$notifs): ?><li class="px-6 py-10 text-center text-slate-400 text-sm">No notifications.</li><?php endif; ?>
    </ul>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
