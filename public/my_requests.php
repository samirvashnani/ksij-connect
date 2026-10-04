<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$member = requireMember();
require_once dirname(__DIR__) . '/includes/money.php';
header('Cache-Control: no-store, private');
$types = ['scholarship' => 'Scholarship', 'medical_aid' => 'Medical aid', 'loan' => 'Loan'];
$requests = [];
$error = '';
$total = 0;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$perPage = 20;
$pages = 1;
try {
    $statement = getDb()->prepare('SELECT COUNT(*) FROM requests WHERE member_id = ?');
    $statement->execute([$member['id']]);
    $total = (int) $statement->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $statement = getDb()->prepare('SELECT r.id, r.type, r.description, r.amount_requested, r.document_path, r.guarantor1_status, r.guarantor2_status, r.guarantor1_notes, r.guarantor2_notes, r.office_status, r.created_at, g1.full_name AS guarantor1_name, g2.full_name AS guarantor2_name FROM requests r LEFT JOIN staff_users g1 ON g1.id = r.guarantor1_id LEFT JOIN staff_users g2 ON g2.id = r.guarantor2_id WHERE r.member_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT ? OFFSET ?');
    $statement->bindValue(1, (int) $member['id'], PDO::PARAM_INT);
    $statement->bindValue(2, $perPage, PDO::PARAM_INT);
    $statement->bindValue(3, ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    $requests = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Request history failed: ' . $exception->getMessage());
    $error = 'Your requests could not be loaded. Please try again shortly.';
}
$success = $_SESSION['request_success'] ?? '';
unset($_SESSION['request_success']);
pageHeader('My requests', $member);
?>
<section class="workspace formal-workspace request-tracker">
    <div class="page-heading"><div><p class="eyebrow">MEMBER SERVICES</p><h1>My formal requests</h1></div><a class="button-link" href="<?= escapeHtml(appUrl('public/member_request.php')) ?>"><?= uiIcon('plus') ?>New request</a></div>
    <?php if ($success): ?><p class="success" role="status"><?= escapeHtml($success) ?></p><?php endif; ?>
    <?php showError($error); ?>
    <?php if (!$requests && !$error): ?><div class="empty-state"><h2>No requests yet</h2><a href="<?= escapeHtml(appUrl('public/member_request.php')) ?>">Submit a request</a></div><?php endif; ?>
    <?php if ($requests && !$error): ?><div class="request-ledger-heading"><h2>Request ledger</h2><span class="muted"><?= $total ?> <?= $total === 1 ? 'request' : 'requests' ?></span></div><?php endif; ?>
    <?php foreach ($requests as $index => $request):
        $office = ['label' => 'Awaiting guarantors', 'tone' => 'pending'];
        if ($request['office_status'] === 'approved') {
            $office = ['label' => 'Approved', 'tone' => 'approved'];
        } elseif ($request['office_status'] === 'rejected') {
            $office = ['label' => 'Rejected', 'tone' => 'rejected'];
        } elseif ($request['guarantor1_status'] === 'rejected' || $request['guarantor2_status'] === 'rejected') {
            $office = ['label' => 'Guarantor rejected', 'tone' => 'rejected'];
        } elseif ($request['guarantor1_status'] === 'approved' && $request['guarantor2_status'] === 'approved') {
            $office = ['label' => 'Awaiting office', 'tone' => 'pending'];
        }
    ?>
    <details class="tracked-request"<?= $index === 0 ? ' open' : '' ?>>
        <summary class="tracked-summary">
            <span class="tracked-identity"><small>REQUEST #<?= (int) $request['id'] ?></small><strong><?= escapeHtml($types[$request['type']] ?? 'Request') ?></strong><time><?= escapeHtml($request['created_at']) ?></time></span>
            <?php $requestAmount = $request['amount_requested'] !== null ? moneyToCents((string) $request['amount_requested'], true) : null; ?>
            <strong class="tracked-amount"><?= $requestAmount !== null ? escapeHtml(formatMoney($requestAmount)) : 'Amount unavailable' ?></strong>
            <span class="status-badge status-<?= $office['tone'] ?>"><?= $office['label'] ?></span><?= uiIcon('chevron-right', 'tracked-chevron') ?>
        </summary>
        <div class="tracked-body"><section aria-label="Verification timeline"><h3>Verification timeline</h3>
        <dl class="request-timeline">
            <div class="timeline-approved"><dt>Request submitted</dt><dd><?= escapeHtml($request['created_at']) ?></dd></div>
            <?php foreach ([1, 2] as $slot): $status = $request['guarantor' . $slot . '_status']; $status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'pending'; ?>
            <div class="timeline-<?= $status ?>"><dt>Guarantor <?= $slot ?> review</dt><dd><span class="guarantor-name"><?= escapeHtml($request['guarantor' . $slot . '_name'] ?? 'Not assigned') ?></span><span class="status-badge status-<?= $status ?>"><?= ucfirst($status) ?></span><?php if ($request['guarantor' . $slot . '_notes']): ?><p class="guarantor-notes"><?= escapeHtml($request['guarantor' . $slot . '_notes']) ?></p><?php endif; ?></dd></div>
            <?php endforeach; ?>
            <?php $officeStatus = in_array($request['office_status'], ['approved', 'rejected'], true) ? $request['office_status'] : 'pending'; ?>
            <div class="timeline-<?= $officeStatus ?>"><dt>Office review</dt><dd><span class="status-badge status-<?= $officeStatus ?>"><?= ucfirst($officeStatus) ?></span><?php if ($officeStatus === 'pending'): ?><p class="muted"><?= $office['label'] === 'Guarantor rejected' ? 'A guarantor rejected this request.' : ($office['label'] === 'Awaiting office' ? 'Awaiting the office decision.' : 'Awaiting both guarantor approvals.') ?></p><?php endif; ?></dd></div>
        </dl>
        </section><aside class="tracked-dossier" aria-label="Request dossier"><p class="eyebrow">REQUEST DOSSIER</p><h3>Purpose of request</h3><p class="tracked-description"><?= escapeHtml($request['description']) ?></p><a class="document-link" href="<?= escapeHtml(appUrl('public/request_details.php?id=' . (int) $request['id'])) ?>"><?= uiIcon('folder') ?>Details and documents<?= uiIcon('arrow-up-right') ?></a>
        <?php if ($request['document_path']): ?><a class="document-link" href="<?= escapeHtml(appUrl('public/request_document.php?id=' . (int) $request['id'])) ?>"><?= uiIcon('download') ?>Download document</a><?php endif; ?>
        </aside></div>
    </details>
    <?php endforeach; ?>
    <?php if ($pages > 1 && !$error): ?><nav class="pagination" aria-label="Request pages"><span>Page <?= $page ?> of <?= $pages ?></span><?php foreach ([-1 => 'Previous page', 1 => 'Next page'] as $direction => $label): $destination = $page + $direction; if ($destination >= 1 && $destination <= $pages): ?><a class="icon-link" title="<?= $label ?>" aria-label="<?= $label ?>" href="<?= escapeHtml(appUrl('public/my_requests.php?page=' . $destination)) ?>"><img src="<?= escapeHtml(appUrl('assets/icons/chevron-' . ($direction < 0 ? 'left' : 'right') . '.svg')) ?>" width="20" height="20" alt=""></a><?php endif; endforeach; ?></nav><?php endif; ?>
</section>
<?php pageFooter(); ?>
