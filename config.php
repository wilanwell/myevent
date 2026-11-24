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
require_once APP_ROOT . '/include/helpers.php';
require_once APP_ROOT . '/include/mailer.php';

set_exception_handler(function (Throwable $ex) {
    error_log('[' . date('c') . '] ' . get_class($ex) . ': ' . $ex->getMessage()
        . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    $detail = DEBUG_MODE ? $ex->getMessage() . ' (' . basename($ex->getFile()) . ':' . $ex->getLine() . ')'
                         : 'Something went wrong on our side. Please try again later.';
    render_error_page('Unexpected error', $detail);
});

/* --- 3. URLs and paths ---------------------------------------------- */
// APP_URL comes from configuration, never from the (spoofable) Host header.
define('BASE_URL', rtrim(env('APP_URL', 'http://localhost/myevent'), '/') . '/');
define('ROOT_PATH', APP_ROOT);
define('IS_HTTPS', strncmp(BASE_URL, 'https://', 8) === 0);

define('BASE_PATH_ASSETS', BASE_URL . 'assets');
define('BASE_PATH_CSS', BASE_PATH_ASSETS . '/css/');
define('BASE_PATH_IMG', BASE_PATH_ASSETS . '/img/');
define('BASE_PATH_INCLUDE', BASE_URL . 'include/');

/* --- 4. Security headers -------------------------------------------- */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

/* --- 5. Hardened session -------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
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

/* --- 7. CSRF: every POST must carry a valid token ------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
}
