<?php
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/money.php';
require_once dirname(__DIR__) . '/includes/fund_documents.php';
$isStaff = false;
if (!empty($_SESSION['member_id'])) {
    require_once dirname(__DIR__) . '/includes/auth_member.php';
    $identity = requireMember();
} elseif (!empty($_SESSION['staff_id'])) {
    require_once dirname(__DIR__) . '/includes/auth_staff.php';
    $identity = requireRole('admin');
    $isStaff = true;
} else {
    redirectTo('public/login.php');
}
header('Cache-Control: private, no-store');
$hasFundId = array_key_exists('fund_id', $_GET);
$fundId = filter_var($_GET['fund_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$fund = null;
$funds = [];
$documents = [];
$error = '';
$pages = 1;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
if ($hasFundId && !$fundId) {
    http_response_code(404);
    $error = 'Fund not found.';
} else {
    try {
        $ownership = $isStaff ? '' : ' AND member_id = ?';
        if ($fundId) {
            $statement = getDb()->prepare('SELECT id, title, reason, purpose, medical_details, amount_needed, due_date, status FROM funds WHERE id = ?' . $ownership);
            $statement->execute($isStaff ? [$fundId] : [$fundId, $identity['id']]);
            $fund = $statement->fetch();
            if (!$fund) {
                http_response_code(404);
                $error = 'Fund not found.';
            } else {
                $statement = getDb()->prepare('SELECT id, document_type, original_name, file_size, created_at FROM fund_documents WHERE fund_id = ? ORDER BY document_type, id');
                $statement->execute([$fundId]);
                $documents = $statement->fetchAll();
            }
        } else {
            $where = $isStaff ? '' : ' WHERE member_id = ?';
            $statement = getDb()->prepare('SELECT COUNT(*) FROM funds' . $where);
            $statement->execute($isStaff ? [] : [$identity['id']]);
            $pages = max(1, (int) ceil((int) $statement->fetchColumn() / 20));
            $page = min($page, $pages);
            $statement = getDb()->prepare('SELECT id, title, purpose, status, amount_needed, created_at FROM funds' . $where . ' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
            $parameter = 1;
            if (!$isStaff) {
                $statement->bindValue($parameter++, (int) $identity['id'], PDO::PARAM_INT);
            }
            $statement->bindValue($parameter++, 20, PDO::PARAM_INT);
            $statement->bindValue($parameter, ($page - 1) * 20, PDO::PARAM_INT);
            $statement->execute();
            $funds = $statement->fetchAll();
        }
    } catch (Throwable $exception) {
        error_log('Private fund documents failed: ' . $exception->getMessage());
        $error = 'Fund documents could not be loaded. Please try again shortly.';
    }
}
$success = $_SESSION['fund_success'] ?? '';
unset($_SESSION['fund_success']);
$statusLabels = ['pending_approval' => 'Awaiting office approval', 'active' => 'Active', 'completed' => 'Completed'];
pageHeader($fundId || $isStaff ? 'Fund documents' : 'My funds', $identity, $isStaff);
?>
<section class="workspace">
    <div class="page-heading"><div><p class="eyebrow">PRIVATE FUND RECORDS</p><h1><?= $fundId || $isStaff ? 'Fund documents' : 'My funds' ?></h1></div><a href="<?= escapeHtml(appUrl($isStaff ? 'public/admin/index.php' : 'public/raise_fund.php')) ?>"><?= $isStaff ? 'Admin workspace' : 'Raise a medical fund' ?></a></div>
    <?php if ($success): ?><p class="success" role="status"><?= escapeHtml($success) ?></p><?php endif; ?>
    <?php showError($error); ?>
    <?php if ($fund && !$error): $target = moneyToCents((string) $fund['amount_needed']); ?>
        <h2><?= escapeHtml($fund['title']) ?> <span class="muted">#<?= (int) $fund['id'] ?></span></h2>
        <dl class="details"><div><dt>Status</dt><dd><?= escapeHtml($statusLabels[$fund['status']] ?? $fund['status']) ?></dd></div><div><dt>Target amount</dt><dd><?= $target !== null ? escapeHtml(formatMoney($target)) : 'Unavailable' ?></dd></div><div><dt>Purpose</dt><dd><?= $fund['purpose'] === 'medical' ? 'Medical' : 'Needs classification' ?></dd></div></dl>
        <h2>Public summary</h2><p class="request-description"><?= escapeHtml($fund['reason'] ?? '') ?></p>
        <h2>Private medical details</h2><p class="request-description"><?= escapeHtml($fund['medical_details'] ?: 'No medical details recorded.') ?></p>
        <?php foreach (medicalDocumentCategories() as $type => $label): $group = array_filter($documents, static fn($document) => $document['document_type'] === $type); ?>
        <section class="document-group"><h2><?= escapeHtml($label) ?></h2>
            <?php if (!$group): ?><p class="muted"><?= $type === 'other' ? 'No additional documents.' : 'Required documents are missing.' ?></p><?php endif; ?>
            <?php foreach ($group as $document): ?><div class="document-row"><a class="document-link" href="<?= escapeHtml(appUrl('public/fund_document.php?id=' . (int) $document['id'])) ?>"><img src="<?= escapeHtml(appUrl('assets/icons/download.svg')) ?>" width="18" height="18" alt=""><span class="document-name"><?= escapeHtml($document['original_name']) ?></span></a><span class="muted"><?= escapeHtml(number_format((int) $document['file_size'] / 1024, 0)) ?> KB</span></div><?php endforeach; ?>
        </section>
        <?php endforeach; ?>
        <a href="<?= escapeHtml(appUrl('public/fund_documents.php')) ?>">All fund records</a>
    <?php elseif (!$hasFundId && !$error): ?>
        <?php if (!$funds): ?><div class="empty-state"><h2>No fund submissions yet</h2></div><?php endif; ?>
        <?php foreach ($funds as $record): $target = moneyToCents((string) $record['amount_needed']); ?>
        <article class="request-row"><div class="request-heading"><div><h2><?= escapeHtml($record['title']) ?> <span class="muted">#<?= (int) $record['id'] ?></span></h2><time class="muted"><?= escapeHtml($record['created_at']) ?></time></div><span class="status-badge <?= $record['status'] === 'pending_approval' ? 'status-pending' : 'status-approved' ?>"><?= escapeHtml($statusLabels[$record['status']] ?? $record['status']) ?></span></div><p><?= $target !== null ? escapeHtml(formatMoney($target)) : 'Amount unavailable' ?></p><a href="<?= escapeHtml(appUrl('public/fund_documents.php?fund_id=' . (int) $record['id'])) ?>">View documents</a></article>
        <?php endforeach; ?>
        <?php if ($pages > 1): ?><nav class="pagination" aria-label="Fund record pages"><span>Page <?= $page ?> of <?= $pages ?></span><?php foreach ([-1 => 'Previous page', 1 => 'Next page'] as $direction => $label): $destination = $page + $direction; if ($destination >= 1 && $destination <= $pages): ?><a class="icon-link" title="<?= $label ?>" aria-label="<?= $label ?>" href="<?= escapeHtml(appUrl('public/fund_documents.php?page=' . $destination)) ?>"><img src="<?= escapeHtml(appUrl('assets/icons/chevron-' . ($direction < 0 ? 'left' : 'right') . '.svg')) ?>" width="20" height="20" alt=""></a><?php endif; endforeach; ?></nav><?php endif; ?>
    <?php endif; ?>
</section>
<?php pageFooter(); ?>
