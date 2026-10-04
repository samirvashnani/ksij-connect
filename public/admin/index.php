<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$staff = requireRole('admin');
pageHeader('Admin workspace', $staff, true);
?>
<section class="workspace"><p class="eyebrow">ADMINISTRATION</p><h1>Welcome, <?= escapeHtml($staff['full_name']) ?></h1><dl class="details"><div><dt>Role</dt><dd>Administrator</dd></div><div><dt>Account</dt><dd>Active</dd></div></dl></section>
<?php pageFooter(); ?>
