<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Services\BookingService;
use App\Services\ClientService;
use App\Services\PaymentService;
use App\Services\VehicleService;

/**
 * Walk-in / manual booking wizard:
 *  1. Search client  2. Create or select client  3. Vehicle + dates + price
 *  4. Payment & deposit  -> booking created (pending, confirm separately)
 */
Auth::requirePermission('bookings');

$step = (int) ($_GET['step'] ?? 1);
$clientId = (int) ($_GET['client'] ?? 0);
$client = $clientId ? Database::one('SELECT * FROM clients WHERE id = ?', [$clientId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'search') {
        redirect('admin/booking-new.php?step=1&q=' . urlencode(trim($_POST['q'] ?? '')));
    }

    if ($action === 'select_client') {
        redirect('admin/booking-new.php?step=2&client=' . (int) $_POST['client_id']);
    }

    if ($action === 'create_client') {
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'national_id' => trim($_POST['national_id'] ?? ''),
            'dob' => ($_POST['dob'] ?? '') ?: null,
            'address' => trim($_POST['address'] ?? ''),
            'licence_no' => trim($_POST['licence_no'] ?? ''),
            'licence_expiry' => ($_POST['licence_expiry'] ?? '') ?: null,
        ];
        if ($data['full_name'] === '' || $data['phone'] === '') {
            flash('error', 'Full name and phone are required.');
            redirect('admin/booking-new.php?step=1&new=1');
        }
        [$cid, $temp] = ClientService::createWalkIn($data, Auth::id());
        if ($temp) {
            flash('success', "Client account created. Temporary password: $temp (must be changed on first login)");
        }
        redirect('admin/booking-new.php?step=2&client=' . $cid);
    }

    if ($action === 'create_booking' && $client) {
        $vehicleId = (int) $_POST['vehicle_id'];
        $pickup = trim($_POST['pickup_date'] . ' ' . ($_POST['pickup_time'] ?: '09:00'));
        $return = trim($_POST['return_date'] . ' ' . ($_POST['return_time'] ?: '17:00'));
        $discount = (float) ($_POST['discount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        [$bookingId, $err] = BookingService::create(
            $clientId, $vehicleId, $pickup, $return, 'walk_in', Auth::id(), $discount, $notes);
        if ($err) {
            flash('error', $err);
            redirect('admin/booking-new.php?step=2&client=' . $clientId);
        }

        // Optional immediate payment + deposit capture.
        $payAmount = (float) ($_POST['pay_amount'] ?? 0);
        $payMethod = $_POST['pay_method'] ?? 'cash';
        if ($payAmount > 0) {
            PaymentService::record($clientId, $bookingId, $payAmount, $payMethod,
                trim($_POST['pay_ref'] ?? '') ?: null, 'rental', Auth::id());
        }
        $depAmount = (float) ($_POST['deposit_amount'] ?? 0);
        if ($depAmount > 0) {
            PaymentService::record($clientId, $bookingId, $depAmount,
                $_POST['deposit_method'] ?? 'cash', null, 'deposit', Auth::id());
        }
        if (!empty($_POST['confirm_now'])) {
            BookingService::setStatus($bookingId, 'confirmed', Auth::id(), 'Confirmed on creation');
        }
        flash('success', 'Booking created.');
        redirect('admin/booking.php?id=' . $bookingId);
    }
    redirect('admin/booking-new.php');
}

$q = trim($_GET['q'] ?? '');
$results = $q !== '' ? ClientService::search($q) : [];
$vehicles = $step === 2 ? Database::all(
    "SELECT * FROM vehicles WHERE status NOT IN ('maintenance','unavailable') ORDER BY make, model") : [];

$pageTitle = 'New Booking';
$active = 'bookings';
require APP_PATH . '/views/admin/header.php';
?>
<!-- Step indicator -->
<div class="flex items-center gap-3 mb-6 text-sm">
    <?php foreach ([1 => 'Find Client', 2 => 'Vehicle & Payment'] as $n => $label): ?>
    <div class="flex items-center gap-2">
        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold
            <?= $step >= $n ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500' ?>"><?= $n ?></span>
        <span class="<?= $step >= $n ? 'text-slate-800 font-medium' : 'text-slate-400' ?>"><?= $label ?></span>
    </div>
    <?php if ($n === 1): ?><i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i><?php endif; ?>
    <?php endforeach; ?>
</div>

