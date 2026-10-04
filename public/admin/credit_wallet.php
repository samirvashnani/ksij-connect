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
    $memberId = (int) ($_POST['member_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $reference = trim((string) ($_POST['reference'] ?? 'Office credit - cash/Sukha deposit'));

    if ($memberId <= 0 || $amount <= 0) {
        $error = 'Please select a member and enter a valid amount.';
    } else {
        $member = $pdo->prepare('SELECT id, full_name, wallet_balance FROM members WHERE id = ?');
        $member->execute([$memberId]);
        $memberRow = $member->fetch();

        if (!$memberRow) {
            $error = 'Member not found.';
        } else {
            $newBalance = (float) $memberRow['wallet_balance'] + $amount;
            $pdo->prepare('UPDATE members SET wallet_balance = ? WHERE id = ?')->execute([$newBalance, $memberId]);
            $pdo->prepare('INSERT INTO wallet_transactions (member_id, type, amount, reference) VALUES (?, ?, ?, ?)')->execute([$memberId, 'credit', $amount, $reference]);
            createNotification('member', $memberId, 'Wallet credited', 'An office credit of ' . formatMoney($amount) . ' was added to your wallet.');
            $success = 'Wallet credited successfully.';
        }
    }
}

adminRedirectAfterSuccess('public/admin/credit_wallet.php', $success);
$members = $pdo->query('SELECT id, membership_id, full_name, wallet_balance FROM members ORDER BY full_name')->fetchAll();

adminPageStart('Credit wallet', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-grid two-columns">
    <section class="admin-card">
        <h2>Manual wallet credit</h2>
        <form method="post" class="admin-form">
            <?php csrfField(); ?>
            <label>Member
                <select name="member_id" required>
                    <option value="">Choose member</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= escapeHtml((string) $member['id']) ?>"><?= escapeHtml($member['full_name']) ?> (<?= escapeHtml($member['membership_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Amount<input type="number" step="0.01" min="0.01" name="amount" required></label>
            <label>Reference<input name="reference" value="Office credit - cash/Sukha deposit"></label>
            <button type="submit">Credit wallet</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Current member balances</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Member</th><th>Balance</th></tr></thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td><?= escapeHtml($member['full_name']) ?> (<?= escapeHtml($member['membership_id']) ?>)</td>
                            <td><?= escapeHtml(formatMoney($member['wallet_balance'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php adminPageEnd(); ?>
