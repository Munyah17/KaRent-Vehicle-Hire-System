<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
$pageTitle = 'About Us';
$navActive = 'about';
require APP_PATH . '/views/site/header.php';
?>
<div class="max-w-4xl mx-auto px-6 py-16">
    <h1 class="text-3xl font-semibold text-slate-800 mb-6">About <?= e(setting('company_name')) ?></h1>
    <div class="prose prose-slate max-w-none text-slate-600 leading-relaxed space-y-4">
        <p>We provide reliable, well-maintained vehicles for hire — from economical city cars
        to rugged SUVs and double-cab pickups. Every vehicle is inspected before handover so
        you can travel with confidence.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 my-8">
            <div class="bg-blue-50 rounded-xl p-5"><i data-lucide="shield-check" class="w-6 h-6 text-blue-600 mb-2"></i>
                <p class="font-semibold text-slate-800">Safe journeys</p>
                <p class="text-sm">Regular maintenance and pre-hire inspections on every vehicle.</p></div>
            <div class="bg-blue-50 rounded-xl p-5"><i data-lucide="banknote" class="w-6 h-6 text-blue-600 mb-2"></i>
                <p class="font-semibold text-slate-800">Transparent pricing</p>
                <p class="text-sm">Clear daily, weekly and monthly rates — no hidden fees.</p></div>
            <div class="bg-blue-50 rounded-xl p-5"><i data-lucide="headphones" class="w-6 h-6 text-blue-600 mb-2"></i>
                <p class="font-semibold text-slate-800">Real support</p>
                <p class="text-sm">Talk to our team for booking help, extensions and roadside queries.</p></div>
        </div>
        <p>Book online in minutes, manage your hire through your client account, or visit us
        in person — walk-in customers are welcome.</p>
    </div>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
