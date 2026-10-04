<?php
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/member_access.php';
$error = '';
$membershipId = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $membershipId = strtoupper(trim((string) ($_POST['membership_id'] ?? '')));
    if (!allowLoginAttempt('member_otp', 5)) {
        $error = 'Too many requests. Please wait five minutes.';
    } elseif (!preg_match('/^[A-Z0-9-]{1,20}$/', $membershipId)) {
        $error = 'Enter a valid membership ID.';
    } else {
        try {
            require_once dirname(__DIR__) . '/includes/db.php';
            $statement = $pdo->prepare('SELECT id, email FROM members WHERE membership_id = ?');
            $statement->execute([$membershipId]);
            $member = $statement->fetch();
            if (!$member) {
                $error = 'Membership ID not found. Please check with the office.';
            } else {
                $otp = (string) random_int(100000, 999999);
                $pdo->beginTransaction();
                $statement = $pdo->prepare('UPDATE otp_verification SET is_used = 1 WHERE membership_id = ? AND is_used = 0');
                $statement->execute([$membershipId]);
                $statement = $pdo->prepare('INSERT INTO otp_verification (membership_id, otp_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))');
                $statement->execute([$membershipId, $otp]);
                $otpId = (int) $pdo->lastInsertId();
                $testMode = defined('OTP_TEST_MODE') && OTP_TEST_MODE;
                if (!$testMode && !mail($member['email'], 'KSIJ Connect verification code', 'Your verification code is ' . $otp . '. It expires in five minutes.')) {
                    throw new RuntimeException('OTP delivery failed.');
                }
                $pdo->commit();
                $_SESSION['pending_otp'] = ['id' => $otpId, 'membership_id' => $membershipId, 'attempts' => 0];
                unset($_SESSION['test_otp']);
                if ($testMode) {
                    $_SESSION['test_otp'] = $otp;
                }
                redirectTo('public/verify_otp.php');
            }
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Member OTP request failed: ' . $exception->getMessage());
            $error = 'Sign-in is unavailable right now. Please try again shortly.';
        }
    }
}
pageHeader('Member login');
?>
<?php memberAccessStart(1); ?>
<?php showError($error); ?>
<form method="post" data-member-access-form><?php csrfField(); ?><label for="membership_id">Jamaat membership ID</label><input id="membership_id" name="membership_id" value="<?= escapeHtml($membershipId) ?>" maxlength="20" autocomplete="username" autocapitalize="characters" spellcheck="false" aria-describedby="membership-note"<?= $error ? ' aria-invalid="true"' : '' ?> required><p class="member-access-note" id="membership-note">Your ID is on your membership card or office receipt.</p><button type="submit">Send verification code <?= uiIcon('arrow-up-right') ?></button></form>
<?php memberAccessEnd(); ?>
<?php pageFooter(); ?>
