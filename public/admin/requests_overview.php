<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
require_once dirname(__DIR__, 2) . '/includes/admin_helpers.php';
require_once dirname(__DIR__, 2) . '/includes/notifications.php';

$staff = requireRole('admin');
$pdo = getDb();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'decide') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            $error = 'Choose a valid office decision.';
        } else {
            $stmt = $pdo->prepare('SELECT r.id, r.member_id, r.type, r.guarantor1_status, r.guarantor2_status, m.full_name FROM requests r JOIN members m ON m.id = r.member_id WHERE r.id = ?');
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            if (!$request) {
                $error = 'Request not found.';
            } elseif ($request['guarantor1_status'] !== 'approved' || $request['guarantor2_status'] !== 'approved') {
                $error = 'Both guarantors must approve before office review can proceed.';
            } else {
                $pdo->prepare('UPDATE requests SET office_status = ? WHERE id = ?')->execute([$decision, $requestId]);
                createNotification('member', (int) $request['member_id'], 'Request updated', 'Your ' . escapeHtml(str_replace('_', ' ', $request['type'])) . ' request was ' . $decision . ' by the office.');
                $success = 'Request marked as ' . $decision . '.';
            }
        }
    }
}

adminRedirectAfterSuccess('public/admin/requests_overview.php', $success);
$rows = $pdo->query('SELECT r.*, m.full_name AS member_name, m.membership_id, g1.full_name AS guarantor1_name, g2.full_name AS guarantor2_name FROM requests r JOIN members m ON m.id = r.member_id LEFT JOIN staff_users g1 ON g1.id = r.guarantor1_id LEFT JOIN staff_users g2 ON g2.id = r.guarantor2_id ORDER BY r.created_at DESC')->fetchAll();

adminPageStart('Requests overview', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-card">
    <h2>Formal requests</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Guarantor 1</th>
                    <th>Guarantor 2</th>
                    <th>Status</th>
                    <th>Office</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $canOfficeDecision = $row['guarantor1_status'] === 'approved' && $row['guarantor2_status'] === 'approved'; ?>
                    <tr>
                        <td><?= escapeHtml($row['member_name']) ?> (<?= escapeHtml($row['membership_id']) ?>)</td>
                        <td><?= escapeHtml(str_replace('_', ' ', $row['type'])) ?><br><a href="<?= escapeHtml(appUrl('public/request_details.php?id=' . (int) $row['id'])) ?>">Details and documents</a></td>
                        <td><?= escapeHtml(formatMoney($row['amount_requested'])) ?></td>
                        <td><?= escapeHtml($row['guarantor1_name'] ?: '—') ?><br><small><?= escapeHtml($row['guarantor1_status'] ?: 'pending') ?></small></td>
                        <td><?= escapeHtml($row['guarantor2_name'] ?: '—') ?><br><small><?= escapeHtml($row['guarantor2_status'] ?: 'pending') ?></small></td>
                        <td><?= escapeHtml($row['office_status']) ?></td>
                        <td>
                            <?php if ($canOfficeDecision): ?>
                                <form method="post" class="inline-form">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="decide">
                                    <input type="hidden" name="request_id" value="<?= escapeHtml((string) $row['id']) ?>">
                                    <div class="inline-actions">
                                        <button type="submit" name="decision" value="approved" class="small-button">Approve</button>
                                        <button type="submit" name="decision" value="rejected" class="small-button danger-button">Reject</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <span class="muted-text">Waiting for both guarantors<br>(<?= escapeHtml($row['guarantor1_status']) ?> / <?= escapeHtml($row['guarantor2_status']) ?>)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php adminPageEnd(); ?>
