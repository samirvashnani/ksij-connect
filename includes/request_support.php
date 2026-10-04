<?php
require_once __DIR__ . '/fund_documents.php';

function requestSingleUpload($upload, string $type, bool $required): array
{
    $label = $type === 'exam_result' ? 'Last examination result' : 'Supporting document';
    $result = ['documents' => [], 'errors' => []];
    if ($upload === null || (is_array($upload) && ($upload['error'] ?? null) === UPLOAD_ERR_NO_FILE)) {
        if ($required) { $result['errors'][] = 'Upload your last examination result.'; }
        return $result;
    }
    if (!is_array($upload) || ($upload['error'] ?? null) !== UPLOAD_ERR_OK
        || !is_string($upload['tmp_name'] ?? null) || !is_string($upload['name'] ?? null)
        || !is_uploaded_file($upload['tmp_name']) || !preg_match('//u', $upload['name'])) {
        $result['errors'][] = $label . ': choose a valid file again.';
        return $result;
    }
    $size = filesize($upload['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
    if (!$size || $size > 5 * 1024 * 1024 || $extension === null) {
        $result['errors'][] = $label . ': upload a non-empty PDF, JPG or PNG up to 5 MB.';
        return $result;
    }
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $upload['name'])));
    $result['documents'][] = ['type' => $type, 'name' => $name ? mb_substr($name, 0, 255) : 'document.' . $extension,
        'tmp_path' => $upload['tmp_name'], 'mime' => $mime, 'extension' => $extension, 'size' => $size];
    return $result;
}

function storeRequestDocuments(PDO $pdo, int $requestId, array $documents, array &$storedPaths): ?string
{
    if (!$documents) { return null; }
    $root = dirname(__DIR__);
    $directory = $root . '/uploads/request_documents';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create request document storage.');
    }
    $statement = $pdo->prepare('INSERT INTO request_documents (request_id,document_type,original_name,stored_path,mime_type,file_size) VALUES (?,?,?,?,?,?)');
    $firstPath = null;
    foreach ($documents as $document) {
        $relativePath = 'uploads/request_documents/' . bin2hex(random_bytes(16)) . '.' . $document['extension'];
        $absolutePath = $root . '/' . $relativePath;
        if (!move_uploaded_file($document['tmp_path'], $absolutePath)) {
            throw new RuntimeException('Could not store a request document.');
        }
        $storedPaths[] = $absolutePath;
        $statement->execute([$requestId, $document['type'], $document['name'], $relativePath, $document['mime'], $document['size']]);
        $firstPath = $firstPath ?? $relativePath;
    }
    return $firstPath;
}

function canViewRequest(array $request, array $identity, string $recipientType): bool
{
    if ($recipientType === 'member') { return (int) $request['member_id'] === (int) $identity['id']; }
    return $identity['role'] === 'admin' || (($identity['role'] === 'cc_member'
        || ($identity['role'] === 'volunteer' && !empty($identity['is_guarantor_approved'])))
        && in_array((int) $identity['id'], [(int) $request['guarantor1_id'], (int) $request['guarantor2_id']], true));
}
