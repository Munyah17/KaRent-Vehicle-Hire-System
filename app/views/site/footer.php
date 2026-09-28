<footer class="bg-[#1e3a8a] dark-footer text-blue-200 mt-16">
    <div class="max-w-7xl mx-auto px-6 py-8 grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8 text-sm">
        <div>
            <div class="flex items-center gap-2 text-white font-semibold mb-3">
                <i data-lucide="car" class="w-5 h-5"></i> <?= e(setting('company_name', 'Vehicle Hire')) ?>
            </div>
            <p class="text-blue-300">Reliable vehicle hire — easy bookings, safe journeys, complete control.</p>
        </div>
        <div>
            <p class="text-white font-medium mb-3">Company</p>
            <ul class="space-y-2">
                <li><a href="<?= url('about.php') ?>" class="hover:text-white">About us</a></li>
                <li><a href="<?= url('contact.php') ?>" class="hover:text-white">Contact</a></li>
            </ul>
        </div>
        <div>
            <p class="text-white font-medium mb-3">Legal</p>
            <ul class="space-y-2">
                <li><a href="<?= url('terms.php') ?>" class="hover:text-white">Terms & Conditions</a></li>
                <li><a href="<?= url('privacy.php') ?>" class="hover:text-white">Privacy Policy</a></li>
            </ul>
        </div>
        <div>
            <p class="text-white font-medium mb-3">Contact</p>
            <ul class="space-y-2">
                <li><?= e(setting('company_phone', '')) ?></li>
                <li><?= e(setting('company_email', '')) ?></li>
                <li><?= e(setting('company_address', '')) ?></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-blue-800 py-4 text-center text-xs text-blue-300">
        &copy; <?= date('Y') ?> <?= e(setting('company_name', 'Vehicle Hire')) ?>. All rights reserved.
    </div>
</footer>
<script>
window.addEventListener('DOMContentLoaded', () => { lucide.createIcons(); });
</script>
</body>
</html>
