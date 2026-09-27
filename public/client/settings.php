<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;

Auth::requireClient();
$client = Auth::client();
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    if ($_POST['action'] === 'password') {
        $current = $_POST['current'] ?? '';
        $new = $_POST['new'] ?? '';
        if (!password_verify($current, $user['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8 || $new !== ($_POST['confirm'] ?? '')) {
            flash('error', 'New passwords must match and be at least 8 characters.');
        } else {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?',
                [Auth::hashPassword($new), $user['id']]);
            Audit::log((int) $user['id'], 'change_password', 'auth', 'user', (int) $user['id']);
            flash('success', 'Password updated.');
        }
    } elseif ($_POST['action'] === 'contact') {
        Database::run('UPDATE users SET phone = ? WHERE id = ?', [trim($_POST['phone']), $user['id']]);
        Database::run('UPDATE clients SET phone = ? WHERE id = ?', [trim($_POST['phone']), $client['id']]);
        flash('success', 'Contact details updated.');
    }
    redirect('client/settings.php');
}

$pageTitle = 'Account Settings';
$active = 'settings';
require APP_PATH . '/views/client/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 max-w-4xl">
    <div class="card">
        <h2 class="font-semibold text-slate-800 mb-4">Account</h2>
        <dl class="text-sm space-y-2 mb-5">
            <div class="flex justify-between"><dt class="text-slate-500">Email (login)</dt><dd class="font-medium"><?= e($user['email']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Client no.</dt><dd class="font-medium"><?= e($client['client_no']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Member since</dt><dd class="font-medium"><?= e(fmt_date($client['created_at'])) ?></dd></div>
        </dl>
        <form method="post" class="space-y-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="contact">
            <label class="label">Phone</label>
            <input name="phone" class="input" value="<?= e($client['phone']) ?>">
            <button class="btn-secondary">Update contact</button>
        </form>
    </div>
    <div class="card">
        <h2 class="font-semibold text-slate-800 mb-4">Change password</h2>
        <form method="post" class="space-y-4">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="password">
            <div><label class="label">Current password</label>
                <input type="password" name="current" required class="input"></div>
            <div><label class="label">New password (min 8 chars)</label>
                <input type="password" name="new" required minlength="8" class="input"></div>
            <div><label class="label">Confirm new password</label>
                <input type="password" name="confirm" required class="input"></div>
            <button class="btn-primary w-full justify-center">Update Password</button>
        </form>
    </div>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
