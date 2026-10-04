<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/request_support.php';
header('Cache-Control: private, no-store');
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
if (!$request || !canViewRequest($request, $identity, $recipientType)) {
    http_response_code(404);
    exit('Document not found.');
}
$documentId = null;
if (isset($_GET['document_id'])) {
    $documentId = filter_var($_GET['document_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$documentId) { http_response_code(404); exit('Document not found.'); }
    try {
        $statement = getDb()->prepare('SELECT stored_path FROM request_documents WHERE id = ? AND request_id = ?');
        $statement->execute([$documentId, $requestId]);
        $storedPath = $statement->fetchColumn();
    } catch (Throwable $exception) {
        error_log('Request attachment lookup failed: ' . get_class($exception));
        http_response_code(503); exit('The document is temporarily unavailable. Please try again.');
    }
} else { $storedPath = $request['document_path']; }
if (!is_string($storedPath) || !preg_match('#^uploads/request_documents/[a-f0-9]{32}\.(pdf|jpg|png)$#D', $storedPath)) {
    http_response_code(404);
    exit('Document not found.');
}
$path = dirname(__DIR__) . '/' . $storedPath;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Document not found.');
}
$extension = pathinfo($path, PATHINFO_EXTENSION);
header('Content-Type: ' . ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'][$extension]);
$disposition = ($_GET['view'] ?? '') === '1' ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disposition . '; filename="request-' . $requestId . ($documentId ? '-document-' . $documentId : '') . '.' . $extension . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
session_write_close();
readfile($path);
