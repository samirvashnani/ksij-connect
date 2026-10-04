<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$member = requireMember();
pageHeader('Member workspace', $member);
?>
<section class="workspace"><p class="eyebrow">MEMBER</p><h1>Welcome, <?= escapeHtml($member['full_name']) ?></h1>
<dl class="details"><div><dt>Membership ID</dt><dd><?= escapeHtml($member['membership_id']) ?></dd></div><div><dt>Status</dt><dd><?= escapeHtml(ucfirst($member['membership_status'])) ?></dd></div><div><dt>Area</dt><dd><?= escapeHtml($member['area'] ?? '-') ?></dd></div></dl>
<div class="form-actions"><a class="button-link" href="<?= escapeHtml(appUrl('public/member_request.php')) ?>">New request</a><a href="<?= escapeHtml(appUrl('public/my_requests.php')) ?>">My requests</a></div>
<div class="overview-help"><h2>Community help</h2><div class="form-actions"><a class="button-link" href="<?= escapeHtml(appUrl('public/help_request_new.php')) ?>">New help request</a><a href="<?= escapeHtml(appUrl('public/help_requests_list.php')) ?>">Help requests</a></div></div>
<div class="overview-help"><h2>Community projects</h2><div class="form-actions"><a class="button-link" href="<?= escapeHtml(appUrl('public/projects.php')) ?>">View active projects</a></div></div>
</section>
<?php pageFooter(); ?>
