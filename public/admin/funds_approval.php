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
    if ($action === 'approve') {
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT f.*, m.full_name, m.membership_id FROM funds f JOIN members m ON m.id = f.member_id WHERE f.id = ?');
        $stmt->execute([$fundId]);
        $fund = $stmt->fetch();

        if (!$fund) {
            $error = 'Fund request not found.';
        } else {
            $pdo->prepare('UPDATE funds SET status = ? WHERE id = ?')->execute(['active', $fundId]);
            createNotification('member', (int) $fund['member_id'], 'Fund request approved', 'Your fund request "' . $fund['title'] . '" is now active.');
            $success = 'Fund approved and activated.';
        }
    }
}

adminRedirectAfterSuccess('public/admin/funds_approval.php', $success);
$rows = $pdo->query('SELECT f.*, m.full_name AS member_name, m.membership_id FROM funds f JOIN members m ON m.id = f.member_id WHERE f.status = "pending_approval" ORDER BY f.created_at DESC')->fetchAll();

adminPageStart('Funds approval', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-card">
    <h2>Pending community funds</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Member</th><th>Title</th><th>Needed</th><th>Raised</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= escapeHtml($row['member_name']) ?> (<?= escapeHtml($row['membership_id']) ?>)</td>
                        <td><?= escapeHtml($row['title']) ?><details class="admin-row-details"><summary>Request summary</summary><p><?= escapeHtml($row['reason']) ?></p></details></td>
                        <td><?= escapeHtml(formatMoney($row['amount_needed'])) ?></td>
                        <td><?= escapeHtml(formatMoney($row['amount_raised'])) ?></td>
                        <td>
                            <a class="button-link button-secondary small-button" target="_blank" rel="noopener" href="<?= escapeHtml(appUrl('public/fund_documents.php?fund_id=' . (int) $row['id'])) ?>"><?= uiIcon('folder') ?>View supporting documents</a>
                            <form method="post" class="inline-form">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="fund_id" value="<?= escapeHtml((string) $row['id']) ?>">
                                <button type="submit" class="small-button">Approve</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php adminPageEnd(); ?>
