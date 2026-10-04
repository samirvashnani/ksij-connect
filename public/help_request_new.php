<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$member = requireMember();
require_once dirname(__DIR__) . '/includes/request_eligibility.php';
requireRequestEligibility($member);
$categories = ['elderly_help' => 'Elderly help', 'urgent_medical' => 'Urgent medical', 'other' => 'Other'];
$category = '';
$description = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $category = is_string($_POST['category'] ?? null) ? trim($_POST['category']) : '';
    $description = is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '';
    $submissionToken = $_POST['submission_token'] ?? '';
    if (!is_string($submissionToken) || empty($_SESSION['help_submission_token']) || !hash_equals($_SESSION['help_submission_token'], $submissionToken)) {
        $errors[] = 'This form has expired or was already submitted. Reload the page before submitting again.';
    }
    if (!isset($categories[$category])) {
        $errors[] = 'Choose a help category.';
    }
    if ($description === '' || mb_strlen($description) > 10000) {
        $errors[] = 'Enter a description of up to 10,000 characters.';
    }
    if (!$errors) {
        $pdo = getDb();
        try {
            $pdo->beginTransaction();
            lockRequestEligibleMember($pdo, (int) $member['id']);
            $statement = getDb()->prepare("INSERT INTO help_requests (member_id, category, description, status) VALUES (?, ?, ?, 'open')");
            $statement->execute([(int) $member['id'], $category, $description]);
            $helpRequestId = (int) getDb()->lastInsertId();
            $pdo->commit();
            unset($_SESSION['help_submission_token']);
            $_SESSION['help_success'] = 'Help request #' . $helpRequestId . ' submitted successfully.';
            redirectTo('public/help_requests_list.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Help request submission failed: ' . $exception->getMessage());
            $errors[] = $exception instanceof DomainException ? $exception->getMessage() : 'Your help request could not be saved. Please try again.';
        }
    }
}
if (empty($_SESSION['help_submission_token'])) {
    $_SESSION['help_submission_token'] = bin2hex(random_bytes(32));
}
pageHeader('New help request', $member);
?>
<section class="workspace">
    <div class="page-heading"><div><p class="eyebrow">COMMUNITY HELP</p><h1>New help request</h1></div><a href="<?= escapeHtml(appUrl('public/help_requests_list.php')) ?>">Help requests</a></div>
    <?php foreach ($errors as $error): showError($error); endforeach; ?>
    <form class="request-form" method="post">
        <?php csrfField(); ?>
        <input type="hidden" name="submission_token" value="<?= escapeHtml($_SESSION['help_submission_token']) ?>">
        <label for="category">Category</label>
        <select id="category" name="category" required><option value="">Select category</option><?php foreach ($categories as $value => $label): ?><option value="<?= $value ?>"<?= $category === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="6" maxlength="10000" required><?= escapeHtml($description) ?></textarea>
        <div class="form-actions"><button type="submit">Submit help request</button><a href="<?= escapeHtml(appUrl('public/help_requests_list.php')) ?>">Cancel</a></div>
    </form>
</section>
<?php pageFooter(); ?>
