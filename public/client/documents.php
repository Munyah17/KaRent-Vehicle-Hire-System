<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requireClient();
$client = Auth::client();

$contracts = Database::all(
    "SELECT ct.*, b.ref booking_ref FROM contracts ct JOIN bookings b ON b.id=ct.booking_id
     WHERE ct.client_id = ? AND ct.status != 'void' ORDER BY ct.id DESC", [$client['id']]);

$pageTitle = 'My Documents';
$active = 'documents';
require APP_PATH . '/views/client/header.php';
?>
<div class="card !p-0 overflow-hidden max-w-4xl">
    <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-slate-800">Contracts & Documents</h2></div>
    <ul class="divide-y divide-gray-100">
        <?php foreach ($contracts as $c): ?>
        <li class="flex items-center justify-between px-6 py-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i data-lucide="file-text" class="w-5 h-5"></i></span>
                <div>
                    <p class="text-sm font-medium text-slate-800"><?= e($c['title']) ?> <span class="text-xs text-slate-400">v<?= $c['template_version'] ?></span></p>
                    <p class="text-xs text-slate-400"><?= e($c['booking_ref']) ?> · <?= e(fmt_datetime($c['created_at'])) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <?= status_badge($c['status']) ?>
                <a href="<?= url('client/document.php?id=' . $c['id']) ?>" target="_blank" class="btn-secondary !py-1.5 !px-3 text-xs">View / Print</a>
            </div>
        </li>
        <?php endforeach; ?>
        <?php if (!$contracts): ?>
        <li class="px-6 py-10 text-center text-slate-400 text-sm">No documents yet — they'll appear here after a booking is confirmed.</li>
        <?php endif; ?>
    </ul>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
