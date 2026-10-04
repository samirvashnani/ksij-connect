<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$member = requireMember();
$categories = ['elderly_help' => 'Elderly help', 'urgent_medical' => 'Urgent medical', 'other' => 'Other'];
$category = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
if (!isset($categories[$category])) {
    $category = '';
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$perPage = 20;
$pages = 1;
$total = 0;
$requests = [];
$error = '';
// Community visibility is intentional: never filter this query by member or area.
$where = "h.status = 'open'";
if ($category !== '') {
    $where .= ' AND h.category = ?';
}
try {
    $statement = getDb()->prepare('SELECT COUNT(*) FROM help_requests h WHERE ' . $where);
    $statement->execute($category !== '' ? [$category] : []);
    $total = (int) $statement->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $statement = getDb()->prepare('SELECT h.id, h.category, h.description, h.created_at, m.area FROM help_requests h LEFT JOIN members m ON m.id = h.member_id WHERE ' . $where . ' ORDER BY h.created_at DESC, h.id DESC LIMIT ? OFFSET ?');
    $parameter = 1;
    if ($category !== '') {
        $statement->bindValue($parameter++, $category, PDO::PARAM_STR);
    }
    $statement->bindValue($parameter++, $perPage, PDO::PARAM_INT);
    $statement->bindValue($parameter, ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    $requests = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Help request list failed: ' . $exception->getMessage());
    $error = 'Help requests could not be loaded. Please try again shortly.';
}
$success = $_SESSION['help_success'] ?? '';
unset($_SESSION['help_success']);
pageHeader('Help requests', $member);
?>
<section class="workspace">
    <div class="page-heading"><div><p class="eyebrow">COMMUNITY HELP</p><h1>Help requests</h1></div></div>
    <?php if ($success): ?><p class="success" role="status"><?= escapeHtml($success) ?></p><?php endif; ?>
    <?php showError($error); ?>
    <form class="list-filters" method="get">
        <div><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option><?php foreach ($categories as $value => $label): ?><option value="<?= $value ?>"<?= $category === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <button type="submit">Apply</button>
        <?php if ($category !== ''): ?><a href="<?= escapeHtml(appUrl('public/help_requests_list.php')) ?>">Clear</a><?php endif; ?>
    </form>
    <?php if (!$error): ?><p class="muted"><?= $total ?> open <?= $total === 1 ? 'request' : 'requests' ?></p><?php endif; ?>
    <?php if (!$requests && !$error): ?><div class="empty-state"><h2><?= $category !== '' ? 'No open help requests in this category' : 'No open help requests' ?></h2></div><?php endif; ?>
    <?php foreach ($requests as $request): ?>
    <article class="request-row">
        <div class="request-heading"><div><h2><?= escapeHtml($categories[$request['category']] ?? 'Other') ?> <span class="muted">#<?= (int) $request['id'] ?></span></h2><time class="muted"><?= escapeHtml($request['created_at']) ?></time></div><span class="status-badge status-open">Open</span></div>
        <p class="request-description"><?= escapeHtml($request['description']) ?></p>
        <p class="help-area"><?= escapeHtml(trim((string) ($request['area'] ?? '')) ?: 'Community-wide') ?></p>
    </article>
    <?php endforeach; ?>
    <?php if ($pages > 1 && !$error): ?><nav class="pagination" aria-label="Help request pages"><span>Page <?= $page ?> of <?= $pages ?></span><?php foreach ([-1 => 'Previous page', 1 => 'Next page'] as $direction => $label): $destination = $page + $direction; if ($destination >= 1 && $destination <= $pages): ?><a class="icon-link" title="<?= $label ?>" aria-label="<?= $label ?>" href="<?= escapeHtml(appUrl('public/help_requests_list.php?' . http_build_query(['category' => $category, 'page' => $destination]))) ?>"><img src="<?= escapeHtml(appUrl('assets/icons/chevron-' . ($direction < 0 ? 'left' : 'right') . '.svg')) ?>" width="20" height="20" alt=""></a><?php endif; endforeach; ?></nav><?php endif; ?>
</section>
<?php pageFooter(); ?>
