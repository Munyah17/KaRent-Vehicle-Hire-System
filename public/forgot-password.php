<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Csrf;
use App\Database;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $user = Database::one('SELECT * FROM users WHERE email = ?', [$email]);
    // Always show the same message — don't leak account existence.
    if ($user) {
        $token = bin2hex(random_bytes(32));
        Database::run(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?,?,NOW() + INTERVAL 1 HOUR)',
            [$user['id'], hash('sha256', $token)]
        );
        // In production: email the link. In dev/demo we show it on screen.
        if (config('demo_mode')) {
            flash('success', 'Reset link (demo): ' . url('reset-password.php?token=' . $token));
        }
    }
    flash('info', 'If that email is registered, a reset link has been sent.');
    redirect('forgot-password.php');
}

$pageTitle = 'Forgot Password';
require APP_PATH . '/views/site/header.php';
?>
<div class="min-h-[60vh] flex items-center justify-center px-6 py-16 bg-gray-50">
    <div class="w-full max-w-md">
        <div class="card !p-8">
            <h1 class="text-xl font-semibold text-slate-800 mb-2">Reset your password</h1>
            <p class="text-sm text-slate-500 mb-5">Enter your account email and we'll send a reset link.</p>
            <?php if ($m = flash('info')): ?>
            <div class="mb-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 text-sm"><?= e($m) ?></div>
            <?php endif; ?>
            <form method="post" class="space-y-4">
                <?= Csrf::field() ?>
                <div><label class="label">Email</label>
                    <input type="email" name="email" required class="input"></div>
                <button class="btn-primary w-full justify-center !py-2.5">Send Reset Link</button>
            </form>
            <p class="text-center text-sm mt-4"><a href="<?= url('login.php') ?>" class="text-blue-600 hover:underline">Back to sign in</a></p>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
