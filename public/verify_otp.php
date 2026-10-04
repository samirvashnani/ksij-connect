<?php
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/member_access.php';
if (empty($_SESSION['pending_otp'])) {
    redirectTo('public/login.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $otp = trim((string) ($_POST['otp'] ?? ''));
    $_SESSION['pending_otp']['attempts']++;
    if ($_SESSION['pending_otp']['attempts'] > 5) {
        unset($_SESSION['pending_otp'], $_SESSION['test_otp']);
        redirectTo('public/login.php');
    }
    if (!preg_match('/^[0-9]{6}$/', $otp)) {
        $error = 'Enter the six-digit code.';
    } else {
        try {
            require_once dirname(__DIR__) . '/includes/db.php';
            $pdo->beginTransaction();
            $statement = $pdo->prepare('SELECT id, otp_code FROM otp_verification WHERE id = ? AND membership_id = ? AND is_used = 0 AND expires_at > NOW() FOR UPDATE');
            $statement->execute([$_SESSION['pending_otp']['id'], $_SESSION['pending_otp']['membership_id']]);
            $row = $statement->fetch();
            if (!$row || !hash_equals($row['otp_code'], $otp)) {
                $pdo->rollBack();
                $error = 'The code is incorrect or expired. Please try again or request a new code.';
            } else {
                $statement = $pdo->prepare('SELECT id, membership_id, full_name FROM members WHERE membership_id = ?');
                $statement->execute([$_SESSION['pending_otp']['membership_id']]);
                $member = $statement->fetch();
                if (!$member) {
                    throw new RuntimeException('Member no longer exists.');
                }
                $statement = $pdo->prepare('UPDATE otp_verification SET is_used = 1 WHERE id = ?');
                $statement->execute([$row['id']]);
                $pdo->commit();
                resetLoginSession();
                $_SESSION['member_id'] = (int) $member['id'];
                $_SESSION['member_name'] = $member['full_name'];
                $_SESSION['membership_id'] = $member['membership_id'];
                redirectTo('public/member_chat.php');
            }
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('OTP verification failed: ' . $exception->getMessage());
            $error = 'Sign-in is unavailable right now. Please try again shortly.';
        }
    }
}
pageHeader('Verify membership');
?>
<?php memberAccessStart(2); ?>
<div class="member-verification-id"><span>Membership ID</span><strong><?= escapeHtml($_SESSION['pending_otp']['membership_id']) ?></strong><a href="<?= escapeHtml(appUrl('public/login.php')) ?>">Change</a></div>
<?php if (defined('OTP_TEST_MODE') && OTP_TEST_MODE && isset($_SESSION['test_otp'])): ?><p class="notice">Development code: <strong><?= escapeHtml($_SESSION['test_otp']) ?></strong></p><?php endif; ?>
<?php showError($error); ?>
<form method="post" data-member-access-form><?php csrfField(); ?><label for="otp">Six-digit verification code</label><input id="otp" name="otp" data-otp-code inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" aria-describedby="otp-note" required><p class="member-access-note" id="otp-note">Codes expire five minutes after they are sent.</p><button type="submit">Verify and continue <?= uiIcon('arrow-up-right') ?></button></form>
<a class="member-resend" href="<?= escapeHtml(appUrl('public/login.php')) ?>">Request a new code</a>
<?php memberAccessEnd(); ?>
<?php pageFooter(); ?>
