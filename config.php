<?php
/**
 * MyEvent - Global Configuration File
 * ----------------------------------
 * Purpose: Defines paths, establishes database connection, and handles branding.
 */

// 1. Session Management: Start session to track logins across the system 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Branding: Define the system name for global use 
define('APP_NAME', 'MyEvent');

// --- 3. URL and Path Settings ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' 
             || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

$host = $_SERVER['HTTP_HOST'];
$projectFolder = '/myevent'; 

// Base URL (for web access - links and images)
define('BASE_URL', $protocol . $host . $projectFolder . '/');

// Base Root Path (for server-side file access)
define('ROOT_PATH', rtrim($_SERVER['DOCUMENT_ROOT'] . $projectFolder, '/'));

// --- 4. Common Subpaths (URL-based) ---
define('BASE_PATH_ASSETS', BASE_URL . '/assets');
define('BASE_PATH_CSS', BASE_PATH_ASSETS . '/css/');
define('BASE_PATH_IMG', BASE_PATH_ASSETS . '/img/');
define('BASE_PATH_UPLOADS', BASE_URL . '/uploads/');
define('BASE_PATH_INCLUDE', BASE_URL . '/include/');

// --- 5. Database Connection ---
$databaseHost = 'localhost';
$databaseUsername = 'root';
$databasePassword = '';
$databaseName = 'myevent';

// Initialize connection using MySQLi
$conn = new mysqli($databaseHost, $databaseUsername, $databasePassword, $databaseName);

// Check for connection errors
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set character set for language compatibility
$conn->set_charset("utf8");

// --- 6. Debug Mode (Enabled for Development) ---
define('DEBUG_MODE', true); 

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>