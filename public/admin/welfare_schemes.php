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
    $stmt = $pdo->prepare('SELECT * FROM welfare_schemes WHERE id = ?');
    $stmt->execute([$editId]);
    $record = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM welfare_schemes WHERE id = ?')->execute([$id]);
        $success = 'Welfare scheme deleted.';
    } else {
        $data = [
            'scheme_name' => trim((string) ($_POST['scheme_name'] ?? '')),
            'type' => trim((string) ($_POST['type'] ?? '')),
            'eligibility' => trim((string) ($_POST['eligibility'] ?? '')),
            'coverage' => trim((string) ($_POST['coverage'] ?? '')),
            'how_to_apply' => trim((string) ($_POST['how_to_apply'] ?? '')),
        ];

        if ($data['scheme_name'] === '' || $data['eligibility'] === '') {
            $error = 'Scheme name and eligibility are required.';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE welfare_schemes SET scheme_name = ?, type = ?, eligibility = ?, coverage = ?, how_to_apply = ? WHERE id = ?');
                $stmt->execute([$data['scheme_name'], $data['type'], $data['eligibility'], $data['coverage'], $data['how_to_apply'], $id]);
                $success = 'Welfare scheme updated.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO welfare_schemes (scheme_name, type, eligibility, coverage, how_to_apply) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$data['scheme_name'], $data['type'], $data['eligibility'], $data['coverage'], $data['how_to_apply']]);
                $success = 'Welfare scheme created.';
            }
        }
    }
}

adminRedirectAfterSuccess('public/admin/welfare_schemes.php', $success);
$rows = $pdo->query('SELECT * FROM welfare_schemes ORDER BY id DESC')->fetchAll();

adminPageStart('Welfare schemes', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-grid two-columns">
    <section class="admin-card">
        <h2><?= $record ? 'Edit scheme' : 'Add scheme' ?></h2>
        <form method="post" class="admin-form">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= escapeHtml((string) ($record['id'] ?? 0)) ?>">
            <label>Scheme name<input name="scheme_name" value="<?= escapeHtml((string) ($record['scheme_name'] ?? '')) ?>" required></label>
            <label>Type
                <select name="type">
                    <?php foreach (['medical','ration','education','emergency','other'] as $option): ?>
                        <option value="<?= escapeHtml($option) ?>" <?= (($record['type'] ?? '') === $option) ? 'selected' : '' ?>><?= escapeHtml(ucwords(str_replace('_', ' ', $option))) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Eligibility<textarea name="eligibility" rows="4" required><?= escapeHtml((string) ($record['eligibility'] ?? '')) ?></textarea></label>
            <label>Coverage<textarea name="coverage" rows="3"><?= escapeHtml((string) ($record['coverage'] ?? '')) ?></textarea></label>
            <label>How to apply<textarea name="how_to_apply" rows="3"><?= escapeHtml((string) ($record['how_to_apply'] ?? '')) ?></textarea></label>
            <button type="submit">Save scheme</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Scheme list</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Type</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= escapeHtml($row['scheme_name']) ?></td>
                            <td><?= escapeHtml($row['type']) ?></td>
                            <td class="row-actions">
                                <a href="<?= escapeHtml(appUrl('public/admin/welfare_schemes.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this scheme?');">
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