<?php if ($step === 1): ?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Search Client</h3>
        <form method="post" class="flex gap-3 mb-4">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="search">
            <div class="relative flex-1">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
                <input name="q" value="<?= e($q) ?>" required placeholder="Phone, email, ID, client no or name" class="input !pl-9">
            </div>
            <button class="btn-primary">Search</button>
        </form>
        <ul class="divide-y divide-gray-100 text-sm">
            <?php foreach ($results as $r): ?>
            <li class="py-3 flex items-center justify-between">
                <div>
                    <p class="font-medium text-slate-800"><?= e($r['full_name']) ?> <span class="text-xs text-slate-400">(<?= e($r['client_no']) ?>)</span></p>
                    <p class="text-xs text-slate-500"><?= e($r['phone']) ?> · <?= e($r['national_id'] ?? 'no ID') ?></p>
                </div>
                <form method="post"><?= Csrf::field() ?>
                    <input type="hidden" name="action" value="select_client">
                    <input type="hidden" name="client_id" value="<?= $r['id'] ?>">
                    <button class="btn-primary !py-1.5 !px-3 text-xs">Use client</button>
                </form>
            </li>
            <?php endforeach; ?>
            <?php if ($q !== '' && !$results): ?>
            <li class="py-4 text-slate-400">No matching client — create a new profile on the right.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="card" id="new">
        <h3 class="font-semibold text-slate-800 mb-1">New Client</h3>
        <p class="text-xs text-slate-500 mb-4">An account with a temporary password is created automatically.</p>
        <form method="post" class="grid grid-cols-2 gap-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create_client">
            <div class="col-span-2"><label class="label">Full Name *</label><input name="full_name" class="input" required></div>
            <div><label class="label">Phone *</label><input name="phone" class="input" required></div>
            <div><label class="label">Email</label><input type="email" name="email" class="input"></div>
            <div><label class="label">National ID</label><input name="national_id" class="input"></div>
            <div><label class="label">Licence No</label><input name="licence_no" class="input"></div>
            <div class="col-span-2"><label class="label">Address</label><input name="address" class="input"></div>
            <button class="btn-primary col-span-2 justify-center"><i data-lucide="user-plus" class="w-4 h-4"></i> Create & Continue</button>
        </form>
    </div>
</div>

<?php elseif ($step === 2 && $client): ?>
<div class="card mb-6 flex items-center gap-4">
    <span class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-semibold"><?= e(strtoupper(substr($client['full_name'],0,1))) ?></span>
    <div>
        <p class="font-medium text-slate-800"><?= e($client['full_name']) ?></p>
        <p class="text-xs text-slate-500"><?= e($client['client_no']) ?> · <?= e($client['phone']) ?></p>
    </div>
    <a href="<?= url('admin/booking-new.php') ?>" class="btn-secondary ml-auto !py-1.5 text-xs">Change client</a>
</div>

<form method="post" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="create_booking">

    <div class="card xl:col-span-2">
        <h3 class="font-semibold text-slate-800 mb-4">Vehicle & Dates</h3>
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2"><label class="label">Vehicle</label>
                <select name="vehicle_id" id="vehicle" class="input" required onchange="quote()">
                    <?php foreach ($vehicles as $v): ?>
                    <option value="<?= $v['id'] ?>" data-daily="<?= $v['daily_rate'] ?>" data-deposit="<?= $v['deposit'] ?>">
                        <?= e($v['make'] . ' ' . $v['model'] . ' — ' . $v['reg_no'] . ' (' . money($v['daily_rate']) . '/day)') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p id="avail" class="text-xs mt-1"></p>
            </div>
            <div><label class="label">Pickup date</label>
                <input type="date" name="pickup_date" id="pd" class="input" required value="<?= date('Y-m-d') ?>" onchange="quote()"></div>
            <div><label class="label">Pickup time</label>
                <input type="time" name="pickup_time" class="input" value="09:00"></div>
            <div><label class="label">Return date</label>
                <input type="date" name="return_date" id="rd" class="input" required value="<?= date('Y-m-d', strtotime('+3 days')) ?>" onchange="quote()"></div>
            <div><label class="label">Return time</label>
                <input type="time" name="return_time" class="input" value="17:00"></div>
            <div><label class="label">Discount</label>
                <input type="number" name="discount" step="0.01" min="0" class="input" value="0"></div>
            <div><label class="label">Notes</label>
                <input name="notes" class="input" placeholder="Optional"></div>
        </div>
        <div id="pricebox" class="mt-4 rounded-lg bg-blue-50 border border-blue-100 p-4 text-sm hidden"></div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Rental Payment (optional)</h3>
            <div class="space-y-3">
                <input type="number" name="pay_amount" step="0.01" min="0" placeholder="Amount received" class="input">
                <select name="pay_method" class="input">
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="card">Card</option>
                    <option value="wallet">Client Wallet</option>
                    <option value="other">Other</option>
                </select>
                <input name="pay_ref" placeholder="Reference / receipt no." class="input">
            </div>
        </div>
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Security Deposit (optional)</h3>
            <div class="space-y-3">
                <input type="number" name="deposit_amount" step="0.01" min="0" placeholder="Deposit received" class="input">
                <select name="deposit_method" class="input">
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="card">Card</option>
                </select>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700 px-1">
            <input type="checkbox" name="confirm_now" checked class="rounded border-gray-300 text-blue-600">
            Confirm booking immediately
        </label>
        <button class="btn-primary w-full justify-center !py-2.5"><i data-lucide="check" class="w-4 h-4"></i> Create Booking</button>
    </div>
</form>

<script>
async function quote() {
    const v = document.getElementById('vehicle').value;
    const pd = document.getElementById('pd').value;
    const rd = document.getElementById('rd').value;
    const availEl = document.getElementById('avail');
    const box = document.getElementById('pricebox');
    if (!v || !pd || !rd) return;
    const res = await fetch('<?= url('api/availability.php') ?>?vehicle=' + v + '&pickup=' + pd + '&return=' + rd);
    const d = await res.json();
    availEl.textContent = d.available ? '✓ Available for selected dates' : '✗ ' + d.message;
    availEl.className = 'text-xs mt-1 ' + (d.available ? 'text-green-600' : 'text-red-600');
    if (d.available) {
        box.classList.remove('hidden');
        box.innerHTML = '<strong>' + d.days + ' day(s)</strong> · Rental ' + d.base_fmt +
            ' · Required deposit ' + d.deposit_fmt;
    } else { box.classList.add('hidden'); }
}
quote();
</script>
<?php endif; ?>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
