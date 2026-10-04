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
    $stmt = $pdo->prepare('SELECT * FROM contacts WHERE id = ?');
    $stmt->execute([$editId]);
    $record = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM contacts WHERE id = ?')->execute([$id]);
        $success = 'Contact deleted.';
    } else {
        $data = [
            'department' => trim((string) ($_POST['department'] ?? '')),
            'person_name' => trim((string) ($_POST['person_name'] ?? '')),
            'designation' => trim((string) ($_POST['designation'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'availability' => trim((string) ($_POST['availability'] ?? '')),
        ];

        if ($data['department'] === '') {
            $error = 'Department is required.';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE contacts SET department = ?, person_name = ?, designation = ?, phone = ?, email = ?, availability = ? WHERE id = ?');
                $stmt->execute([$data['department'], $data['person_name'], $data['designation'], $data['phone'], $data['email'], $data['availability'], $id]);
                $success = 'Contact updated.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO contacts (department, person_name, designation, phone, email, availability) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$data['department'], $data['person_name'], $data['designation'], $data['phone'], $data['email'], $data['availability']]);
                $success = 'Contact created.';
            }
        }
    }
}

adminRedirectAfterSuccess('public/admin/contacts.php', $success);
$rows = $pdo->query('SELECT * FROM contacts ORDER BY department, person_name')->fetchAll();

adminPageStart('Contacts', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-grid two-columns">
    <section class="admin-card">
        <h2><?= $record ? 'Edit contact' : 'Add contact' ?></h2>
        <form method="post" class="admin-form">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= escapeHtml((string) ($record['id'] ?? 0)) ?>">
            <label>Department<input name="department" value="<?= escapeHtml((string) ($record['department'] ?? '')) ?>" required></label>
            <label>Person name<input name="person_name" value="<?= escapeHtml((string) ($record['person_name'] ?? '')) ?>"></label>
            <label>Designation<input name="designation" value="<?= escapeHtml((string) ($record['designation'] ?? '')) ?>"></label>
            <div class="two-field-row">
                <label>Phone<input name="phone" value="<?= escapeHtml((string) ($record['phone'] ?? '')) ?>"></label>
                <label>Email<input type="email" name="email" value="<?= escapeHtml((string) ($record['email'] ?? '')) ?>"></label>
            </div>
            <label>Availability<input name="availability" value="<?= escapeHtml((string) ($record['availability'] ?? '')) ?>"></label>
            <button type="submit">Save contact</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Contact list</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Department</th><th>Person</th><th>Phone</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= escapeHtml($row['department']) ?></td>
                            <td><?= escapeHtml($row['person_name'] ?: '—') ?></td>
                            <td><?= escapeHtml($row['phone'] ?: '—') ?></td>
                            <td class="row-actions">
                                <a href="<?= escapeHtml(appUrl('public/admin/contacts.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this contact?');">
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
