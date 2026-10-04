<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (!empty($_SESSION['member_id'])) {
    require_once dirname(__DIR__) . '/includes/auth_member.php';
    $identity = requireMember();
    $recipientType = 'member';
} elseif (!empty($_SESSION['staff_id'])) {
    require_once dirname(__DIR__) . '/includes/auth_staff.php';
    $identity = requireRole(['admin', 'volunteer', 'cc_member']);
    $recipientType = 'staff';
} else { redirectTo('public/login.php'); }
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/money.php';
require_once dirname(__DIR__) . '/includes/request_support.php';
header('Cache-Control: private, no-store');
$requestId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$requestId) { http_response_code(404); exit('Request not found.'); }
try {
    $statement = getDb()->prepare('SELECT r.*,m.full_name AS member_name,m.membership_id FROM requests r JOIN members m ON m.id = r.member_id WHERE r.id = ?');
    $statement->execute([$requestId]);
    $request = $statement->fetch();
} catch (Throwable $exception) {
    error_log('Private request lookup failed: ' . get_class($exception));
    http_response_code(503); exit('The request is temporarily unavailable. Please try again.');
}
if (!$request || !canViewRequest($request, $identity, $recipientType)) {
    http_response_code(404); exit('Request not found.');
}
$documents = [];
$documentError = '';
try {
    $statement = getDb()->prepare('SELECT id,document_type,original_name,stored_path,file_size FROM request_documents WHERE request_id = ? ORDER BY id');
    $statement->execute([$requestId]);
    $documents = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Request attachment list failed: ' . get_class($exception));
    $documentError = 'Attachments could not be loaded. Please refresh to try again.';
}
$types = ['scholarship' => 'Education / scholarship', 'medical_aid' => 'Medical aid', 'loan' => 'Loan'];
$amount = $request['amount_requested'] !== null ? moneyToCents((string) $request['amount_requested'], true) : null;
$back = $recipientType === 'member' ? 'public/my_requests.php' : ($identity['role'] === 'admin' ? 'public/admin/requests_overview.php' : 'public/staff_reviews.php');
pageHeader('Request #' . $requestId, $identity, $recipientType === 'staff');
?>
<section class="workspace service-workspace request-detail-workspace">
    <div class="page-heading"><div><p class="eyebrow">PRIVATE REQUEST</p><h1><?= escapeHtml($types[$request['type']] ?? 'Request') ?> #<?= $requestId ?></h1></div><a href="<?= escapeHtml(appUrl($back)) ?>">Back to requests</a></div>
    <dl class="details"><div><dt>Member</dt><dd><?= escapeHtml($request['member_name']) ?></dd></div><div><dt>Membership ID</dt><dd><?= escapeHtml($request['membership_id']) ?></dd></div><div><dt>Amount requested</dt><dd><?= $amount !== null ? escapeHtml(formatMoney($amount)) : 'Not available' ?></dd></div><div><dt>Submitted</dt><dd><?= escapeHtml($request['created_at']) ?></dd></div></dl>
    <h2>Request summary</h2><p class="request-description"><?= escapeHtml($request['description']) ?></p>
    <?php if ($request['type'] === 'scholarship'): ?>
    <h2>Education details</h2><dl class="details"><div><dt>Grade / class / course year</dt><dd><?= escapeHtml($request['education_grade'] ?? 'Not recorded') ?></dd></div><div><dt>School / college</dt><dd><?= escapeHtml($request['school_name'] ?? 'Not recorded') ?></dd></div><div><dt>Last examination marks</dt><dd><?= isset($request['last_exam_marks'], $request['last_exam_total']) ? escapeHtml($request['last_exam_marks'] . ' / ' . $request['last_exam_total']) : 'Not recorded' ?></dd></div></dl>
    <?php elseif ($request['type'] === 'medical_aid'): ?>
    <h2>Medical aid details</h2><dl class="details"><div><dt>Title</dt><dd><?= escapeHtml($request['medical_title'] ?? 'Not recorded') ?></dd></div><div><dt>Due date</dt><dd><?= escapeHtml($request['due_date'] ?? 'Not recorded') ?></dd></div><div><dt>Document authorization confirmed</dt><dd><?= !empty($request['medical_confirmation']) ? 'Yes' : 'Not recorded' ?></dd></div></dl><p class="request-description"><?= escapeHtml($request['medical_details'] ?? 'No additional medical details on record.') ?></p>
    <?php endif; ?>
    <h2>Review status</h2><dl class="request-statuses"><?php foreach ([1 => 'Guarantor 1', 2 => 'Guarantor 2', 'office' => 'Office'] as $slot => $label): $status = $request[$slot === 'office' ? 'office_status' : 'guarantor' . $slot . '_status']; ?><div><dt><?= $label ?></dt><dd><?= escapeHtml(ucfirst((string) $status)) ?></dd><?php if ($slot !== 'office' && !empty($request['guarantor' . $slot . '_notes'])): ?><p class="guarantor-notes"><?= escapeHtml($request['guarantor' . $slot . '_notes']) ?></p><?php endif; ?></div><?php endforeach; ?></dl>
    <h2>Documents</h2><?php showError($documentError); ?>
    <?php $labels = ['exam_result' => 'Last examination result'] + medicalDocumentCategories(); foreach ($documents as $document): ?>
    <article class="request-row"><h3><?= escapeHtml($labels[$document['document_type']] ?? 'Supporting document') ?></h3><p><?= escapeHtml($document['original_name']) ?></p><div class="document-actions"><a class="button-link button-secondary" target="_blank" rel="noopener" href="<?= escapeHtml(appUrl('public/request_document.php?id=' . $requestId . '&document_id=' . (int) $document['id'] . '&view=1')) ?>"><?= uiIcon('arrow-up-right') ?>View supporting document</a><a class="document-link" href="<?= escapeHtml(appUrl('public/request_document.php?id=' . $requestId . '&document_id=' . (int) $document['id'])) ?>"><?= uiIcon('download') ?>Download</a><span class="muted"><?= number_format((int) $document['file_size'] / 1024, 1) ?> KB</span></div></article>
    <?php endforeach; ?>
    <?php $legacy = !empty($request['document_path']) && !in_array($request['document_path'], array_column($documents, 'stored_path'), true); if ($legacy): ?><div class="document-actions"><a class="button-link button-secondary" target="_blank" rel="noopener" href="<?= escapeHtml(appUrl('public/request_document.php?id=' . $requestId . '&view=1')) ?>"><?= uiIcon('arrow-up-right') ?>View supporting document</a><a class="document-link" href="<?= escapeHtml(appUrl('public/request_document.php?id=' . $requestId)) ?>"><?= uiIcon('download') ?>Download supporting document</a></div><?php endif; ?>
    <?php if (!$documents && !$legacy && !$documentError): ?><p class="muted">No documents on record.</p><?php endif; ?>
</section>
<?php pageFooter(); ?>
