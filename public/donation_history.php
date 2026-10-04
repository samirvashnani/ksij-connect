<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/donations.php';
$member = requireMember();
header('Cache-Control: private, no-store');
$rows = []; $error = ''; $total = 0;
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
try {
    $s = getDb()->prepare('SELECT COUNT(*) FROM donation_payments WHERE member_id=?'); $s->execute([$member['id']]); $total = (int) $s->fetchColumn();
    $page = min($page, max(1, (int) ceil($total/25)));
    $s = getDb()->prepare('SELECT id,payment_id,project_title,amount,payment_mode,received_on FROM donation_payments WHERE member_id=? ORDER BY id DESC LIMIT 25 OFFSET ' . (($page-1)*25)); $s->execute([$member['id']]); $rows=$s->fetchAll();
} catch (Throwable $e) { $error = 'Donation history is currently unavailable.'; }
$success = $_SESSION['donation_success'] ?? null; unset($_SESSION['donation_success']);
pageHeader('My donations', $member);
?><section class="workspace donation-workspace"><header class="page-heading"><h1>My donations</h1><a class="button-link" href="<?= escapeHtml(appUrl('public/donations.php')) ?>"><?= uiIcon('hand-heart') ?>Donate</a></header><?php showError($error); ?>
<?php if ($success): ?><p class="success" role="status">Demo donation recorded. No money was transferred. <a href="<?= escapeHtml(donationReceiptLink((int) $success)) ?>">Download receipt</a></p><?php endif; ?>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Payment ID</th><th>Project</th><th>Date</th><th>Method</th><th>Amount</th><th>Receipt</th></tr></thead><tbody><?php foreach ($rows as $p): ?><tr><td><?= escapeHtml($p['payment_id']) ?></td><td><?= escapeHtml($p['project_title']) ?></td><td><?= escapeHtml($p['received_on']) ?></td><td><?= escapeHtml(ucwords(str_replace('_',' ',$p['payment_mode']))) ?></td><td><?= escapeHtml(donationAmount($p['amount'])) ?></td><td><a href="<?= escapeHtml(donationReceiptLink((int) $p['id'])) ?>"><?= uiIcon('download') ?>Receipt</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php if (!$rows && !$error): ?><p class="notice">No donations recorded yet.</p><?php endif; ?><nav class="form-actions" aria-label="Donation history pages"><?php if ($page>1): ?><a href="?page=<?= $page-1 ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= max(1,(int)ceil($total/25)) ?></span><?php if ($page*25<$total): ?><a href="?page=<?= $page+1 ?>">Next</a><?php endif; ?></nav></section><?php pageFooter(); ?>
