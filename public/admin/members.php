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
    $stmt = $pdo->prepare('SELECT id, membership_id, full_name, email, phone, area, membership_status, fees_due, fees_last_paid_date, renewal_date, payment_link FROM members WHERE id = ?');
    $stmt->execute([$editId]);
    $record = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM members WHERE id = ?')->execute([$id]);
            $success = 'Member deleted.';
        } catch (Throwable $e) {
            $error = 'This member record is still linked to requests or transactions and cannot be deleted.';
        }
    } else {
        $data = [
            'membership_id' => trim((string) ($_POST['membership_id'] ?? '')),
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'area' => trim((string) ($_POST['area'] ?? '')),
            'membership_status' => trim((string) ($_POST['membership_status'] ?? 'active')),
            'fees_due' => (float) ($_POST['fees_due'] ?? 0),
            'fees_last_paid_date' => $_POST['fees_last_paid_date'] ?? null,
            'renewal_date' => $_POST['renewal_date'] ?? null,
            'payment_link' => trim((string) ($_POST['payment_link'] ?? '')),
        ];

        if ($data['membership_id'] === '' || $data['full_name'] === '' || $data['email'] === '') {
            $error = 'Membership ID, name and email are required.';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE members SET membership_id = ?, full_name = ?, email = ?, phone = ?, area = ?, membership_status = ?, fees_due = ?, fees_last_paid_date = ?, renewal_date = ?, payment_link = ? WHERE id = ?');
                $stmt->execute([$data['membership_id'], $data['full_name'], $data['email'], $data['phone'], $data['area'], $data['membership_status'], $data['fees_due'], $data['fees_last_paid_date'], $data['renewal_date'], $data['payment_link'], $id]);
                $success = 'Member updated.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO members (membership_id, full_name, email, phone, area, membership_status, fees_due, fees_last_paid_date, renewal_date, payment_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$data['membership_id'], $data['full_name'], $data['email'], $data['phone'], $data['area'], $data['membership_status'], $data['fees_due'], $data['fees_last_paid_date'], $data['renewal_date'], $data['payment_link']]);
                $success = 'Member created.';
            }
        }
    }
}

adminRedirectAfterSuccess('public/admin/members.php', $success);
$rows = $pdo->query('SELECT id, membership_id, full_name, area FROM members ORDER BY id DESC')->fetchAll();

adminPageStart('Members', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-grid two-columns">
    <section class="admin-card">
        <h2><?= $record ? 'Edit member' : 'Add member' ?></h2>
        <form method="post" class="admin-form">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= escapeHtml((string) ($record['id'] ?? 0)) ?>">
            <div class="two-field-row">
                <label>Membership ID<input name="membership_id" value="<?= escapeHtml((string) ($record['membership_id'] ?? '')) ?>" required></label>
                <label>Status
                    <select name="membership_status">
                        <?php foreach (['active','pending','expired'] as $status): ?>
                            <option value="<?= escapeHtml($status) ?>" <?= (($record['membership_status'] ?? 'active') === $status) ? 'selected' : '' ?>><?= escapeHtml(ucfirst($status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Full name<input name="full_name" value="<?= escapeHtml((string) ($record['full_name'] ?? '')) ?>" required></label>
            <div class="two-field-row">
                <label>Email<input type="email" name="email" value="<?= escapeHtml((string) ($record['email'] ?? '')) ?>" required></label>
                <label>Phone<input name="phone" value="<?= escapeHtml((string) ($record['phone'] ?? '')) ?>"></label>
            </div>
            <label>Area<input name="area" value="<?= escapeHtml((string) ($record['area'] ?? '')) ?>"></label>
            <div class="two-field-row">
                <label>Fees due<input step="0.01" type="number" name="fees_due" value="<?= escapeHtml((string) ($record['fees_due'] ?? '0')) ?>"></label>
                <label>Payment link<input name="payment_link" value="<?= escapeHtml((string) ($record['payment_link'] ?? '')) ?>"></label>
            </div>
            <div class="two-field-row">
                <label>Membership Fees last paid<input type="date" name="fees_last_paid_date" value="<?= escapeHtml((string) ($record['fees_last_paid_date'] ?? '')) ?>"></label>
                <label>Renewal date<input type="date" name="renewal_date" value="<?= escapeHtml((string) ($record['renewal_date'] ?? '')) ?>"></label>
            </div>
            <button type="submit">Save member</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Member list</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Name</th><th>Area</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= escapeHtml($row['membership_id']) ?></td>
                            <td><?= escapeHtml($row['full_name']) ?></td>
                            <td><?= escapeHtml($row['area'] ?: '—') ?></td>
                            <td class="row-actions">
                                <a href="<?= escapeHtml(appUrl('public/admin/members.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this member?');">
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
