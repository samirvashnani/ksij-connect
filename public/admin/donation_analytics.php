<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
require_once dirname(__DIR__, 2) . '/includes/donations.php';
$staff = requireRole('admin');
header('Cache-Control: private, no-store');
$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));
$from = $today->modify('first day of this month')->modify('-11 months')->format('Y-m-d');
$to = $today->format('Y-m-d');
$scope = 'all'; $category = ''; $error = ''; $categoryNotice = '';
$totals = ['actual'=>'0.00','demo'=>'0.00','payments'=>0,'donors'=>0,'projects'=>0];
$categories = []; $months = []; $methods = []; $recent = []; $top = [];
$labels = donationCategories();
$scopeLabels = ['all'=>'All records','actual'=>'Actual receipts','demo'=>'Demo only'];
$modeLabels = ['demo'=>'Demo','cash'=>'Cash','bank_transfer'=>'Bank transfer','cheque'=>'Cheque','upi'=>'UPI'];
try {
    $from = array_key_exists('from',$_GET) ? donationText($_GET,'from',10) : $from;
    $to = array_key_exists('to',$_GET) ? donationText($_GET,'to',10) : $to;
    $scope = array_key_exists('scope',$_GET) ? donationText($_GET,'scope',10) : 'all';
    $category = donationText($_GET,'category',30,false);
    foreach ([$from,$to] as $value) {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$date || $date->format('Y-m-d')!==$value) { throw new DomainException('Select valid dates.'); }
    }
    $start=new DateTimeImmutable($from); $end=new DateTimeImmutable($to);
    if ($start>$end || $start->diff($end)->days>3660) { throw new DomainException('Choose a date range of up to ten years, with the start before the end.'); }
    if (!isset($scopeLabels[$scope]) || ($category!=='' && !isset($labels[$category]))) { throw new DomainException('Choose valid donation filters.'); }
    $categorySql=donationCategorySql();
    if (!donationHasCategoryColumn()) { $categoryNotice='Project categories have not been installed yet. Existing payments are shown under Other. Run the donation category migration in query.sql to enable category comparisons.'; }
    $base=' FROM donation_payments p JOIN donation_projects j ON j.id=p.project_id';
    $where=' WHERE p.received_on BETWEEN ? AND ?'; $params=[$from,$to];
    if ($scope!=='all') { $where .= $scope==='demo' ? " AND p.payment_mode='demo'" : " AND p.payment_mode<>'demo'"; }
    if ($category!=='') { $where.=' AND (' . $categorySql . ')=?'; $params[]=$category; }
    $sums="COALESCE(SUM(CASE WHEN p.payment_mode<>'demo' THEN p.amount ELSE 0 END),0) AS actual, COALESCE(SUM(CASE WHEN p.payment_mode='demo' THEN p.amount ELSE 0 END),0) AS demo";
    $query=static function(string $sql) use ($params): array { $s=getDb()->prepare($sql); $s->execute($params); return $s->fetchAll(); };
    $totals=$query('SELECT ' . $sums . ',COUNT(*) AS payments,COUNT(DISTINCT p.member_id) AS donors,COUNT(DISTINCT p.project_id) AS projects' . $base . $where)[0];
    $categories=$query('SELECT (' . $categorySql . ') AS category,' . $sums . ',COUNT(*) AS payments' . $base . $where . ' GROUP BY (' . $categorySql . ') ORDER BY actual DESC,demo DESC');
    $trend=$query("SELECT DATE_FORMAT(p.received_on,'%Y-%m') AS month," . $sums . $base . $where . " GROUP BY DATE_FORMAT(p.received_on,'%Y-%m') ORDER BY month");
    foreach ($trend as $row) { $months[$row['month']]=$row; }
    $series=[];
    for ($cursor=$start->modify('first day of this month'); $cursor<=$end; $cursor=$cursor->modify('+1 month')) {
        $key=$cursor->format('Y-m');
        $series[]=['month'=>$cursor->format('M Y'),'actual'=>$months[$key]['actual'] ?? '0.00','demo'=>$months[$key]['demo'] ?? '0.00'];
    }
    $methods=$query('SELECT p.payment_mode,COUNT(*) AS payments,COALESCE(SUM(p.amount),0) AS amount' . $base . $where . ' GROUP BY p.payment_mode ORDER BY payments DESC');
    $top=$query('SELECT j.id,j.title,' . $sums . ',COUNT(*) AS payments' . $base . $where . ' GROUP BY j.id,j.title ORDER BY actual DESC,demo DESC,j.id DESC LIMIT 8');
    $recent=$query('SELECT p.id,p.payment_id,p.project_title,p.donor_name,p.amount,p.payment_mode,p.received_on' . $base . $where . ' ORDER BY p.received_on DESC,p.id DESC LIMIT 8');
} catch (DomainException $e) { $error=$e->getMessage(); }
catch (Throwable $e) { $error=donationReadError($e,'Donation analytics'); }
$ledgerFilters=['from'=>$from,'to'=>$to,'scope'=>$scope,'category'=>$category];
$ledgerUrl=appUrl('public/admin/donation_ledger.php?' . http_build_query($ledgerFilters));
$chartData=['trend'=>$series ?? [],'categories'=>array_map(static fn($r)=>['label'=>$labels[$r['category']] ?? 'Other','actual'=>$r['actual'],'demo'=>$r['demo']],$categories),'methods'=>array_map(static fn($r)=>['label'=>$modeLabels[$r['payment_mode']] ?? $r['payment_mode'],'count'=>(int)$r['payments']],$methods)];
pageHeader('Donation overview',$staff,true);
?><section class="workspace donation-workspace donation-analytics"><header class="operations-heading"><div><p class="eyebrow">COMMUNITY GIVING</p><h1>Donation overview</h1></div><a class="button-link button-secondary" href="<?= escapeHtml($ledgerUrl) ?>"><?= uiIcon('clipboard-list') ?>Open ledger</a></header>
<?php donationAdminNavigation('analytics'); ?>
<?php if ($categoryNotice): ?><p class="notice" role="status"><?= escapeHtml($categoryNotice) ?></p><?php endif; ?>
<form method="get" class="analytics-filters"><label>From<input type="date" name="from" required value="<?= escapeHtml($from) ?>"></label><label>To<input type="date" name="to" required value="<?= escapeHtml($to) ?>"></label><label>Records<select name="scope"><?php foreach ($scopeLabels as $key=>$label): ?><option value="<?= $key ?>"<?= $scope===$key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><label>Category<select name="category"><option value="">All categories</option><?php foreach ($labels as $key=>$label): ?><option value="<?= $key ?>"<?= $category===$key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button type="submit" class="icon-button" title="Update overview" aria-label="Update overview"><?= uiIcon('refresh-cw') ?></button></form>
<?php showError($error); if (!$error): ?>
<div class="analytics-metrics"><div><span><?= uiIcon('hand-heart') ?>Actual receipts</span><strong><?= escapeHtml(donationAmount($totals['actual'])) ?></strong><small>Recorded as received by the office</small></div><div class="analytics-demo"><span><?= uiIcon('clipboard-list') ?>Simulated donations</span><strong><?= escapeHtml(donationAmount($totals['demo'])) ?></strong><small>Demo only; no money collected</small></div><div><span><?= uiIcon('users') ?>Payments</span><strong><?= (int)$totals['payments'] ?></strong><small><?= (int)$totals['donors'] ?> registered member donors</small></div><div><span><?= uiIcon('folder') ?>Supported projects</span><strong><?= (int)$totals['projects'] ?></strong><small>With payments in this period</small></div></div>
<?php if (!(int)$totals['payments']): ?><div class="operations-empty"><?= uiIcon('hand-heart') ?><h2>No payments in this period</h2><p>Choose another date range or record type.</p></div><?php else: ?>
<div class="analytics-primary"><section class="analytics-section"><div class="analytics-section-heading"><h2>Donation trend</h2><span>Monthly / INR</span></div><div class="analytics-chart"><canvas id="donation-trend" role="img" aria-label="Monthly actual receipts and demo donations. Figures are available below."></canvas></div><details class="analytics-data"><summary>Monthly figures</summary><div class="table-wrap"><table><thead><tr><th>Month</th><th>Actual</th><th>Demo</th></tr></thead><tbody><?php foreach ($series as $row): ?><tr><td><?= escapeHtml($row['month']) ?></td><td><?= escapeHtml(donationAmount($row['actual'])) ?></td><td><?= escapeHtml(donationAmount($row['demo'])) ?></td></tr><?php endforeach; ?></tbody></table></div></details></section>
<section class="analytics-section"><div class="analytics-section-heading"><h2>Payment methods</h2><span>Payment count</span></div><div class="analytics-chart analytics-doughnut"><canvas id="donation-methods" role="img" aria-label="Payment counts by method, listed below."></canvas></div><ul class="analytics-method-list"><?php foreach ($methods as $row): ?><li><span><?= escapeHtml($modeLabels[$row['payment_mode']] ?? $row['payment_mode']) ?></span><strong><?= (int)$row['payments'] ?></strong></li><?php endforeach; ?></ul></section></div>
<section class="analytics-section analytics-category"><div class="analytics-section-heading"><h2>Category comparison</h2><span>Current project categories / INR</span></div><div class="analytics-category-columns"><div class="analytics-chart analytics-category-chart"><canvas id="donation-categories" role="img" aria-label="Actual and simulated amounts by category, listed in the adjacent table."></canvas></div><div class="table-wrap"><table><thead><tr><th>Category</th><th>Actual</th><th>Demo</th><th>Payments</th></tr></thead><tbody><?php foreach ($categories as $row): ?><tr><th><a href="<?= escapeHtml(appUrl('public/admin/donation_ledger.php?' . http_build_query(array_merge($ledgerFilters,['category'=>$row['category']])))) ?>"><?= escapeHtml($labels[$row['category']] ?? 'Other') ?></a></th><td><?= escapeHtml(donationAmount($row['actual'])) ?></td><td><?= escapeHtml(donationAmount($row['demo'])) ?></td><td><?= (int)$row['payments'] ?></td></tr><?php endforeach; ?></tbody></table></div></div></section>
<section class="analytics-section"><div class="analytics-section-heading"><h2>Top projects</h2><span>Ranked by actual receipts, then demo amount</span></div><div class="table-wrap"><table><thead><tr><th>Project</th><th>Payments</th><th>Actual receipts</th><th>Demo amount</th></tr></thead><tbody><?php foreach ($top as $row): ?><tr><th><a href="<?= escapeHtml(appUrl('public/admin/donation_ledger.php?' . http_build_query($ledgerFilters+['project'=>$row['id']])) ) ?>"><?= escapeHtml($row['title']) ?></a></th><td><?= (int)$row['payments'] ?></td><td><?= escapeHtml(donationAmount($row['actual'])) ?></td><td><?= escapeHtml(donationAmount($row['demo'])) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="analytics-section"><div class="analytics-section-heading"><h2>Recent donations</h2><a href="<?= escapeHtml($ledgerUrl) ?>">All payments <?= uiIcon('arrow-up-right') ?></a></div><div class="table-wrap"><table><thead><tr><th>Donor / project</th><th>Payment date</th><th>Method</th><th>Amount</th><th>Receipt</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><strong><?= escapeHtml($row['donor_name']) ?></strong><small><?= escapeHtml($row['project_title']) ?></small></td><td><?= escapeHtml($row['received_on']) ?></td><td><span class="status-badge <?= $row['payment_mode']==='demo' ? 'status-pending' : 'status-approved' ?>"><?= escapeHtml($modeLabels[$row['payment_mode']] ?? $row['payment_mode']) ?></span></td><td><?= escapeHtml(donationAmount($row['amount'])) ?></td><td><a class="document-link" href="<?= escapeHtml(donationReceiptLink((int)$row['id'],true)) ?>" title="Download PDF receipt <?= escapeHtml($row['payment_id']) ?>"><?= uiIcon('download') ?>PDF</a></td></tr><?php endforeach; ?></tbody></table></div></section>
<p class="analytics-chart-error" data-chart-error hidden>Charts could not be loaded. The figures remain available in the tables.</p>
<script type="application/json" id="donation-chart-data"><?= json_encode($chartData,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?></script><script src="<?= escapeHtml(appUrl('assets/chartjs/chart.umd.min.js')) ?>" defer></script><script src="<?= escapeHtml(appUrl('assets/js/donation_analytics.js?v=1')) ?>" defer></script>
<?php endif; endif; ?></section><?php pageFooter(); ?>
