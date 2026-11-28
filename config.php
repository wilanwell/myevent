<?php
/**
 * MyEvent - Global Configuration
 * ------------------------------
 * Loads environment settings from .env, configures error handling,
 * starts a hardened session, connects to the database and enforces
 * CSRF protection on every POST request.
 *
 * Every page must include this file BEFORE producing any output.
 */

define('APP_ROOT', __DIR__);

/* --- 1. Environment (.env) ------------------------------------------ */
(function () {
    $file = APP_ROOT . '/.env';
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
})();

function env(string $key, $default = null)
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

define('APP_NAME', env('APP_NAME', 'MyEvent'));

// One time zone for PHP and MySQL, so "today" means the same thing in both.
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Kuala_Lumpur'));
define('DEBUG_MODE', filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));

/* --- 2. Error handling ---------------------------------------------- */
error_reporting(E_ALL);
ini_set('display_errors', DEBUG_MODE ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(APP_ROOT . '/storage/logs')) {
    @mkdir(APP_ROOT . '/storage/logs', 0755, true);
}
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

require_once APP_ROOT . '/include/security.php';
require_once APP_ROOT . '/include/i18n.php';
require_once APP_ROOT . '/include/helpers.php';
require_once APP_ROOT . '/include/mailer.php';
require_once APP_ROOT . '/include/notify.php';
require_once APP_ROOT . '/include/waitlist.php';
require_once APP_ROOT . '/include/reports.php';
require_once APP_ROOT . '/include/demo.php';

set_exception_handler(function (Throwable $ex) {
    error_log('[' . date('c') . '] ' . get_class($ex) . ': ' . $ex->getMessage()
        . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    $detail = DEBUG_MODE ? $ex->getMessage() . ' (' . basename($ex->getFile()) . ':' . $ex->getLine() . ')'
                         : __('Something went wrong on our side. Please try again later.');
    render_error_page(__('Unexpected error'), $detail);
});

/* --- 3. URLs and paths ---------------------------------------------- */
// APP_URL comes from configuration, never from the (spoofable) Host header.
define('BASE_URL', rtrim(env('APP_URL', 'http://localhost/myevent'), '/') . '/');
define('ROOT_PATH', APP_ROOT);                // project root: code, storage, .env (never served)
define('PUBLIC_PATH', APP_ROOT . '/public');  // web root: pages, assets/, uploads/
define('IS_HTTPS', strncmp(BASE_URL, 'https://', 8) === 0);

define('BASE_PATH_ASSETS', BASE_URL . 'assets');
define('BASE_PATH_CSS', BASE_PATH_ASSETS . '/css/');
define('BASE_PATH_IMG', BASE_PATH_ASSETS . '/img/');
define('BASE_PATH_INCLUDE', BASE_URL . 'include/');

/* --- 4. HTTPS and security headers ---------------------------------- */
/** True when this request arrived over HTTPS (optionally trusting a reverse proxy's header). */
function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return filter_var(env('APP_TRUST_PROXY', 'false'), FILTER_VALIDATE_BOOLEAN)
        && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

// When APP_URL is https://, send plain-HTTP visitors to the HTTPS address of the same page.
if (IS_HTTPS && PHP_SAPI !== 'cli' && !request_is_https() && !headers_sent()) {
    $basePath = (string) parse_url(BASE_URL, PHP_URL_PATH);
    $requestPath = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $relative = strpos($requestPath, $basePath) === 0 ? substr($requestPath, strlen($basePath)) : ltrim($requestPath, '/');
    header('Location: ' . BASE_URL . $relative, true, 301);
    exit;
}

// Per-request nonce: only inline <script nonce="..."> blocks written by the app may run.
define('CSP_NONCE', base64_encode(random_bytes(16)));

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'nonce-" . CSP_NONCE . "' https://cdnjs.cloudflare.com; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
        . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
        . "img-src 'self' data: blob:; connect-src 'self'; "
        . "form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
    if (IS_HTTPS) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

/* --- 5. Hardened session -------------------------------------------- */
// Command-line scripts (tools/, tests) have no cookies, so they get no session.
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('MYEVENTSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => IS_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Idle timeout: log the user out after inactivity.
const SESSION_IDLE_SECONDS = 7200; // 2 hours
if (!empty($_SESSION['loggedin'])) {
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

// Language: the account's choice is copied into the session at login and when it changes.
set_locale(PHP_SAPI === 'cli' ? default_locale() : detect_locale());

/* --- 6. Database ---------------------------------------------------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(
    env('DB_HOST', 'localhost'),
    env('DB_USERNAME', 'root'),
    env('DB_PASSWORD', ''),
    env('DB_DATABASE', 'myevent'),
    (int) env('DB_PORT', 3306)
);
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '" . (new DateTime())->format('P') . "'");

// The account decides, not the session: a deleted account is signed out at once and a
// changed role (an admin demoted or promoted) applies from the very next request.
if (!empty($_SESSION['loggedin']) && isset($_SESSION['user_id'])) {
    $__role = db_value($conn, 'SELECT role FROM users WHERE user_id = ?', 'i', [(int) $_SESSION['user_id']]);
    if ($__role === null) {
        force_logout(__('Your account could not be found. Please log in again.'));
    }
    $_SESSION['role'] = trim((string) $__role);
    unset($__role);
}

/* --- 7. CSRF: every POST must carry a valid token ------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
}
