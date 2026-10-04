<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
$member = requireMember();
require_once dirname(__DIR__) . '/includes/community_help_view.php';
header('Cache-Control: no-store, private');
$categories = communityHelpCategories();
$category = is_string($_GET['category'] ?? null) && isset($categories[$_GET['category']]) ? $_GET['category'] : '';
$search = is_string($_GET['search'] ?? null) && preg_match('//u', $_GET['search']) ? mb_substr(trim($_GET['search']), 0, 120) : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$perPage = 12;
$pages = 1;
$total = 0;
$requests = [];
$error = '';
// The member board contains community-wide open requests, without member identities.
$where = "h.status = 'open'";
$parameters = [];
if ($category !== '') { $where .= ' AND h.category = ?'; $parameters[] = $category; }
if ($search !== '') {
    $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
    $where .= " AND (h.description LIKE ? ESCAPE '!' OR m.area LIKE ? ESCAPE '!' OR CAST(h.id AS CHAR) LIKE ? ESCAPE '!')";
    array_push($parameters, $pattern, $pattern, $pattern);
}
try {
    $from = 'help_requests h LEFT JOIN members m ON m.id = h.member_id';
    $statement = getDb()->prepare('SELECT COUNT(*) FROM ' . $from . ' WHERE ' . $where);
    $statement->execute($parameters);
    $total = (int) $statement->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $statement = getDb()->prepare('SELECT h.id, h.category, h.description, h.status, h.created_at, m.area FROM ' . $from . ' WHERE ' . $where . ' ORDER BY h.created_at DESC, h.id DESC LIMIT ? OFFSET ?');
    foreach ($parameters as $index => $value) { $statement->bindValue($index + 1, $value, PDO::PARAM_STR); }
    $statement->bindValue(count($parameters) + 1, $perPage, PDO::PARAM_INT);
    $statement->bindValue(count($parameters) + 2, ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    $requests = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Community help board failed: ' . get_class($exception));
    $error = 'Community help could not be loaded. Please refresh to try again.';
}
$success = $_SESSION['help_success'] ?? '';
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') { unset($_SESSION['help_success']); }
pageHeader('Community help', $member);
?>
<section class="workspace community-help-workspace" data-help-board>
    <header class="operations-heading"><div><p class="eyebrow">COMMUNITY SUPPORT</p><h1>Community help</h1><p class="operations-person">Open requests across the community</p></div><button type="button" class="button-secondary" data-chat-open><?= uiIcon('message-circle') ?>KSIJ Assistant</button></header>
    <?php if ($success): ?><p class="success" role="status"><?= escapeHtml($success) ?></p><?php endif; ?>
    <form class="community-help-filters" data-help-filter method="get" action="<?= escapeHtml(appUrl('public/help_requests_list.php')) ?>">
        <div class="help-search-field"><label for="help-search">Search requests</label><input id="help-search" type="search" name="search" value="<?= escapeHtml($search) ?>" maxlength="120" placeholder="Request, area or ID" autocomplete="off"></div>
        <div><label for="help-category">Category</label><select id="help-category" name="category"><option value="">All categories</option><?php foreach ($categories as $value => $label): ?><option value="<?= $value ?>"<?= $category === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="help-filter-actions"><button type="submit">Apply</button><a class="button-link button-secondary" data-help-clear href="<?= escapeHtml(appUrl('public/help_requests_list.php')) ?>">Clear</a><button type="button" class="icon-button" data-help-refresh title="Refresh requests" aria-label="Refresh requests" hidden><?= uiIcon('refresh-cw') ?></button></div>
    </form>
    <p class="help-feedback" data-help-feedback role="status" aria-live="polite"></p>
    <div data-help-results data-page="<?= $page ?>"<?= $error ? ' data-load-error' : '' ?>>
        <?php showError($error); ?>
        <?php if (!$error): ?>
        <div class="community-help-results-heading"><h2>Open requests <span><?= $total ?></span></h2><span class="muted"><?= escapeHtml($categories[$category] ?? 'All categories') ?></span></div>
        <?php if (!$requests): ?><div class="operations-empty"><?= uiIcon('hand-heart') ?><h3><?= $search !== '' || $category !== '' ? 'No matching requests' : 'No open requests' ?></h3><?php if ($search !== '' || $category !== ''): ?><a data-help-clear href="<?= escapeHtml(appUrl('public/help_requests_list.php')) ?>">Clear filters</a><?php endif; ?></div><?php endif; ?>
        <div class="community-help-grid">
            <?php foreach ($requests as $request): ?><article class="community-help-card<?= $request['category'] === 'urgent_medical' ? ' is-urgent' : '' ?>"><?php renderCommunityHelpContent($request); ?></article><?php endforeach; ?>
        </div>
        <?php renderCommunityHelpPagination('public/help_requests_list.php', ['category' => $category, 'search' => $search], $page, $pages, $total, $perPage); ?>
        <?php endif; ?>
    </div>
</section>
<script src="<?= escapeHtml(appUrl('assets/js/help_board.js?v=2')) ?>" defer></script>
<?php pageFooter(); ?>
