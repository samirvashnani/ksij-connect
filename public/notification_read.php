<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use the notifications menu.');
}
checkCsrf();
$recipientType = $_POST['recipient_type'] ?? '';
if ($recipientType === 'member') {
    require_once dirname(__DIR__) . '/includes/auth_member.php';
    requireMember();
    $defaultPage = 'public/member_chat.php';
} elseif ($recipientType === 'staff') {
    require_once dirname(__DIR__) . '/includes/auth_staff.php';
    $staff = requireRole(['volunteer', 'cc_member', 'admin']);
    $defaultPage = $staff['role'] === 'admin' ? 'public/admin/index.php' : 'public/staff_dashboard.php';
} else {
    http_response_code(400);
    exit('Invalid recipient type.');
}
require_once dirname(__DIR__) . '/includes/notification_bell.php';
$notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$notificationId) {
    http_response_code(400);
    exit('Invalid notification.');
}
$returnPage = $_POST['return_page'] ?? $defaultPage;
if (!is_string($returnPage) || !in_array($returnPage, notificationReturnPages(), true)) {
    $returnPage = $defaultPage;
}
if (($recipientType === 'member' && in_array($returnPage, ['public/staff_dashboard.php', 'public/admin/index.php'], true))
    || ($recipientType === 'staff' && $returnPage !== $defaultPage)) {
    $returnPage = $defaultPage;
}
try {
    markNotificationRead($notificationId, $recipientType);
} catch (Throwable $exception) {
    error_log('Notification update failed: ' . $exception->getMessage());
    $_SESSION['notification_error'] = 'The notification could not be marked as read. Please try again.';
}
redirectTo($returnPage);
