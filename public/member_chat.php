<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$member = requireMember();
require_once dirname(__DIR__) . '/includes/member_dashboard.php';
require_once dirname(__DIR__) . '/includes/money.php';
$dashboard = memberDashboardData((int) $member['id']);
$fees = moneyToCents((string) $member['fees_due'], true);
$types = ['scholarship' => 'Scholarship', 'medical_aid' => 'Medical aid', 'loan' => 'Loan'];
$categories = ['elderly_help' => 'Elderly help', 'urgent_medical' => 'Urgent medical', 'other' => 'Community help'];
$membershipStatus = (string) ($member['membership_status'] ?? '');
$membershipLabel = ['active' => 'Active', 'expired' => 'Expired', 'pending' => 'Pending'][$membershipStatus] ?? 'Not recorded';
$membershipTone = ['active' => 'approved', 'expired' => 'rejected', 'pending' => 'pending'][$membershipStatus] ?? 'pending';
header('Cache-Control: no-store');
pageHeader('Member dashboard', $member);
?>
<section class="workspace member-dashboard">
    <header class="member-dashboard-summary">
        <div><p class="eyebrow">MEMBER DASHBOARD</p><h1>Salaam, <?= escapeHtml($member['full_name']) ?></h1><dl class="member-dashboard-meta"><div><dt>Membership ID</dt><dd><?= escapeHtml($member['membership_id']) ?></dd></div><div><dt>Area</dt><dd><?= escapeHtml(trim((string) $member['area']) ?: 'Not recorded') ?></dd></div><div><dt>Status</dt><dd><span class="status-badge status-<?= $membershipTone ?>"><?= escapeHtml($membershipLabel) ?></span></dd></div><div><dt>Renewal</dt><dd><?= escapeHtml($member['renewal_date'] ?: 'Not recorded') ?></dd></div></dl></div>
        <div class="member-fees"><?= uiIcon('users') ?><div><span>Membership fees due</span><strong><?= $fees !== null ? escapeHtml(formatMoney($fees)) : 'Unavailable' ?></strong><a href="<?= escapeHtml(appUrl('public/membership_payment.php')) ?>"><?= $fees !== null && $fees > 0 ? 'Pay membership fees' : 'Membership payments' ?> <?= uiIcon('arrow-up-right') ?></a></div></div>
    </header>
    <nav class="member-quick-actions" aria-label="Member quick actions"><a href="<?= escapeHtml(appUrl('public/member_request.php')) ?>"><?= uiIcon('file-plus') ?>New request</a><a href="<?= escapeHtml(appUrl('public/membership_payment.php')) ?>"><?= uiIcon('users') ?>Membership fees</a><a href="<?= escapeHtml(appUrl('public/funds_board.php')) ?>"><?= uiIcon('heart-pulse') ?>Donate</a></nav>
    <div class="member-dashboard-grid">
            <section class="dashboard-formal-requests" aria-labelledby="dashboard-requests-heading"><div class="dashboard-section-heading"><h2 id="dashboard-requests-heading"><?= uiIcon('clipboard-list') ?>My formal requests</h2><a href="<?= escapeHtml(appUrl('public/my_requests.php')) ?>">View all <?= uiIcon('arrow-up-right') ?></a></div>
                <?php showError($dashboard['errors']['requests'] ?? ''); ?>
                <?php if (!$dashboard['requests'] && !isset($dashboard['errors']['requests'])): ?><p class="dashboard-empty">No formal requests yet.</p><?php endif; ?>
                <?php foreach ($dashboard['requests'] as $request): [$label, $tone] = memberDashboardRequestState($request); $amount = $request['amount_requested'] !== null ? moneyToCents((string) $request['amount_requested'], true) : null; ?>
                <article class="dashboard-request"><div class="dashboard-item-heading"><h3><?= escapeHtml($types[$request['type']] ?? 'Request') ?> <span>#<?= (int) $request['id'] ?></span></h3><span class="status-badge status-<?= $tone ?>"><?= escapeHtml($label) ?></span></div><div class="dashboard-request-amount"><span>Requested amount</span><strong><?= $amount !== null ? escapeHtml(formatMoney($amount)) : ($request['amount_requested'] === null ? 'Not specified' : 'Unavailable') ?></strong></div><dl class="dashboard-request-decisions"><?php foreach ([1 => 'Guarantor 1', 2 => 'Guarantor 2', 'office' => 'Office'] as $slot => $slotLabel): $status = (string) ($request[$slot === 'office' ? 'office_status' : 'guarantor' . $slot . '_status'] ?? ''); ?><div><dt><?= $slotLabel ?></dt><dd><?= escapeHtml($status !== '' ? ucfirst($status) : 'Unavailable') ?></dd></div><?php endforeach; ?></dl></article>
                <?php endforeach; ?>
            </section>
            <section aria-labelledby="dashboard-help-heading"><div class="dashboard-section-heading"><h2 id="dashboard-help-heading"><?= uiIcon('hand-heart') ?>Community help</h2><a href="<?= escapeHtml(appUrl('public/help_requests_list.php')) ?>">View board <?= uiIcon('arrow-up-right') ?></a></div>
                <?php showError($dashboard['errors']['help'] ?? ''); ?>
                <?php if (!$dashboard['help'] && !isset($dashboard['errors']['help'])): ?><p class="dashboard-empty">No open help requests.</p><?php endif; ?>
                <?php foreach ($dashboard['help'] as $help): ?><article class="dashboard-preview"><div class="dashboard-item-heading"><h3><?= escapeHtml($categories[$help['category']] ?? 'Community help') ?></h3><span class="status-badge <?= $help['category'] === 'urgent_medical' ? 'status-rejected' : 'status-open' ?>"><?= $help['category'] === 'urgent_medical' ? 'Urgent medical' : 'Open' ?></span></div><p class="dashboard-preview-copy"><?= escapeHtml($help['description']) ?></p><span class="muted"><?= escapeHtml(trim((string) $help['area']) ?: 'Community-wide') ?> / #<?= (int) $help['id'] ?></span></article><?php endforeach; ?>
            </section>
            <section aria-labelledby="dashboard-funds-heading"><div class="dashboard-section-heading"><h2 id="dashboard-funds-heading"><?= uiIcon('heart-pulse') ?>Medical funds</h2><a href="<?= escapeHtml(appUrl('public/funds_board.php')) ?>">View all <?= uiIcon('arrow-up-right') ?></a></div>
                <?php showError($dashboard['errors']['funds'] ?? ''); ?>
                <?php if (!$dashboard['funds'] && !isset($dashboard['errors']['funds'])): ?><p class="dashboard-empty">No active medical funds.</p><?php endif; ?>
                <?php foreach ($dashboard['funds'] as $fund): $needed = moneyToCents((string) $fund['amount_needed']); $raised = moneyToCents((string) $fund['amount_raised'], true); ?>
                <article class="dashboard-preview"><h3><?= escapeHtml($fund['title']) ?></h3><?php if ($needed !== null && $raised !== null): $percentage = min(100, (int) floor($raised * 100 / $needed)); ?><progress value="<?= min($raised, $needed) ?>" max="<?= $needed ?>" aria-label="<?= escapeHtml($fund['title'] . ' funding progress') ?>"><?= $percentage ?>%</progress><div class="dashboard-fund-total"><span><?= escapeHtml(formatMoney($raised)) ?> of <?= escapeHtml(formatMoney($needed)) ?></span><strong><?= $percentage ?>%</strong></div><?php if ($raised < $needed): ?><a class="dashboard-donate" href="<?= escapeHtml(appUrl('public/donate.php?id=' . (int) $fund['id'])) ?>">Contribute <?= uiIcon('arrow-up-right') ?></a><?php else: ?><span class="status-badge status-approved">Fully funded</span><?php endif; ?><?php else: ?><p class="error">Fund amounts are unavailable.</p><?php endif; ?></article>
                <?php endforeach; ?>
            </section>
    </div>
    <div class="dashboard-assistant-action"><?= uiIcon('message-circle') ?><button class="text-button" type="button" data-chat-open>KSIJ Assistant <?= uiIcon('arrow-up-right') ?></button></div>
</section>
<?php pageFooter(); ?>
