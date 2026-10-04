<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/money.php';
require_once dirname(__DIR__) . '/includes/fund_documents.php';
$member = requireMember();
$funds = [];
$error = '';
$pages = 1;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$perPage = 12;
try {
    $eligible = medicalFundEligibilitySql();
    $total = (int) getDb()->query("SELECT COUNT(*) FROM funds WHERE status = 'active' AND " . $eligible)->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    // Beneficiary and donor identities must never be selected for this view.
    $statement = getDb()->prepare("SELECT id, title, reason, amount_needed, amount_raised, due_date FROM funds WHERE status = 'active' AND " . $eligible . ' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
    $statement->bindValue(1, $perPage, PDO::PARAM_INT);
    $statement->bindValue(2, ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    $funds = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Community funds failed: ' . $exception->getMessage());
    $error = 'Community funds could not be loaded. Please try again shortly.';
}
$success = $_SESSION['fund_success'] ?? '';
unset($_SESSION['fund_success']);
pageHeader('Medical funds', $member);
?>
<section class="workspace">
    <div class="page-heading"><div><p class="eyebrow">MEDICAL SUPPORT</p><h1>Medical funds</h1></div><div class="form-actions"><a href="<?= escapeHtml(appUrl('public/fund_documents.php')) ?>">My funds</a><a class="button-link" href="<?= escapeHtml(appUrl('public/raise_fund.php')) ?>">Raise a medical fund</a></div></div>
    <?php if ($success): ?><p class="success" role="status"><?= escapeHtml($success) ?></p><?php endif; ?>
    <?php showError($error); ?>
    <?php if (!$funds && !$error): ?><div class="empty-state"><h2>No active medical funds</h2></div><?php endif; ?>
    <div class="fund-grid">
    <?php foreach ($funds as $fund): $needed = moneyToCents((string) $fund['amount_needed']); $raised = moneyToCents((string) $fund['amount_raised'], true); ?>
    <article class="fund-card"><h2><?= escapeHtml($fund['title']) ?></h2><p class="request-description"><?= escapeHtml($fund['reason'] ?? '') ?></p>
        <?php if ($needed !== null && $raised !== null): $percentage = min(100, (int) floor($raised * 100 / $needed)); ?>
        <div class="fund-progress-label"><strong><?= escapeHtml(formatMoney($raised)) ?></strong><span class="muted"><?= $percentage ?>%</span></div>
        <progress value="<?= min($raised, $needed) ?>" max="<?= $needed ?>" aria-label="<?= escapeHtml($fund['title'] . ' progress') ?>"><?= $percentage ?>%</progress>
        <p class="muted">Target <?= escapeHtml(formatMoney($needed)) ?></p>
        <?php else: ?><p class="error">Fund amounts are unavailable.</p><?php endif; ?>
        <?php if ($fund['due_date']): ?><p class="muted">Due <?= escapeHtml($fund['due_date']) ?></p><?php endif; ?>
        <div class="fund-card-action"><?php if ($needed !== null && $raised !== null && $raised < $needed): ?><a class="button-link" href="<?= escapeHtml(appUrl('public/donate.php?id=' . (int) $fund['id'])) ?>">Donate</a><?php elseif ($needed !== null && $raised !== null): ?><span class="status-badge status-approved">Fully funded</span><?php endif; ?></div>
    </article>
    <?php endforeach; ?>
    </div>
    <?php if ($pages > 1 && !$error): ?><nav class="pagination" aria-label="Community fund pages"><span>Page <?= $page ?> of <?= $pages ?></span><?php foreach ([-1 => 'Previous page', 1 => 'Next page'] as $direction => $label): $destination = $page + $direction; if ($destination >= 1 && $destination <= $pages): ?><a class="icon-link" title="<?= $label ?>" aria-label="<?= $label ?>" href="<?= escapeHtml(appUrl('public/funds_board.php?page=' . $destination)) ?>"><img src="<?= escapeHtml(appUrl('assets/icons/chevron-' . ($direction < 0 ? 'left' : 'right') . '.svg')) ?>" width="20" height="20" alt=""></a><?php endif; endforeach; ?></nav><?php endif; ?>
</section>
<?php pageFooter(); ?>
