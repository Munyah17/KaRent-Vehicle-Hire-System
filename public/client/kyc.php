<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;

Auth::requireClient();
$client = Auth::client();
$cid = (int) $client['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save_profile') {
        Database::run(
            'UPDATE clients SET full_name=?, dob=?, phone=?, address=?, national_id=?, licence_no=?, licence_expiry=?, tax_no=?, fiscalise=? WHERE id=?',
            [
                trim($_POST['full_name']), ($_POST['dob'] ?: null), trim($_POST['phone']),
                trim($_POST['address']), trim($_POST['national_id']), trim($_POST['licence_no']),
                ($_POST['licence_expiry'] ?: null), trim($_POST['tax_no'] ?? ''),
                isset($_POST['fiscalise']) ? 1 : 0, $cid,
            ]
        );
        if ($client['kyc_status'] === 'pending') {
            Database::run("UPDATE clients SET kyc_status='under_review' WHERE id=?", [$cid]);
        }
        flash('success', 'Profile updated.');
    } elseif ($action === 'upload_doc') {
        [$name, $err] = upload_file($_FILES['doc'] ?? [], UPLOAD_PATH . '/kyc', config('uploads.doc_types'));
        if ($err) {
            flash('error', $err);
        } else {
            Database::run('INSERT INTO client_documents (client_id, doc_type, file_path) VALUES (?,?,?)',
                [$cid, $_POST['doc_type'] ?? 'other', 'kyc/' . $name]);
            if ($client['kyc_status'] === 'pending') {
                Database::run("UPDATE clients SET kyc_status='under_review' WHERE id=?", [$cid]);
            }
            \App\Notify::staff('kyc', 'KYC document uploaded',
                $client['full_name'] . ' uploaded a ' . ($_POST['doc_type'] ?? 'document'),
                'admin/client-edit.php?id=' . $cid);
            flash('success', 'Document uploaded for review.');
        }
    }
    redirect('client/kyc.php');
}

$client = Auth::client(); // refresh
$docs = Database::all('SELECT * FROM client_documents WHERE client_id = ? ORDER BY id DESC', [$cid]);

$pageTitle = 'Profile & KYC';
$active = 'kyc';
require APP_PATH . '/views/client/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="card">
        <div class="flex items-center justify-between mb-5">
            <h2 class="font-semibold text-slate-800">My Profile</h2>
            <?= status_badge($client['kyc_status']) ?>
        </div>
        <form method="post" class="grid grid-cols-2 gap-4">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_profile">
            <div class="col-span-2"><label class="label">Full name</label>
                <input name="full_name" class="input" value="<?= e($client['full_name']) ?>" required></div>
            <div><label class="label">Date of birth</label>
                <input type="date" name="dob" class="input" value="<?= e($client['dob']) ?>"></div>
            <div><label class="label">Phone</label>
                <input name="phone" class="input" value="<?= e($client['phone']) ?>"></div>
            <div class="col-span-2"><label class="label">Address</label>
                <textarea name="address" rows="2" class="input"><?= e($client['address']) ?></textarea></div>
            <div><label class="label">National ID / Passport</label>
                <input name="national_id" class="input" value="<?= e($client['national_id']) ?>"></div>
            <div><label class="label">Licence no.</label>
                <input name="licence_no" class="input" value="<?= e($client['licence_no']) ?>"></div>
            <div><label class="label">Licence expiry</label>
                <input type="date" name="licence_expiry" class="input" value="<?= e($client['licence_expiry']) ?>"></div>
            <div><label class="label">Tax / TIN no. (optional)</label>
                <input name="tax_no" class="input" value="<?= e($client['tax_no'] ?? '') ?>" placeholder="Buyer TIN for fiscal receipts"></div>
            <div class="col-span-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="fiscalise" <?= !empty($client['fiscalise']) ? 'checked' : '' ?>>
                    I want fiscalised (ZIMRA FDMS) receipts for my payments
                </label>
            </div>
            <div class="col-span-2"><button class="btn-primary w-full justify-center"><i data-lucide="save" class="w-4 h-4"></i> Save Profile</button></div>
        </form>
    </div>

    <div class="card">
        <h2 class="font-semibold text-slate-800 mb-1">Verification documents</h2>
        <p class="text-xs text-slate-400 mb-4">Upload your ID and driver's licence. Documents are stored securely and are never publicly accessible.</p>
        <ul class="divide-y divide-gray-100 text-sm mb-4">
            <?php foreach ($docs as $d): ?>
            <li class="py-3 flex items-center justify-between">
                <span class="flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4 text-gray-400"></i>
                    <?= e(ucwords(str_replace('_',' ',$d['doc_type']))) ?>
                    <span class="text-xs text-slate-400"><?= e(fmt_date($d['uploaded_at'])) ?></span></span>
                <?= status_badge($d['status']) ?>
            </li>
            <?php endforeach; ?>
            <?php if (!$docs): ?><li class="py-3 text-slate-400">No documents uploaded yet.</li><?php endif; ?>
        </ul>
        <form method="post" enctype="multipart/form-data" class="grid grid-cols-2 gap-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="upload_doc">
            <select name="doc_type" class="input">
                <option value="national_id">National ID</option>
                <option value="drivers_licence">Driver's Licence</option>
                <option value="passport">Passport</option>
                <option value="proof_of_address">Proof of Address</option>
                <option value="other">Other</option>
            </select>
            <input type="file" name="doc" required class="input !py-1.5" accept="image/*,application/pdf">
            <button class="btn-secondary col-span-2 justify-center"><i data-lucide="upload" class="w-4 h-4"></i> Upload Document</button>
        </form>
    </div>
</div>
<?php require APP_PATH . '/views/client/footer.php'; ?>
