<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
$staff = requireRole('admin');
require_once dirname(__DIR__, 2) . '/includes/community_help_view.php';
require_once dirname(__DIR__, 2) . '/includes/admin_help.php';
header('Cache-Control: no-store, private');
$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$error = $loadError = $success = '';
$pdo = getDb();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'Your session changed. Reload before assigning a request.']);
            exit;
        }
        checkCsrf();
    }
    $statusCode = 422;
    try {
        $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $assigneeId = filter_var($_POST['volunteer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $expectedAssignee = filter_var($_POST['expected_assignee'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $expectedStatus = $_POST['expected_status'] ?? '';
        if (($_POST['action'] ?? '') !== 'assign' || !$requestId || !$assigneeId || $expectedAssignee === false
            || $expectedAssignee === null || !in_array($expectedStatus, ['open', 'assigned'], true)) {
            throw new DomainException('Choose a valid request and team member.');
        }
        assignCommunityHelp((int) $staff['id'], $requestId, $assigneeId, $expectedAssignee, $expectedStatus);
        $success = 'Help request #' . $requestId . ' assigned successfully.';
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Office help assignment failed: ' . get_class($exception));
        $error = 'The assignment could not be saved. Refresh the queue before trying again.';
        $statusCode = 500;
    }
    if ($ajax) {
        http_response_code($error !== '' ? $statusCode : 200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $error === '', 'message' => $error !== '' ? $error : $success]);
        exit;
    }
    if ($success !== '') {
        $_SESSION['community_help_admin_success'] = $success;
        $query = [];
        foreach (['status', 'category', 'area', 'search', 'page'] as $key) {
            if (isset($_GET[$key]) && is_string($_GET[$key])) { $query[$key] = $_GET[$key]; }
        }
        redirectTo('public/admin/help_requests_assign.php' . ($query ? '?' . http_build_query($query) : ''));
    }
}
if (!$ajax) {
    $success = $_SESSION['community_help_admin_success'] ?? '';
    unset($_SESSION['community_help_admin_success']);
}
$categories = communityHelpCategories();
$statuses = ['open' => 'Open', 'assigned' => 'Assigned', 'resolved' => 'Resolved'];
$statusFilter = is_string($_GET['status'] ?? null) && isset($statuses[$_GET['status']]) ? $_GET['status'] : '';
$categoryFilter = is_string($_GET['category'] ?? null) && isset($categories[$_GET['category']]) ? $_GET['category'] : '';
$areaFilter = is_string($_GET['area'] ?? null) && preg_match('//u', $_GET['area']) ? mb_substr(trim($_GET['area']), 0, 100) : '';
$search = is_string($_GET['search'] ?? null) && preg_match('//u', $_GET['search']) ? mb_substr(trim($_GET['search']), 0, 120) : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$perPage = 12;
$pages = 1;
$total = 0;
$counts = ['open' => 0, 'assigned' => 0, 'resolved' => 0];
$areas = $volunteers = $rows = [];
try {
    $volunteers = $pdo->query("SELECT id, full_name, area, role FROM staff_users WHERE role IN ('volunteer', 'cc_member') AND is_active = 1 ORDER BY full_name, id")->fetchAll();
    $areas = $pdo->query("SELECT DISTINCT TRIM(m.area) AS area FROM help_requests h JOIN members m ON m.id = h.member_id WHERE m.area IS NOT NULL AND TRIM(m.area) <> '' ORDER BY area")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($areaFilter, $areas, true)) { $areaFilter = ''; }
    $conditions = ['1 = 1'];
    $parameters = [];
    foreach (['h.status' => $statusFilter, 'h.category' => $categoryFilter, 'TRIM(m.area)' => $areaFilter] as $field => $value) {
        if ($value !== '') { $conditions[] = $field . ' = ?'; $parameters[] = $value; }
    }
    if ($search !== '') {
        $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
        $conditions[] = "(h.description LIKE ? ESCAPE '!' OR m.full_name LIKE ? ESCAPE '!' OR m.membership_id LIKE ? ESCAPE '!' OR CAST(h.id AS CHAR) LIKE ? ESCAPE '!' OR v.full_name LIKE ? ESCAPE '!')";
        array_push($parameters, $pattern, $pattern, $pattern, $pattern, $pattern);
    }
    $from = 'help_requests h LEFT JOIN members m ON m.id = h.member_id LEFT JOIN staff_users v ON v.id = h.assigned_volunteer_id';
    $where = implode(' AND ', $conditions);
    $statement = $pdo->prepare('SELECT h.status, COUNT(*) AS total FROM ' . $from . ' WHERE ' . $where . ' GROUP BY h.status');
    $statement->execute($parameters);
    foreach ($statement->fetchAll() as $summary) {
        $total += (int) $summary['total'];
        if (isset($counts[$summary['status']])) { $counts[$summary['status']] = (int) $summary['total']; }
    }
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $statement = $pdo->prepare('SELECT h.id, h.category, h.description, h.status, h.created_at, h.assigned_volunteer_id, m.full_name AS member_name, m.membership_id, m.area AS member_area, v.full_name AS volunteer_name FROM ' . $from . ' WHERE ' . $where . ' ORDER BY h.created_at DESC, h.id DESC LIMIT ? OFFSET ?');
    foreach ($parameters as $index => $value) { $statement->bindValue($index + 1, $value, PDO::PARAM_STR); }
    $statement->bindValue(count($parameters) + 1, $perPage, PDO::PARAM_INT);
    $statement->bindValue(count($parameters) + 2, ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Office community help list failed: ' . get_class($exception));
    $loadError = 'Community help could not be loaded. Please refresh to try again.';
}
$filters = ['status' => $statusFilter, 'category' => $categoryFilter, 'area' => $areaFilter, 'search' => $search, 'page' => $page];
$pageUrl = appUrl('public/admin/help_requests_assign.php?' . http_build_query($filters));
pageHeader('Community help', $staff, true);
?>
<section class="workspace community-help-workspace community-help-admin" data-help-board>
    <header class="operations-heading"><div><p class="eyebrow">OFFICE COORDINATION</p><h1>Community help</h1><p class="operations-person">Assignments and request history</p></div><button type="button" class="button-secondary" data-chat-open><?= uiIcon('message-circle') ?>KSIJ Assistant</button></header>
    <?php if ($success): ?><p class="success" role="status"><?= escapeHtml($success) ?></p><?php endif; ?>
    <?php showError($error); ?>
    <form class="community-help-filters" data-help-filter method="get" action="<?= escapeHtml(appUrl('public/admin/help_requests_assign.php')) ?>">
        <div class="help-search-field"><label for="help-search">Search requests</label><input id="help-search" type="search" name="search" value="<?= escapeHtml($search) ?>" maxlength="120" placeholder="Request, member, assignee or ID" autocomplete="off"></div>
        <div><label for="help-status">Status</label><select id="help-status" name="status"><option value="">All statuses</option><?php foreach ($statuses as $value => $label): ?><option value="<?= $value ?>"<?= $statusFilter === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div><label for="help-category">Category</label><select id="help-category" name="category"><option value="">All categories</option><?php foreach ($categories as $value => $label): ?><option value="<?= $value ?>"<?= $categoryFilter === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div><label for="help-area">Member area</label><select id="help-area" name="area"><option value="">All areas</option><?php foreach ($areas as $area): ?><option value="<?= escapeHtml($area) ?>"<?= $areaFilter === $area ? ' selected' : '' ?>><?= escapeHtml($area) ?></option><?php endforeach; ?></select></div>
        <div class="help-filter-actions"><button type="submit">Apply</button><a class="button-link button-secondary" data-help-clear href="<?= escapeHtml(appUrl('public/admin/help_requests_assign.php')) ?>">Clear</a><button type="button" class="icon-button" data-help-refresh title="Refresh requests" aria-label="Refresh requests" hidden><?= uiIcon('refresh-cw') ?></button></div>
    </form>
    <p class="help-feedback" data-help-feedback role="status" aria-live="polite"></p>
    <div data-help-results data-page="<?= $page ?>"<?= $loadError ? ' data-load-error' : '' ?>>
        <?php showError($loadError); ?>
        <?php if (!$loadError): ?>
        <div class="community-help-results-heading"><h2>Request queue <span><?= $total ?></span></h2><span class="muted">Current filters</span></div>
        <dl class="community-help-summary"><?php foreach ($statuses as $key => $label): ?><div><dt><?= $label ?></dt><dd><?= $counts[$key] ?></dd></div><?php endforeach; ?></dl>
        <?php if (!$rows): ?><div class="operations-empty"><?= uiIcon('hand-heart') ?><h3>No requests match these filters</h3><a data-help-clear href="<?= escapeHtml(appUrl('public/admin/help_requests_assign.php')) ?>">Clear filters</a></div><?php endif; ?>
        <div class="community-help-admin-list">
        <?php foreach ($rows as $row):
            $eligible = array_filter($volunteers, static function (array $volunteer) use ($row): bool {
                $area = trim((string) ($row['member_area'] ?? ''));
                return $area === '' || strcasecmp($area, trim((string) $volunteer['area'])) === 0;
            });
        ?>
            <article class="community-help-card community-help-admin-row<?= $row['category'] === 'urgent_medical' ? ' is-urgent' : '' ?>" data-help-card="<?= (int) $row['id'] ?>">
                <div class="community-help-main"><?php renderCommunityHelpContent($row); ?><div class="community-help-member"><?= uiIcon('users') ?><span><strong><?= escapeHtml($row['member_name'] ?: 'Community task') ?></strong><small><?= escapeHtml($row['membership_id'] ?: '') ?></small></span></div></div>
                <div class="community-help-assignment">
                    <dl><div><dt>Assigned team member</dt><dd><?= escapeHtml($row['volunteer_name'] ?: 'Unassigned') ?></dd></div></dl>
                    <?php if (in_array($row['status'], ['open', 'assigned'], true)): ?>
                        <form method="post" action="<?= escapeHtml($pageUrl) ?>" data-help-assign>
                            <?php csrfField(); ?><input type="hidden" name="action" value="assign"><input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="expected_assignee" value="<?= (int) $row['assigned_volunteer_id'] ?>"><input type="hidden" name="expected_status" value="<?= escapeHtml($row['status']) ?>">
                            <label for="help-assignee-<?= (int) $row['id'] ?>"><?= $row['status'] === 'assigned' ? 'Reassign to' : 'Assign to' ?></label>
                            <select id="help-assignee-<?= (int) $row['id'] ?>" name="volunteer_id" required<?= !$eligible ? ' disabled' : '' ?>><option value="">Select team member</option><?php foreach ($eligible as $volunteer): ?><option value="<?= (int) $volunteer['id'] ?>"><?= escapeHtml($volunteer['full_name'] . ($volunteer['role'] === 'cc_member' ? ' (CC member)' : '')) ?></option><?php endforeach; ?></select>
                            <button type="submit"<?= !$eligible ? ' disabled' : '' ?>><?= uiIcon('users') ?><?= $row['status'] === 'assigned' ? 'Reassign request' : 'Assign request' ?></button>
                            <?php if (!$eligible): ?><p class="field-hint">No active team members are available for this area.</p><?php endif; ?>
                        </form>
                    <?php else: ?><p class="community-help-closed"><?= uiIcon('shield-check') ?>Request resolved</p><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
        <?php renderCommunityHelpPagination('public/admin/help_requests_assign.php', $filters, $page, $pages, $total, $perPage); ?>
        <?php endif; ?>
    </div>
</section>
<script src="<?= escapeHtml(appUrl('assets/js/help_board.js?v=1')) ?>" defer></script>
<?php pageFooter(); ?>
