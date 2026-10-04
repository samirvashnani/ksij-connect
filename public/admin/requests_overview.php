<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
require_once dirname(__DIR__, 2) . '/includes/money.php';
require_once dirname(__DIR__, 2) . '/includes/notifications.php';
$staff = requireRole('admin');
$pdo = getDb();
header('Cache-Control: private, no-store');
$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$error = ''; $loadError = ''; $rows = []; $areas = []; $total = 0; $pages = 1;
$types = ['scholarship'=>'Education / scholarship','medical_aid'=>'Medical aid','loan'=>'Loan support'];
$stages = ['all'=>'All requests','ready'=>'Ready for office','waiting'=>'Awaiting guarantors','blocked'=>'Guarantor rejected','approved'=>'Office approved','rejected'=>'Office rejected'];
$sorts = ['newest'=>'Newest first','oldest'=>'Oldest first','amount'=>'Highest amount'];
$stageSql = "CASE WHEN r.office_status='approved' THEN 'approved' WHEN r.office_status='rejected' THEN 'rejected' WHEN r.guarantor1_status='rejected' OR r.guarantor2_status='rejected' THEN 'blocked' WHEN r.guarantor1_status='approved' AND r.guarantor2_status='approved' THEN 'ready' ELSE 'waiting' END";
function officeBadge(string $status): string {
    $status = in_array($status,['approved','rejected','pending'],true) ? $status : 'pending';
    return '<span class="status-badge status-' . $status . '">' . ucfirst($status) . '</span>';
}
$filters = ['search'=>'','stage'=>'all','type'=>'','area'=>'','from'=>'','to'=>'','sort'=>'newest'];
$page = max(1,filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT) ?: 1);
try {
    foreach ($filters as $key=>$default) {
        $value = $_GET[$key] ?? $default;
        if (!is_string($value) || !mb_check_encoding($value,'UTF-8') || mb_strlen($value)>160) { throw new DomainException('Check the search and filter values.'); }
        $filters[$key] = trim($value);
    }
    if (!isset($stages[$filters['stage']]) || !isset($sorts[$filters['sort']]) || ($filters['type']!=='' && !isset($types[$filters['type']]))) { throw new DomainException('Choose a valid request filter.'); }
    foreach (['from','to'] as $key) {
        if ($filters[$key]==='') { continue; }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d',$filters[$key]);
        if (!$date || $date->format('Y-m-d')!==$filters[$key]) { throw new DomainException('Choose valid dates.'); }
    }
    if ($filters['from']!=='' && $filters['to']!=='' && $filters['from']>$filters['to']) { throw new DomainException('The start date must not be after the end date.'); }
} catch (DomainException $e) { $loadError = $e->getMessage(); }
$pagePath = 'public/admin/requests_overview.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!is_string($csrf) || !hash_equals(csrfToken(),$csrf)) { throw new DomainException('Your session changed. Reload before trying again.'); }
        $requestId = filter_var($_POST['request_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $decision = $_POST['decision'] ?? '';
        if (($_POST['action'] ?? '')!=='decide' || !$requestId || !in_array($decision,['approved','rejected'],true)) { throw new DomainException('Choose a valid request and office decision.'); }
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT id FROM staff_users WHERE id=? AND role='admin' AND is_active=1 FOR UPDATE"); $s->execute([$staff['id']]);
        if (!$s->fetchColumn()) { throw new DomainException('Your office permissions changed. Please sign in again.'); }
        $s = $pdo->prepare('SELECT id,member_id,type,office_status,guarantor1_status,guarantor2_status FROM requests WHERE id=? FOR UPDATE'); $s->execute([$requestId]); $request=$s->fetch();
        if (!$request) { throw new DomainException('Request not found.'); }
        if ($request['office_status']!=='pending') { throw new DomainException('This request already has an office decision. Refresh the list.'); }
        if ($request['guarantor1_status']!=='approved' || $request['guarantor2_status']!=='approved') { throw new DomainException('Both guarantors must approve before office review.'); }
        $s = $pdo->prepare("UPDATE requests SET office_status=? WHERE id=? AND office_status='pending'"); $s->execute([$decision,$requestId]);
        if ($s->rowCount()!==1) { throw new DomainException('The request changed. Refresh the list.'); }
        createNotification('member',(int)$request['member_id'],'Request updated','Your ' . str_replace('_',' ',$request['type']) . ' request #' . $requestId . ' was ' . $decision . ' by the office.');
        $pdo->commit();
        $message = 'Request #' . $requestId . ' ' . $decision . '.';
        if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'message'=>$message]); exit; }
        $_SESSION['office_request_success'] = $message;
        redirectTo($pagePath . '?' . http_build_query($filters+['page'=>$page]));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = $e instanceof DomainException ? $e->getMessage() : 'The decision could not be confirmed. Refresh the list before trying again.';
        if (!($e instanceof DomainException)) { error_log('Office decision failed: ' . get_class($e)); }
        if ($ajax) { http_response_code($e instanceof DomainException ? 422 : 500); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'message'=>$error]); exit; }
    }
}
if ($loadError==='') {
    try {
        $areas = $pdo->query("SELECT DISTINCT TRIM(area) FROM members WHERE area IS NOT NULL AND TRIM(area)<>'' ORDER BY TRIM(area)")->fetchAll(PDO::FETCH_COLUMN);
        $where = '1=1'; $params = [];
        if ($filters['stage']!=='all') { $where .= ' AND (' . $stageSql . ')=?'; $params[]=$filters['stage']; }
        if ($filters['type']!=='') { $where .= ' AND r.type=?'; $params[]=$filters['type']; }
        if ($filters['area']!=='') { $where .= ' AND LOWER(TRIM(m.area))=LOWER(?)'; $params[]=$filters['area']; }
        if ($filters['from']!=='') { $where .= ' AND r.created_at>=?'; $params[]=$filters['from'] . ' 00:00:00'; }
        if ($filters['to']!=='') { $where .= ' AND r.created_at<=?'; $params[]=$filters['to'] . ' 23:59:59'; }
        if ($filters['search']!=='') {
            $like = '%' . str_replace(['!','%','_'],['!!','!%','!_'],$filters['search']) . '%';
            $where .= " AND (m.full_name LIKE ? ESCAPE '!' OR m.membership_id LIKE ? ESCAPE '!' OR CAST(r.id AS CHAR) LIKE ? ESCAPE '!')";
            array_push($params,$like,$like,$like);
        }
        $from = 'requests r JOIN members m ON m.id=r.member_id';
        $s = $pdo->prepare('SELECT COUNT(*) FROM ' . $from . ' WHERE ' . $where); $s->execute($params); $total=(int)$s->fetchColumn();
        $pages = max(1,(int)ceil($total/12)); $page=min($page,$pages);
        $order = ['newest'=>'r.created_at DESC,r.id DESC','oldest'=>'r.created_at,r.id','amount'=>'r.amount_requested DESC,r.id DESC'][$filters['sort']];
        $s = $pdo->prepare('SELECT r.id,r.type,r.amount_requested,r.created_at,r.office_status,r.guarantor1_status,r.guarantor2_status,r.document_path,m.full_name AS member_name,m.membership_id,m.area,g1.full_name AS guarantor1_name,g2.full_name AS guarantor2_name,(' . $stageSql . ') AS review_stage FROM ' . $from . ' LEFT JOIN staff_users g1 ON g1.id=r.guarantor1_id LEFT JOIN staff_users g2 ON g2.id=r.guarantor2_id WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT 12 OFFSET ' . (($page-1)*12));
        $s->execute($params); $rows=$s->fetchAll();
    } catch (Throwable $e) { error_log('Office requests list: ' . get_class($e)); $loadError='Requests could not be loaded. Please try again.'; }
}
$pageUrl = appUrl($pagePath . '?' . http_build_query($filters+['page'=>$page]));
$success = $_SESSION['office_request_success'] ?? ''; unset($_SESSION['office_request_success']);
pageHeader('Requests overview',$staff,true);
?>
<section class="workspace office-requests" data-office-requests>
<header class="operations-heading"><div><p class="eyebrow">OFFICE REVIEW</p><h1>Formal requests</h1></div><a class="document-link" href="<?= escapeHtml(appUrl('public/admin/index.php')) ?>"><?= uiIcon('layout-dashboard') ?>Dashboard</a></header>
<?php showError($error); if ($success): ?><p class="success" role="status"><?= escapeHtml($success) ?></p><?php endif; ?>
<form method="get" action="<?= escapeHtml(appUrl($pagePath)) ?>" class="office-request-filters" data-office-filter>
<label class="office-request-search">Member, membership ID or request ID<input type="search" name="search" maxlength="160" value="<?= escapeHtml($filters['search']) ?>" placeholder="Search requests"></label>
<label>Review stage<select name="stage"><?php foreach ($stages as $key=>$label): ?><option value="<?= $key ?>"<?= $filters['stage']===$key ? ' selected' : '' ?>><?= escapeHtml($label) ?></option><?php endforeach; ?></select></label>
<label>Request type<select name="type"><option value="">All types</option><?php foreach ($types as $key=>$label): ?><option value="<?= $key ?>"<?= $filters['type']===$key ? ' selected' : '' ?>><?= escapeHtml($label) ?></option><?php endforeach; ?></select></label>
<label>Area<select name="area"><option value="">All areas</option><?php foreach ($areas as $area): ?><option value="<?= escapeHtml($area) ?>"<?= $filters['area']===$area ? ' selected' : '' ?>><?= escapeHtml($area) ?></option><?php endforeach; ?></select></label>
<label>Submitted from<input type="date" name="from" value="<?= escapeHtml($filters['from']) ?>"></label><label>Submitted to<input type="date" name="to" value="<?= escapeHtml($filters['to']) ?>"></label>
<label>Sort by<select name="sort"><?php foreach ($sorts as $key=>$label): ?><option value="<?= $key ?>"<?= $filters['sort']===$key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<div class="office-request-filter-actions"><button type="submit" class="icon-button" title="Apply filters" aria-label="Apply filters"><?= uiIcon('search') ?></button><a class="icon-button" href="<?= escapeHtml(appUrl($pagePath)) ?>" title="Clear filters" aria-label="Clear filters"><?= uiIcon('x') ?></a><button type="button" class="icon-button" data-office-refresh hidden title="Refresh requests" aria-label="Refresh requests"><?= uiIcon('refresh-cw') ?></button></div></form>
<p class="office-request-feedback" data-office-feedback role="status" aria-live="polite"></p>
<div data-office-results data-page="<?= $page ?>"<?= $loadError ? ' data-load-error' : '' ?>><?php showError($loadError); ?>
<div class="office-request-results-heading"><h2><?= escapeHtml($stages[$filters['stage']] ?? 'Requests') ?></h2><span data-office-count><?= $total ? (($page-1)*12+1) . '-' . min($page*12,$total) . ' of ' : '' ?><?= $total ?> requests</span></div>
<?php if (!$rows && !$loadError): ?><div class="operations-empty"><?= uiIcon('clipboard-list') ?><h3>No matching requests</h3><a href="<?= escapeHtml(appUrl($pagePath)) ?>">Clear filters</a></div><?php endif; ?>
<?php foreach ($rows as $row): $ready=$row['review_stage']==='ready'; $amount=$row['amount_requested']!==null ? moneyToCents((string)$row['amount_requested'],true) : null; ?>
<article class="office-request-row"><header class="office-request-row-heading"><div><div class="office-request-reference"><span>#<?= (int)$row['id'] ?></span><span><?= escapeHtml($types[$row['type']] ?? $row['type']) ?></span></div><h3><?= escapeHtml($row['member_name']) ?></h3><p><?= escapeHtml($row['membership_id']) ?> <span>/</span> <?= escapeHtml(trim((string)$row['area']) ?: 'Area not recorded') ?></p></div><div class="office-request-amount"><span>Requested amount</span><strong><?= $amount!==null ? escapeHtml(formatMoney($amount)) : 'Not specified' ?></strong><small>Submitted <?= escapeHtml(substr($row['created_at'],0,10)) ?></small></div></header>
<div class="office-request-review-grid"><?php foreach ([1,2] as $slot): ?><div><span class="office-request-label">Guarantor <?= $slot ?></span><strong><?= escapeHtml($row['guarantor'.$slot.'_name'] ?: 'Not recorded') ?></strong><?= officeBadge((string)$row['guarantor'.$slot.'_status']) ?></div><?php endforeach; ?><div><span class="office-request-label">Office review</span><strong><?= escapeHtml($stages[$row['review_stage']]) ?></strong><?= officeBadge((string)$row['office_status']) ?></div></div>
<footer class="office-request-row-actions"><div class="document-actions"><a class="document-link" href="<?= escapeHtml(appUrl('public/request_details.php?id=' . $row['id'])) ?>"><?= uiIcon('folder') ?>Details & documents</a><?php if ($row['document_path']): ?><a class="document-link" target="_blank" rel="noopener" href="<?= escapeHtml(appUrl('public/request_document.php?id=' . $row['id'] . '&view=1')) ?>"><?= uiIcon('arrow-up-right') ?>View document</a><a class="document-link" href="<?= escapeHtml(appUrl('public/request_document.php?id=' . $row['id'])) ?>"><?= uiIcon('download') ?>Download</a><?php endif; ?></div>
<?php if ($ready): ?><form method="post" action="<?= escapeHtml($pageUrl) ?>" data-office-decision><?php csrfField(); ?><input type="hidden" name="action" value="decide"><input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>"><button type="submit" name="decision" value="approved" class="small-button"><?= uiIcon('shield-check') ?>Approve</button><button type="submit" name="decision" value="rejected" class="small-button danger-button"><?= uiIcon('x') ?>Reject</button></form><?php else: ?><span class="muted"><?= in_array($row['review_stage'],['approved','rejected'],true) ? 'Office decision recorded' : ($row['review_stage']==='blocked' ? 'Guarantor rejection recorded' : 'Both guarantor approvals required') ?></span><?php endif; ?></footer></article><?php endforeach; ?>
<?php if ($pages>1): ?><nav class="information-pagination" aria-label="Request pages"><?php if ($page>1): ?><a data-office-page href="<?= escapeHtml(appUrl($pagePath . '?' . http_build_query($filters+['page'=>$page-1]))) ?>"><?= uiIcon('chevron-left') ?>Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page<$pages): ?><a data-office-page href="<?= escapeHtml(appUrl($pagePath . '?' . http_build_query($filters+['page'=>$page+1]))) ?>">Next <?= uiIcon('chevron-right') ?></a><?php endif; ?></nav><?php endif; ?></div></section><script src="<?= escapeHtml(appUrl('assets/js/admin_requests.js?v=1')) ?>" defer></script><?php pageFooter(); ?>
