<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
$pageTitle = 'Privacy Policy';
require APP_PATH . '/views/site/header.php';
?>
<div class="max-w-3xl mx-auto px-6 py-16">
    <h1 class="text-3xl font-semibold text-slate-800 mb-6">Privacy Policy</h1>
    <div class="text-slate-600 leading-relaxed whitespace-pre-line"><?= nl2br(e(setting('privacy_page', 'We respect your privacy.'))) ?></div>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
