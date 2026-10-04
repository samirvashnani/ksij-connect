<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
require_once dirname(__DIR__, 2) . '/includes/admin_helpers.php';

$staff = requireRole('admin');
$pdo = getDb();

$success = '';
$error = '';
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$record = null;

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM general_info WHERE id = ?');
    $stmt->execute([$editId]);
    $record = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM general_info WHERE id = ?')->execute([$id]);
        $success = 'General info deleted.';
    } else {
        $data = [
            'category' => trim((string) ($_POST['category'] ?? '')),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'content' => trim((string) ($_POST['content'] ?? '')),
        ];

        if ($data['title'] === '' || $data['content'] === '') {
            $error = 'Title and content are required.';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE general_info SET category = ?, title = ?, content = ? WHERE id = ?');
                $stmt->execute([$data['category'], $data['title'], $data['content'], $id]);
                $success = 'General info updated.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO general_info (category, title, content) VALUES (?, ?, ?)');
                $stmt->execute([$data['category'], $data['title'], $data['content']]);
                $success = 'General info created.';
            }
        }
    }
}

adminRedirectAfterSuccess('public/admin/general_info.php', $success);
$rows = $pdo->query('SELECT * FROM general_info ORDER BY category, title')->fetchAll();

adminPageStart('General information', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-grid two-columns">
    <section class="admin-card">
        <h2><?= $record ? 'Edit info' : 'Add info' ?></h2>
        <form method="post" class="admin-form">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= escapeHtml((string) ($record['id'] ?? 0)) ?>">
            <label>Category<input name="category" value="<?= escapeHtml((string) ($record['category'] ?? '')) ?>"></label>
            <label>Title<input name="title" value="<?= escapeHtml((string) ($record['title'] ?? '')) ?>" required></label>
            <label>Content<textarea name="content" rows="5" required><?= escapeHtml((string) ($record['content'] ?? '')) ?></textarea></label>
            <button type="submit">Save item</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Info list</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Category</th><th>Title</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= escapeHtml($row['category'] ?: '—') ?></td>
                            <td><?= escapeHtml($row['title']) ?></td>
                            <td class="row-actions">
                                <a href="<?= escapeHtml(appUrl('public/admin/general_info.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this item?');">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= escapeHtml((string) $row['id']) ?>">
                                    <button type="submit" class="danger-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php adminPageEnd(); ?>
