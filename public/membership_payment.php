<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$member = requireMember();
require_once dirname(__DIR__) . '/includes/money.php';
header('Cache-Control: no-store, private');
// Existing development configs use OTP_TEST_MODE; an explicit payment flag overrides it.
$demoEnabled = defined('DEMO_MEMBERSHIP_PAYMENTS') ? DEMO_MEMBERSHIP_PAYMENTS === true
    : (defined('OTP_TEST_MODE') && OTP_TEST_MODE === true);
$error = '';
$historyError = '';
$history = [];
$due = moneyToCents((string) $member['fees_due'], true);
$today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $token = $_POST['payment_token'] ?? '';
    $intent = $_SESSION['membership_payment_intent'] ?? null;
    if (!$demoEnabled) {
        $error = 'Demo payments are disabled. Contact the office for membership payment.';
    } elseif (!is_string($token) || !is_array($intent) || !hash_equals($intent['token'], $token)
        || $intent['member_id'] !== (int) $member['id'] || time() - $intent['created_at'] > 900) {
        $error = 'This payment form has expired or was already submitted. Reload before trying again.';
    } elseif (($_POST['demo_confirmation'] ?? '') !== '1') {
        $error = 'Confirm that this is a simulated payment.';
    } else {
        $pdo = getDb();
        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('SELECT fees_due FROM members WHERE id = ? FOR UPDATE');
            $statement->execute([$member['id']]);
            $current = $statement->fetchColumn();
            $lockedDue = $current !== false ? moneyToCents((string) $current, true) : null;
            if ($lockedDue === null || $lockedDue <= 0 || $lockedDue !== $intent['amount']) {
                $pdo->rollBack();
                unset($_SESSION['membership_payment_intent']);
                $error = 'Your fees have changed or are already settled. Reload to see the current amount.';
            } else {
                $reference = 'DEMO-' . strtoupper(bin2hex(random_bytes(8)));
                $statement = $pdo->prepare("INSERT INTO membership_payments (member_id, amount, payment_mode, reference, submission_token, paid_on) VALUES (?, ?, 'demo', ?, ?, ?)");
                $statement->execute([$member['id'], centsToDecimal($lockedDue), $reference, $token, $today]);
                $statement = $pdo->prepare("UPDATE members SET fees_due = 0, fees_last_paid_date = ?, membership_status = 'active' WHERE id = ?");
                $statement->execute([$today, $member['id']]);
                $pdo->commit();
                unset($_SESSION['membership_payment_intent']);
                $_SESSION['membership_payment_success'] = ['reference' => $reference, 'amount' => $lockedDue, 'paid_on' => $today];
                redirectTo('public/membership_payment.php');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Demo membership payment failed: ' . $exception->getMessage());
            $error = 'Payment could not be recorded. Reload and check your fees before trying again.';
        }
    }
}
$success = $_SESSION['membership_payment_success'] ?? null;
unset($_SESSION['membership_payment_success']);
try {
    $statement = getDb()->prepare('SELECT amount, reference, paid_on FROM membership_payments WHERE member_id = ? ORDER BY id DESC LIMIT 10');
    $statement->execute([$member['id']]);
    $history = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Membership payment history failed: ' . $exception->getMessage());
    $historyError = 'Payment records are unavailable. The membership_payments migration must be installed before using demo payments.';
}
$intent = $_SESSION['membership_payment_intent'] ?? null;
if (!$intent || $intent['member_id'] !== (int) $member['id'] || $intent['amount'] !== $due || time() - $intent['created_at'] > 900) {
    $_SESSION['membership_payment_intent'] = ['token' => bin2hex(random_bytes(32)), 'member_id' => (int) $member['id'], 'amount' => $due, 'created_at' => time()];
}
pageHeader('Membership fees', $member);
?>
<section class="workspace formal-workspace membership-payment">
    <div class="page-heading"><div><p class="eyebrow">MEMBERSHIP</p><h1>Membership fees</h1></div><a href="<?= escapeHtml(appUrl('public/member_chat.php')) ?>">Dashboard <?= uiIcon('arrow-up-right') ?></a></div>
    <p class="notice"><strong>Presentation demo.</strong> No money is collected. Completing this simulation changes your membership fee records in the database.</p>
    <?php showError($error); ?>
    <?php if ($success): ?>
        <section class="payment-receipt" role="status"><span class="payment-complete-mark"><?= uiIcon('shield-check') ?></span><h2>Demo payment recorded</h2><strong><?= escapeHtml(formatMoney($success['amount'])) ?></strong><p>Simulated payment only. No money was transferred.</p><dl><div><dt>Reference</dt><dd><?= escapeHtml($success['reference']) ?></dd></div><div><dt>Date</dt><dd><?= escapeHtml($success['paid_on']) ?></dd></div></dl></section>
    <?php endif; ?>
    <div class="membership-payment-columns">
        <section class="membership-fee-summary"><h2>Membership account</h2><dl><div><dt>Member</dt><dd><?= escapeHtml($member['full_name']) ?></dd></div><div><dt>Membership ID</dt><dd><?= escapeHtml($member['membership_id']) ?></dd></div><div><dt>Last payment</dt><dd><?= escapeHtml($member['fees_last_paid_date'] ?: 'Not recorded') ?></dd></div><div><dt>Renewal date</dt><dd><?= escapeHtml($member['renewal_date'] ?: 'Not recorded') ?></dd></div></dl><span class="muted">Fees due</span><strong class="membership-fee-amount"><?= $due !== null ? escapeHtml(formatMoney($due)) : 'Unavailable' ?></strong></section>
        <section class="membership-pay-action"><h2>Demo checkout</h2>
            <?php showError($historyError); ?>
            <?php if (!$demoEnabled): ?><p class="notice">Demo payments are disabled.</p>
            <?php elseif ($due === null): ?><p class="error">Your fees could not be read. Contact the office.</p>
            <?php elseif ($due === 0): ?><p class="success">No outstanding membership fees.</p>
            <?php else: ?>
            <form method="post" data-membership-payment><?php csrfField(); ?><input type="hidden" name="payment_token" value="<?= escapeHtml($_SESSION['membership_payment_intent']['token']) ?>">
                <label class="checkbox-label"><input type="checkbox" name="demo_confirmation" value="1" required><span>I understand this is a demo, no money will be collected, and my fee records will be updated.</span></label>
                <button type="submit"<?= $historyError ? ' disabled' : '' ?>><?= uiIcon('users') ?>Simulate payment of <?= escapeHtml(formatMoney($due)) ?></button>
                <p class="payment-progress" data-payment-progress role="status" hidden><span class="payment-spinner" aria-hidden="true"></span>Processing demo payment...</p>
            </form>
            <?php endif; ?>
        </section>
    </div>
    <section class="membership-payment-history"><h2>Recent demo payments</h2><?php if (!$history && !$historyError): ?><p class="muted">No demo payments recorded.</p><?php endif; ?>
        <?php foreach ($history as $payment): $amount = moneyToCents((string) $payment['amount'], true); ?><article><div><strong><?= escapeHtml($payment['reference']) ?></strong><time><?= escapeHtml($payment['paid_on']) ?></time></div><span class="status-badge status-pending">Demo</span><strong><?= $amount !== null ? escapeHtml(formatMoney($amount)) : 'Unavailable' ?></strong></article><?php endforeach; ?>
    </section>
</section>
<script src="<?= escapeHtml(appUrl('assets/js/membership_payment.js?v=1')) ?>" defer></script>
<?php pageFooter(); ?>
