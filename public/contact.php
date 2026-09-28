<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Csrf;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    // Store the enquiry as a notification for staff.
    \App\Notify::staff('enquiry', 'Website enquiry: ' . trim($_POST['subject']),
        trim($_POST['name']) . ' (' . trim($_POST['email']) . '): ' . trim($_POST['message']));
    flash('success', 'Thanks — your message has been sent.');
    redirect('contact.php');
}

$pageTitle = 'Contact';
$navActive = 'contact';
require APP_PATH . '/views/site/header.php';
?>
<div class="max-w-5xl mx-auto px-6 py-16">
    <h1 class="text-3xl font-semibold text-slate-800 mb-8">Contact Us</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="space-y-5">
            <div class="card flex items-start gap-4">
                <span class="icon-box bg-blue-50 text-blue-600"><i data-lucide="phone" class="w-5 h-5"></i></span>
                <div><p class="font-medium text-slate-800">Phone</p><p class="text-sm text-slate-500"><?= e(setting('company_phone')) ?></p></div>
            </div>
            <div class="card flex items-start gap-4">
                <span class="icon-box bg-blue-50 text-blue-600"><i data-lucide="mail" class="w-5 h-5"></i></span>
                <div><p class="font-medium text-slate-800">Email</p><p class="text-sm text-slate-500"><?= e(setting('company_email')) ?></p></div>
            </div>
            <div class="card flex items-start gap-4">
                <span class="icon-box bg-blue-50 text-blue-600"><i data-lucide="map-pin" class="w-5 h-5"></i></span>
                <div><p class="font-medium text-slate-800">Address</p><p class="text-sm text-slate-500"><?= e(setting('company_address')) ?></p></div>
            </div>
        </div>
        <div class="card">
            <h2 class="font-semibold text-slate-800 mb-4">Send a message</h2>
            <form method="post" class="space-y-4">
                <?= Csrf::field() ?>
                <div class="grid grid-cols-2 gap-4">
                    <input name="name" placeholder="Your name" required class="input">
                    <input type="email" name="email" placeholder="Email" required class="input">
                </div>
                <input name="subject" placeholder="Subject" required class="input" value="<?= e($_GET['subject'] ?? '') ?>">
                <textarea name="message" rows="5" placeholder="Message" required class="input"></textarea>
                <button class="btn-primary w-full justify-center"><i data-lucide="send" class="w-4 h-4"></i> Send Message</button>
            </form>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/site/footer.php'; ?>
