<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Auth;
use App\Csrf;
use App\Database;
use App\Audit;

Auth::requireLogin();
$user = Auth::user();
$forced = !empty($user['must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $current = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!password_verify($current, $user['password_hash'])) {
        flash('error', 'Current password is incorrect.');
    } elseif (strlen($new) < 8) {
        flash('error', 'New password must be at least 8 characters.');
    } elseif ($new !== $confirm) {
        flash('error', 'Passwords do not match.');
    } else {
        Database::run(
            'UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?',
            [Auth::hashPassword($new), $user['id']]
        );
        Audit::log((int) $user['id'], 'change_password', 'auth', 'user', (int) $user['id']);
        flash('success', 'Password updated.');
        redirect(Auth::isAdmin() ? 'admin/index.php' : 'client/index.php');
    }
}

$pageTitle = 'Change Password';
require APP_PATH . '/views/site/header.php';
?>
<div class="min-h-[70vh] flex items-center justify-center px-6 py-16 bg-gray-50">
    <div class="w-full max-w-md">
        <div class="card !p-8">
            <h1 class="text-xl font-semibold text-slate-800 mb-1">Change password</h1>
            <?php if ($forced): ?>
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-3">
                For security you must set a new password before continuing.</p>
            <?php else: ?>
            <p class="text-sm text-slate-500 mb-4">Update your account password.</p>
            <?php endif; ?>
            <form method="post" class="space-y-4 mt-4">
                <?= Csrf::field() ?>
                <div><label class="label">Current password</label>
                    <input type="password" name="current" required class="input"></div>
                <div><label class="label">New password (min 8 chars)</label>
                    <input type="password" name="new" required minlength="8" class="input"></div>
                <div><label class="label">Confirm new password</label>
                    <input type="password" name="confirm" required class="input"></div>
                <button class="btn-primary w-full justify-center !py-2.5">Update Password</button>
            </form>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
