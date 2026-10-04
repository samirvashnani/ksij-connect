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
    <nav class="member-quick-actions" aria-label="Member quick actions"><a href="<?= escapeHtml(appUrl('public/member_request.php')) ?>"><?= uiIcon('file-plus') ?>New request</a><a href="<?= escapeHtml(appUrl('public/membership_payment.php')) ?>"><?= uiIcon('users') ?>Membership fees</a><a href="<?= escapeHtml(appUrl('public/donations.php')) ?>"><?= uiIcon('hand-heart') ?>Donate</a></nav>
    <nav class="member-information-shortcuts" aria-label="Community information">
        <?php foreach ([['events','Upcoming events','calendar-days'],['scholarships','Scholarships','graduation-cap'],['welfare','Welfare schemes','hand-heart'],['contacts','Office contacts','contact'],['announcements','Announcements','newspaper'],['information','General information','book-open']] as [$section,$label,$icon]): ?><a href="<?= escapeHtml(appUrl('public/member_' . $section . '.php')) ?>"><?= uiIcon($icon) ?><span><?= escapeHtml($label) ?></span><?= uiIcon('chevron-right') ?></a><?php endforeach; ?>
    </nav>
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
            <section aria-labelledby="dashboard-donations-heading"><div class="dashboard-section-heading"><h2 id="dashboard-donations-heading"><?= uiIcon('hand-heart') ?>Donation projects</h2><a href="<?= escapeHtml(appUrl('public/donations.php')) ?>">View all <?= uiIcon('arrow-up-right') ?></a></div>
                <?php showError($dashboard['errors']['donations'] ?? ''); ?>
                <?php if (!$dashboard['donations'] && !isset($dashboard['errors']['donations'])): ?><p class="dashboard-empty">No active donation projects.</p><?php endif; ?>
                <?php foreach ($dashboard['donations'] as $project): ?>
                <article class="dashboard-preview"><img class="donation-preview-image" src="<?= escapeHtml(appUrl('public/donation_image.php?id=' . (int)$project['id'])) ?>" alt="<?= escapeHtml($project['title']) ?>" loading="lazy"><h3><?= escapeHtml($project['title']) ?></h3><p><?= escapeHtml($project['quote']) ?></p><a class="dashboard-donate" href="<?= escapeHtml(appUrl('public/donations.php?project=' . (int)$project['id'])) ?>">View & donate <?= uiIcon('arrow-up-right') ?></a></article>
                <?php endforeach; ?>
            </section>
    </div>
    <div class="dashboard-assistant-action"><?= uiIcon('message-circle') ?><button class="text-button" type="button" data-chat-open>KSIJ Assistant <?= uiIcon('arrow-up-right') ?></button></div>
</section>
<?php pageFooter(); ?>
