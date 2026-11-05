<?php
/*
 * Database connection
 * XAMPP default: host localhost, user root, empty password.
 */
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'ewaste_repair_cafe';
$DB_PORT = (int) (getenv('DB_PORT') ?: 3306);

// Make mysqli throw exceptions, so every SQL error can be caught with try / catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    exit('Database connection failed. Please start MySQL in XAMPP and import database/ewaste_repair_cafe.sql.');
}
