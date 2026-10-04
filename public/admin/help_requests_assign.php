<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
require_once dirname(__DIR__, 2) . '/includes/admin_helpers.php';
require_once dirname(__DIR__, 2) . '/includes/notifications.php';

$staff = requireRole('admin');
$pdo = getDb();

$success = '';
$error = '';
$volunteers = $pdo->query("SELECT id, full_name, area FROM staff_users WHERE role IN ('volunteer', 'cc_member') AND is_active = 1 ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'assign') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $volunteerId = (int) ($_POST['volunteer_id'] ?? 0);
        if ($requestId <= 0 || $volunteerId <= 0) {
            $error = 'Please choose a valid volunteer.';
        } else {
            $request = $pdo->prepare('SELECT h.*, m.full_name AS member_name, m.area AS member_area FROM help_requests h LEFT JOIN members m ON m.id = h.member_id WHERE h.id = ?');
            $request->execute([$requestId]);
            $helpRequest = $request->fetch();
            if (!$helpRequest) {
                $error = 'Request not found.';
            } else {
                $eligibleVolunteer = $pdo->prepare("SELECT id, area FROM staff_users WHERE id = ? AND role IN ('volunteer', 'cc_member') AND is_active = 1");
                $eligibleVolunteer->execute([$volunteerId]);
                $volunteer = $eligibleVolunteer->fetch();
                $memberArea = trim((string) ($helpRequest['member_area'] ?? ''));
                $volunteerArea = trim((string) ($volunteer['area'] ?? ''));
                if (!$volunteer || ($memberArea !== '' && ($volunteerArea === '' || strcasecmp($memberArea, $volunteerArea) !== 0))) {
                    $error = $memberArea
                        ? 'Choose an active volunteer from ' . $memberArea . '.'
                        : 'Please choose an active volunteer.';
                } else {
                    $pdo->prepare('UPDATE help_requests SET assigned_volunteer_id = ?, assigned_by_staff_id = ?, status = ? WHERE id = ?')->execute([$volunteerId, $staff['id'], 'assigned', $requestId]);
                    createNotification('staff', $volunteerId, 'Help request assigned', 'A new support request has been assigned to you.');
                    $success = 'Request assigned to volunteer.';
                }
            }
        }
    }
}

adminRedirectAfterSuccess('public/admin/help_requests_assign.php', $success);
$statusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$categoryFilter = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
$areaFilter = is_string($_GET['area'] ?? null) ? trim($_GET['area']) : '';
$statuses = ['open', 'assigned', 'resolved'];
$categories = ['elderly_help', 'urgent_medical', 'other'];
if (!in_array($statusFilter, $statuses, true)) {
    $statusFilter = '';
}
if (!in_array($categoryFilter, $categories, true)) {
    $categoryFilter = '';
}
$areas = $pdo->query("SELECT DISTINCT m.area FROM help_requests h JOIN members m ON m.id = h.member_id WHERE m.area IS NOT NULL AND TRIM(m.area) <> '' ORDER BY m.area")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($areaFilter, $areas, true)) {
    $areaFilter = '';
}

$conditions = [];
$params = [];
if ($statusFilter !== '') {
    $conditions[] = 'h.status = ?';
    $params[] = $statusFilter;
}
if ($categoryFilter !== '') {
    $conditions[] = 'h.category = ?';
    $params[] = $categoryFilter;
}
if ($areaFilter !== '') {
    $conditions[] = 'm.area = ?';
    $params[] = $areaFilter;
}
$sql = 'SELECT h.*, m.full_name AS member_name, m.membership_id, m.area AS member_area, v.full_name AS volunteer_name FROM help_requests h LEFT JOIN members m ON m.id = h.member_id LEFT JOIN staff_users v ON v.id = h.assigned_volunteer_id';
if ($conditions !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY h.created_at DESC';
$rowQuery = $pdo->prepare($sql);
$rowQuery->execute($params);
$rows = $rowQuery->fetchAll();

adminPageStart('Assign help requests', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-card">
    <h2>Open and assigned help requests</h2>
    <form method="get" class="task-filters" aria-label="Filter help requests">
        <label>Status
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= escapeHtml($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= escapeHtml(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Category
            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= escapeHtml($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= escapeHtml(ucfirst(str_replace('_', ' ', $category))) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Member area
            <select name="area">
                <option value="">All areas</option>
                <?php foreach ($areas as $area): ?>
                    <option value="<?= escapeHtml($area) ?>" <?= $areaFilter === $area ? 'selected' : '' ?>><?= escapeHtml($area) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="small-button">Apply filters</button>
        <a class="filter-reset" href="<?= escapeHtml(appUrl('public/admin/help_requests_assign.php')) ?>">Clear</a>
    </form>
    <p class="filter-summary"><?= count($rows) ?> request<?= count($rows) === 1 ? '' : 's' ?> shown</p>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Member</th><th>Area</th><th>Category</th><th>Description</th><th>Assigned</th><th>Assign</th></tr></thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="6" class="empty-state">No help requests match these filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $eligibleVolunteers = array_filter($volunteers, static function (array $volunteer) use ($row): bool {
                        $memberArea = trim((string) ($row['member_area'] ?? ''));
                        $volunteerArea = trim((string) ($volunteer['area'] ?? ''));
                        return $memberArea === '' || ($volunteerArea !== '' && strcasecmp($memberArea, $volunteerArea) === 0);
                    });
                    ?>
                    <tr>
                        <td><?= escapeHtml($row['member_name'] ?: 'Community task') ?><br><small><?= escapeHtml($row['membership_id'] ?: '') ?></small></td>
                        <td><?= escapeHtml($row['member_area'] ?: '—') ?></td>
                        <td><?= escapeHtml(ucfirst(str_replace('_', ' ', $row['category']))) ?></td>
                        <td><?= escapeHtml($row['description']) ?></td>
                        <td><?= escapeHtml($row['volunteer_name'] ?: 'Unassigned') ?><br><small><?= escapeHtml($row['status']) ?></small></td>
                        <td>
                            <?php if ($row['status'] === 'open' || $row['status'] === 'assigned'): ?>
                                <form method="post" class="inline-form">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="assign">
                                    <input type="hidden" name="request_id" value="<?= escapeHtml((string) $row['id']) ?>">
                                    <select name="volunteer_id" required>
                                        <option value="">Select <?= $row['member_area'] ? escapeHtml($row['member_area']) . ' volunteer' : 'volunteer' ?></option>
                                        <?php foreach ($eligibleVolunteers as $volunteer): ?>
                                            <option value="<?= escapeHtml((string) $volunteer['id']) ?>"><?= escapeHtml($volunteer['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="small-button" <?= $eligibleVolunteers === [] ? 'disabled' : '' ?>>Assign</button>
                                    <?php if ($eligibleVolunteers === []): ?>
                                        <small class="muted-text">No active volunteers are listed for this area.</small>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span class="muted-text">Resolved</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php adminPageEnd(); ?>
