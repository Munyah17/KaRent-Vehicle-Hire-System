<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;

Auth::requirePermission('staff');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_staff') {
        $name = trim($_POST['name']);
        $email = strtolower(trim($_POST['email']));
        $password = $_POST['password'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            flash('error', 'Valid email and an 8+ character password are required.');
        } elseif (Database::value('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('error', 'Email already in use.');
        } else {
            $staffRole = (int) Database::value("SELECT id FROM roles WHERE name='STAFF'");
            $uid = Database::insert(
                'INSERT INTO users (role_id, name, email, phone, password_hash) VALUES (?,?,?,?,?)',
                [$staffRole, $name, $email, trim($_POST['phone'] ?? ''), Auth::hashPassword($password)]
            );
            Audit::log(Auth::id(), 'add_staff', 'staff', 'user', $uid);
            flash('success', 'Staff member added.');
        }
    } elseif ($action === 'permissions') {
        $uid = (int) $_POST['user_id'];
        Database::run('DELETE FROM user_permissions WHERE user_id = ?', [$uid]);
        $all = Database::all('SELECT id, code FROM permissions');
        foreach ($all as $p) {
            $granted = isset($_POST['perm'][$p['code']]);
            Database::run(
                'INSERT INTO user_permissions (user_id, permission_id, allowed) VALUES (?,?,?)',
                [$uid, $p['id'], $granted ? 1 : 0]
            );
        }
        Audit::log(Auth::id(), 'update_permissions', 'staff', 'user', $uid);
        flash('success', 'Permissions updated.');
    } elseif ($action === 'toggle_status') {
        $uid = (int) $_POST['user_id'];
        if ($uid !== Auth::id()) {
            Database::run(
                "UPDATE users SET status = IF(status='active','suspended','active') WHERE id = ?",
                [$uid]);
            Audit::log(Auth::id(), 'toggle_staff_status', 'staff', 'user', $uid);
            flash('success', 'Account status changed.');
        }
    }
    redirect('admin/staff.php');
}

$staff = Database::all(
    "SELECT u.*, r.name role_name FROM users u JOIN roles r ON r.id=u.role_id
     WHERE r.name IN ('SUPER_ADMIN','STAFF') ORDER BY u.id");
$allPerms = Database::all('SELECT * FROM permissions ORDER BY module, id');
$editUser = isset($_GET['perms']) ? Database::one('SELECT * FROM users WHERE id = ?', [(int) $_GET['perms']]) : null;
$userPerms = $editUser ? array_column(
    Database::all('SELECT p.code, up.allowed FROM user_permissions up JOIN permissions p ON p.id=up.permission_id WHERE up.user_id = ?', [$editUser['id']]),
    'allowed', 'code') : [];
$rolePerms = $editUser ? array_column(
    Database::all('SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id = ?', [$editUser['role_id']]),
    'code') : [];

$pageTitle = 'Staff & Permissions';
$active = 'staff';
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="card !p-0 overflow-x-auto">
            <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-slate-800">Staff Accounts</h3></div>
            <table class="w-full">
                <thead><tr><th class="th">Name</th><th class="th">Email</th><th class="th">Role</th><th class="th">Status</th><th class="th"></th></tr></thead>
                <tbody>
                <?php foreach ($staff as $s): ?>
                <tr class="table-row">
                    <td class="td"><div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-semibold"><?= e(strtoupper(substr($s['name'],0,1))) ?></span>
                        <span class="font-medium"><?= e($s['name']) ?></span></div></td>
                    <td class="td"><?= e($s['email']) ?></td>
                    <td class="td"><?= status_badge($s['role_name'] === 'SUPER_ADMIN' ? 'verified' : 'active') ?> <?= e(ucwords(strtolower($s['role_name']))) ?></td>
                    <td class="td"><?= status_badge($s['status']) ?></td>
                    <td class="td text-right space-x-2">
                        <a href="?perms=<?= $s['id'] ?>" class="text-blue-600 text-sm hover:underline">Permissions</a>
                        <?php if ($s['id'] != Auth::id()): ?>
                        <form method="post" class="inline"><?= Csrf::field() ?>
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                            <button class="text-sm <?= $s['status'] === 'active' ? 'text-red-600' : 'text-green-600' ?> hover:underline">
                                <?= $s['status'] === 'active' ? 'Suspend' : 'Activate' ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($editUser): ?>
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-1">Permissions — <?= e($editUser['name']) ?></h3>
            <p class="text-xs text-slate-400 mb-4">Checked boxes override the role defaults for this user only.</p>
            <form method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="permissions">
                <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2.5 mb-5">
                    <?php foreach ($allPerms as $p):
                        $roleDefault = in_array($p['code'], $rolePerms, true);
                        $granted = array_key_exists($p['code'], $userPerms) ? (bool) $userPerms[$p['code']] : $roleDefault; ?>
                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm <?= $granted ? 'bg-blue-50 border-blue-200' : '' ?>">
                        <input type="checkbox" name="perm[<?= e($p['code']) ?>]" <?= $granted ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600">
                        <?= e($p['label']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button class="btn-primary"><i data-lucide="save" class="w-4 h-4"></i> Save Permissions</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 class="font-semibold text-slate-800 mb-4">Add Staff Member</h3>
        <form method="post" class="space-y-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="add_staff">
            <input name="name" placeholder="Full name" class="input" required>
            <input name="email" type="email" placeholder="Email" class="input" required>
            <input name="phone" placeholder="Phone" class="input">
            <input name="password" type="password" placeholder="Password (min 8 chars)" class="input" required minlength="8">
            <button class="btn-primary w-full justify-center"><i data-lucide="user-plus" class="w-4 h-4"></i> Add Staff</button>
        </form>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
