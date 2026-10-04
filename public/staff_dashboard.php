<?php
require_once dirname(__DIR__) . '/includes/auth_staff.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$staff = requireRole(['volunteer', 'cc_member']);
pageHeader('Team workspace', $staff, true);
?>
<section class="workspace"><p class="eyebrow"><?= $staff['role'] === 'cc_member' ? 'CC MEMBER' : 'VOLUNTEER' ?></p><h1>Welcome, <?= escapeHtml($staff['full_name']) ?></h1>
<dl class="details"><div><dt>Area</dt><dd><?= escapeHtml($staff['area'] ?? '-') ?></dd></div><div><dt>Account</dt><dd>Active</dd></div><?php if ($staff['role'] === 'volunteer'): ?><div><dt>Guarantor authority</dt><dd><?= $staff['is_guarantor_approved'] ? 'Approved' : 'Not approved' ?></dd></div><?php endif; ?></dl>
</section>
<?php pageFooter(); ?>
