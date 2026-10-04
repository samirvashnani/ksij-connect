<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$member = requireMember();
require_once dirname(__DIR__) . '/includes/notifications.php';
$memberArea = trim((string) ($member['area'] ?? ''));
$types = ['scholarship' => 'Scholarship', 'medical_aid' => 'Medical aid', 'loan' => 'Loan'];
$values = ['type' => '', 'description' => '', 'amount' => '', 'guarantor1_id' => '', 'guarantor2_id' => ''];
$errors = [];
$guarantors = [];
$available = $memberArea !== '';
$pdo = getDb();
if (!$available) {
    $errors[] = 'Your membership has no area assigned. Please contact the office to update it.';
} else {
    try {
        $statement = $pdo->prepare("SELECT id, full_name, area, role FROM staff_users WHERE (role = 'cc_member' OR (role = 'volunteer' AND is_guarantor_approved = 1)) AND is_active = 1 AND LOWER(TRIM(area)) = LOWER(?) ORDER BY full_name, id");
        $statement->execute([$memberArea]);
        $guarantors = $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('Guarantor list failed: ' . $exception->getMessage());
        $errors[] = 'Guarantors could not be loaded. Please try again shortly.';
        $available = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    foreach ($values as $key => $value) {
        $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    $submissionToken = $_POST['submission_token'] ?? '';
    if (!is_string($submissionToken) || empty($_SESSION['request_submission_token']) || !hash_equals($_SESSION['request_submission_token'], $submissionToken)) {
        $errors[] = 'This form has expired or was already submitted. Reload the page before submitting again.';
    }
    if (!isset($types[$values['type']])) {
        $errors[] = 'Choose a request type.';
    }
    if ($values['description'] === '' || mb_strlen($values['description']) > 10000) {
        $errors[] = 'Enter a description of up to 10,000 characters.';
    }
    if (!preg_match('/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D', $values['amount']) || !preg_match('/[1-9]/', $values['amount'])) {
        $errors[] = 'Enter an amount greater than zero, up to 99,999,999.99.';
    }
    $guarantor1 = filter_var($values['guarantor1_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $guarantor2 = filter_var($values['guarantor2_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$guarantor1 || !$guarantor2 || $guarantor1 === $guarantor2) {
        $errors[] = 'Choose two different guarantors.';
    }

    $upload = $_FILES['document'] ?? null;
    $extension = null;
    if ($upload !== null) {
        if (!is_array($upload) || !isset($upload['error']) || is_array($upload['error'])) {
            $errors[] = 'The document upload is invalid.';
        } elseif ($upload['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($upload['error'] !== UPLOAD_ERR_OK || !isset($upload['tmp_name']) || !is_string($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
                $errors[] = 'The document could not be uploaded. Choose the file again.';
            } else {
                $size = filesize($upload['tmp_name']);
                if (!$size || $size > 5 * 1024 * 1024) {
                    $errors[] = 'The document must be no larger than 5 MB.';
                } else {
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
                    $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
                    if ($extension === null) {
                        $errors[] = 'Upload a PDF, JPG or PNG document.';
                    }
                }
            }
        }
    }

    if (!$errors && $available) {
        $documentPath = null;
        $absolutePath = null;
        try {
            $pdo->beginTransaction();
            // Lock and recheck eligibility so an approval change cannot slip past the form.
            $statement = $pdo->prepare("SELECT id FROM staff_users WHERE id IN (?, ?) AND (role = 'cc_member' OR (role = 'volunteer' AND is_guarantor_approved = 1)) AND is_active = 1 AND LOWER(TRIM(area)) = LOWER(?) ORDER BY id FOR UPDATE");
            $statement->execute([$guarantor1, $guarantor2, $memberArea]);
            if (count($statement->fetchAll()) !== 2) {
                $pdo->rollBack();
                $errors[] = 'Choose two different active guarantors from your area: approved volunteers or CC members.';
            } else {
                if ($extension !== null) {
                    $directory = dirname(__DIR__) . '/uploads/request_documents';
                    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                        throw new RuntimeException('Could not create document storage.');
                    }
                    $documentPath = 'uploads/request_documents/' . bin2hex(random_bytes(16)) . '.' . $extension;
                    $absolutePath = dirname(__DIR__) . '/' . $documentPath;
                    if (!move_uploaded_file($upload['tmp_name'], $absolutePath)) {
                        throw new RuntimeException('Could not store the document.');
                    }
                }
                $statement = $pdo->prepare("INSERT INTO requests (member_id, type, description, amount_requested, document_path, guarantor1_id, guarantor2_id, guarantor1_status, guarantor2_status, office_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', 'pending')");
                $statement->execute([$member['id'], $values['type'], $values['description'], $values['amount'], $documentPath, $guarantor1, $guarantor2]);
                $requestId = (int) $pdo->lastInsertId();
                foreach ([$guarantor1, $guarantor2] as $guarantorId) {
                    createNotification('staff', $guarantorId, 'Guarantor approval requested', 'Request #' . $requestId . ' requires your review.');
                }
                notifyAdminsOfNewRequest($requestId, $types[$values['type']], $member['full_name']);
                $pdo->commit();
                unset($_SESSION['request_submission_token']);
                $_SESSION['request_success'] = 'Request #' . $requestId . ' submitted successfully.';
                redirectTo('public/my_requests.php');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($absolutePath !== null && is_file($absolutePath)) {
                unlink($absolutePath);
            }
            error_log('Request submission failed: ' . $exception->getMessage());
            $errors[] = 'Your request could not be saved. Please try again.';
        }
    }
}
if (empty($_SESSION['request_submission_token'])) {
    $_SESSION['request_submission_token'] = bin2hex(random_bytes(32));
}
pageHeader('New request', $member);
?>
<section class="workspace">
    <div class="page-heading"><div><p class="eyebrow">MEMBER REQUESTS</p><h1>New request</h1></div><a href="<?= escapeHtml(appUrl('public/my_requests.php')) ?>">My requests</a></div>
    <?php foreach ($errors as $error): showError($error); endforeach; ?>
    <?php if ($errors && isset($upload) && is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK): ?><p class="notice">Select your supporting document again before resubmitting.</p><?php endif; ?>
    <?php if ($available && count($guarantors) < 2): ?><p class="notice">Fewer than two eligible guarantors are available in <?= escapeHtml($memberArea) ?>. Please contact the office.</p><?php endif; ?>
    <form class="request-form" method="post" enctype="multipart/form-data">
        <?php csrfField(); ?>
        <input type="hidden" name="submission_token" value="<?= escapeHtml($_SESSION['request_submission_token']) ?>">
        <div class="form-grid">
            <div><label for="type">Request type</label><select id="type" name="type" required><option value="">Select type</option><?php foreach ($types as $value => $label): ?><option value="<?= $value ?>"<?= $values['type'] === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            <div><label for="amount">Amount requested (INR)</label><input id="amount" name="amount" type="number" inputmode="decimal" min="0.01" max="99999999.99" step="0.01" value="<?= escapeHtml($values['amount']) ?>" required></div>
        </div>
        <label for="description">Description</label><textarea id="description" name="description" rows="6" maxlength="10000" required><?= escapeHtml($values['description']) ?></textarea>
        <fieldset><legend>Guarantors<?= $memberArea !== '' ? ' - ' . escapeHtml($memberArea) : '' ?></legend><div class="form-grid">
            <?php foreach ([1, 2] as $slot): ?><div><label for="guarantor<?= $slot ?>">Guarantor <?= $slot ?></label><select id="guarantor<?= $slot ?>" name="guarantor<?= $slot ?>_id" required><option value="">Select guarantor</option><?php foreach ($guarantors as $guarantor): ?><option value="<?= (int) $guarantor['id'] ?>"<?= $values['guarantor' . $slot . '_id'] === (string) $guarantor['id'] ? ' selected' : '' ?>><?= escapeHtml($guarantor['full_name'] . ' - ' . ($guarantor['role'] === 'cc_member' ? 'CC member' : 'Volunteer') . ($guarantor['area'] ? ' - ' . $guarantor['area'] : '')) ?></option><?php endforeach; ?></select></div><?php endforeach; ?>
        </div></fieldset>
        <label for="document">Supporting document (optional)</label><input id="document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="document-limits"><p id="document-limits" class="field-hint">PDF, JPG or PNG. Maximum 5 MB.</p>
        <div class="form-actions"><button type="submit"<?= !$available || count($guarantors) < 2 ? ' disabled' : '' ?>>Submit request</button><a href="<?= escapeHtml(appUrl('public/my_requests.php')) ?>">Cancel</a></div>
    </form>
</section>
<?php pageFooter(); ?>
