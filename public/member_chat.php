<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$member = requireMember();
pageHeader('Member workspace', $member);
?>
<section class="workspace"><p class="eyebrow">MEMBER</p><h1>Welcome, <?= escapeHtml($member['full_name']) ?></h1>
<dl class="details"><div><dt>Membership ID</dt><dd><?= escapeHtml($member['membership_id']) ?></dd></div><div><dt>Status</dt><dd><?= escapeHtml(ucfirst($member['membership_status'])) ?></dd></div><div><dt>Area</dt><dd><?= escapeHtml($member['area'] ?? '-') ?></dd></div></dl>
</section>
<?php pageFooter(); ?>
