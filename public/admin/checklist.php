<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;
use App\Services\BookingService;

Auth::requirePermission('checklists');

$bookingId = (int) ($_GET['booking'] ?? 0);
$type = ($_GET['type'] ?? 'collection') === 'return' ? 'return' : 'collection';
$b = BookingService::find($bookingId);
if (!$b) { http_response_code(404); exit('Booking not found.'); }

$items = Database::all('SELECT * FROM checklist_items WHERE is_active = 1 ORDER BY sort_order');
$existing = Database::one('SELECT * FROM checklists WHERE booking_id = ? AND type = ?', [$bookingId, $type]);
$results = $existing ? array_column(
    Database::all('SELECT * FROM checklist_results WHERE checklist_id = ?', [$existing['id']]),
    null, 'item_id') : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $mileage = ($_POST['mileage'] ?? '') !== '' ? (int) $_POST['mileage'] : null;
    $fuel = ($_POST['fuel_level'] ?? '') !== '' ? (int) $_POST['fuel_level'] : null;
    $ack = trim($_POST['client_ack_name'] ?? '');

    Database::begin();
    try {
        if ($existing) {
            $checklistId = (int) $existing['id'];
            Database::run(
                'UPDATE checklists SET mileage=?, fuel_level=?, condition_notes=?, staff_id=?, client_ack_name=?, client_ack_at=?
                 WHERE id=?',
                [$mileage, $fuel, trim($_POST['condition_notes'] ?? ''), Auth::id(),
                 $ack ?: null, $ack ? date('Y-m-d H:i:s') : null, $checklistId]
            );
        } else {
            $checklistId = Database::insert(
                'INSERT INTO checklists (booking_id, type, mileage, fuel_level, condition_notes, staff_id, client_ack_name, client_ack_at)
                 VALUES (?,?,?,?,?,?,?,?)',
                [$bookingId, $type, $mileage, $fuel, trim($_POST['condition_notes'] ?? ''),
                 Auth::id(), $ack ?: null, $ack ? date('Y-m-d H:i:s') : null]
            );
        }
        foreach ($items as $item) {
            $st = $_POST['status'][$item['id']] ?? 'good';
            if (!in_array($st, ['good','damaged','missing','na'], true)) $st = 'good';
            Database::run(
                'INSERT INTO checklist_results (checklist_id, item_id, status, comment)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), comment = VALUES(comment)',
                [$checklistId, $item['id'], $st, trim($_POST['comment'][$item['id']] ?? '')]
            );
        }
        Database::commit();
    } catch (\Throwable $e) {
        Database::rollback();
        flash('error', 'Could not save checklist.');
        redirect('admin/checklist.php?booking=' . $bookingId . '&type=' . $type);
    }
    Audit::log(Auth::id(), $type . '_checklist', 'checklists', 'checklist', $checklistId);
    flash('success', ucfirst($type) . ' checklist saved.');
    redirect('admin/booking.php?id=' . $bookingId);
}

// For return: compare with collection results
$collection = null;
$collectionResults = [];
if ($type === 'return') {
    $collection = Database::one("SELECT * FROM checklists WHERE booking_id = ? AND type='collection'", [$bookingId]);
    if ($collection) {
        $collectionResults = array_column(
            Database::all('SELECT * FROM checklist_results WHERE checklist_id = ?', [$collection['id']]),
            null, 'item_id');
    }
}

$statusOpts = ['good' => 'Good', 'damaged' => 'Damaged', 'missing' => 'Missing', 'na' => 'N/A'];
$pageTitle = ucfirst($type) . ' Checklist — ' . $b['ref'];
$active = 'bookings';
require APP_PATH . '/views/admin/header.php';
?>
<div class="card mb-6 flex items-center gap-4">
    <div>
        <p class="font-semibold text-slate-800"><?= e($b['make'] . ' ' . $b['model']) ?> (<?= e($b['reg_no']) ?>)</p>
        <p class="text-sm text-slate-500"><?= e($b['client_name']) ?> · <?= e($b['ref']) ?> · <?= status_badge($b['status']) ?></p>
    </div>
    <a href="<?= url('admin/booking.php?id=' . $bookingId) ?>" class="btn-secondary ml-auto !py-1.5 text-xs">Back to booking</a>
</div>

<?php if ($existing): ?>
<div class="mb-6 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
    This <?= $type ?> checklist was completed <?= e(fmt_datetime($existing['created_at'])) ?>. Saving will update it.
</div>
<?php endif; ?>

<form method="post">
    <?= Csrf::field() ?>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <?php foreach (['EXTERIOR','INTERIOR'] as $cat): ?>
        <div class="card !p-0 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50"><h3 class="font-semibold text-slate-800"><?= e(ucfirst(strtolower($cat))) ?></h3></div>
            <table class="w-full">
                <thead><tr><th class="th">Item</th><th class="th">Status</th><th class="th">Comment</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item):
                    if ($item['category'] !== $cat) continue;
                    $cur = $results[$item['id']]['status'] ?? 'good';
                    $coll = $collectionResults[$item['id']]['status'] ?? null; ?>
                <tr class="table-row <?= $coll && $coll !== $cur && $type==='return' && isset($results[$item['id']]) ? 'bg-red-50' : '' ?>">
                    <td class="td font-medium"><?= e($item['label']) ?>
                        <?php if ($coll && $coll !== 'good'): ?>
                        <span class="text-[10px] text-amber-600 block">at collection: <?= e($coll) ?></span>
                        <?php endif; ?></td>
                    <td class="td">
                        <select name="status[<?= $item['id'] ?>]" class="input !py-1 !px-2 text-xs w-28">
                            <?php foreach ($statusOpts as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= $cur === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="td"><input name="comment[<?= $item['id'] ?>]" class="input !py-1 text-xs" value="<?= e($results[$item['id']]['comment'] ?? '') ?>" placeholder="Optional"></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card mt-6">
        <h3 class="font-semibold text-slate-800 mb-4">Inspection Details</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div><label class="label">Mileage (km)</label>
                <input name="mileage" type="number" class="input" value="<?= e((string) ($existing['mileage'] ?? '')) ?>"></div>
            <div><label class="label">Fuel level (%)</label>
                <input name="fuel_level" type="number" min="0" max="100" class="input" value="<?= e((string) ($existing['fuel_level'] ?? '')) ?>"></div>
            <div class="col-span-2"><label class="label">Client acknowledgement (name)</label>
                <input name="client_ack_name" class="input" value="<?= e($existing['client_ack_name'] ?? '') ?>" placeholder="Client confirms condition"></div>
            <div class="col-span-2 md:col-span-4"><label class="label">Condition notes</label>
                <textarea name="condition_notes" rows="2" class="input"><?= e($existing['condition_notes'] ?? '') ?></textarea></div>
        </div>
        <button class="btn-primary mt-5"><i data-lucide="save" class="w-4 h-4"></i> Save <?= e(ucfirst($type)) ?> Checklist</button>
    </div>
</form>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
