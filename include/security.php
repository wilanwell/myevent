<?php
/**
 * MyEvent - Security helpers
 * --------------------------
 * Loaded by config.php. Provides output escaping, CSRF protection,
 * access-control guards, safe image uploads and flash messages.
 */

/* ------------------------------------------------------------------ */
/* Output escaping                                                     */
/* ------------------------------------------------------------------ */

/** Escape a value for safe output in HTML text or attributes. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Render a notification message safely.
 * Messages are stored as plain text that may contain <strong> markers.
 * Everything is escaped, then only the exact <strong>/</strong> tags are restored.
 */
function render_message(?string $message): string
{
    $safe = e($message);
    $safe = str_replace(['&lt;strong&gt;', '&lt;/strong&gt;'], ['<strong>', '</strong>'], $safe);
    return nl2br($safe);
}

/** Escape a value for use inside a JavaScript string literal. */
function js($value): string
{
    return json_encode((string) $value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

/* ------------------------------------------------------------------ */
/* CSRF protection                                                     */
/* ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to place inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Reject any POST request without a valid CSRF token.
 * Called automatically from config.php for every POST request.
 */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(403);
        render_error_page(
            'Session expired',
            'Your form session has expired or the request could not be verified. Please go back, refresh the page and try again.'
        );
    }
}

/* ------------------------------------------------------------------ */
/* Authentication & authorisation guards                               */
/* ------------------------------------------------------------------ */

function is_logged_in(): bool
{
    return !empty($_SESSION['loggedin']) && isset($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'Admin';
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/** Redirect to the login page unless a user is logged in. */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect('auth/login.php');
    }
}

/** Allow only administrators. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        render_error_page('Access denied', 'You do not have permission to view this page.');
    }
}

/** Only accept POST requests (state-changing endpoints). */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        render_error_page('Method not allowed', 'This action must be submitted from a form.');
    }
}

/** Redirect to a path relative to the application root and stop. */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

/* ------------------------------------------------------------------ */
/* Flash messages                                                      */
/* ------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Returns and clears pending flash messages. */
function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ------------------------------------------------------------------ */
/* Safe image uploads                                                  */
/* ------------------------------------------------------------------ */

const ALLOWED_IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

/**
 * Validate and store an uploaded image.
 *
 * The file type is detected from the file contents (not the name or the
 * browser-supplied MIME), the image must be decodable, and the stored
 * name is random with an extension derived from the detected type.
 *
 * @param array|null $file      Entry from $_FILES
 * @param string     $subdir    Directory relative to ROOT_PATH (e.g. 'uploads')
 * @param string     $prefix    File name prefix
 * @param int        $maxBytes  Maximum size in bytes
 * @return array{0: ?string, 1: ?string} [stored file name, error message]
 *         Both null when no file was submitted.
 */
function upload_image(?array $file, string $subdir, string $prefix = 'img', int $maxBytes = 5 * 1024 * 1024): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'The file could not be uploaded. Please try again.'];
    }
    if ($file['size'] > $maxBytes) {
        return [null, 'The image must be smaller than ' . round($maxBytes / 1048576) . 'MB.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [null, 'Invalid upload.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(ALLOWED_IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return [null, 'Only JPG, PNG, GIF or WEBP images are allowed.'];
    }

    $dir = ROOT_PATH . '/' . trim($subdir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return [null, 'Upload folder is not available.'];
    }

    $name = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . ALLOWED_IMAGE_TYPES[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return [null, 'The file could not be saved.'];
    }
    return [$name, null];
}

/** Delete a previously uploaded file, guarding against path traversal. */
function delete_upload(?string $name, string $subdir): void
{
    if (!$name || basename($name) !== $name) {
        return;
    }
    $path = ROOT_PATH . '/' . trim($subdir, '/') . '/' . $name;
    if (is_file($path)) {
        @unlink($path);
    }
}

/* ------------------------------------------------------------------ */
/* Login throttling                                                    */
/* ------------------------------------------------------------------ */

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_MINUTES = 15;

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** True when this email or IP has too many recent failed logins. */
function login_is_throttled(mysqli $conn, string $email): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE (email = ? OR ip_address = ?)
           AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)'
    );
    $ip = client_ip();
    $stmt->bind_param('ss', $email, $ip);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count >= LOGIN_MAX_ATTEMPTS;
}

function login_record_failure(mysqli $conn, string $email): void
{
    $stmt = $conn->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)');
    $ip = client_ip();
    $stmt->bind_param('ss', $email, $ip);
    $stmt->execute();
    $stmt->close();
}

function login_clear_failures(mysqli $conn, string $email): void
{
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE email = ? OR attempted_at < (NOW() - INTERVAL 1 DAY)');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
}

/* ------------------------------------------------------------------ */
/* Error page                                                          */
/* ------------------------------------------------------------------ */

/** Show a minimal, self-contained error page and stop. */
function render_error_page(string $title, string $message): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    $home = defined('BASE_URL') ? BASE_URL : '/';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' | ' . e(defined('APP_NAME') ? APP_NAME : 'MyEvent') . '</title>'
        . '<style>body{font-family:system-ui,sans-serif;background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
        . '.card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);max-width:440px;text-align:center;border-top:5px solid #ef4444}'
        . 'h1{font-size:1.4rem;margin:0 0 12px;color:#0f172a}p{color:#475569;line-height:1.6}'
        . 'a{display:inline-block;margin-top:16px;padding:10px 22px;background:#4f46e5;color:#fff;border-radius:6px;text-decoration:none}</style>'
        . '</head><body><div class="card"><h1>' . e($title) . '</h1><p>' . e($message) . '</p>'
        . '<a href="javascript:history.back()">Go back</a> <a href="' . e($home) . '">Home</a></div></body></html>';
    exit;
}

/* ------------------------------------------------------------------ */
/* CSV export helpers                                                  */
/* ------------------------------------------------------------------ */

/** Safe file name for Content-Disposition (no quotes, slashes or newlines). */
function safe_filename(string $name, string $ext = 'csv'): string
{
    $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $name);
    $name = trim($name, '_') ?: 'export';
    return substr($name, 0, 80) . '.' . $ext;
}

/** Prevent CSV/formula injection when the file is opened in Excel. */
function csv_row(array $row): array
{
    return array_map(static function ($v) {
        $v = (string) ($v ?? '');
        return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
    }, array_values($row));
}
