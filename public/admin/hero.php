<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Audit;
use App\Database;
use App\Csrf;

Auth::requirePermission('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['slide_id'] ?? 0);

    if ($action === 'save') {
        $count = (int) Database::value('SELECT COUNT(*) FROM hero_slides');
        $overlay = min(80, max(60, (int) ($_POST['overlay'] ?? 70)));
        $fields = [
            'title' => trim($_POST['title'] ?? ''),
            'subtitle' => trim($_POST['subtitle'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'cta1_label' => trim($_POST['cta1_label'] ?? ''),
            'cta1_url' => trim($_POST['cta1_url'] ?? ''),
            'cta2_label' => trim($_POST['cta2_label'] ?? ''),
            'cta2_url' => trim($_POST['cta2_url'] ?? ''),
            'overlay' => $overlay,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($fields['title'] === '') {
            flash('error', 'A title is required.');
            redirect('admin/hero.php' . ($id ? "?edit=$id" : ''));
        }
        if (!$id && $count >= 20) {
            flash('error', 'Maximum of 20 slides reached.');
            redirect('admin/hero.php');
        }

        $imagePath = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            [$name, $err] = upload_file(
                $_FILES['image'],
                PUBLIC_UPLOAD_PATH . '/hero',
                $GLOBALS['config']['uploads']['image_types'] ?? []
            );
            $imagePath = $name ? 'uploads/hero/' . $name : null;
            if ($err) {
                flash('error', $err);
                redirect('admin/hero.php' . ($id ? "?edit=$id" : ''));
            }
        }

        if ($id) {
            if ($imagePath) $fields['image'] = $imagePath;
            Database::run(
                'UPDATE hero_slides SET title=?, subtitle=?, description=?, cta1_label=?, cta1_url=?,
                    cta2_label=?, cta2_url=?, overlay=?, sort_order=?, is_active=?'
                    . ($imagePath ? ', image=?' : '') . ' WHERE id=?',
                array_merge(array_values($fields), $imagePath ? [$imagePath, $id] : [$id])
            );
            Audit::log(Auth::id(), 'update', 'content', 'hero_slide', $id);
            flash('success', 'Slide updated.');
        } else {
            if (!$imagePath) {
                flash('error', 'An image is required for a new slide.');
                redirect('admin/hero.php');
            }
            $newId = Database::insert(
                'INSERT INTO hero_slides (title, subtitle, description, cta1_label, cta1_url,
                    cta2_label, cta2_url, overlay, sort_order, is_active, image)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                array_merge(array_values($fields), [$imagePath])
            );
            Audit::log(Auth::id(), 'create', 'content', 'hero_slide', $newId);
            flash('success', 'Slide created.');
        }
        redirect('admin/hero.php');
    }

    if ($action === 'delete' && $id) {
        $img = Database::value('SELECT image FROM hero_slides WHERE id = ?', [$id]);
        Database::run('DELETE FROM hero_slides WHERE id = ?', [$id]);
        if ($img && str_starts_with($img, 'uploads/hero/') && file_exists(PUBLIC_PATH . '/' . $img)) {
            @unlink(PUBLIC_PATH . '/' . $img);
        }
        Audit::log(Auth::id(), 'delete', 'content', 'hero_slide', $id);
        flash('success', 'Slide deleted.');
        redirect('admin/hero.php');
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $editing = Database::one('SELECT * FROM hero_slides WHERE id = ?', [(int) $_GET['edit']]);
}
$slides = Database::all('SELECT * FROM hero_slides ORDER BY sort_order, id');

$pageTitle = 'Hero Slides';
$active = 'settings';
require APP_PATH . '/views/admin/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 card !p-0 overflow-x-auto">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800">Slides (<?= count($slides) ?>/20)</h2>
            <p class="text-xs text-slate-500">Shown on the homepage slider, in order.</p>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr>
                <th class="th">#</th><th class="th">Slide</th><th class="th">Overlay</th>
                <th class="th">Order</th><th class="th">Status</th><th class="th text-right">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($slides as $i => $s): ?>
            <tr class="table-row">
                <td class="td"><?= $i + 1 ?></td>
                <td class="td">
                    <div class="flex items-center gap-3 min-w-[220px]">
                        <img src="<?= url($s['image']) ?>" alt="" class="w-16 h-10 object-cover rounded border border-gray-200">
                        <div>
                            <p class="font-medium text-slate-800"><?= e($s['title']) ?></p>
                            <p class="text-xs text-slate-500"><?= e(mb_strimwidth((string) $s['description'], 0, 50, '…')) ?></p>
                        </div>
                    </div>
                </td>
                <td class="td"><?= (int) $s['overlay'] ?>%</td>
                <td class="td"><?= (int) $s['sort_order'] ?></td>
                <td class="td"><?= status_badge($s['is_active'] ? 'active' : 'inactive') ?></td>
                <td class="td text-right whitespace-nowrap">
                    <a href="<?= url('admin/hero.php?edit=' . $s['id']) ?>" class="text-blue-600 text-sm hover:underline">Edit</a>
                    <form method="post" class="inline" onsubmit="return confirm('Delete this slide?')">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slide_id" value="<?= $s['id'] ?>">
                        <button class="text-red-600 text-sm hover:underline ml-3">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$slides): ?>
            <tr><td colspan="6" class="td text-center text-slate-400 py-8">No slides yet — add one to activate the homepage slider.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="card h-fit">
        <h2 class="font-semibold text-slate-800 mb-4"><?= $editing ? 'Edit slide #' . $editing['id'] : 'New slide' ?></h2>
        <form method="post" enctype="multipart/form-data" class="space-y-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="slide_id" value="<?= $editing['id'] ?? 0 ?>">
            <div><label class="label">Title (vehicle name)</label>
                <input name="title" class="input" required value="<?= e($editing['title'] ?? '') ?>" maxlength="120"></div>
            <div><label class="label">Subtitle (optional kicker)</label>
                <input name="subtitle" class="input" value="<?= e($editing['subtitle'] ?? '') ?>" maxlength="255"></div>
            <div><label class="label">Description — specs &amp; pricing</label>
                <textarea name="description" class="input" rows="3"><?= e($editing['description'] ?? '') ?></textarea></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Button 1 label</label>
                    <input name="cta1_label" class="input" value="<?= e($editing['cta1_label'] ?? 'Book Now') ?>"></div>
                <div><label class="label">Button 1 URL</label>
                    <input name="cta1_url" class="input" value="<?= e($editing['cta1_url'] ?? 'vehicles.php') ?>"></div>
                <div><label class="label">Button 2 label</label>
                    <input name="cta2_label" class="input" value="<?= e($editing['cta2_label'] ?? 'Our Fleet') ?>"></div>
                <div><label class="label">Button 2 URL</label>
                    <input name="cta2_url" class="input" value="<?= e($editing['cta2_url'] ?? 'vehicles.php') ?>"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Dark overlay (60–80%)</label>
                    <input type="number" name="overlay" class="input" min="60" max="80"
                           value="<?= (int) ($editing['overlay'] ?? 70) ?>"></div>
                <div><label class="label">Sort order</label>
                    <input type="number" name="sort_order" class="input"
                           value="<?= (int) ($editing['sort_order'] ?? 0) ?>"></div>
            </div>
            <div>
                <label class="label">Background image <?= $editing ? '(leave blank to keep)' : '' ?></label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="input !py-1.5" <?= $editing ? '' : 'required' ?>>
                <?php if ($editing): ?>
                <img src="<?= url($editing['image']) ?>" class="mt-2 h-20 rounded border border-gray-200 object-cover">
                <?php endif; ?>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" <?= !$editing || $editing['is_active'] ? 'checked' : '' ?>> Active
            </label>
            <div class="flex gap-3 pt-1">
                <button class="btn-primary"><?= $editing ? 'Save changes' : 'Add slide' ?></button>
                <?php if ($editing): ?>
                <a href="<?= url('admin/hero.php') ?>" class="btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php require APP_PATH . '/views/admin/footer.php'; ?>
