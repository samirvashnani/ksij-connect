<?php

$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    throw new RuntimeException('Create config.php from config.example.php.');
}
require_once $configPath;

function getDb(): PDO
{
    static $connection = null;
    if ($connection === null) {
        $port = defined('DB_PORT') ? DB_PORT : 3306;
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $connection = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $connection;
}

$pdo = getDb();
