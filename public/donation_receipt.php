<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/auth_staff.php';
require_once dirname(__DIR__) . '/includes/donations.php';
$admin = ($_GET['admin'] ?? '') === '1';
$identity = $admin ? requireRole('admin') : requireMember();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
try {
    $s = getDb()->prepare('SELECT * FROM donation_payments WHERE id = ?' . ($admin ? '' : ' AND member_id = ?'));
    $s->execute($admin ? [$id ?: 0] : [$id ?: 0, $identity['id']]);
    $p = $s->fetch();
} catch (Throwable $e) { http_response_code(503); exit('Receipts are temporarily unavailable. Please try again later.'); }
if (!$p) { http_response_code(404); exit('Receipt not found.'); }
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="receipt-' . $p['payment_id'] . '.html"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Donation receipt <?= escapeHtml($p['payment_id']) ?></title><style>body{font:16px Arial,sans-serif;color:#182b26;max-width:720px;margin:48px auto;padding:24px;line-height:1.6}h1{font-size:28px}dt{font-weight:bold}dd{margin:0 0 18px;overflow-wrap:anywhere}.amount{font-size:28px;border-block:1px solid #ccc;padding:20px 0}.notice{padding:16px;background:#fff4cf}@media print{body{margin:0}}</style></head><body><p>KSIJ CONNECT</p><h1><?= $p['payment_mode'] === 'demo' ? 'Demo donation receipt' : 'Donation receipt' ?></h1>
<?php if ($p['payment_mode'] === 'demo'): ?><p class="notice"><strong>SIMULATED PAYMENT ONLY.</strong> No money was collected or transferred. This is not proof of an actual donation.</p><?php endif; ?>
<p class="amount"><?= escapeHtml(donationAmount($p['amount'])) ?></p><dl>
<?php foreach (['Payment ID'=>$p['payment_id'],'Project / scheme'=>$p['project_title'],'Donor'=>$p['donor_name'],'Membership ID'=>$p['membership_id'] ?: 'Non-member','Payment date'=>$p['received_on'],'Payment method'=>ucwords(str_replace('_',' ',$p['payment_mode'])),'External reference'=>$p['external_reference'] ?: 'Not applicable','Recorded at'=>$p['created_at']] as $label=>$value): ?><dt><?= escapeHtml($label) ?></dt><dd><?= escapeHtml($value) ?></dd><?php endforeach; ?>
</dl><p>Thank you for supporting our community.</p><small>This receipt acknowledges the recorded entry. It is not a tax exemption certificate.</small></body></html>
