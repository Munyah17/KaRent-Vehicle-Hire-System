<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;

Auth::requirePermission('expenses');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        Database::run(
            'INSERT INTO expenses (expense_date, category, amount, description, supplier_id, vehicle_id, staff_id)
             VALUES (?,?,?,?,?,?,?)',
            [
                $_POST['expense_date'] ?: date('Y-m-d'),
                $_POST['category'],
                (float) $_POST['amount'],
                trim($_POST['description'] ?? ''),
                ($_POST['supplier_id'] ?? '') ?: null,
                ($_POST['vehicle_id'] ?? '') ?: null,
                Auth::id(),
            ]
        );
        Audit::log(Auth::id(), 'add_expense', 'expenses', 'expense',
            (int) Database::value('SELECT LAST_INSERT_ID()'));
        flash('success', 'Expense recorded.');
    } elseif ($action === 'add_supplier') {
        Database::run(
            'INSERT INTO suppliers (name, contact_person, phone, email, service_category, notes)
             VALUES (?,?,?,?,?,?)',
            [trim($_POST['s_name']), trim($_POST['s_contact'] ?? ''), trim($_POST['s_phone'] ?? ''),
             trim($_POST['s_email'] ?? ''), trim($_POST['s_service'] ?? ''), trim($_POST['s_notes'] ?? '')]
        );
        flash('success', 'Supplier added.');
    }
    redirect('admin/expenses.php');
}

$cat = $_GET['cat'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = '1=1';
$params = [];
if ($cat !== '') { $where .= ' AND e.category = ?'; $params[] = $cat; }
if ($from !== '') { $where .= ' AND e.expense_date >= ?'; $params[] = $from; }
if ($to !== '') { $where .= ' AND e.expense_date <= ?'; $params[] = $to; }

[$expenses, $total, $pages, $page] = paginate(
    "SELECT e.*, s.name supplier_name, v.reg_no, v.make, v.model
     FROM expenses e LEFT JOIN suppliers s ON s.id=e.supplier_id
     LEFT JOIN vehicles v ON v.id=e.vehicle_id
     WHERE $where ORDER BY e.expense_date DESC, e.id DESC",
    "SELECT COUNT(*) FROM expenses e WHERE $where",
    $params, $page, 15
);
$sumFiltered = (float) Database::value("SELECT COALESCE(SUM(amount),0) FROM expenses e WHERE $where", $params);
$suppliers = Database::all('SELECT * FROM suppliers ORDER BY name');
$vehicles = Database::all('SELECT id, make, model, reg_no FROM vehicles ORDER BY make');
$categories = ['fuel','maintenance','repairs','cleaning','insurance','licensing','office','marketing','other'];
$catColors = [
    'fuel' => 'bg-amber-100 text-amber-800', 'maintenance' => 'bg-blue-100 text-blue-800',
    'repairs' => 'bg-red-100 text-red-800', 'cleaning' => 'bg-cyan-100 text-cyan-800',
    'insurance' => 'bg-purple-100 text-purple-800', 'licensing' => 'bg-indigo-100 text-indigo-800',
    'office' => 'bg-gray-200 text-gray-700', 'marketing' => 'bg-pink-100 text-pink-800',
    'other' => 'bg-gray-200 text-gray-700',
];

$pageTitle = 'Expenses';
$active = 'expenses';
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
    <div class="xl:col-span-3 card !p-0 overflow-x-auto">
        <div class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100">
            <form method="get" class="flex flex-wrap items-center gap-3 flex-1">
                <input type="date" name="from" value="<?= e($from) ?>" class="input w-36">
                <input type="date" name="to" value="<?= e($to) ?>" class="input w-36">
                <select name="cat" class="input w-40">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c ?>" <?= $cat === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-secondary">Filter</button>
            </form>
            <span class="text-sm text-slate-500">Total: <span class="font-semibold text-slate-800"><?= money($sumFiltered) ?></span></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr>
                    <th class="th">Date</th><th class="th">Category</th><th class="th">Description</th>
                    <th class="th">Supplier</th><th class="th">Vehicle</th><th class="th text-right">Amount</th>
                </tr></thead>
                <tbody>
                <?php foreach ($expenses as $x): ?>
                <tr class="table-row">
                    <td class="td"><?= e(fmt_date($x['expense_date'])) ?></td>
                    <td class="td"><span class="badge <?= $catColors[$x['category']] ?? 'bg-gray-200 text-gray-700' ?>"><?= e(ucfirst($x['category'])) ?></span></td>
                    <td class="td"><?= e($x['description'] ?? '—') ?></td>
                    <td class="td"><?= e($x['supplier_name'] ?? '—') ?></td>
                    <td class="td"><?= e($x['reg_no'] ? $x['make'] . ' ' . $x['model'] . ' (' . $x['reg_no'] . ')' : '—') ?></td>
                    <td class="td text-right font-medium"><?= money($x['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$expenses): ?><tr><td colspan="6" class="td text-center py-10 text-slate-400">No expenses found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-6 py-4">
            <p class="text-sm text-slate-500">Showing <?= count($expenses) ?> of <?= $total ?></p>
            <?= pagination_links($page, $pages, 'expenses.php?x=1' . ($cat ? "&cat=$cat" : '') . ($from ? "&from=$from" : '') . ($to ? "&to=$to" : '')) ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Add Expense</h3>
            <form method="post" class="space-y-3">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="add">
                <input type="date" name="expense_date" class="input" value="<?= date('Y-m-d') ?>" required>
                <select name="category" class="input" required>
                    <?php foreach ($categories as $c): ?><option value="<?= $c ?>"><?= ucfirst($c) ?></option><?php endforeach; ?>
                </select>
                <input name="description" placeholder="Description" class="input">
                <input name="amount" type="number" step="0.01" min="0.01" placeholder="Amount" class="input" required>
                <select name="supplier_id" class="input">
                    <option value="">No supplier</option>
                    <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                </select>
                <select name="vehicle_id" class="input">
                    <option value="">No vehicle</option>
                    <?php foreach ($vehicles as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['make'] . ' ' . $v['model'] . ' ' . $v['reg_no']) ?></option><?php endforeach; ?>
                </select>
                <button class="btn-primary w-full justify-center"><i data-lucide="plus" class="w-4 h-4"></i> Record Expense</button>
            </form>
        </div>
        <div class="card">
            <h3 class="font-semibold text-slate-800 mb-4">Add Supplier</h3>
            <form method="post" class="space-y-3">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="add_supplier">
                <input name="s_name" placeholder="Supplier name" class="input" required>
                <input name="s_phone" placeholder="Phone" class="input">
                <input name="s_email" placeholder="Email" class="input">
                <input name="s_service" placeholder="Service category" class="input">
                <button class="btn-secondary w-full justify-center">Add Supplier</button>
            </form>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
