<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
$pageTitle = 'Terms & Conditions';
require APP_PATH . '/views/site/header.php';
?>
<div class="max-w-3xl mx-auto px-6 py-16">
    <h1 class="text-3xl font-semibold text-slate-800 mb-6">Terms & Conditions</h1>
    <div class="text-slate-600 leading-relaxed whitespace-pre-line"><?= nl2br(e(setting('terms_page', 'Standard terms apply.'))) ?></div>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
