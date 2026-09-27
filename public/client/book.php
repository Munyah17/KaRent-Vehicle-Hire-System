<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Paynow;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\VehicleService;
use App\Services\WalletService;

Auth::requireClient();
$client = Auth::client();
$cid = (int) $client['id'];

$vehicleId = (int) ($_GET['vehicle'] ?? $_POST['vehicle_id'] ?? 0);
$vehicle = $vehicleId ? VehicleService::find($vehicleId) : null;
if (!$vehicle || !$vehicle['is_public']) { http_response_code(404); exit('Vehicle not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $pickup = trim($_POST['pickup_date'] . ' ' . ($_POST['pickup_time'] ?: '09:00'));
    $return = trim($_POST['return_date'] . ' ' . ($_POST['return_time'] ?: '17:00'));

    [$bookingId, $err] = BookingService::create(
        $cid, $vehicleId, $pickup, $return, 'online', null, 0, trim($_POST['notes'] ?? ''));

    if ($err) {
        flash('error', $err);
        redirect('client/book.php?vehicle=' . $vehicleId);
    }

    // Optional immediate payment.
    $payMethod = $_POST['pay_method'] ?? 'none';
    if ($payMethod === 'wallet') {
        $total = (float) Database::value('SELECT total FROM bookings WHERE id = ?', [$bookingId]);
        PaymentService::record($cid, $bookingId, $total, 'wallet', null, 'rental', null);
        BookingService::setStatus($bookingId, 'confirmed', null, 'Paid via wallet');
        flash('success', 'Booking confirmed — paid from your wallet.');
        redirect('client/booking.php?id=' . $bookingId);
    }
    if ($payMethod === 'paynow') {
        // Create pending payment + initiate Paynow.
        $total = (float) Database::value('SELECT total FROM bookings WHERE id = ?', [$bookingId]);
        $txn = PaymentService::nextTxnId();
        $paynow = new Paynow();
        $res = $paynow->init($txn, $total, $client['email'] ?? '', 'Booking ' . BookingService::refOf($bookingId));
        if ($res['ok']) {
            Database::run(
                "INSERT INTO payments (txn_id, booking_id, client_id, amount, method, purpose, status, poll_url)
                 VALUES (?,?,?,?,'paynow','rental','pending',?)",
                [$txn, $bookingId, $cid, $total, $res['poll_url'] ?? null]
            );
            redirect($res['browser_url']);
        }
        flash('error', 'Paynow initiation failed: ' . ($res['error'] ?? 'unknown'));
    }

    flash('success', 'Booking request submitted — we\'ll confirm once payment is arranged.');
    redirect('client/booking.php?id=' . $bookingId);
}

$pickup = $_GET['pickup'] ?? date('Y-m-d');
$return = $_GET['return'] ?? date('Y-m-d', strtotime('+3 days'));
$quote = VehicleService::quote($vehicle, $pickup . ' 09:00', $return . ' 17:00');
$photo = VehicleService::photo($vehicleId);
$wallet = WalletService::balance($cid);

$pageTitle = 'Book ' . $vehicle['make'] . ' ' . $vehicle['model'];
$active = 'browse';
require APP_PATH . '/views/client/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="card !p-0 overflow-hidden">
        <img src="<?= url($photo) ?>" class="w-full h-64 object-cover">
        <div class="p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800"><?= e($vehicle['make'] . ' ' . $vehicle['model']) ?></h2>
                    <p class="text-sm text-slate-500"><?= e($vehicle['reg_no']) ?> · <?= e($vehicle['year']) ?></p>
                </div>
                <?= status_badge($vehicle['status']) ?>
            </div>
            <div class="grid grid-cols-3 gap-3 text-sm">
                <div class="rounded-lg bg-gray-50 p-3 text-center"><i data-lucide="cog" class="w-4 h-4 mx-auto mb-1 text-slate-500"></i><?= e(ucfirst($vehicle['transmission'])) ?></div>
                <div class="rounded-lg bg-gray-50 p-3 text-center"><i data-lucide="fuel" class="w-4 h-4 mx-auto mb-1 text-slate-500"></i><?= e(ucfirst($vehicle['fuel_type'])) ?></div>
                <div class="rounded-lg bg-gray-50 p-3 text-center"><i data-lucide="users" class="w-4 h-4 mx-auto mb-1 text-slate-500"></i><?= (int) $vehicle['seats'] ?> seats</div>
            </div>
            <p class="text-sm text-slate-500 mt-4"><?= e($vehicle['description']) ?></p>
        </div>
    </div>

    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-5">Booking details</h3>
        <form method="post" class="space-y-4">
            <?= Csrf::field() ?>
            <input type="hidden" name="vehicle_id" value="<?= $vehicleId ?>">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="label">Pickup date</label>
                    <input type="date" name="pickup_date" value="<?= e($pickup) ?>" min="<?= date('Y-m-d') ?>" class="input" required></div>
                <div><label class="label">Pickup time</label>
                    <input type="time" name="pickup_time" value="09:00" class="input"></div>
                <div><label class="label">Return date</label>
                    <input type="date" name="return_date" value="<?= e($return) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="input" required></div>
                <div><label class="label">Return time</label>
                    <input type="time" name="return_time" value="17:00" class="input"></div>
            </div>
            <div class="rounded-lg bg-blue-50 border border-blue-100 p-4 text-sm space-y-1.5">
                <div class="flex justify-between"><span class="text-slate-600">Daily rate</span><span class="font-medium"><?= money($vehicle['daily_rate']) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-600">Security deposit</span><span class="font-medium"><?= money($vehicle['deposit']) ?></span></div>
                <p class="text-xs text-slate-400 pt-1">Final rental is calculated on your exact dates when you submit.</p>
            </div>
            <div><label class="label">Notes (optional)</label>
                <textarea name="notes" rows="2" class="input"></textarea></div>
            <div>
                <label class="label">Payment</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50 cursor-pointer">
                        <input type="radio" name="pay_method" value="none" checked class="text-blue-600">
                        <span class="text-sm">Request now, pay on confirmation</span>
                    </label>
                    <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50 cursor-pointer">
                        <input type="radio" name="pay_method" value="paynow" class="text-blue-600">
                        <span class="text-sm">Pay now via <strong>Paynow</strong> (EcoCash / bank)</span>
                    </label>
                    <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50 cursor-pointer">
                        <input type="radio" name="pay_method" value="wallet" class="text-blue-600" <?= $wallet <= 0 ? 'disabled' : '' ?>>
                        <span class="text-sm">Wallet <span class="text-slate-400">(balance <?= money($wallet) ?>)</span></span>
                    </label>
                </div>
            </div>
            <button class="btn-primary w-full justify-center !py-3 text-base">
                <i data-lucide="calendar-check" class="w-5 h-5"></i> Submit Booking
            </button>
        </form>
    </div>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
