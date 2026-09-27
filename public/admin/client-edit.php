<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;
use App\Services\ClientService;

Auth::requirePermission('clients');

$id = (int) ($_GET['id'] ?? 0);
$client = $id ? Database::one('SELECT * FROM clients WHERE id = ?', [$id]) : null;
if ($id && !$client) { http_response_code(404); exit('Client not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'dob' => ($_POST['dob'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => strtolower(trim($_POST['email'] ?? '')),
            'address' => trim($_POST['address'] ?? ''),
            'national_id' => trim($_POST['national_id'] ?? ''),
            'licence_no' => trim($_POST['licence_no'] ?? ''),
            'licence_expiry' => ($_POST['licence_expiry'] ?? '') ?: null,
        ];
        if ($data['full_name'] === '' || $data['phone'] === '') {
            flash('error', 'Full name and phone are required.');
            remember_old($_POST);
            redirect('admin/client-edit.php' . ($id ? "?id=$id" : ''));
        }
        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
            Database::run("UPDATE clients SET $sets WHERE id = :id", $data + ['id' => $id]);
            // keep linked user record in sync
            if ($client['user_id']) {
                Database::run('UPDATE users SET name = ?, phone = ? WHERE id = ?',
                    [$data['full_name'], $data['phone'], $client['user_id']]);
            }
            Audit::log(Auth::id(), 'update_client', 'clients', 'client', $id, $client, $data);
            flash('success', 'Client updated.');
            clear_old();
            redirect('admin/client-edit.php?id=' . $id);
        }
        // New client — create account + temp password (walk-in workflow).
        [$newId, $temp] = ClientService::createWalkIn($data, Auth::id());
        clear_old();
        if ($temp) {
            flash('success', "Client created. Temporary password: $temp — share it with the client; they must change it on first login.");
        } else {
            flash('success', 'Existing client matched — profile updated.');
            $newId = Database::value(
                'SELECT id FROM clients WHERE phone = ? OR email = ? OR national_id = ? LIMIT 1',
                [$data['phone'], $data['email'], $data['national_id']]
            ) ?: $newId;
        }
        redirect('admin/client-edit.php?id=' . $newId);
    }

    if ($id && $action === 'upload_doc') {
        [$name, $err] = upload_file($_FILES['doc'] ?? [], UPLOAD_PATH . '/kyc', config('uploads.doc_types'));
        if ($err) {
            flash('error', $err);
        } else {
            Database::run(
                'INSERT INTO client_documents (client_id, doc_type, file_path) VALUES (?,?,?)',
                [$id, $_POST['doc_type'] ?? 'other', 'kyc/' . $name]
            );
            if (in_array($client['kyc_status'], ['pending'], true)) {
                Database::run("UPDATE clients SET kyc_status = 'under_review' WHERE id = ?", [$id]);
            }
            flash('success', 'Document uploaded.');
        }
        redirect('admin/client-edit.php?id=' . $id);
    }

    if ($id && $action === 'review_doc' && Auth::can('kyc')) {
        $docId = (int) $_POST['doc_id'];
        $decision = $_POST['decision'] === 'verified' ? 'verified' : 'rejected';
        Database::run(
            'UPDATE client_documents SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND client_id = ?',
            [$decision, Auth::id(), $docId, $id]
        );
        Audit::log(Auth::id(), 'review_doc', 'clients', 'client_document', $docId, null, $decision);
        flash('success', 'Document ' . $decision . '.');
        redirect('admin/client-edit.php?id=' . $id);
    }

    if ($id && $action === 'set_kyc' && Auth::can('kyc')) {
        $status = $_POST['kyc_status'] ?? 'pending';
        if (in_array($status, ['pending','under_review','verified','rejected'], true)) {
            Database::run('UPDATE clients SET kyc_status = ? WHERE id = ?', [$status, $id]);
            Audit::log(Auth::id(), 'kyc_status', 'clients', 'client', $id, $client['kyc_status'], $status);
            flash('success', 'KYC status updated.');
        }
        redirect('admin/client-edit.php?id=' . $id);
    }
}

