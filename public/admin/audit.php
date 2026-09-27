<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requirePermission('audit');

$module = $_GET['module'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$where = '1=1';
$params = [];
if ($module !== '') { $where .= ' AND a.module = ?'; $params[] = $module; }

[$logs, $total, $pages, $page] = paginate(
    "SELECT a.*, u.name user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id
     WHERE $where ORDER BY a.id DESC",
    "SELECT COUNT(*) FROM audit_logs a WHERE $where",
    $params, $page, 30
);
$modules = array_column(Database::all('SELECT DISTINCT module FROM audit_logs ORDER BY module'), 'module');

$pageTitle = 'Audit Log';
$active = 'audit';
require APP_PATH . '/views/admin/header.php';
?>
<div class="card !p-0 overflow-hidden">
    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
        <form method="get" class="flex items-center gap-3">
            <select name="module" class="input w-48">
                <option value="">All Modules</option>
                <?php foreach ($modules as $m): ?>
                <option value="<?= e($m) ?>" <?= $module === $m ? 'selected' : '' ?>><?= e(ucfirst($m)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-secondary">Filter</button>
        </form>
    </div>
    <table class="w-full">
        <thead><tr>
            <th class="th">Time</th><th class="th">User</th><th class="th">Action</th>
            <th class="th">Module</th><th class="th">Record</th><th class="th">Details</th><th class="th">IP</th>
        </tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
        <tr class="table-row">
            <td class="td whitespace-nowrap"><?= e(fmt_datetime($l['created_at'])) ?></td>
            <td class="td"><?= e($l['user_name'] ?? 'System') ?></td>
            <td class="td font-medium"><?= e(ucwords(str_replace('_',' ',$l['action']))) ?></td>
            <td class="td"><?= e($l['module']) ?></td>
            <td class="td"><?= e(($l['record_type'] ?? '—') . ($l['record_id'] ? ' #' . $l['record_id'] : '')) ?></td>
            <td class="td max-w-xs">
                <?php if ($l['old_value'] || $l['new_value']): ?>
                <span class="text-xs text-slate-400">
                    <?= e(mb_strimwidth((string) $l['new_value'], 0, 80, '…')) ?>
                </span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td class="td text-xs"><?= e($l['ip'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="7" class="td text-center py-10 text-slate-400">No log entries.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <div class="flex items-center justify-between px-6 py-4">
        <p class="text-sm text-slate-500">Showing <?= count($logs) ?> of <?= $total ?></p>
        <?= pagination_links($page, $pages, 'audit.php?x=1' . ($module ? '&module=' . $module : '')) ?>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
