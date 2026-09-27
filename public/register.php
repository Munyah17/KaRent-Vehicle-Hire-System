<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Auth;
use App\Csrf;
use App\Services\ClientService;

if (Auth::check()) {
    redirect(Auth::isAdmin() ? 'admin/index.php' : 'client/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($password !== $confirm) {
        flash('error', 'Passwords do not match.');
        remember_old($_POST);
        redirect('register.php');
    }
    [$clientId, $err] = ClientService::register($name, $email, $phone, $password);
    if ($err) {
        flash('error', $err);
        remember_old($_POST);
        redirect('register.php');
    }
    clear_old();
    Auth::attempt($email, $password);
    flash('success', 'Welcome! Your account is ready.');
    redirect('client/index.php');
}

$pageTitle = 'Create Account';
require APP_PATH . '/views/site/header.php';
?>
<div class="min-h-[70vh] flex items-center justify-center px-6 py-16 bg-gray-50">
    <div class="w-full max-w-md">
        <div class="card !p-8">
            <div class="text-center mb-6">
                <span class="inline-flex w-12 h-12 rounded-xl bg-blue-600 text-white items-center justify-center mb-3">
                    <i data-lucide="user-plus" class="w-6 h-6"></i>
                </span>
                <h1 class="text-xl font-semibold text-slate-800">Create your account</h1>
                <p class="text-sm text-slate-500">Book vehicles faster with an account</p>
            </div>
            <form method="post" class="space-y-4">
                <?= Csrf::field() ?>
                <div><label class="label">Full name</label>
                    <input name="name" required class="input" value="<?= old('name') ?>"></div>
                <div><label class="label">Email</label>
                    <input type="email" name="email" required class="input" value="<?= old('email') ?>"></div>
                <div><label class="label">Phone</label>
                    <input name="phone" required class="input" value="<?= old('phone') ?>"></div>
                <div><label class="label">Password (min 8 chars)</label>
                    <input type="password" name="password" required minlength="8" class="input"></div>
                <div><label class="label">Confirm password</label>
                    <input type="password" name="confirm" required class="input"></div>
                <button class="btn-primary w-full justify-center !py-2.5">Create Account</button>
            </form>
            <p class="text-center text-sm mt-4 text-slate-500">Already registered?
                <a href="<?= url('login.php') ?>" class="text-blue-600 hover:underline">Sign in</a></p>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
