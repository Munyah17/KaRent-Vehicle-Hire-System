<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;
use App\Csrf;
use App\Audit;

Auth::requirePermission('contracts');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $id = (int) ($_POST['template_id'] ?? 0);
    $tpl = Database::one('SELECT * FROM contract_templates WHERE id = ?', [$id]);
    if ($tpl) {
        $body = $_POST['body'] ?? '';
        if (trim($body) === '') {
            flash('error', 'Template body cannot be empty.');
        } else {
            // Saving a template bumps its version — existing contracts keep theirs.
            Database::run(
                'UPDATE contract_templates SET body = ?, version = version + 1, updated_by = ? WHERE id = ?',
                [$body, Auth::id(), $id]
            );
            Audit::log(Auth::id(), 'edit_template', 'contracts', 'contract_template', $id,
                ['version' => $tpl['version']], ['version' => $tpl['version'] + 1]);
            flash('success', 'Template saved as version ' . ($tpl['version'] + 1) . '.');
        }
    }
    redirect('admin/templates.php' . ($id ? '?id=' . $id : ''));
}

$id = (int) ($_GET['id'] ?? 0);
$templates = Database::all('SELECT * FROM contract_templates ORDER BY name');
$current = $id ? Database::one('SELECT * FROM contract_templates WHERE id = ?', [$id]) : ($templates[0] ?? null);

$placeholders = ['company_name','client_name','client_no','client_id','booking_ref',
    'vehicle_make','vehicle_model','vehicle_year','registration','pickup_date','return_date',
    'rental_amount','deposit_amount','outstanding','mileage','fuel_level','generated_date'];

$pageTitle = 'Document Templates';
$active = 'templates';
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
    <div class="card !p-0 overflow-x-auto">
        <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-slate-800">Templates</h3></div>
        <ul class="divide-y divide-gray-100 text-sm">
            <?php foreach ($templates as $t): ?>
            <li><a href="?id=<?= $t['id'] ?>"
                   class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 <?= $current && $current['id'] == $t['id'] ? 'bg-blue-50' : '' ?>">
                <span class="font-medium text-slate-700"><?= e($t['name']) ?></span>
                <span class="text-xs text-slate-400">v<?= (int) $t['version'] ?><?= $t['is_active'] ? '' : ' · off' ?></span>
            </a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="xl:col-span-3 card">
        <?php if ($current): ?>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-semibold text-slate-800"><?= e($current['name']) ?></h3>
                <p class="text-xs text-slate-400">Editing creates version <?= (int) $current['version'] + 1 ?> — existing documents keep their version.</p>
            </div>
            <span class="badge bg-blue-100 text-blue-800">v<?= (int) $current['version'] ?></span>
        </div>
        <form method="post">
            <?= Csrf::field() ?>
            <input type="hidden" name="template_id" value="<?= $current['id'] ?>">
            <textarea name="body" rows="20" class="input font-mono text-xs leading-relaxed"><?= e($current['body']) ?></textarea>
            <div class="flex flex-wrap gap-1.5 my-4">
                <?php foreach ($placeholders as $p): ?>
                <code class="text-[11px] bg-gray-100 text-slate-600 rounded px-2 py-1">{{<?= $p ?>}}</code>
                <?php endforeach; ?>
            </div>
            <button class="btn-primary"><i data-lucide="save" class="w-4 h-4"></i> Save Template</button>
        </form>
        <?php else: ?>
        <p class="text-slate-400">Select a template to edit.</p>
        <?php endif; ?>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
