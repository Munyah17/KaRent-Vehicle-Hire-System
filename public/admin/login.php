<?php
/**
 * Staff-only sign in (CoolAdmin auth layout).
 * Clients must use /login.php — accounts are separated by portal.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Csrf;

if (Auth::check()) {
    redirect(Auth::isAdmin() ? 'admin/index.php' : 'client/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    [$ok, $err] = Auth::attempt($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($ok && Auth::isAdmin()) {
        redirect(!empty(Auth::user()['must_change_password']) ? 'change-password.php' : 'admin/index.php');
    }
    if ($ok) {
        Auth::logout(); // a client account used the staff portal — do not create a session
        flash('error', 'That account is a client account. Please sign in at the client portal.');
    } else {
        flash('error', $err);
    }
    redirect('admin/login.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Sign In — <?= e(config('app_name')) ?></title>
<script>try{if((localStorage.getItem('karent.theme')||(document.cookie.match(/theme=(dark)/)||[])[1])==='dark'){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-bs-theme','dark')}}catch(e){}</script>
<link href="<?= asset('assets/admin/css/font-face.css') ?>" rel="stylesheet">
<link href="<?= asset('assets/admin/vendor/bootstrap-5.3.8.min.css') ?>" rel="stylesheet">
<link href="<?= asset('assets/admin/css/theme.css') ?>" rel="stylesheet">
<link href="<?= asset('assets/admin/css/app.css') ?>" rel="stylesheet">
</head>
<body class="app auth-page">
<main class="login-wrap">
    <div class="login-content">
        <a href="<?= url('index.php') ?>" class="auth-brand">
            <span class="logo-mark">K</span>
            <span class="logo-text"><?= e(config('app_name')) ?></span>
        </a>
        <h1 class="auth-title">Staff sign in</h1>
        <p class="auth-subtitle">Back-office access — clients should use the
            <a href="<?= url('login.php') ?>">client sign-in page</a>.</p>

        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger" style="font-size:.85rem"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success" style="font-size:.85rem"><?= e($msg) ?></div>
        <?php endif; ?>

        <form class="login-form" method="post">
            <?= Csrf::field() ?>
            <div class="form-group">
                <label for="email">Email address</label>
                <input id="email" class="au-input" type="email" name="email" placeholder="staff@company.com" autocomplete="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" class="au-input" type="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="au-btn au-btn--green" type="submit">Sign in</button>
            <div class="register-link" style="margin-top:14px">
                <p><a href="<?= url('index.php') ?>">← Back to the website</a></p>
            </div>
        </form>

        <?php if (config('demo_mode')): ?>
        <div class="alert alert-info mt-3" style="font-size:.78rem">
            <strong>Demo staff:</strong> admin@demo.test / Admin@123 · staff@demo.test / Staff@123
        </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
