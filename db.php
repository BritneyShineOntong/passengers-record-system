<?php
// ── Database connection ────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'passengers_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    http_response_code(503);
    die('Service unavailable.');
}
$conn->set_charset('utf8mb4');
