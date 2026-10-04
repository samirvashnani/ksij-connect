<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    require_once dirname(__DIR__) . '/includes/db.php';
    $requiredTables = ['members', 'otp_verification', 'staff_users', 'requests', 'help_requests', 'funds', 'donations', 'wallet_transactions', 'notifications'];
    $tables = getDb()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $missingTables = array_diff($requiredTables, $tables);
    if ($missingTables) {
        fwrite(STDERR, 'Missing tables: ' . implode(', ', $missingTables) . PHP_EOL);
        exit(1);
    }
    echo 'Database connection OK. Core tables are present.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Setup check failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
