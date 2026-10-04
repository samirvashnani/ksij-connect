<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
require_once dirname(__DIR__, 2) . '/includes/donations.php';
$staff = requireRole('admin');
header('Cache-Control: private, no-store');
$error = ''; $rows = []; $projects = []; $total = 0; $actual = '0.00'; $demo = '0.00';
$modes = ['cash'=>'Cash','bank_transfer'=>'Bank transfer','cheque'=>'Cheque','upi'=>'UPI'];
$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
function donationLedgerDate(string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        $token = donationCheckIntent('manual_donation_intent', (int) $staff['id']);
        $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $amount = moneyToCents(donationText($_POST,'amount',11));
        if ($amount === null) { throw new DomainException('Enter a positive amount with at most two decimal places.'); }
        $mode = donationText($_POST,'payment_mode',20);
        if (!isset($modes[$mode])) { throw new DomainException('Choose a payment method.'); }
        $date = donationText($_POST,'received_on',10);
        if (!donationLedgerDate($date) || $date > $today) { throw new DomainException('Enter a valid payment date, not in the future.'); }
        $reference = donationText($_POST,'external_reference',160,$mode !== 'cash');
        $notes = donationText($_POST,'notes',1000,false);
        $membershipId = donationText($_POST,'membership_id',100,false);
        $memberId = null;
        $name = donationText($_POST,'donor_name',160,$membershipId === '');
        if ($membershipId !== '') {
            $s = getDb()->prepare('SELECT id FROM members WHERE membership_id=?'); $s->execute([$membershipId]);
            $memberId = $s->fetchColumn();
            if (!$memberId) { throw new DomainException('Membership ID not found. Check it before recording the payment.'); }
            $memberId = (int) $memberId;
        }
        if (($_POST['received_confirmation'] ?? '') !== '1') { throw new DomainException('Confirm that the payment has actually been received.'); }
        $id = recordDonation($projectId,$memberId,(int)$staff['id'],$token,$mode,$amount,$name,$date,$reference,$notes);
        unset($_SESSION['manual_donation_intent']);
        $_SESSION['manual_donation_success'] = $id;
        redirectTo('public/admin/donation_ledger.php?project=' . $projectId);
    }
} catch (DomainException $e) { $error = $e->getMessage(); }
catch (Throwable $e) { error_log('Manual donation: ' . get_class($e)); $error = 'Payment could not be confirmed. Check the ledger before submitting again.'; }
$project = max(0,filter_var($_GET['project'] ?? 0,FILTER_VALIDATE_INT) ?: 0);
$page = max(1,filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT) ?: 1);
$search = ''; $modeFilter = ''; $from = ''; $to = ''; $scope = 'all'; $category = ''; $categoryNotice = '';
try {
    $search = donationText($_GET,'search',160,false);
    $scope = isset($_GET['scope']) ? donationText($_GET,'scope',10) : 'all';
    $category = donationText($_GET,'category',30,false);
    if (!in_array($scope,['all','actual','demo'],true) || ($category!=='' && !isset(donationCategories()[$category]))) { throw new DomainException('Choose valid record and category filters.'); }
    $modeFilter = donationText($_GET,'mode',20,false);
    if ($modeFilter !== '' && !isset($modes[$modeFilter]) && $modeFilter !== 'demo') { throw new DomainException('Choose a valid payment method filter.'); }
    $from = donationText($_GET,'from',10,false); $to = donationText($_GET,'to',10,false);
    if (($from !== '' && !donationLedgerDate($from)) || ($to !== '' && !donationLedgerDate($to)) || ($from !== '' && $to !== '' && $from > $to)) { throw new DomainException('Check the date range.'); }
    $projects = getDb()->query('SELECT id,title,is_active FROM donation_projects ORDER BY title,id')->fetchAll();
    $categorySql = donationCategorySql();
    if (!donationHasCategoryColumn()) { $categoryNotice='Project categories are not installed. Payments are listed under Other until the category migration in query.sql is applied.'; }
    $where = '1=1'; $params = [];
    if ($project) { $where .= ' AND p.project_id=?'; $params[]=$project; }
    if ($modeFilter !== '') { $where .= ' AND p.payment_mode=?'; $params[]=$modeFilter; }
    if ($scope!=='all') { $where .= $scope==='demo' ? " AND p.payment_mode='demo'" : " AND p.payment_mode<>'demo'"; }
    if ($category!=='') { $where .= ' AND (' . $categorySql . ')=?'; $params[]=$category; }
    if ($from !== '') { $where .= ' AND p.received_on>=?'; $params[]=$from; }
    if ($to !== '') { $where .= ' AND p.received_on<=?'; $params[]=$to; }
    if ($search !== '') {
        $like = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $search) . '%';
        $where .= " AND (p.payment_id LIKE ? ESCAPE '!' OR p.donor_name LIKE ? ESCAPE '!' OR p.membership_id LIKE ? ESCAPE '!' OR p.external_reference LIKE ? ESCAPE '!')";
        array_push($params,$like,$like,$like,$like);
    }
    $s = getDb()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN p.payment_mode <> 'demo' THEN p.amount ELSE 0 END),0) AS actual, COALESCE(SUM(CASE WHEN p.payment_mode = 'demo' THEN p.amount ELSE 0 END),0) AS demo FROM donation_payments p JOIN donation_projects j ON j.id=p.project_id WHERE " . $where);
    $s->execute($params); $totals=$s->fetch(); $total=(int)$totals['total']; $actual=$totals['actual']; $demo=$totals['demo'];
    $page=min($page,max(1,(int)ceil($total/25)));
    $s=getDb()->prepare('SELECT p.*,(' . $categorySql . ') AS category,s.full_name AS recorder FROM donation_payments p JOIN donation_projects j ON j.id=p.project_id LEFT JOIN staff_users s ON s.id=p.recorded_by WHERE ' . $where . ' ORDER BY p.received_on DESC,p.id DESC LIMIT 25 OFFSET ' . (($page-1)*25));
    $s->execute($params); $rows=$s->fetchAll();
} catch (DomainException $e) { $error=$e->getMessage(); }
catch (Throwable $e) { $error=donationReadError($e,'Donation ledger'); }
$success=$_SESSION['manual_donation_success'] ?? null; unset($_SESSION['manual_donation_success']);
$filters=['project'=>$project,'mode'=>$modeFilter,'search'=>$search,'from'=>$from,'to'=>$to,'scope'=>$scope,'category'=>$category];
$posted = static fn(string $key, string $default=''): string => escapeHtml(is_string($_POST[$key] ?? null) ? $_POST[$key] : $default);
pageHeader('Donation ledger',$staff,true);
?><section class="workspace donation-workspace donation-ledger" data-donation-ledger><header class="page-heading"><div><p class="eyebrow">DONATION ACCOUNTS</p><h1>Payment ledger</h1></div><a class="button-link button-secondary" href="<?= escapeHtml(appUrl('public/admin/donation_projects.php')) ?>"><?= uiIcon('hand-heart') ?>Projects</a></header>
<?php donationAdminNavigation('ledger'); if ($success): ?><p class="success">Payment recorded. <a href="<?= escapeHtml(donationReceiptLink((int)$success,true)) ?>">Download receipt</a></p><?php endif; ?>
<details class="donation-editor"<?= $_SERVER['REQUEST_METHOD']==='POST' ? ' open' : '' ?>><summary>Record payment received</summary><form method="post" class="donation-form"><?php csrfField(); ?><input type="hidden" name="payment_token" value="<?= escapeHtml(donationIntent('manual_donation_intent',(int)$staff['id'])) ?>">
<label>Project / scheme<select name="project_id" required><option value="">Select project</option><?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (string)$p['id'] === (string)($_POST['project_id'] ?? $project) ? ' selected' : '' ?>><?= escapeHtml($p['title']) ?><?= $p['is_active'] ? '' : ' (closed)' ?></option><?php endforeach; ?></select></label>
<label>Amount (INR)<input type="number" name="amount" min="0.01" max="99999999.99" step="0.01" required value="<?= $posted('amount') ?>"></label>
<label>Membership ID (optional)<input name="membership_id" maxlength="100" value="<?= $posted('membership_id') ?>"></label><label>Donor name (required for non-members)<input name="donor_name" maxlength="160" value="<?= $posted('donor_name') ?>"></label>
<label>Received on<input name="received_on" type="date" required max="<?= $today ?>" value="<?= $posted('received_on',$today) ?>"></label><label>Payment method<select name="payment_mode" required><?php foreach ($modes as $key=>$label): ?><option value="<?= $key ?>"<?= ($_POST['payment_mode'] ?? '') === $key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<label>Transaction / cheque reference (required except cash)<input name="external_reference" maxlength="160" value="<?= $posted('external_reference') ?>"></label><label>Internal notes<textarea name="notes" maxlength="1000" rows="2"><?= $posted('notes') ?></textarea></label>
<label class="checkbox-label full-width"><input type="checkbox" name="received_confirmation" value="1" required>I confirm this amount has been received. This entry is permanent.</label><div class="full-width"><button type="submit"><?= uiIcon('save') ?>Record payment</button></div></form></details>
<form method="get" action="<?= escapeHtml(appUrl('public/admin/donation_ledger.php')) ?>" class="ledger-filter-bar" data-ledger-filter>
<label class="ledger-search">Search payments<input type="search" name="search" maxlength="160" placeholder="Payment ID, donor or reference" value="<?= escapeHtml($search) ?>"></label>
<label>Project<select name="project"><option value="0">All projects</option><?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>"<?= $project===(int)$p['id'] ? ' selected' : '' ?>><?= escapeHtml($p['title']) ?></option><?php endforeach; ?></select></label>
<div class="ledger-filter-actions"><button type="submit" class="icon-button" title="Search payments" aria-label="Search payments"><?= uiIcon('search') ?></button><a class="icon-button" href="<?= escapeHtml(appUrl('public/admin/donation_ledger.php')) ?>" title="Clear filters" aria-label="Clear filters"><?= uiIcon('x') ?></a></div>
<details class="ledger-extra-filters"<?= $modeFilter!=='' || $from!=='' || $to!=='' || $scope!=='all' || $category!=='' ? ' open' : '' ?>><summary>More filters<?= $modeFilter!=='' || $from!=='' || $to!=='' || $scope!=='all' || $category!=='' ? ' (active)' : '' ?></summary><div>
<label>Records<select name="scope"><?php foreach (['all'=>'All records','actual'=>'Actual receipts','demo'=>'Demo only'] as $key=>$label): ?><option value="<?= $key ?>"<?= $scope===$key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<label>Method<select name="mode"><option value="">All methods</option><?php foreach (['demo'=>'Demo']+$modes as $key=>$label): ?><option value="<?= $key ?>"<?= $modeFilter===$key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<label>Category<select name="category"><option value="">All categories</option><?php foreach (donationCategories() as $key=>$label): ?><option value="<?= $key ?>"<?= $category===$key ? ' selected' : '' ?>><?= escapeHtml($label) ?></option><?php endforeach; ?></select></label>
<label>From<input type="date" name="from" value="<?= escapeHtml($from) ?>"></label><label>To<input type="date" name="to" value="<?= escapeHtml($to) ?>"></label></div></details></form>
<p class="ledger-feedback" data-ledger-feedback role="status" aria-live="polite"></p>
<div data-ledger-results data-page="<?= $page ?>"<?= $error ? ' data-load-error' : '' ?>><?php showError($error); ?>
<?php if ($categoryNotice): ?><p class="notice" role="status"><?= escapeHtml($categoryNotice) ?></p><?php endif; ?>
<div class="donation-totals"><div><span>Amount received</span><strong><?= escapeHtml(donationAmount($actual)) ?></strong></div><div><span>Simulated donations (not received)</span><strong><?= escapeHtml(donationAmount($demo)) ?></strong></div><div><span>Payments matching filters</span><strong><?= $total ?></strong></div></div>
<div class="ledger-register-heading"><h2>Receipt register</h2><span data-ledger-count><?= $total ? (($page-1)*25+1) . '-' . min($page*25,$total) . ' of ' : '' ?><?= $total ?> payments</span></div>
<div class="table-wrap ledger-table-wrap" tabindex="0" role="region" aria-label="Donation receipt register"><table class="ledger-table"><thead><tr><th>Date / payment ID</th><th>Donor / project</th><th>Method</th><th class="ledger-money">Received (INR)</th><th class="ledger-money">Demo (INR)</th><th>Receipt</th></tr></thead><tbody>
<?php foreach ($rows as $p): ?><tr><td><time><?= escapeHtml($p['received_on']) ?></time><small class="ledger-reference"><?= escapeHtml($p['payment_id']) ?></small><details class="ledger-entry-details"><summary>Entry details</summary><dl><dt>Recorded by</dt><dd><?= escapeHtml($p['recorder'] ?: ($p['recorded_by'] ? 'Admin #' . $p['recorded_by'] : 'Member portal')) ?></dd><dt>Recorded at</dt><dd><?= escapeHtml($p['created_at']) ?></dd><?php if ($p['external_reference']): ?><dt>External reference</dt><dd><?= escapeHtml($p['external_reference']) ?></dd><?php endif; ?><?php if ($p['notes']): ?><dt>Internal notes</dt><dd><?= escapeHtml($p['notes']) ?></dd><?php endif; ?></dl></details></td>
<td><strong><?= escapeHtml($p['donor_name']) ?></strong><small><?= escapeHtml($p['membership_id'] ?: 'Non-member') ?></small><span><?= escapeHtml($p['project_title']) ?></span><small><?= escapeHtml(donationCategories()[$p['category']] ?? 'Other') ?></small></td>
<td><span class="status-badge <?= $p['payment_mode']==='demo' ? 'status-pending' : 'status-approved' ?>"><?= escapeHtml($modes[$p['payment_mode']] ?? 'Demo') ?></span></td>
<td class="ledger-money"><?= $p['payment_mode']!=='demo' ? escapeHtml(substr(donationAmount($p['amount']),4)) : '-' ?></td><td class="ledger-money ledger-demo"><?= $p['payment_mode']==='demo' ? escapeHtml(substr(donationAmount($p['amount']),4)) : '-' ?></td>
<td><a class="document-link" href="<?= escapeHtml(donationReceiptLink((int)$p['id'],true)) ?>" title="Download PDF receipt <?= escapeHtml($p['payment_id']) ?>"><?= uiIcon('download') ?>PDF</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if (!$rows && !$error): ?><p class="notice">No payments match these filters.</p><?php endif; ?><nav class="form-actions" aria-label="Ledger pages"><?php if ($page>1): ?><a data-ledger-page href="?<?= escapeHtml(http_build_query($filters+['page'=>$page-1])) ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= max(1,(int)ceil($total/25)) ?></span><?php if ($page*25<$total): ?><a data-ledger-page href="?<?= escapeHtml(http_build_query($filters+['page'=>$page+1])) ?>">Next</a><?php endif; ?></nav></div></section><script src="<?= escapeHtml(appUrl('assets/js/donation_ledger.js?v=1')) ?>" defer></script><?php pageFooter(); ?>