$docs = $id ? Database::all('SELECT * FROM client_documents WHERE client_id = ? ORDER BY id DESC', [$id]) : [];
$wallet = $id ? \App\Services\WalletService::balance($id) : 0;
$bookings = $id ? Database::all(
    'SELECT b.*, v.make, v.model, v.reg_no FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id
     WHERE b.client_id = ? ORDER BY b.id DESC LIMIT 10', [$id]) : [];
$c = array_merge($client ?? [], $_SESSION['_old'] ?? []);
clear_old();

$pageTitle = $id ? 'Edit Client — ' . $client['full_name'] : 'Add Client';
$active = 'clients';
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <!-- Client details -->
    <div class="card">
        <div class="flex items-center gap-4 mb-6">
            <span class="w-14 h-14 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-lg font-semibold">
                <?= e(strtoupper(substr($c['full_name'] ?? 'C', 0, 1))) ?>
            </span>
            <div>
                <p class="font-semibold text-slate-800"><?= e($c['full_name'] ?? 'New Client') ?></p>
                <p class="text-sm text-slate-500"><?= e($c['client_no'] ?? 'Account created automatically') ?></p>
            </div>
            <?php if ($id): ?><div class="ml-auto"><?= status_badge($c['kyc_status']) ?></div><?php endif; ?>
        </div>
        <form method="post" class="grid grid-cols-2 gap-4">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save">
            <div class="col-span-2"><label class="label">Full Name *</label>
                <input name="full_name" class="input" required value="<?= e($c['full_name'] ?? '') ?>"></div>
            <div><label class="label">Date of Birth</label>
                <input type="date" name="dob" class="input" value="<?= e($c['dob'] ?? '') ?>"></div>
            <div><label class="label">Phone *</label>
                <input name="phone" class="input" required value="<?= e($c['phone'] ?? '') ?>"></div>
            <div class="col-span-2"><label class="label">Email</label>
                <input type="email" name="email" class="input" value="<?= e($c['email'] ?? '') ?>"></div>
            <div class="col-span-2"><label class="label">Address</label>
                <textarea name="address" rows="2" class="input"><?= e($c['address'] ?? '') ?></textarea></div>
            <div><label class="label">National ID / Passport</label>
                <input name="national_id" class="input" value="<?= e($c['national_id'] ?? '') ?>"></div>
            <div><label class="label">Driver's Licence No</label>
                <input name="licence_no" class="input" value="<?= e($c['licence_no'] ?? '') ?>"></div>
            <div><label class="label">Licence Expiry</label>
                <input type="date" name="licence_expiry" class="input" value="<?= e($c['licence_expiry'] ?? '') ?>"></div>
            <div class="col-span-2">
                <button class="btn-primary w-full justify-center"><i data-lucide="save" class="w-4 h-4"></i>
                    <?= $id ? 'Save Changes' : 'Create Client + Account' ?></button>
            </div>
            <?php if (!$id): ?>
            <p class="col-span-2 text-xs text-slate-400">A login account with a temporary password will be created automatically; the client must change it on first sign-in.</p>
            <?php endif; ?>
        </form>
    </div>

    <!-- Documents & KYC -->
    <div class="space-y-6">
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Documents</h3>
            <ul class="divide-y divide-gray-100 text-sm">
                <?php foreach ($docs as $d): ?>
                <li class="py-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i data-lucide="file-text" class="w-4 h-4 text-gray-400"></i>
                        <div>
                            <p class="font-medium text-slate-700"><?= e(ucwords(str_replace('_', ' ', $d['doc_type']))) ?></p>
                            <p class="text-xs text-slate-400">Uploaded <?= e(fmt_date($d['uploaded_at'])) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <?= status_badge($d['status']) ?>
                        <a href="<?= url('admin/doc.php?id=' . $d['id'] . '&t=client') ?>" class="text-blue-600 text-xs hover:underline">View</a>
                        <?php if ($d['status'] !== 'verified' && Auth::can('kyc')): ?>
                        <form method="post" class="inline"><?= Csrf::field() ?>
                            <input type="hidden" name="action" value="review_doc">
                            <input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
                            <button name="decision" value="verified" class="text-green-600 text-xs hover:underline">Verify</button>
                            <button name="decision" value="rejected" class="text-red-600 text-xs hover:underline">Reject</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
                <?php if (!$docs): ?><li class="py-3 text-slate-400">No documents uploaded.</li><?php endif; ?>
            </ul>
            <?php if ($id): ?>
            <form method="post" enctype="multipart/form-data" class="mt-4 grid grid-cols-2 gap-3">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="upload_doc">
                <select name="doc_type" class="input">
                    <option value="national_id">National ID</option>
                    <option value="drivers_licence">Driver's Licence</option>
                    <option value="passport">Passport</option>
                    <option value="proof_of_address">Proof of Address</option>
                    <option value="other">Other</option>
                </select>
                <input type="file" name="doc" required class="input !py-1.5">
                <button class="btn-secondary col-span-2 justify-center"><i data-lucide="upload" class="w-4 h-4"></i> Upload Document</button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($id && Auth::can('kyc')): ?>
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">KYC Status</h3>
            <form method="post" class="flex items-center gap-3">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="set_kyc">
                <select name="kyc_status" class="input flex-1">
                    <?php foreach (['pending','under_review','verified','rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= $c['kyc_status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-primary">Update</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($id): ?>
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-2">Account Summary</h3>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-lg bg-gray-50 p-3">
                    <p class="text-xs text-slate-500">Wallet balance</p>
                    <p class="font-semibold text-slate-800"><?= money($wallet) ?></p>
                </div>
                <div class="rounded-lg bg-gray-50 p-3">
                    <p class="text-xs text-slate-500">Bookings</p>
                    <p class="font-semibold text-slate-800"><?= count($bookings) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($id && $bookings): ?>
<div class="card !p-0 overflow-x-auto mt-6">
    <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-slate-800">Booking History</h3></div>
    <table class="w-full">
        <thead><tr><th class="th">Ref</th><th class="th">Vehicle</th><th class="th">Dates</th><th class="th">Status</th><th class="th">Total</th><th class="th"></th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
        <tr class="table-row">
            <td class="td font-medium"><?= e($b['ref']) ?></td>
            <td class="td"><?= e($b['make'] . ' ' . $b['model']) ?> <span class="text-slate-400">(<?= e($b['reg_no']) ?>)</span></td>
            <td class="td"><?= e(fmt_date($b['pickup_at'])) ?> → <?= e(fmt_date($b['return_at'])) ?></td>
            <td class="td"><?= status_badge($b['status']) ?></td>
            <td class="td"><?= money($b['total']) ?></td>
            <td class="td text-right"><a href="<?= url('admin/booking.php?id=' . $b['id']) ?>" class="text-blue-600 text-sm hover:underline">View</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
