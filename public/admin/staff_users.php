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
    $stmt = $pdo->prepare('SELECT * FROM staff_users WHERE id = ?');
    $stmt->execute([$editId]);
    $record = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM staff_users WHERE id = ?')->execute([$id]);
        $success = 'Staff member deleted.';
    } else {
        $data = [
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'username' => trim((string) ($_POST['username'] ?? '')),
            'role' => trim((string) ($_POST['role'] ?? 'volunteer')),
            'area' => trim((string) ($_POST['area'] ?? '')),
            'is_guarantor_approved' => isset($_POST['is_guarantor_approved']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
        ];

        if ($data['full_name'] === '' || $data['username'] === '') {
            $error = 'Full name and username are required.';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $sql = 'UPDATE staff_users SET full_name = ?, username = ?, role = ?, area = ?, is_guarantor_approved = ?, is_active = ?, phone = ?, email = ?';
                $params = [$data['full_name'], $data['username'], $data['role'], $data['area'], $data['is_guarantor_approved'], $data['is_active'], $data['phone'], $data['email']];
                if ($data['password'] !== '') {
                    $sql .= ', password_hash = ?';
                    $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
                }
                $sql .= ' WHERE id = ?';
                $params[] = $id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success = 'Staff member updated.';
            } else {
                if ($data['password'] === '') {
                    $error = 'A password is required when creating a staff account.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO staff_users (full_name, username, password_hash, role, area, is_guarantor_approved, is_active, phone, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$data['full_name'], $data['username'], password_hash($data['password'], PASSWORD_BCRYPT), $data['role'], $data['area'], $data['is_guarantor_approved'], $data['is_active'], $data['phone'], $data['email']]);
                    $success = 'Staff member created.';
                }
            }
        }
    }
}

adminRedirectAfterSuccess('public/admin/staff_users.php', $success);
$rows = $pdo->query('SELECT * FROM staff_users ORDER BY role, full_name')->fetchAll();

adminPageStart('Staff users', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-grid two-columns">
    <section class="admin-card">
        <h2><?= $record ? 'Edit staff member' : 'Add staff member' ?></h2>
        <form method="post" class="admin-form">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= escapeHtml((string) ($record['id'] ?? 0)) ?>">
            <div class="two-field-row">
                <label>Full name<input name="full_name" value="<?= escapeHtml((string) ($record['full_name'] ?? '')) ?>" required></label>
                <label>Username<input name="username" value="<?= escapeHtml((string) ($record['username'] ?? '')) ?>" required></label>
            </div>
            <div class="two-field-row">
                <label>Role
                    <select name="role">
                        <?php foreach (['volunteer','cc_member','admin'] as $role): ?>
                            <option value="<?= escapeHtml($role) ?>" <?= (($record['role'] ?? 'volunteer') === $role) ? 'selected' : '' ?>><?= escapeHtml(ucfirst(str_replace('_', ' ', $role))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Area<input name="area" value="<?= escapeHtml((string) ($record['area'] ?? '')) ?>"></label>
            </div>
            <div class="two-field-row">
                <label>Phone<input name="phone" value="<?= escapeHtml((string) ($record['phone'] ?? '')) ?>"></label>
                <label>Email<input type="email" name="email" value="<?= escapeHtml((string) ($record['email'] ?? '')) ?>"></label>
            </div>
            <label>Password<input type="password" name="password" <?= $record ? '' : 'required' ?> placeholder="<?= $record ? 'Leave blank to keep current password' : 'Set a password' ?>"></label>
            <div class="two-field-row">
                <label class="checkbox-row"><input type="checkbox" name="is_guarantor_approved" value="1" <?= !empty($record['is_guarantor_approved']) ? 'checked' : '' ?>> Approved as guarantor</label>
                <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= !empty($record['is_active']) ? 'checked' : '' ?>> Active</label>
            </div>
            <button type="submit">Save staff member</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Staff roster</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Role</th><th>Area</th><th>Guarantor</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= escapeHtml($row['full_name']) ?></td>
                            <td><?= escapeHtml($row['role']) ?></td>
                            <td><?= escapeHtml($row['area'] ?: '—') ?></td>
                            <td><?= !empty($row['is_guarantor_approved']) ? 'Yes' : 'No' ?></td>
                            <td class="row-actions">
                                <a href="<?= escapeHtml(appUrl('public/admin/staff_users.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this staff record?');">
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
