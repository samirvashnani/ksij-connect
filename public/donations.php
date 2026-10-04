<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/donations.php';
$member = requireMember();
header('Cache-Control: private, no-store');
$error = '';
$projects = [];
$selected = null;
$projectId = filter_var($_GET['project'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        if (!donationDemoEnabled()) { throw new DomainException('Demo donation payments are disabled. Contact the office.'); }
        $token = donationCheckIntent('donation_intent', (int) $member['id']);
        if (($_POST['demo_confirmation'] ?? '') !== '1') { throw new DomainException('Confirm that this is a simulated payment.'); }
        $amount = moneyToCents(donationText($_POST, 'amount', 11));
        $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        if ($amount === null) { throw new DomainException('Enter a positive amount with at most two decimal places.'); }
        $id = recordDonation($projectId, (int) $member['id'], null, $token, 'demo', $amount, '', (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d'));
        unset($_SESSION['donation_intent']);
        $_SESSION['donation_success'] = $id;
        redirectTo('public/donation_history.php');
    }
} catch (DomainException $e) { $error = $e->getMessage(); }
catch (Throwable $e) { error_log('Donation checkout: ' . get_class($e)); $error = 'Payment could not be confirmed. Check My donations before trying again.'; }
try {
    $projects = getDb()->query('SELECT id,title,description,quote FROM donation_projects WHERE is_active=1 ORDER BY id DESC')->fetchAll();
    foreach ($projects as $p) { if ((int) $p['id'] === $projectId) { $selected = $p; } }
    if ($projectId && !$selected && !$error) { $error = 'This project is no longer accepting donations.'; }
} catch (Throwable $e) { $error = 'Donation projects are currently unavailable. Please contact the office.'; }
pageHeader('Donate', $member);
?><section class="workspace donation-workspace"><header class="page-heading"><div><p class="eyebrow">COMMUNITY GIVING</p><h1><?= $selected ? escapeHtml($selected['title']) : 'Projects & schemes' ?></h1></div><a class="button-link button-secondary" href="<?= escapeHtml(appUrl('public/donation_history.php')) ?>"><?= uiIcon('clipboard-list') ?>My donations</a></header>
<?php showError($error); ?>
<?php if ($selected): ?><div class="donation-checkout"><section><img class="donation-detail-image" src="<?= escapeHtml(appUrl('public/donation_image.php?id=' . $selected['id'])) ?>" alt="<?= escapeHtml($selected['title']) ?>"><blockquote><?= escapeHtml($selected['quote']) ?></blockquote><p class="donation-description"><?= escapeHtml($selected['description']) ?></p><a href="<?= escapeHtml(appUrl('public/donations.php')) ?>">All projects</a></section><section class="donation-pay"><h2>Make a donation</h2><p class="notice"><strong>Presentation demo.</strong> No money is collected. This records a simulated donation.</p>
<?php if (donationDemoEnabled()): ?><form method="post" data-membership-payment><?php csrfField(); ?><input type="hidden" name="payment_token" value="<?= escapeHtml(donationIntent('donation_intent', (int) $member['id'])) ?>"><input type="hidden" name="project_id" value="<?= (int) $selected['id'] ?>"><label for="donation-amount">Amount (INR)</label><input id="donation-amount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" required value="<?= escapeHtml(is_string($_POST['amount'] ?? null) ? $_POST['amount'] : '') ?>"><label class="checkbox-label"><input type="checkbox" name="demo_confirmation" value="1" required>I understand this is a demo payment and no money will be transferred.</label><button type="submit"><?= uiIcon('hand-heart') ?>Simulate donation</button><p class="payment-progress" data-payment-progress role="status" hidden><span class="payment-spinner" aria-hidden="true"></span>Processing demo donation...</p></form><?php else: ?><p>Demo payments are disabled. Contact the office to donate.</p><?php endif; ?></section></div>
<?php else: ?><div class="donation-grid"><?php foreach ($projects as $p): ?><article class="donation-card"><img class="donation-cover" src="<?= escapeHtml(appUrl('public/donation_image.php?id=' . $p['id'])) ?>" alt="<?= escapeHtml($p['title']) ?>" loading="lazy"><div class="donation-card-body"><h2><?= escapeHtml($p['title']) ?></h2><blockquote><?= escapeHtml($p['quote']) ?></blockquote><p><?= escapeHtml(mb_strimwidth($p['description'], 0, 220, '...')) ?></p><a class="button-link" href="<?= escapeHtml(appUrl('public/donations.php?project=' . $p['id'])) ?>"><?= uiIcon('hand-heart') ?>View & donate</a></div></article><?php endforeach; ?></div><?php if (!$projects && !$error): ?><p class="notice">No active donation projects at the moment.</p><?php endif; ?><?php endif; ?></section><script src="<?= escapeHtml(appUrl('assets/js/membership_payment.js?v=1')) ?>" defer></script><?php pageFooter(); ?>
