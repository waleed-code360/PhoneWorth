<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    // MySQL stores TIMESTAMP values in UTC internally.
    // Read and write timestamps in Pakistan Standard Time (UTC+05:00).
    $pdo->exec("SET time_zone = '+05:00'");
    date_default_timezone_set('Asia/Karachi');
} catch (PDOException $error) {
    http_response_code(503);
    exit('Database unavailable. Check your MySQL connection and config/database.php.');
}
