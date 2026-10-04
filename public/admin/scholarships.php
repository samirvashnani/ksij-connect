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
    $stmt = $pdo->prepare('SELECT * FROM scholarships WHERE id = ?');
    $stmt->execute([$editId]);
    $record = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM scholarships WHERE id = ?')->execute([$id]);
        $success = 'Scholarship deleted.';
    } else {
        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'eligibility' => trim((string) ($_POST['eligibility'] ?? '')),
            'amount' => trim((string) ($_POST['amount'] ?? '')),
            'deadline' => $_POST['deadline'] ?? null,
            'how_to_apply' => trim((string) ($_POST['how_to_apply'] ?? '')),
        ];

        if ($data['name'] === '' || $data['eligibility'] === '') {
            $error = 'Name and eligibility are required.';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE scholarships SET name = ?, eligibility = ?, amount = ?, deadline = ?, how_to_apply = ? WHERE id = ?');
                $stmt->execute([$data['name'], $data['eligibility'], $data['amount'], $data['deadline'], $data['how_to_apply'], $id]);
                $success = 'Scholarship updated.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO scholarships (name, eligibility, amount, deadline, how_to_apply) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$data['name'], $data['eligibility'], $data['amount'], $data['deadline'], $data['how_to_apply']]);
                $success = 'Scholarship created.';
            }
        }
    }
}

adminRedirectAfterSuccess('public/admin/scholarships.php', $success);
$rows = $pdo->query('SELECT * FROM scholarships ORDER BY deadline DESC, id DESC')->fetchAll();

adminPageStart('Scholarships', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-grid two-columns">
    <section class="admin-card">
        <h2><?= $record ? 'Edit scholarship' : 'Add scholarship' ?></h2>
        <form method="post" class="admin-form">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= escapeHtml((string) ($record['id'] ?? 0)) ?>">
            <label>Name<input name="name" value="<?= escapeHtml((string) ($record['name'] ?? '')) ?>" required></label>
            <label>Eligibility<textarea name="eligibility" rows="4" required><?= escapeHtml((string) ($record['eligibility'] ?? '')) ?></textarea></label>
            <div class="two-field-row">
                <label>Amount<input name="amount" value="<?= escapeHtml((string) ($record['amount'] ?? '')) ?>"></label>
                <label>Deadline<input type="date" name="deadline" value="<?= escapeHtml((string) ($record['deadline'] ?? '')) ?>"></label>
            </div>
            <label>How to apply<textarea name="how_to_apply" rows="4"><?= escapeHtml((string) ($record['how_to_apply'] ?? '')) ?></textarea></label>
            <button type="submit">Save scholarship</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Scholarship list</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Amount</th><th>Deadline</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= escapeHtml($row['name']) ?></td>
                            <td><?= escapeHtml($row['amount'] ?: '—') ?></td>
                            <td><?= escapeHtml(formatDate($row['deadline'])) ?></td>
                            <td class="row-actions">
                                <a href="<?= escapeHtml(appUrl('public/admin/scholarships.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this scholarship?');">
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
