<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (!empty($_SESSION['member_id'])) {
    require_once dirname(__DIR__) . '/includes/auth_member.php';
    $identity = requireMember();
    $recipientType = 'member';
} elseif (!empty($_SESSION['staff_id'])) {
    require_once dirname(__DIR__) . '/includes/auth_staff.php';
    $identity = requireRole(['admin', 'volunteer', 'cc_member']);
    $recipientType = 'staff';
} else {
    redirectTo('public/login.php');
}
$requestId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$requestId) {
    http_response_code(404);
    exit('Document not found.');
}
try {
    $statement = getDb()->prepare('SELECT member_id, guarantor1_id, guarantor2_id, document_path FROM requests WHERE id = ?');
    $statement->execute([$requestId]);
    $request = $statement->fetch();
} catch (Throwable $exception) {
    error_log('Request document lookup failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The document is temporarily unavailable. Please try again.');
}
$allowed = $request && ($recipientType === 'member'
    ? (int) $request['member_id'] === (int) $identity['id']
    : ($identity['role'] === 'admin' || (($identity['role'] === 'cc_member' || (int) $identity['is_guarantor_approved'] === 1) && in_array((int) $identity['id'], [(int) $request['guarantor1_id'], (int) $request['guarantor2_id']], true))));
if (!$allowed || !preg_match('#^uploads/request_documents/[a-f0-9]{32}\.(pdf|jpg|png)$#D', $request['document_path'] ?? '')) {
    http_response_code(404);
    exit('Document not found.');
}
$path = dirname(__DIR__) . '/' . $request['document_path'];
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Document not found.');
}
$extension = pathinfo($path, PATHINFO_EXTENSION);
header('Content-Type: ' . ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'][$extension]);
header('Content-Disposition: attachment; filename="request-' . $requestId . '.' . $extension . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
session_write_close();
readfile($path);
