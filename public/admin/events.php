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
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$editId]);
    $record = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
            $success = 'Event deleted.';
        } catch (Throwable $e) {
            $error = 'Unable to delete this event.';
        }
    } else {
        $data = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'event_date' => $_POST['event_date'] ?? null,
            'event_time' => $_POST['event_time'] ?? null,
            'venue' => trim((string) ($_POST['venue'] ?? '')),
            'registration_required' => isset($_POST['registration_required']) ? 1 : 0,
            'registration_link' => trim((string) ($_POST['registration_link'] ?? '')),
        ];

        if ($data['title'] === '' || $data['description'] === '') {
            $error = 'Title and description are required.';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE events SET title = ?, description = ?, event_date = ?, event_time = ?, venue = ?, registration_required = ?, registration_link = ? WHERE id = ?');
                $stmt->execute([$data['title'], $data['description'], $data['event_date'], $data['event_time'], $data['venue'], $data['registration_required'], $data['registration_link'], $id]);
                $success = 'Event updated.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO events (title, description, event_date, event_time, venue, registration_required, registration_link) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$data['title'], $data['description'], $data['event_date'], $data['event_time'], $data['venue'], $data['registration_required'], $data['registration_link']]);
                $success = 'Event created.';
            }
        }
    }
}

adminRedirectAfterSuccess('public/admin/events.php', $success);
$rows = $pdo->query('SELECT * FROM events ORDER BY event_date DESC, id DESC')->fetchAll();

adminPageStart('Manage events', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-grid two-columns">
    <section class="admin-card">
        <h2><?= $record ? 'Edit event' : 'Add event' ?></h2>
        <form method="post" class="admin-form">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= escapeHtml((string) ($record['id'] ?? 0)) ?>">
            <label>Title<input name="title" value="<?= escapeHtml((string) ($record['title'] ?? '')) ?>" required></label>
            <label>Description<textarea name="description" rows="4" required><?= escapeHtml((string) ($record['description'] ?? '')) ?></textarea></label>
            <div class="two-field-row">
                <label>Event date<input type="date" name="event_date" value="<?= escapeHtml((string) ($record['event_date'] ?? '')) ?>"></label>
                <label>Event time<input type="time" name="event_time" value="<?= escapeHtml((string) ($record['event_time'] ?? '')) ?>"></label>
            </div>
            <label>Venue<input name="venue" value="<?= escapeHtml((string) ($record['venue'] ?? '')) ?>"></label>
            <label>Registration link (If Online)<input name="registration_link" value="<?= escapeHtml((string) ($record['registration_link'] ?? '')) ?>"></label>
            <label class="checkbox-row"><input type="checkbox" name="registration_required" value="1" <?= !empty($record['registration_required']) ? 'checked' : '' ?>> Registration required</label>
            <button type="submit">Save event</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Existing events</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Title</th><th>Date</th><th>Venue</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= escapeHtml($row['title']) ?></td>
                            <td><?= escapeHtml(formatDate($row['event_date'])) ?></td>
                            <td><?= escapeHtml($row['venue'] ?: '—') ?></td>
                            <td class="row-actions">
                                <a href="<?= escapeHtml(appUrl('public/admin/events.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this event?');">
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
