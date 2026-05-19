<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

define('APP_NAME', 'College Faculty Management System');
define('APP_TAGLINE', 'Government-style portal for faculty administration');
define('APP_SECRET', 'college-faculty-management-secret');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'college_faculty_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('UPLOAD_PATH', __DIR__ . '/uploads');

$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = preg_replace('#/(admin|faculty)$#', '', $scriptDirectory);
define('BASE_URL', $baseUrl === '/' ? '' : rtrim((string) $baseUrl, '/'));

$dbError = null;

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    $pdo = null;
    $dbError = $exception->getMessage();
}

require_once __DIR__ . '/includes/functions.php';
