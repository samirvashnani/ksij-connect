<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$isStaff = false;
if (!empty($_SESSION['member_id'])) {
    require_once dirname(__DIR__) . '/includes/auth_member.php';
    $identity = requireMember();
} elseif (!empty($_SESSION['staff_id'])) {
    require_once dirname(__DIR__) . '/includes/auth_staff.php';
    $identity = requireRole('admin');
    $isStaff = true;
} else {
    redirectTo('public/login.php');
}
$documentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$documentId) {
    http_response_code(404);
    exit('Document not found.');
}
try {
    $statement = getDb()->prepare('SELECT d.id, d.stored_path, d.mime_type FROM fund_documents d JOIN funds f ON f.id = d.fund_id WHERE d.id = ?' . ($isStaff ? '' : ' AND f.member_id = ?'));
    $statement->execute($isStaff ? [$documentId] : [$documentId, $identity['id']]);
    $document = $statement->fetch();
} catch (Throwable $exception) {
    error_log('Medical document lookup failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The document is temporarily unavailable. Please try again.');
}
if (!$document || !preg_match('#^uploads/fund_documents/[a-f0-9]{32}\.(pdf|jpg|png)$#D', $document['stored_path'])) {
    http_response_code(404);
    exit('Document not found.');
}
$path = dirname(__DIR__) . '/' . $document['stored_path'];
$extension = pathinfo($path, PATHINFO_EXTENSION);
$mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'][$extension];
if ($document['mime_type'] !== $mime || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Document not found.');
}
header('Content-Type: ' . $mime);
$disposition = ($_GET['view'] ?? '') === '1' ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disposition . '; filename="medical-document-' . $documentId . '.' . $extension . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
session_write_close();
readfile($path);
