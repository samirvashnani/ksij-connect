<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
require_once dirname(__DIR__, 2) . '/includes/admin_helpers.php';
require_once dirname(__DIR__, 2) . '/includes/admin_context.php';

$staff = requireRole('admin');
header('Cache-Control: no-store');
$pdo = getDb();

$overview = [
    'members' => (int) $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn(),
    'pending_fees' => (int) $pdo->query('SELECT COUNT(*) FROM members WHERE fees_due > 0')->fetchColumn(),
    'open_help' => (int) $pdo->query("SELECT COUNT(*) FROM help_requests WHERE status = 'open'")->fetchColumn(),
    'pending_funds' => (int) $pdo->query("SELECT COUNT(*) FROM funds WHERE status = 'pending_approval'")->fetchColumn(),
    'pending_requests' => (int) $pdo->query("SELECT COUNT(*) FROM requests WHERE office_status = 'pending'")->fetchColumn(),
];

$context = getAdminContext('pending fees, funds approval, and open help requests');

adminPageStart('dashboard', $staff);
?>
<div class="stats-grid">
    <div class="stat-card"><span class="stat-label">Total Members</span><strong><?= escapeHtml((string) $overview['members']) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Pending Fees</span><strong><?= escapeHtml((string) $overview['pending_fees']) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Open Help Requests</span><strong><?= escapeHtml((string) $overview['open_help']) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Funds Pending</span><strong><?= escapeHtml((string) $overview['pending_funds']) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Pending Office Requests</span><strong><?= escapeHtml((string) $overview['pending_requests']) ?></strong></div>
</div>

<div class="admin-grid two-columns">
    <section class="admin-card">
        <h2>Office summary</h2>
        <ul class="context-list">
            <?php foreach ($context['snippets'] as $snippet): ?>
                <li>
                    <strong><?= escapeHtml($snippet['title']) ?></strong>
                    <span><?= escapeHtml($snippet['value']) ?></span>
                    <small><?= escapeHtml($snippet['detail']) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <section class="admin-card">
        <h2>Quick actions</h2>
        <div class="tool-list">
            <a class="tool-link" href="<?= escapeHtml(appUrl('public/admin/events.php')) ?>">Manage events <?= uiIcon('arrow-up-right') ?></a>
            <a class="tool-link" href="<?= escapeHtml(appUrl('public/admin/members.php')) ?>">Manage members <?= uiIcon('arrow-up-right') ?></a>
            <a class="tool-link" href="<?= escapeHtml(appUrl('public/admin/staff_users.php')) ?>">Manage team <?= uiIcon('arrow-up-right') ?></a>
            <a class="tool-link" href="<?= escapeHtml(appUrl('public/admin/requests_overview.php')) ?>">Review requests <?= uiIcon('arrow-up-right') ?></a>
            <a class="tool-link" href="<?= escapeHtml(appUrl('public/admin/funds_approval.php')) ?>">Approve funds <?= uiIcon('arrow-up-right') ?></a>
            <a class="tool-link" href="<?= escapeHtml(appUrl('public/admin/help_requests_assign.php')) ?>">Assign help requests <?= uiIcon('arrow-up-right') ?></a>
        </div>
    </section>
</div>
<?php adminPageEnd(); ?>
