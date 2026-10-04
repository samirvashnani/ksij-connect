<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/money.php';
require_once dirname(__DIR__) . '/includes/fund_documents.php';
$member = requireMember();
require_once dirname(__DIR__) . '/includes/notifications.php';
$values = ['title' => '', 'reason' => '', 'medical_details' => '', 'amount_needed' => '', 'due_date' => ''];
$errors = [];
$medicalConfirmed = false;
$today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    foreach ($values as $key => $value) {
        $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    $submissionToken = $_POST['submission_token'] ?? '';
    if (!is_string($submissionToken) || empty($_SESSION['fund_submission_token']) || !hash_equals($_SESSION['fund_submission_token'], $submissionToken)) {
        $errors[] = 'This fund form has expired or was already submitted. Reload the page before submitting again.';
    }
    if ($values['title'] === '' || mb_strlen($values['title']) > 150) {
        $errors[] = 'Enter a title of up to 150 characters.';
    }
    if ($values['reason'] === '' || mb_strlen($values['reason']) > 10000) {
        $errors[] = 'Enter a public summary of up to 10,000 characters.';
    }
    if ($values['medical_details'] === '' || mb_strlen($values['medical_details']) > 10000) {
        $errors[] = 'Enter medical details of up to 10,000 characters.';
    }
    $medicalConfirmed = ($_POST['medical_confirmation'] ?? '') === '1';
    if (!$medicalConfirmed) {
        $errors[] = 'Confirm that the fund is for medical treatment and you are authorized to submit the documents.';
    }
    $needed = moneyToCents($values['amount_needed']);
    if ($needed === null) {
        $errors[] = 'Enter an amount greater than zero, up to 99,999,999.99.';
    }
    if ($values['due_date'] !== '') {
        $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $values['due_date'])
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $values['due_date'], new DateTimeZone('Asia/Kolkata'))
            : false;
        if (!$date || $date->format('Y-m-d') !== $values['due_date'] || $values['due_date'] < $today) {
            $errors[] = 'Choose today or a future date.';
        }
    }
    $uploads = validateMedicalFundUploads($_FILES);
    $errors = array_merge($errors, $uploads['errors']);
    if (!$errors) {
        $pdo = getDb();
        $storedPaths = [];
        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare("INSERT INTO funds (member_id, title, reason, purpose, medical_details, amount_needed, amount_raised, due_date, status) VALUES (?, ?, ?, 'medical', ?, ?, 0.00, ?, 'pending_approval')");
            $statement->execute([$member['id'], $values['title'], $values['reason'], $values['medical_details'], centsToDecimal($needed), $values['due_date'] !== '' ? $values['due_date'] : null]);
            $fundId = (int) $pdo->lastInsertId();
            storeMedicalFundDocuments($pdo, $fundId, $uploads['documents'], $storedPaths);
            $admins = $pdo->query("SELECT id FROM staff_users WHERE role = 'admin' AND is_active = 1")->fetchAll();
            foreach ($admins as $admin) {
                createNotification('staff', (int) $admin['id'], 'Medical fund awaiting approval', 'Fund #' . $fundId . ' and its medical documents are ready for review.');
            }
            createNotification('member', (int) $member['id'], 'Fund submitted', 'Your fund #' . $fundId . ' is awaiting office approval.');
            $pdo->commit();
            unset($_SESSION['fund_submission_token']);
            $_SESSION['fund_success'] = 'Medical fund #' . $fundId . ' and its documents were submitted for office approval.';
            redirectTo('public/fund_documents.php?fund_id=' . $fundId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($storedPaths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            error_log('Fund submission failed: ' . $exception->getMessage());
            $errors[] = 'Your fund could not be saved. Please try again.';
        }
    }
}
if (empty($_SESSION['fund_submission_token'])) {
    $_SESSION['fund_submission_token'] = bin2hex(random_bytes(32));
}
pageHeader('Raise a medical fund', $member);
?>
<section class="workspace">
    <div class="page-heading"><div><p class="eyebrow">MEDICAL SUPPORT</p><h1>Raise a medical fund</h1></div><a href="<?= escapeHtml(appUrl('public/fund_documents.php')) ?>">My funds</a></div>
    <?php foreach ($errors as $error): showError($error); endforeach; ?>
    <?php if ($errors): ?><p class="notice">Select all required documents again before resubmitting.</p><?php endif; ?>
    <form class="request-form" method="post" enctype="multipart/form-data">
        <?php csrfField(); ?><input type="hidden" name="submission_token" value="<?= escapeHtml($_SESSION['fund_submission_token']) ?>">
        <label for="title">Fund title</label><input id="title" name="title" maxlength="150" value="<?= escapeHtml($values['title']) ?>" required>
        <label for="reason">Public summary</label><textarea id="reason" name="reason" rows="4" maxlength="10000" aria-describedby="public-summary-hint" required><?= escapeHtml($values['reason']) ?></textarea>
        <p id="public-summary-hint" class="field-hint">Keep names, contact details and identifying medical information out of the public title and summary.</p>
        <label for="medical_details">Medical details for office review</label><textarea id="medical_details" name="medical_details" rows="5" maxlength="10000" required><?= escapeHtml($values['medical_details']) ?></textarea>
        <div class="form-grid"><div><label for="amount_needed">Target amount (INR)</label><input id="amount_needed" name="amount_needed" type="number" inputmode="decimal" min="0.01" max="99999999.99" step="0.01" value="<?= escapeHtml($values['amount_needed']) ?>" required></div><div><label for="due_date">Due date (optional)</label><input id="due_date" name="due_date" type="date" min="<?= $today ?>" value="<?= escapeHtml($values['due_date']) ?>"></div></div>
        <fieldset><legend>Medical documents</legend><p id="fund-upload-limits" class="field-hint">PDF, JPG or PNG. Up to 5 MB per file, 10 files and 20 MB in total. The first three groups are required.</p>
            <?php foreach (medicalDocumentCategories() as $type => $label): ?><label for="<?= $type ?>"><?= escapeHtml($label) ?><?= $type === 'other' ? ' (optional)' : ' (required)' ?></label><input type="file" id="<?= $type ?>" name="<?= $type ?>[]" accept=".pdf,.jpg,.jpeg,.png" multiple aria-describedby="fund-upload-limits"<?= $type !== 'other' ? ' required' : '' ?>><?php endforeach; ?>
        </fieldset>
        <label class="checkbox-label"><input type="checkbox" name="medical_confirmation" value="1"<?= $medicalConfirmed ? ' checked' : '' ?> required><span>I confirm this fund is for medical treatment and I am authorized to submit these documents for office review.</span></label>
        <div class="form-actions"><button type="submit">Submit for approval</button><a href="<?= escapeHtml(appUrl('public/funds_board.php')) ?>">Cancel</a></div>
    </form>
</section>
<?php pageFooter(); ?>
