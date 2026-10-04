<?php

function medicalDocumentCategories(): array
{
    return [
        'medical_report' => 'Medical reports / diagnosis',
        'treatment_plan' => "Doctor's treatment recommendation / prescription",
        'cost_estimate' => 'Hospital cost estimates / bills',
        'other' => 'Additional medical documents',
    ];
}

function medicalFundEligibilitySql(): string
{
    return "funds.purpose = 'medical' AND (SELECT COUNT(DISTINCT fd.document_type) FROM fund_documents fd WHERE fd.fund_id = funds.id AND fd.document_type IN ('medical_report', 'treatment_plan', 'cost_estimate')) = 3";
}

function validateMedicalFundUploads(array $uploads): array
{
    $documents = [];
    $errors = [];
    $totalSize = 0;
    $count = 0;
    $allowedMime = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    foreach (medicalDocumentCategories() as $type => $label) {
        $validCount = 0;
        $upload = $uploads[$type] ?? null;
        if ($upload !== null && (!is_array($upload) || !is_array($upload['error'] ?? null) || !is_array($upload['tmp_name'] ?? null) || !is_array($upload['name'] ?? null))) {
            $errors[] = $label . ': invalid upload.';
        } elseif ($upload !== null) {
            foreach ($upload['error'] as $index => $uploadError) {
                if ($uploadError === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $count++;
                $temporaryPath = $upload['tmp_name'][$index] ?? null;
                $originalName = $upload['name'][$index] ?? null;
                if ($uploadError !== UPLOAD_ERR_OK || !is_string($temporaryPath) || !is_string($originalName) || !is_uploaded_file($temporaryPath)) {
                    $errors[] = $label . ': a file could not be uploaded. Select it again.';
                    continue;
                }
                $size = filesize($temporaryPath);
                if (!$size || $size > 5 * 1024 * 1024) {
                    $errors[] = $label . ': each file must be non-empty and no larger than 5 MB.';
                    continue;
                }
                $totalSize += $size;
                $mime = $fileInfo->file($temporaryPath);
                if (!isset($allowedMime[$mime])) {
                    $errors[] = $label . ': only PDF, JPG and PNG files are accepted.';
                    continue;
                }
                $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $originalName)));
                $name = $name ? mb_substr($name, 0, 255) : 'document.' . $allowedMime[$mime];
                $documents[] = ['type' => $type, 'name' => $name, 'tmp_path' => $temporaryPath, 'mime' => $mime, 'extension' => $allowedMime[$mime], 'size' => $size];
                $validCount++;
            }
        }
        if ($type !== 'other' && $validCount === 0) {
            $errors[] = 'Upload at least one file for ' . $label . '.';
        }
    }
    if ($count > 10) {
        $errors[] = 'Upload no more than 10 files in total.';
    }
    if ($totalSize > 20 * 1024 * 1024) {
        $errors[] = 'The combined document size must not exceed 20 MB.';
    }
    return ['documents' => $documents, 'errors' => $errors];
}

function storeMedicalFundDocuments(PDO $pdo, int $fundId, array $documents, array &$storedPaths): void
{
    $root = dirname(__DIR__);
    $directory = $root . '/uploads/fund_documents';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create medical document storage.');
    }
    $statement = $pdo->prepare('INSERT INTO fund_documents (fund_id, document_type, original_name, stored_path, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($documents as $document) {
        $relativePath = 'uploads/fund_documents/' . bin2hex(random_bytes(16)) . '.' . $document['extension'];
        $absolutePath = $root . '/' . $relativePath;
        if (!move_uploaded_file($document['tmp_path'], $absolutePath)) {
            throw new RuntimeException('Could not store a medical document.');
        }
        $storedPaths[] = $absolutePath;
        $statement->execute([$fundId, $document['type'], $document['name'], $relativePath, $document['mime'], $document['size']]);
    }
}
