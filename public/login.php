<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Auth;
use App\Csrf;

if (Auth::check()) {
    redirect(Auth::isAdmin() ? 'admin/index.php' : 'client/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    [$ok, $err] = Auth::attempt($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($ok) {
        $user = Auth::user();
        if (!empty($user['must_change_password'])) {
            redirect('change-password.php');
        }
        redirect(Auth::isAdmin() ? 'admin/index.php' : 'client/index.php');
    }
    flash('error', $err);
    redirect('login.php');
}

$pageTitle = 'Sign In';
require APP_PATH . '/views/site/header.php';
?>
<div class="min-h-[70vh] flex items-center justify-center px-6 py-16 bg-gray-50">
    <div class="w-full max-w-md">
        <div class="card !p-8">
            <div class="text-center mb-6">
                <span class="inline-flex w-12 h-12 rounded-xl bg-blue-600 text-white items-center justify-center mb-3">
                    <i data-lucide="car" class="w-6 h-6"></i>
                </span>
                <h1 class="text-xl font-semibold text-slate-800">Welcome back</h1>
                <p class="text-sm text-slate-500">Sign in to your account</p>
            </div>
            <form method="post" class="space-y-4">
                <?= Csrf::field() ?>
                <div><label class="label">Email</label>
                    <input type="email" name="email" required class="input" placeholder="you@example.com"></div>
                <div><label class="label">Password</label>
                    <input type="password" name="password" required class="input"></div>
                <button class="btn-primary w-full justify-center !py-2.5">Sign In</button>
            </form>
            <div class="flex items-center justify-between mt-4 text-sm">
                <a href="<?= url('forgot-password.php') ?>" class="text-blue-600 hover:underline">Forgot password?</a>
                <a href="<?= url('register.php') ?>" class="text-blue-600 hover:underline">Create account</a>
            </div>
        </div>
        <?php if (config('demo_mode')): ?>
        <div class="mt-4 rounded-lg bg-blue-50 border border-blue-100 p-4 text-xs text-blue-800">
            <p class="font-semibold mb-1">Demo accounts</p>
            <p>Admin: admin@demo.test / Admin@123</p>
            <p>Staff: staff@demo.test / Staff@123</p>
            <p>Client: john@demo.test / Client@123</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
