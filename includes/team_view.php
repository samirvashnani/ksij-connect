<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/community_help_view.php';

function teamDashboardUrl(array $changes = [], string $anchor = ''): string
{
    $query = [];
    foreach (['task_status', 'category', 'review', 'tasks_search', 'open_search', 'reviews_search', 'activity_search', 'tasks_page', 'open_page', 'reviews_page', 'activity_page'] as $key) {
        if (isset($_GET[$key]) && is_string($_GET[$key])) { $query[$key] = $_GET[$key]; }
    }
    $query = array_merge($query, $changes);
    $pages = ['assigned-tasks' => 'staff_tasks.php', 'open-help' => 'staff_help.php', 'guarantor-reviews' => 'staff_reviews.php', 'volunteer-activity' => 'staff_activity.php'];
    return appUrl('public/' . ($pages[$anchor] ?? 'staff_dashboard.php') . ($query ? '?' . http_build_query($query) : ''));
}

function renderTeamPagination(array $list, string $parameter, string $anchor): void
{
    if ($list['pages'] <= 1) { return; }
    ?>
    <nav class="pagination" data-current-page="<?= (int) $list['page'] ?>" aria-label="<?= escapeHtml($anchor) ?> pages"><span>Page <?= (int) $list['page'] ?> of <?= (int) $list['pages'] ?></span>
        <?php foreach ([-1 => 'Previous page', 1 => 'Next page'] as $direction => $label): $page = $list['page'] + $direction; if ($page >= 1 && $page <= $list['pages']): ?>
        <a class="icon-link" href="<?= escapeHtml(teamDashboardUrl([$parameter => $page], $anchor)) ?>" title="<?= $label ?>" aria-label="<?= $label ?>"><img src="<?= escapeHtml(appUrl('assets/icons/chevron-' . ($direction < 0 ? 'left' : 'right') . '.svg')) ?>" width="20" height="20" alt=""></a>
        <?php endif; endforeach; ?>
    </nav>
    <?php
}

function renderTeamFilterFields(string $filter, string $pageParameter): void
{
    $searchParameter = str_replace('_page', '_search', $pageParameter);
    foreach (['task_status', 'category', 'review', 'tasks_search', 'open_search', 'reviews_search', 'activity_search', 'tasks_page', 'open_page', 'reviews_page', 'activity_page'] as $key) {
        if ($key !== $filter && $key !== $pageParameter && $key !== $searchParameter && isset($_GET[$key]) && is_string($_GET[$key])) {
            echo '<input type="hidden" name="' . $key . '" value="' . escapeHtml($_GET[$key]) . '">';
        }
    }
}

function renderTeamSearch(string $name, string $value, string $label): void
{
    ?>
    <div class="team-search"><label for="<?= escapeHtml($name) ?>"><?= escapeHtml($label) ?></label><input type="search" id="<?= escapeHtml($name) ?>" name="<?= escapeHtml($name) ?>" value="<?= escapeHtml($value) ?>" maxlength="120" autocomplete="off"></div>
    <?php
}

function renderTeamHelpRow(array $task, array $categories, bool $assigned, ?array $volunteers): void
{
    ?>
    <article class="request-row team-task-row community-help-card<?= $task['category'] === 'urgent_medical' ? ' is-urgent' : '' ?>">
        <?php if ($assigned): ?><div class="team-task-strip"><?= uiIcon('clipboard-list') ?><?= $task['status'] === 'resolved' ? 'Resolved task' : 'Assigned to you' ?></div><?php endif; ?>
        <?php renderCommunityHelpContent($task); ?>
        <?php if (!$assigned && $task['status'] === 'open'): ?>
        <form method="post" action="<?= escapeHtml(teamDashboardUrl([], 'open-help')) ?>" class="team-command"><?php csrfField(); ?><input type="hidden" name="action" value="claim"><input type="hidden" name="request_id" value="<?= (int) $task['id'] ?>"><button type="submit"><?= uiIcon('hand-heart') ?>I can help</button></form>
        <?php endif; ?>
        <?php if ($assigned && $task['status'] === 'assigned'): ?>
        <form method="post" action="<?= escapeHtml(teamDashboardUrl([], 'assigned-tasks')) ?>" class="team-command"><?php csrfField(); ?><input type="hidden" name="action" value="resolve"><input type="hidden" name="request_id" value="<?= (int) $task['id'] ?>"><button type="submit"><?= uiIcon('shield-check') ?>Mark resolved</button></form>
        <?php elseif (!$assigned && $volunteers !== null): ?>
        <?php if ($volunteers): ?>
        <form method="post" action="<?= escapeHtml(teamDashboardUrl([], 'open-help')) ?>" class="team-assignment"><?php csrfField(); ?><input type="hidden" name="action" value="assign"><input type="hidden" name="request_id" value="<?= (int) $task['id'] ?>"><div><label for="volunteer-<?= (int) $task['id'] ?>">Volunteer</label><select id="volunteer-<?= (int) $task['id'] ?>" name="volunteer_id" required><option value="">Select volunteer</option><?php foreach ($volunteers as $volunteer): ?><option value="<?= (int) $volunteer['id'] ?>"><?= escapeHtml($volunteer['full_name']) ?></option><?php endforeach; ?></select></div><button type="submit">Assign</button></form>
        <?php else: ?><p class="muted">No active volunteers are available in your area.</p><?php endif; ?>
        <?php elseif ($task['status'] === 'resolved'): ?><p class="community-help-closed"><?= uiIcon('shield-check') ?>Request resolved</p><?php endif; ?>
    </article>
    <?php
}
