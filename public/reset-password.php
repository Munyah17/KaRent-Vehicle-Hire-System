<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Csrf;
use App\Database;
use App\Auth;

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$reset = $token ? Database::one(
    'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
     ORDER BY id DESC LIMIT 1',
    [hash('sha256', $token)]
) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    if (!$reset) {
        flash('error', 'Reset link is invalid or expired.');
        redirect('forgot-password.php');
    }
    $new = $_POST['new'] ?? '';
    if (strlen($new) < 8 || $new !== ($_POST['confirm'] ?? '')) {
        flash('error', 'Passwords must match and be at least 8 characters.');
        redirect('reset-password.php?token=' . urlencode($token));
    }
    Database::run('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?',
        [Auth::hashPassword($new), $reset['user_id']]);
    Database::run('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$reset['id']]);
    flash('success', 'Password updated — please sign in.');
    redirect('login.php');
}

$pageTitle = 'Set New Password';
require APP_PATH . '/views/site/header.php';
?>
<div class="min-h-[60vh] flex items-center justify-center px-6 py-16 bg-gray-50">
    <div class="w-full max-w-md">
        <div class="card !p-8">
            <?php if (!$reset): ?>
            <h1 class="text-xl font-semibold text-slate-800 mb-2">Link expired</h1>
            <p class="text-sm text-slate-500">This reset link is invalid or has expired.</p>
            <a href="<?= url('forgot-password.php') ?>" class="btn-primary w-full justify-center mt-5">Request a new link</a>
            <?php else: ?>
            <h1 class="text-xl font-semibold text-slate-800 mb-5">Choose a new password</h1>
            <form method="post" class="space-y-4">
                <?= Csrf::field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div><label class="label">New password (min 8 chars)</label>
                    <input type="password" name="new" required minlength="8" class="input"></div>
                <div><label class="label">Confirm password</label>
                    <input type="password" name="confirm" required class="input"></div>
                <button class="btn-primary w-full justify-center !py-2.5">Update Password</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
