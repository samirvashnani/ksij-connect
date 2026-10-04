<?php
require_once __DIR__ . '/layout.php';

function communityHelpCategories(): array
{
    return ['elderly_help' => 'Elderly assistance', 'urgent_medical' => 'Urgent medical', 'other' => 'Community support'];
}

function renderCommunityHelpContent(array $request): void
{
    $categories = communityHelpCategories();
    $icon = ['elderly_help' => 'hand-heart', 'urgent_medical' => 'heart-pulse', 'other' => 'users'][$request['category']] ?? 'hand-heart';
    $status = in_array($request['status'] ?? '', ['open', 'assigned', 'resolved'], true) ? $request['status'] : 'open';
    $description = (string) $request['description'];
    $area = trim((string) ($request['area'] ?? $request['member_area'] ?? ''));
    ?>
    <div class="community-help-heading"><span class="community-help-icon"><?= uiIcon($icon) ?></span><div><span class="community-help-reference">HELP #<?= (int) $request['id'] ?></span><h3><?= escapeHtml($categories[$request['category']] ?? 'Community support') ?></h3></div><span class="status-badge status-<?= $status ?>"><?= ucfirst($status) ?></span></div>
    <?php if (mb_strlen($description) > 280): ?>
        <p class="community-help-description"><?= escapeHtml(mb_substr($description, 0, 250)) ?>...</p>
        <details class="community-help-detail"><summary>Full request <?= uiIcon('chevron-right') ?></summary><p class="community-help-description"><?= escapeHtml($description) ?></p></details>
    <?php else: ?><p class="community-help-description"><?= escapeHtml($description) ?></p><?php endif; ?>
    <div class="community-help-meta"><span><?= uiIcon('map-pin') ?><?= escapeHtml($area ?: 'Community-wide') ?></span><span><?= uiIcon('calendar-days') ?><time><?= escapeHtml($request['created_at']) ?></time></span></div>
    <?php
}

function renderCommunityHelpPagination(string $path, array $filters, int $page, int $pages, int $total, int $perPage): void
{
    if (!$total) { return; }
    ?>
    <nav class="pagination community-help-pagination" aria-label="Community help pages"><span><?= ($page - 1) * $perPage + 1 ?> - <?= min($page * $perPage, $total) ?> of <?= $total ?></span><span>Page <?= $page ?> of <?= $pages ?></span>
    <?php foreach ([-1 => 'Previous page', 1 => 'Next page'] as $direction => $label): $destination = $page + $direction; ?>
        <?php if ($destination >= 1 && $destination <= $pages): ?><a class="icon-link" title="<?= $label ?>" aria-label="<?= $label ?>" href="<?= escapeHtml(appUrl($path . '?' . http_build_query(array_merge($filters, ['page' => $destination])))) ?>"><?= uiIcon($direction < 0 ? 'chevron-left' : 'chevron-right') ?></a><?php endif; ?>
    <?php endforeach; ?></nav>
    <?php
}
