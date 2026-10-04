<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
require_once dirname(__DIR__, 2) . '/includes/admin_helpers.php';

$staff = requireRole('admin');
header('Cache-Control: no-store');
$pdo = getDb();

$metrics = [
    'members' => ['Members', 'users', 'public/admin/members.php', 'SELECT COUNT(*) FROM members'],
    'pending_fees' => ['Members with fees due', 'calendar-days', 'public/admin/members.php', 'SELECT COUNT(*) FROM members WHERE fees_due > 0'],
    'open_help' => ['Unassigned help', 'hand-heart', 'public/admin/help_requests_assign.php', "SELECT COUNT(*) FROM help_requests WHERE status = 'open'"],
    'pending_requests' => ['Ready for office review', 'clipboard-list', 'public/admin/requests_overview.php', "SELECT COUNT(*) FROM requests WHERE office_status = 'pending' AND guarantor1_status = 'approved' AND guarantor2_status = 'approved'"],
];
$overview = [];
$metricError = '';
foreach ($metrics as $key => [$label, $icon, $path, $sql]) {
    try {
        $overview[$key] = (int) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $exception) {
        $overview[$key] = null;
        error_log('Admin dashboard metric failed: ' . $key . ' ' . get_class($exception));
        $metricError = 'Some dashboard counts are unavailable. Please reload to try again.';
    }
}
$readyRequests = [];
$queueError = '';
try {
    $readyRequests = $pdo->query("SELECT r.id, r.type, r.created_at, m.full_name, m.membership_id FROM requests r JOIN members m ON m.id = r.member_id WHERE r.office_status = 'pending' AND r.guarantor1_status = 'approved' AND r.guarantor2_status = 'approved' ORDER BY r.created_at, r.id LIMIT 8")->fetchAll();
} catch (Throwable $exception) {
    error_log('Admin dashboard review queue failed: ' . get_class($exception));
    $queueError = 'The office review queue could not be loaded.';
}
$types = ['scholarship' => 'Education / scholarship', 'medical_aid' => 'Medical aid', 'loan' => 'Loan'];

pageHeader('Office dashboard', $staff, true);
?>
<section class="workspace office-dashboard">
    <header class="operations-heading"><div><p class="eyebrow">OFFICE OPERATIONS</p><h1>Office dashboard</h1><p class="operations-person">Salaam, <?= escapeHtml($staff['full_name']) ?></p></div><button type="button" class="button-secondary" data-chat-open><?= uiIcon('message-circle') ?>KSIJ Assistant</button></header>
    <?php showError($metricError); ?>
    <div class="operations-metrics office-metrics">
        <?php foreach ($metrics as $key => [$label, $icon, $path]): ?><a class="operations-metric metric-<?= $key ?>" href="<?= escapeHtml(appUrl($path)) ?>"><span class="metric-top"><?= uiIcon($icon) ?><?= uiIcon('arrow-up-right') ?></span><strong<?= $overview[$key] === null ? ' class="metric-unavailable"' : '' ?>><?= $overview[$key] !== null ? (int) $overview[$key] : 'Unavailable' ?></strong><span><?= escapeHtml($label) ?></span></a><?php endforeach; ?>
    </div>
    <div class="office-dashboard-columns">
    <section class="office-review-queue" aria-labelledby="office-review-heading">
        <div class="dashboard-section-heading"><h2 id="office-review-heading"><?= uiIcon('clipboard-list') ?>Office review queue</h2><a href="<?= escapeHtml(appUrl('public/admin/requests_overview.php')) ?>">All requests <?= uiIcon('arrow-up-right') ?></a></div>
        <?php showError($queueError); ?>
        <?php if (!$readyRequests && !$queueError): ?><div class="operations-empty"><?= uiIcon('shield-check') ?><h3>No requests ready for review</h3><p>Both guarantor approvals are required.</p></div><?php endif; ?>
        <?php foreach ($readyRequests as $request): ?><a class="office-queue-row" href="<?= escapeHtml(appUrl('public/request_details.php?id=' . (int) $request['id'])) ?>"><span class="office-queue-icon"><?= uiIcon($request['type'] === 'medical_aid' ? 'heart-pulse' : ($request['type'] === 'scholarship' ? 'graduation-cap' : 'file-plus')) ?></span><span class="office-queue-person"><strong><?= escapeHtml($request['full_name']) ?></strong><span><?= escapeHtml($types[$request['type']] ?? 'Request') ?> / #<?= (int) $request['id'] ?></span><small><?= escapeHtml($request['membership_id']) ?> / <?= escapeHtml($request['created_at']) ?></small></span><span class="status-badge status-pending">Ready for review</span><?= uiIcon('chevron-right') ?></a><?php endforeach; ?>
    </section>
    <section class="office-management" aria-labelledby="office-management-heading"><div class="dashboard-section-heading"><h2 id="office-management-heading"><?= uiIcon('settings') ?>Management</h2></div>
        <?php foreach ([['public/admin/help_requests_assign.php', 'Coordinate help', 'hand-heart'], ['public/admin/members.php', 'Member directory', 'users'], ['public/admin/staff_users.php', 'Team accounts', 'shield-check'], ['public/admin/events.php', 'Community events', 'calendar-days'], ['public/admin/news_updates.php', 'Announcements', 'newspaper']] as [$path, $label, $icon]): ?><a class="office-management-link" href="<?= escapeHtml(appUrl($path)) ?>"><?= uiIcon($icon) ?><span><?= escapeHtml($label) ?></span><?= uiIcon('arrow-up-right') ?></a><?php endforeach; ?>
    </section>
    </div>
</section>
<?php pageFooter(); ?>
