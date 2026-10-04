<?php
require_once dirname(__DIR__) . '/includes/auth_staff.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$staff = requireRole(['volunteer', 'cc_member']);
require_once dirname(__DIR__) . '/includes/team_workspace.php';
require_once dirname(__DIR__) . '/includes/team_view.php';
require_once dirname(__DIR__) . '/includes/money.php';
header('Cache-Control: no-store');
$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
function finishTeamAction(string $message, string $anchor, bool $ajax): void
{
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => $message]);
    } else {
        $_SESSION['team_success'] = $message;
        header('Location: ' . teamDashboardUrl([], $anchor), true, 303);
    }
    exit;
}
$error = '';
$submittedNotes = '';
$submittedRequestId = 0;
$success = !$ajax ? ($_SESSION['team_success'] ?? '') : '';
if (!$ajax) { unset($_SESSION['team_success']); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($ajax) {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'Your session changed. Reload the page and try again.']);
            exit;
        }
    }
    checkCsrf();
    $action = $_POST['action'] ?? '';
    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        if (!$requestId) { throw new DomainException('Choose a valid request.'); }
        if ($action === 'guarantor') {
            $decision = $_POST['decision'] ?? '';
            $notes = $_POST['notes'] ?? '';
            if (!is_string($decision) || !is_string($notes) || !preg_match('//u', $notes)) {
                throw new DomainException('Enter a valid decision and notes.');
            }
            $submittedNotes = $notes;
            $submittedRequestId = $requestId;
            teamDecideGuarantor((int) $staff['id'], $requestId, $decision, $notes);
            finishTeamAction('Your guarantor decision was saved and the member was notified.', 'guarantor-reviews', $ajax);
        } elseif ($action === 'assign') {
            $volunteerId = filter_var($_POST['volunteer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$volunteerId) { throw new DomainException('Choose an active local volunteer.'); }
            teamAssignHelp((int) $staff['id'], $requestId, $volunteerId);
            finishTeamAction('The task was assigned and the volunteer was notified.', 'open-help', $ajax);
        } elseif ($action === 'resolve') {
            teamResolveHelp((int) $staff['id'], $requestId);
            finishTeamAction('The task was marked resolved.', 'assigned-tasks', $ajax);
        } else { throw new DomainException('Choose a valid team action.'); }
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Team action failed: ' . get_class($exception));
        $error = 'The change could not be saved. Please try again.';
    }
    if ($ajax) {
        http_response_code(isset($exception) && $exception instanceof DomainException ? 422 : 500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $error]);
        exit;
    }
}
$categories = ['elderly_help' => 'Elderly help', 'urgent_medical' => 'Urgent medical', 'other' => 'Other'];
$taskStatus = is_string($_GET['task_status'] ?? null) ? $_GET['task_status'] : 'assigned';
if (!in_array($taskStatus, ['', 'assigned', 'resolved'], true)) { $taskStatus = 'assigned'; }
$category = is_string($_GET['category'] ?? null) && isset($categories[$_GET['category']]) ? $_GET['category'] : '';
$review = ($_GET['review'] ?? '') === 'all' ? 'all' : 'pending';
$emptyList = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$tasks = $open = $reviews = $activity = $emptyList;
$volunteers = [];
$loadError = '';
$guarantor = teamGuarantorEligible($staff);
$cc = $staff['role'] === 'cc_member';
$requestedPanel = $ajax && $_SERVER['REQUEST_METHOD'] === 'GET' ? ($_SERVER['HTTP_X_TEAM_PANEL'] ?? '') : '';
$allowedPanels = ['assigned-tasks', 'open-help'];
if ($guarantor) { $allowedPanels[] = 'guarantor-reviews'; }
if ($cc) { $allowedPanels[] = 'volunteer-activity'; }
if ($requestedPanel !== '' && !in_array($requestedPanel, $allowedPanels, true)) {
    http_response_code(403);
    exit('This queue is not available to your account.');
}
$searches = [];
foreach (['tasks_search', 'open_search', 'reviews_search', 'activity_search'] as $key) {
    $value = $_GET[$key] ?? '';
    $searches[$key] = is_string($value) && strlen($value) <= 480 && preg_match('//u', $value) ? trim($value) : '';
}
try {
    $getPage = static function (string $key): int { return filter_var($_GET[$key] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1; };
    if ($requestedPanel === '' || $requestedPanel === 'assigned-tasks') {
        $tasks = teamAssignedTasks((int) $staff['id'], $taskStatus, $getPage('tasks_page'), $searches['tasks_search']);
    }
    if ($requestedPanel === '' || $requestedPanel === 'open-help') {
        $open = teamOpenTasks($category, $getPage('open_page'), $searches['open_search']);
        if ($cc) { $volunteers = teamAreaVolunteers($staff); }
    }
    if ($guarantor && ($requestedPanel === '' || $requestedPanel === 'guarantor-reviews')) {
        $reviews = teamGuarantorRequests((int) $staff['id'], $review, $getPage('reviews_page'), $searches['reviews_search']);
    }
    if ($cc && ($requestedPanel === '' || $requestedPanel === 'volunteer-activity')) {
        $activity = teamVolunteerActivity($staff, $getPage('activity_page'), $searches['activity_search']);
    }
} catch (Throwable $exception) {
    error_log('Team workspace lookup failed: ' . get_class($exception));
    $loadError = 'The team lists could not be loaded. Please reload the page.';
}
pageHeader($cc ? 'Coordination dashboard' : 'Volunteer dashboard', $staff, true);
?>
<section class="workspace team-workspace" data-team-workspace data-default-panel="<?= $cc ? 'open-help' : 'assigned-tasks' ?>"><header class="operations-heading"><div><p class="eyebrow"><?= $cc ? 'COMMUNITY COORDINATION' : 'VOLUNTEER OPERATIONS' ?></p><h1><?= $cc ? 'Coordination dashboard' : 'Volunteer dashboard' ?></h1><p class="operations-person">Salaam, <?= escapeHtml($staff['full_name']) ?></p></div><button type="button" class="button-secondary" data-chat-open><?= uiIcon('message-circle') ?>KSIJ Assistant</button></header>
<div class="operations-identity"><span><?= uiIcon('users') ?><?= escapeHtml(trim((string) $staff['area']) ?: 'Area not recorded') ?></span><span class="status-badge status-approved">Active account</span><span><?= uiIcon('shield-check') ?><?= $guarantor ? 'Guarantor eligible' : 'Guarantor not approved' ?></span></div>
<?php if (!$loadError): ?>
<div class="operations-metrics team-metrics">
<?php $queueMetrics = [['assigned-tasks', 'Tasks in view', 'clipboard-list', $tasks], ['open-help', 'Open help in view', 'hand-heart', $open]];
if ($guarantor) { $queueMetrics[] = ['guarantor-reviews', 'Reviews in view', 'shield-check', $reviews]; }
if ($cc) { $queueMetrics[] = ['volunteer-activity', 'Local volunteers in view', 'users', $activity]; }
foreach ($queueMetrics as [$panelId, $label, $icon, $list]): ?><a class="operations-metric" href="#<?= $panelId ?>" data-team-link><span class="metric-top"><?= uiIcon($icon) ?><?= uiIcon('arrow-up-right') ?></span><strong data-team-summary="<?= $panelId ?>"><?= (int) $list['total'] ?></strong><span><?= $label ?></span></a><?php endforeach; ?>
</div>
<?php endif; ?>
<nav class="team-nav" aria-label="Team sections"><a href="#assigned-tasks"><?= uiIcon('clipboard-list') ?>My tasks</a><a href="#open-help"><?= uiIcon('hand-heart') ?>Open help</a><?php if ($guarantor): ?><a href="#guarantor-reviews"><?= uiIcon('shield-check') ?>Guarantor reviews</a><?php endif; ?><?php if ($cc): ?><a href="#volunteer-activity"><?= uiIcon('users') ?>Volunteer activity</a><?php endif; ?></nav>
<p class="team-feedback" role="status" aria-live="polite" data-team-feedback></p>
<?php if ($success): ?><p class="success" role="status"><?= escapeHtml($success) ?></p><?php endif; ?>
<?php showError($error); showError($loadError); ?>
<?php if (!$loadError): ?>
<section class="team-section" id="assigned-tasks" data-team-panel data-page="tasks_page"><div class="page-heading"><h2>My tasks</h2><span class="muted" data-team-count data-total="<?= (int) $tasks['total'] ?>"><?= (int) $tasks['total'] ?> tasks</span></div>
<form class="list-filters" data-team-filter method="get" action="<?= escapeHtml(appUrl('public/staff_dashboard.php')) ?>#assigned-tasks"><?php renderTeamFilterFields('task_status', 'tasks_page'); renderTeamSearch('tasks_search', $searches['tasks_search'], 'Search tasks'); ?><div><label for="task-status">Status</label><select id="task-status" name="task_status"><option value="assigned"<?= $taskStatus === 'assigned' ? ' selected' : '' ?>>Assigned</option><option value="resolved"<?= $taskStatus === 'resolved' ? ' selected' : '' ?>>Resolved</option><option value=""<?= $taskStatus === '' ? ' selected' : '' ?>>All</option></select></div><button type="submit">Apply</button></form>
<div data-team-results>
<?php if (!$tasks['rows']): ?><div class="operations-empty"><?= uiIcon('clipboard-list') ?><h3>No tasks in this view</h3></div><?php endif; ?>
<?php foreach ($tasks['rows'] as $task) { renderTeamHelpRow($task, $categories, true, null); } ?>
<?php renderTeamPagination($tasks, 'tasks_page', 'assigned-tasks'); ?></div></section>

<section class="team-section" id="open-help" data-team-panel data-page="open_page"><div class="page-heading"><h2>Open community help</h2><span class="muted" data-team-count data-total="<?= (int) $open['total'] ?>"><?= (int) $open['total'] ?> open requests</span></div>
<form class="list-filters" data-team-filter method="get" action="<?= escapeHtml(appUrl('public/staff_dashboard.php')) ?>#open-help"><?php renderTeamFilterFields('category', 'open_page'); renderTeamSearch('open_search', $searches['open_search'], 'Search help requests'); ?><div><label for="help-category">Category</label><select id="help-category" name="category"><option value="">All categories</option><?php foreach ($categories as $key => $label): ?><option value="<?= $key ?>"<?= $category === $key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div><button type="submit">Apply</button></form>
<div data-team-results>
<?php if (!$open['rows']): ?><div class="operations-empty"><?= uiIcon('hand-heart') ?><h3>No open help requests</h3></div><?php endif; ?>
<?php foreach ($open['rows'] as $task) { renderTeamHelpRow($task, $categories, false, $cc ? $volunteers : null); } ?>
<?php renderTeamPagination($open, 'open_page', 'open-help'); ?></div></section>

<?php if ($guarantor): ?>
<section class="team-section" id="guarantor-reviews" data-team-panel data-page="reviews_page"><div class="page-heading"><h2>Guarantor reviews</h2><span class="muted" data-team-count data-total="<?= (int) $reviews['total'] ?>"><?= (int) $reviews['total'] ?> requests</span></div>
<form class="list-filters" data-team-filter method="get" action="<?= escapeHtml(appUrl('public/staff_dashboard.php')) ?>#guarantor-reviews"><?php renderTeamFilterFields('review', 'reviews_page'); renderTeamSearch('reviews_search', $searches['reviews_search'], 'Search member or request'); ?><div><label for="review-filter">View</label><select id="review-filter" name="review"><option value="pending"<?= $review === 'pending' ? ' selected' : '' ?>>Pending my decision</option><option value="all"<?= $review === 'all' ? ' selected' : '' ?>>All my guarantor requests</option></select></div><button type="submit">Apply</button></form>
<div data-team-results>
<?php if (!$reviews['rows']): ?><div class="operations-empty"><?= uiIcon('shield-check') ?><h3>No reviews in this view</h3></div><?php endif; ?>
<?php foreach ($reviews['rows'] as $request): $slot = (int) $request['guarantor1_id'] === (int) $staff['id'] ? 1 : 2; $myStatus = $request['guarantor' . $slot . '_status']; $canDecide = $myStatus === 'pending' && $request['office_status'] === 'pending' && (int) $request['guarantor1_id'] !== (int) $request['guarantor2_id']; ?>
<article class="request-row team-review-row"><div class="request-heading"><h3><?= escapeHtml(ucfirst(str_replace('_', ' ', $request['type']))) ?> <span class="muted">#<?= (int) $request['id'] ?></span></h3><strong><?= escapeHtml(formatMoney(moneyToCents((string) ($request['amount_requested'] ?? '0'), true) ?? 0)) ?></strong></div>
<p><?= escapeHtml($request['member_name']) ?> <span class="muted"><?= escapeHtml($request['membership_id']) ?> / <?= escapeHtml($request['area']) ?></span></p>
<p class="request-description"><?= escapeHtml($request['description']) ?></p>
<a class="document-link" href="<?= escapeHtml(appUrl('public/request_details.php?id=' . (int) $request['id'])) ?>"><?= uiIcon('folder') ?>View details and documents</a>
<dl class="team-review-status"><div><dt>Your decision</dt><dd><span class="status-badge status-<?= escapeHtml($myStatus) ?>"><?= escapeHtml(ucfirst($myStatus)) ?></span></dd></div><?php foreach ([1, 2] as $decisionSlot): ?><div><dt>Guarantor <?= $decisionSlot ?></dt><dd><span class="status-badge status-<?= escapeHtml($request['guarantor' . $decisionSlot . '_status']) ?>"><?= escapeHtml(ucfirst($request['guarantor' . $decisionSlot . '_status'])) ?></span></dd></div><?php endforeach; ?><div><dt>Office</dt><dd><span class="status-badge status-<?= escapeHtml($request['office_status']) ?>"><?= escapeHtml(ucfirst($request['office_status'])) ?></span></dd></div></dl>
<?php if ($request['document_path']): ?><a class="document-link" href="<?= escapeHtml(appUrl('public/request_document.php?id=' . $request['id'])) ?>"><img src="<?= escapeHtml(appUrl('assets/icons/download.svg')) ?>" width="18" height="18" alt="">Supporting document</a><?php endif; ?>
<?php if ($request['guarantor' . $slot . '_notes']): ?><p class="guarantor-notes"><?= escapeHtml($request['guarantor' . $slot . '_notes']) ?></p><?php endif; ?>
<?php if ($canDecide): ?>
<form method="post" action="<?= escapeHtml(teamDashboardUrl([], 'guarantor-reviews')) ?>" class="team-review-form"><?php csrfField(); ?><input type="hidden" name="action" value="guarantor"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><label for="notes-<?= (int) $request['id'] ?>">Decision notes</label><textarea id="notes-<?= (int) $request['id'] ?>" name="notes" rows="2" maxlength="2000"><?= $submittedRequestId === (int) $request['id'] ? escapeHtml($submittedNotes) : '' ?></textarea><div class="form-actions"><button type="submit" name="decision" value="approved">Approve</button><button type="submit" name="decision" value="rejected" class="danger-button">Reject</button></div></form>
<?php endif; ?></article>
<?php endforeach; ?>
<?php renderTeamPagination($reviews, 'reviews_page', 'guarantor-reviews'); ?></div></section>
<?php endif; ?>

<?php if ($cc): ?>
<section class="team-section" id="volunteer-activity" data-team-panel data-page="activity_page"><div class="page-heading"><h2>Volunteer activity</h2><span class="muted" data-team-count data-total="<?= (int) $activity['total'] ?>"><?= (int) $activity['total'] ?> volunteers</span></div>
<form class="list-filters" data-team-filter method="get" action="<?= escapeHtml(appUrl('public/staff_dashboard.php')) ?>#volunteer-activity"><?php renderTeamFilterFields('', 'activity_page'); renderTeamSearch('activity_search', $searches['activity_search'], 'Search volunteer'); ?><button type="submit">Apply</button></form>
<div data-team-results>
<?php if (!$activity['rows']): ?><p class="muted">No volunteers are recorded in your area.</p><?php else: ?>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Volunteer</th><th>Account</th><th>Guarantor</th><th>Selected requests</th><th>Pending</th><th>Approved</th><th>Rejected</th></tr></thead><tbody>
<?php foreach ($activity['rows'] as $volunteer): ?><tr><td><?= escapeHtml($volunteer['full_name']) ?> <span class="muted">#<?= (int) $volunteer['id'] ?></span></td><td><?= $volunteer['is_active'] ? 'Active' : 'Inactive' ?></td><td><?= $volunteer['is_guarantor_approved'] ? 'Authorized' : 'Not approved' ?></td><td><?= (int) $volunteer['total_requests'] ?></td><td><?= (int) $volunteer['pending_requests'] ?></td><td><?= (int) $volunteer['approved_requests'] ?></td><td><?= (int) $volunteer['rejected_requests'] ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<?php renderTeamPagination($activity, 'activity_page', 'volunteer-activity'); ?></div></section>
<?php endif; ?>
<?php endif; ?>
</section>
<script src="<?= escapeHtml(appUrl('assets/js/team.js?v=3')) ?>" defer></script>
<?php pageFooter(); ?>
