<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/money.php';
require_once dirname(__DIR__) . '/includes/fund_documents.php';
$member = requireMember();
require_once dirname(__DIR__) . '/includes/notifications.php';
$fundId = filter_var($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['fund_id'] ?? null) : ($_GET['id'] ?? null), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$fund = null;
$errors = [];
$amount = '';
$balance = moneyToCents((string) $member['wallet_balance'], true);
$needed = null;
$raised = null;
$remaining = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
}
if (!$fundId) {
    http_response_code(404);
    $errors[] = 'Fund not found.';
} else {
    try {
        $statement = getDb()->prepare("SELECT id, title, reason, amount_needed, amount_raised, status FROM funds WHERE id = ? AND status = 'active' AND " . medicalFundEligibilitySql());
        $statement->execute([$fundId]);
        $fund = $statement->fetch();
        if (!$fund || $fund['status'] !== 'active') {
            http_response_code(404);
            $errors[] = 'This fund is not currently accepting donations.';
            $fund = null;
        } else {
            $needed = moneyToCents((string) $fund['amount_needed']);
            $raised = moneyToCents((string) $fund['amount_raised'], true);
            if ($needed === null || $raised === null || $balance === null) {
                $errors[] = 'The fund or wallet amount is unavailable. Please contact the office.';
            } else {
                $remaining = max(0, $needed - $raised);
            }
        }
    } catch (Throwable $exception) {
        error_log('Donation fund lookup failed: ' . $exception->getMessage());
        $errors[] = 'The fund could not be loaded. Please try again shortly.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fund) {
    $amount = is_string($_POST['amount'] ?? null) ? trim($_POST['amount']) : '';
    $donation = moneyToCents($amount);
    $submissionToken = $_POST['submission_token'] ?? '';
    if (!is_string($submissionToken) || empty($_SESSION['donation_tokens'][$fundId]) || !hash_equals($_SESSION['donation_tokens'][$fundId], $submissionToken)) {
        $errors[] = 'This donation form has expired or was already submitted. Reload the page before donating again.';
    }
    if ($donation === null) {
        $errors[] = 'Enter a positive amount with no more than two decimal places.';
    }
    if (!$errors) {
        $pdo = getDb();
        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('SELECT wallet_balance FROM members WHERE id = ? FOR UPDATE');
            $statement->execute([$member['id']]);
            $wallet = $statement->fetch();
            $statement = $pdo->prepare("SELECT id, title, amount_needed, amount_raised, status FROM funds WHERE id = ? AND status = 'active' AND " . medicalFundEligibilitySql() . ' FOR UPDATE');
            $statement->execute([$fundId]);
            $lockedFund = $statement->fetch();
            if (!$wallet || !$lockedFund || $lockedFund['status'] !== 'active') {
                $fund = null;
                throw new InvalidArgumentException('This fund is no longer accepting donations.');
            }
            $lockedBalance = moneyToCents((string) $wallet['wallet_balance'], true);
            $lockedNeeded = moneyToCents((string) $lockedFund['amount_needed']);
            $lockedRaised = moneyToCents((string) $lockedFund['amount_raised'], true);
            if ($lockedBalance === null || $lockedNeeded === null || $lockedRaised === null) {
                throw new RuntimeException('Invalid stored wallet or fund amount.');
            }
            $balance = $lockedBalance;
            $needed = $lockedNeeded;
            $raised = $lockedRaised;
            $remaining = max(0, $lockedNeeded - $lockedRaised);
            if ($donation > $lockedBalance) {
                throw new InvalidArgumentException('Your wallet balance is insufficient for this donation.');
            }
            if ($donation > $remaining) {
                throw new InvalidArgumentException('This fund only needs ' . formatMoney($remaining) . ' more. Enter a smaller donation.');
            }
            $newBalance = $lockedBalance - $donation;
            $newRaised = $lockedRaised + $donation;
            $decimal = centsToDecimal($donation);
            $statement = $pdo->prepare('UPDATE members SET wallet_balance = ? WHERE id = ?');
            $statement->execute([centsToDecimal($newBalance), $member['id']]);
            $statement = $pdo->prepare('UPDATE funds SET amount_raised = ?, status = ? WHERE id = ?');
            $statement->execute([centsToDecimal($newRaised), $newRaised >= $lockedNeeded ? 'completed' : 'active', $fundId]);
            $statement = $pdo->prepare('INSERT INTO donations (fund_id, donor_member_id, amount) VALUES (?, ?, ?)');
            $statement->execute([$fundId, $member['id'], $decimal]);
            $statement = $pdo->prepare("INSERT INTO wallet_transactions (member_id, type, amount, reference) VALUES (?, 'debit', ?, ?)");
            $statement->execute([$member['id'], $decimal, 'Donation to fund #' . $fundId]);
            createNotification('member', (int) $member['id'], 'Donation confirmed', 'Your donation of ' . formatMoney($donation) . ' to fund #' . $fundId . ' was received.');
            if ($newRaised >= $lockedNeeded) {
                $admins = $pdo->query("SELECT id FROM staff_users WHERE role = 'admin' AND is_active = 1")->fetchAll();
                foreach ($admins as $admin) {
                    createNotification('staff', (int) $admin['id'], 'Fund target reached', 'Fund #' . $fundId . ' is fully funded.');
                }
            }
            $pdo->commit();
            unset($_SESSION['donation_tokens'][$fundId]);
            $_SESSION['fund_success'] = 'Donation of ' . formatMoney($donation) . ' received. Your wallet balance is now ' . formatMoney($newBalance) . '.' . ($newRaised >= $lockedNeeded ? ' This fund is fully funded.' : '');
            redirectTo('public/funds_board.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof InvalidArgumentException) {
                $errors[] = $exception->getMessage();
            } else {
                error_log('Donation failed: ' . $exception->getMessage());
                $errors[] = 'Your donation could not be completed. Please try again.';
            }
        }
    }
}
if ($fund && empty($_SESSION['donation_tokens'][$fundId])) {
    $_SESSION['donation_tokens'][$fundId] = bin2hex(random_bytes(32));
}
$canDonate = $fund && $balance !== null && $needed !== null && $raised !== null && $balance > 0 && $remaining > 0;
pageHeader('Donate', $member);
?>
<section class="workspace service-workspace donation-workspace">
    <div class="page-heading"><div><p class="eyebrow">MEDICAL SUPPORT</p><h1>Donate</h1></div><a href="<?= escapeHtml(appUrl('public/funds_board.php')) ?>">Medical funds</a></div>
    <?php foreach ($errors as $error): showError($error); endforeach; ?>
    <?php if ($fund): ?>
    <div class="donation-layout">
    <div class="donation-details"><h2><?= escapeHtml($fund['title']) ?></h2><p class="request-description"><?= escapeHtml($fund['reason'] ?? '') ?></p><dl class="details"><div><dt>Your wallet balance</dt><dd><?= $balance !== null ? escapeHtml(formatMoney($balance)) : 'Unavailable' ?></dd></div><div><dt>Remaining target</dt><dd><?= $needed !== null && $raised !== null ? escapeHtml(formatMoney($remaining)) : 'Unavailable' ?></dd></div></dl></div>
    <div class="donation-checkout"><h2>Your contribution</h2>
    <?php if ($balance === 0): ?><p class="notice">Your wallet has no available balance. Please contact the office to add funds.</p><?php elseif ($needed !== null && $raised !== null && $remaining === 0): ?><p class="notice">This fund is already fully funded.</p><?php endif; ?>
    <form class="request-form" method="post">
        <?php csrfField(); ?><input type="hidden" name="fund_id" value="<?= (int) $fundId ?>"><input type="hidden" name="submission_token" value="<?= escapeHtml($_SESSION['donation_tokens'][$fundId]) ?>">
        <label for="amount">Donation amount (INR)</label><input id="amount" name="amount" type="number" inputmode="decimal" min="0.01" step="0.01" max="<?= $canDonate ? centsToDecimal(min($balance, $remaining)) : '0' ?>" value="<?= escapeHtml($amount) ?>" required<?= !$canDonate ? ' disabled' : '' ?>>
        <div class="form-actions"><button type="submit"<?= !$canDonate ? ' disabled' : '' ?>>Confirm donation</button><a href="<?= escapeHtml(appUrl('public/funds_board.php')) ?>">Cancel</a></div>
    </form>
    </div></div>
    <?php endif; ?>
</section>
<?php pageFooter(); ?>
