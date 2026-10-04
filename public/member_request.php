<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$member = requireMember();
require_once dirname(__DIR__) . '/includes/notifications.php';
require_once dirname(__DIR__) . '/includes/request_support.php';
$memberArea = trim((string) ($member['area'] ?? ''));
$types = ['scholarship' => 'Education / scholarship', 'medical_aid' => 'Medical aid', 'loan' => 'Loan'];
$values = ['type' => '', 'description' => '', 'amount' => '', 'guarantor1_id' => '', 'guarantor2_id' => '',
    'education_grade' => '', 'school_name' => '', 'last_exam_marks' => '', 'last_exam_total' => '',
    'medical_title' => '', 'medical_details' => '', 'due_date' => ''];
$medicalConfirmed = false;
$today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
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
        $values[$key] = is_string($_POST[$key] ?? null) && preg_match('//u', $_POST[$key]) ? trim($_POST[$key]) : '';
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

    $documents = [];
    if ($values['type'] === 'scholarship') {
        if ($values['education_grade'] === '' || mb_strlen($values['education_grade']) > 60) {
            $errors[] = 'Enter your grade, class or course year (up to 60 characters).';
        }
        if ($values['school_name'] === '' || mb_strlen($values['school_name']) > 150) {
            $errors[] = 'Enter your school or college name (up to 150 characters).';
        }
        if (!preg_match('/^[0-9]{1,5}(?:\.[0-9]{1,2})?$/D', $values['last_exam_marks'])
            || !preg_match('/^[0-9]{1,5}(?:\.[0-9]{1,2})?$/D', $values['last_exam_total'])
            || (float) $values['last_exam_total'] <= 0 || (float) $values['last_exam_marks'] > (float) $values['last_exam_total']) {
            $errors[] = 'Enter valid examination marks and a positive total; marks cannot exceed the total (maximum 99,999.99).';
        }
        $result = requestSingleUpload($_FILES['exam_result'] ?? null, 'exam_result', true);
        $documents = $result['documents'];
        $errors = array_merge($errors, $result['errors']);
    } elseif ($values['type'] === 'medical_aid') {
        if ($values['medical_title'] === '' || mb_strlen($values['medical_title']) > 150) {
            $errors[] = 'Enter a medical request title of up to 150 characters.';
        }
        if ($values['medical_details'] === '' || mb_strlen($values['medical_details']) > 10000) {
            $errors[] = 'Enter medical details of up to 10,000 characters.';
        }
        $medicalConfirmed = ($_POST['medical_confirmation'] ?? '') === '1';
        if (!$medicalConfirmed) { $errors[] = 'Confirm this request is for medical treatment and you are authorized to submit the documents.'; }
        if ($values['due_date'] !== '') {
            $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $values['due_date'])
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $values['due_date'], new DateTimeZone('Asia/Kolkata')) : false;
            if (!$date || $date->format('Y-m-d') !== $values['due_date'] || $values['due_date'] < $today) {
                $errors[] = 'Choose today or a future due date.';
            }
        }
        $result = validateMedicalFundUploads($_FILES);
        $documents = $result['documents'];
        $errors = array_merge($errors, $result['errors']);
    }
    if (in_array($values['type'], ['scholarship', 'loan'], true)) {
        $result = requestSingleUpload($_FILES['document'] ?? null, 'other', false);
        $documents = array_merge($documents, $result['documents']);
        $errors = array_merge($errors, $result['errors']);
    }

    if (!$errors && $available) {
        $storedPaths = [];
        try {
            $pdo->beginTransaction();
            // Lock and recheck eligibility so an approval change cannot slip past the form.
            $statement = $pdo->prepare("SELECT id FROM staff_users WHERE id IN (?, ?) AND (role = 'cc_member' OR (role = 'volunteer' AND is_guarantor_approved = 1)) AND is_active = 1 AND LOWER(TRIM(area)) = LOWER(?) ORDER BY id FOR UPDATE");
            $statement->execute([$guarantor1, $guarantor2, $memberArea]);
            if (count($statement->fetchAll()) !== 2) {
                $pdo->rollBack();
                $errors[] = 'Choose two different active guarantors from your area: approved volunteers or CC members.';
            } else {
                $education = $values['type'] === 'scholarship';
                $medical = $values['type'] === 'medical_aid';
                $statement = $pdo->prepare("INSERT INTO requests (member_id,type,description,amount_requested,guarantor1_id,guarantor2_id,education_grade,school_name,last_exam_marks,last_exam_total,medical_title,medical_details,due_date,medical_confirmation,guarantor1_status,guarantor2_status,office_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending','pending','pending')");
                $statement->execute([$member['id'], $values['type'], $values['description'], $values['amount'], $guarantor1, $guarantor2,
                    $education ? $values['education_grade'] : null, $education ? $values['school_name'] : null,
                    $education ? $values['last_exam_marks'] : null, $education ? $values['last_exam_total'] : null,
                    $medical ? $values['medical_title'] : null, $medical ? $values['medical_details'] : null,
                    $medical && $values['due_date'] !== '' ? $values['due_date'] : null, $medical ? 1 : 0]);
                $requestId = (int) $pdo->lastInsertId();
                $documentPath = storeRequestDocuments($pdo, $requestId, $documents, $storedPaths);
                if ($documentPath !== null) {
                    $pdo->prepare('UPDATE requests SET document_path = ? WHERE id = ?')->execute([$documentPath, $requestId]);
                }
                foreach ([$guarantor1, $guarantor2] as $guarantorId) {
                    createNotification('staff', $guarantorId, 'Guarantor approval requested', 'Request #' . $requestId . ' requires your review.');
                }
                $pdo->commit();
                unset($_SESSION['request_submission_token']);
                $_SESSION['request_success'] = 'Request #' . $requestId . ' submitted successfully.';
                redirectTo('public/my_requests.php');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($storedPaths as $path) { if (is_file($path)) { unlink($path); } }
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
<section class="workspace formal-workspace">
    <div class="page-heading"><div><p class="eyebrow">MEMBER SERVICES</p><h1>New formal request</h1></div><a class="document-link" href="<?= escapeHtml(appUrl('public/my_requests.php')) ?>"><?= uiIcon('clipboard-list') ?>My requests</a></div>
    <nav class="formal-section-nav" aria-label="Request sections"><a href="#request-information"><span>01</span>Request details</a><a href="#request-guarantors"><span>02</span>Guarantors</a><a href="#request-submit"><span>03</span>Submission</a></nav>
    <?php foreach ($errors as $error): showError($error); endforeach; ?>
    <?php if ($errors && $_SERVER['REQUEST_METHOD'] === 'POST'): ?><p class="notice">Select all required documents again before resubmitting.</p><?php endif; ?>
    <?php if ($available && count($guarantors) < 2): ?><p class="notice">Fewer than two eligible guarantors are available in <?= escapeHtml($memberArea) ?>. Please contact the office.</p><?php endif; ?>
    <form class="request-form" method="post" enctype="multipart/form-data" data-formal-request>
        <?php csrfField(); ?>
        <input type="hidden" name="submission_token" value="<?= escapeHtml($_SESSION['request_submission_token']) ?>">
        <div class="formal-columns">
        <div class="formal-details" id="request-information">
        <h2>Request details</h2>
        <div class="form-grid">
            <div><label for="type">Request type</label><select id="type" name="type" required><option value="">Select type</option><?php foreach ($types as $value => $label): ?><option value="<?= $value ?>"<?= $values['type'] === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            <div><label for="amount">Amount requested (INR)</label><input id="amount" name="amount" type="number" inputmode="decimal" min="0.01" max="99999999.99" step="0.01" value="<?= escapeHtml($values['amount']) ?>" required></div>
        </div>
        <label for="description">Request summary</label><textarea id="description" name="description" rows="4" maxlength="10000" required><?= escapeHtml($values['description']) ?></textarea>
        <fieldset data-request-types="scholarship"><legend>Education details</legend><div class="form-grid">
            <div><label for="education_grade">Grade / class / course year (required)</label><input id="education_grade" name="education_grade" maxlength="60" value="<?= escapeHtml($values['education_grade']) ?>" data-request-required></div>
            <div><label for="school_name">School / college name (required)</label><input id="school_name" name="school_name" maxlength="150" value="<?= escapeHtml($values['school_name']) ?>" data-request-required></div>
            <div><label for="last_exam_marks">Last examination marks obtained (required)</label><input id="last_exam_marks" name="last_exam_marks" type="number" min="0" max="99999.99" step="0.01" value="<?= escapeHtml($values['last_exam_marks']) ?>" data-request-required></div>
            <div><label for="last_exam_total">Total possible marks (required)</label><input id="last_exam_total" name="last_exam_total" type="number" min="0.01" max="99999.99" step="0.01" value="<?= escapeHtml($values['last_exam_total']) ?>" data-request-required></div>
        </div><label for="exam_result">Last examination result / marksheet (required)</label><input id="exam_result" name="exam_result" type="file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="exam-result-limits" data-request-required><p class="field-hint" id="exam-result-limits">PDF, JPG or PNG. Maximum 5 MB.</p></fieldset>
        <fieldset data-request-types="medical_aid"><legend>Medical aid details</legend>
            <label for="medical_title">Medical request title (required)</label><input id="medical_title" name="medical_title" maxlength="150" value="<?= escapeHtml($values['medical_title']) ?>" data-request-required>
            <label for="medical_details">Private medical details for review (required)</label><textarea id="medical_details" name="medical_details" rows="5" maxlength="10000" data-request-required><?= escapeHtml($values['medical_details']) ?></textarea>
            <label for="due_date">Due date (optional)</label><input id="due_date" name="due_date" type="date" min="<?= $today ?>" value="<?= escapeHtml($values['due_date']) ?>">
            <p id="request-medical-limits" class="field-hint">PDF, JPG or PNG. Up to 5 MB per file, 10 files and 20 MB in total. The first three groups are required.</p>
            <?php foreach (medicalDocumentCategories() as $documentType => $label): ?><label for="<?= $documentType ?>"><?= escapeHtml($label) ?><?= $documentType === 'other' ? ' (optional)' : ' (required)' ?></label><input id="<?= $documentType ?>" name="<?= $documentType ?>[]" type="file" accept=".pdf,.jpg,.jpeg,.png" multiple aria-describedby="request-medical-limits"<?= $documentType !== 'other' ? ' data-request-required' : '' ?>><?php endforeach; ?>
            <label class="checkbox-label"><input type="checkbox" name="medical_confirmation" value="1"<?= $medicalConfirmed ? ' checked' : '' ?> data-request-required><span>I confirm this request is for medical treatment and I am authorized to submit these documents for review.</span></label>
        </fieldset>
        <fieldset data-request-types="scholarship loan"><legend>Additional document</legend><label for="document">Supporting document (optional)</label><input id="document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="document-limits"><p id="document-limits" class="field-hint">PDF, JPG or PNG. Maximum 5 MB.</p></fieldset>
        </div>
        <aside class="formal-guarantors" id="request-guarantors" aria-labelledby="guarantors-heading">
        <div class="formal-section-heading"><h2 id="guarantors-heading">Communal guarantors</h2><span class="status-badge status-pending" data-guarantor-count aria-live="polite">0 / 2 selected</span></div>
        <p class="formal-area"><?= uiIcon('users') ?><?= escapeHtml($memberArea !== '' ? $memberArea : 'Area not assigned') ?></p>
        <fieldset><legend>Selected guarantors</legend><div class="form-grid">
            <?php foreach ([1, 2] as $slot): ?><div><label for="guarantor<?= $slot ?>">Guarantor <?= $slot ?></label><select id="guarantor<?= $slot ?>" name="guarantor<?= $slot ?>_id" required><option value="">Select guarantor</option><?php foreach ($guarantors as $guarantor): ?><option value="<?= (int) $guarantor['id'] ?>"<?= $values['guarantor' . $slot . '_id'] === (string) $guarantor['id'] ? ' selected' : '' ?>><?= escapeHtml($guarantor['full_name'] . ' - ' . ($guarantor['role'] === 'cc_member' ? 'CC member' : 'Volunteer') . ($guarantor['area'] ? ' - ' . $guarantor['area'] : '')) ?></option><?php endforeach; ?></select></div><?php endforeach; ?>
        </div></fieldset>
        <div data-guarantor-directory hidden>
            <label for="guarantor-search">Search eligible guarantors</label><input id="guarantor-search" type="search" placeholder="Name or role" autocomplete="off">
            <div class="guarantor-directory">
            <?php foreach ($guarantors as $guarantor): $roleLabel = $guarantor['role'] === 'cc_member' ? 'CC member' : 'Volunteer'; ?>
                <label class="guarantor-option" data-guarantor-option data-search="<?= escapeHtml(mb_strtolower($guarantor['full_name'] . ' ' . $roleLabel)) ?>">
                    <span class="guarantor-avatar" aria-hidden="true"><?= escapeHtml(mb_strtoupper(mb_substr($guarantor['full_name'], 0, 1))) ?></span>
                    <span class="guarantor-profile"><strong><?= escapeHtml($guarantor['full_name']) ?></strong><small><?= escapeHtml($roleLabel . ' / ' . $guarantor['area']) ?></small></span>
                    <input type="checkbox" value="<?= (int) $guarantor['id'] ?>" data-guarantor-choice aria-label="<?= escapeHtml('Select ' . $guarantor['full_name']) ?>">
                </label>
            <?php endforeach; ?>
            </div>
            <p class="muted" data-guarantor-empty hidden>No matching guarantors.</p>
        </div>
        </aside>
        </div>
        <div class="formal-submit" id="request-submit"><div><h2>Submit for review</h2><p class="muted">Two guarantors required. Office review follows both approvals.</p></div><div class="form-actions"><a href="<?= escapeHtml(appUrl('public/my_requests.php')) ?>">Cancel</a><button type="submit"<?= !$available || count($guarantors) < 2 ? ' disabled' : '' ?>><?= uiIcon('file-plus') ?>Submit request</button></div></div>
    </form>
</section>
<script src="<?= escapeHtml(appUrl('assets/js/member_request.js?v=2')) ?>" defer></script>
<?php pageFooter(); ?>
