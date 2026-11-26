<?php
/**
 * MyEvent - Pre-launch checklist (command line only).
 *
 *   php tools/check_production.php
 *
 * Prints PASS / WARN / FAIL for each item and exits with status 1 if anything FAILs,
 * so it can gate a deploy script.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config.php';

$results = ['PASS' => 0, 'WARN' => 0, 'FAIL' => 0];
function report(string $status, string $label, string $hint = ''): void
{
    global $results;
    $results[$status]++;
    printf("  %-4s  %s%s\n", $status, $label, $hint !== '' ? "\n          -> $hint" : '');
}
function section(string $title): void
{
    echo "\n$title\n";
}

$host = (string) parse_url(BASE_URL, PHP_URL_HOST);
$isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.localhost');

section('PHP');
version_compare(PHP_VERSION, '8.1.0', '>=')
    ? report('PASS', 'PHP ' . PHP_VERSION)
    : report('FAIL', 'PHP ' . PHP_VERSION . ' is too old', 'Use PHP 8.1 or newer.');
foreach (['mysqli', 'mbstring', 'openssl', 'fileinfo', 'zlib', 'phar'] as $ext) {
    extension_loaded($ext) ? report('PASS', "Extension $ext") : report($ext === 'phar' ? 'WARN' : 'FAIL', "Extension $ext is missing");
}
ini_get('display_errors') && ini_get('display_errors') !== '0' && !DEBUG_MODE
    ? report('FAIL', 'display_errors is on in php.ini', 'Set display_errors = Off.')
    : report('PASS', 'Errors are not shown to visitors' . (DEBUG_MODE ? ' (except while APP_DEBUG is on)' : ''));

section('Application settings (.env)');
DEBUG_MODE
    ? report('FAIL', 'APP_DEBUG is true', 'Set APP_DEBUG=false so error details are never shown to visitors.')
    : report('PASS', 'APP_DEBUG is false');
DEMO_MODE
    ? report('WARN', 'APP_DEMO is true: one-click demo sign-in is on',
        'Fine for a public showcase that runs tools/demo_reset.php nightly. Never on a site with real users.')
    : report('PASS', 'APP_DEMO is false');
IS_HTTPS
    ? report('PASS', 'APP_URL uses https (' . BASE_URL . ')')
    : report($isLocal ? 'WARN' : 'FAIL', 'APP_URL is not https (' . BASE_URL . ')', 'Install a certificate (Let\'s Encrypt / AutoSSL) and set APP_URL=https://...');
$key = (string) env('APP_KEY', '');
strlen($key) >= 32
    ? report('PASS', 'APP_KEY is set')
    : report('FAIL', 'APP_KEY is missing or too short', 'php -r "echo bin2hex(random_bytes(32));"');
in_array(date_default_timezone_get(), DateTimeZone::listIdentifiers(), true)
    ? report('PASS', 'Time zone ' . date_default_timezone_get() . ' (MySQL session ' . db_value($conn, 'SELECT @@session.time_zone') . ')')
    : report('FAIL', 'APP_TIMEZONE is not a valid time zone');

section('Database');
$dbUser = (string) env('DB_USERNAME', 'root');
$dbUser === 'root'
    ? report($isLocal ? 'WARN' : 'FAIL', 'Connecting as MySQL root', 'Create a dedicated user: see database/create_app_user.sql.')
    : report('PASS', "Dedicated database user ($dbUser)");
(string) env('DB_PASSWORD', '') === ''
    ? report($isLocal ? 'WARN' : 'FAIL', 'Database password is empty')
    : report('PASS', 'Database password is set');
$schemaOk = db_value($conn, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'waitlist'")
    && db_value($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'end_time'")
    && db_value($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'locale'");
$schemaOk ? report('PASS', 'Schema is up to date') : report('FAIL', 'Schema is behind', 'Apply the files in database/migrations/ in order.');
$demo = (int) db_value($conn, "SELECT COUNT(*) FROM users WHERE email LIKE '%@myevent.test'");
$demo > 0
    ? report($isLocal || DEMO_MODE ? 'WARN' : 'FAIL', "$demo demo account(s) from seed.sql exist",
        DEMO_MODE ? 'Expected on a public demo (APP_DEMO=true).' : 'Delete them or change their passwords before going live.')
    : report('PASS', 'No demo accounts');
$admins = (int) db_value($conn, "SELECT COUNT(*) FROM users WHERE role = 'Admin'");
$admins > 0 ? report('PASS', "$admins administrator account(s)") : report('FAIL', 'No administrator account');

section('Email');
env('MAIL_DRIVER', 'log') === 'smtp' && env('SMTP_HOST', '') !== ''
    ? report('PASS', 'SMTP is configured (' . env('SMTP_HOST') . ')')
    : report($isLocal || DEMO_MODE ? 'WARN' : 'FAIL', 'Emails are only written to storage/mail',
        DEMO_MODE ? 'Expected on a public demo: nothing is ever emailed.' : 'Set MAIL_DRIVER=smtp and the SMTP_* settings, or password resets will not reach anyone.');
filter_var(env('MAIL_FROM', ''), FILTER_VALIDATE_EMAIL) && !str_ends_with((string) env('MAIL_FROM'), '.test')
    ? report('PASS', 'MAIL_FROM is ' . env('MAIL_FROM'))
    : report($isLocal ? 'WARN' : 'FAIL', 'MAIL_FROM is not a real address (' . env('MAIL_FROM', 'unset') . ')');

$stuck = (int) db_value($conn, "SELECT COUNT(*) FROM mail_queue WHERE status = 'pending' AND created_at < NOW() - INTERVAL 30 MINUTE");
$stuck
    ? report('FAIL', "$stuck queued email(s) waiting more than 30 minutes", 'The mail worker is not running: schedule php tools/send_mail.php every 5 minutes.')
    : report('PASS', 'Email queue is moving');
$failed = (int) db_value($conn, "SELECT COUNT(*) FROM mail_queue WHERE status = 'failed' AND created_at > NOW() - INTERVAL 7 DAY");
$failed
    ? report('WARN', "$failed email(s) failed in the last 7 days", 'See mail_queue.last_error (usually a wrong SMTP password or a rejected From address).')
    : report('PASS', 'No failed emails in the last 7 days');

section('Files');
foreach (['storage/logs', 'storage/backups', 'public/' . POSTER_DIR, 'public/' . AVATAR_DIR] as $dir) {
    is_writable(ROOT_PATH . '/' . $dir) ? report('PASS', "$dir is writable") : report('FAIL', "$dir is not writable by PHP");
}
foreach (['.htaccess', 'public/.htaccess', 'public/uploads/.htaccess', 'storage/.htaccess', 'include/.htaccess', 'database/.htaccess', 'tools/.htaccess', 'tests/.htaccess'] as $guard) {
    is_file(ROOT_PATH . '/' . $guard) ? report('PASS', "$guard present") : report('FAIL', "$guard is missing");
}
$latest = glob(ROOT_PATH . '/storage/backups/myevent_*.sql.gz') ?: [];
rsort($latest);
$latest && filemtime($latest[0]) > time() - 2 * 86400
    ? report('PASS', 'Recent backup: ' . basename($latest[0]))
    : report('WARN', 'No backup in the last 2 days', 'Schedule php tools/backup.php daily (see DEPLOY.md).');

section('Over HTTP (' . BASE_URL . ')');
$ctx = stream_context_create(['http' => ['method' => 'HEAD', 'ignore_errors' => true, 'timeout' => 5, 'follow_location' => 0],
                              'ssl' => ['verify_peer' => true]]);
foreach (['.env', 'config.php', 'storage/logs/php-error.log', 'include/security.php', 'database/schema.sql', 'tools/backup.php', 'vendor/autoload.php', 'phpunit.xml', 'composer.json'] as $path) {
    $headers = @get_headers(BASE_URL . $path, false, $ctx);
    if (!$headers) {
        report('WARN', "Could not reach $path to test it", 'Run this check on the server, or check the URL by hand.');
        continue;
    }
    preg_match('#HTTP/\S+\s+(\d{3})#', $headers[0], $m);
    in_array($m[1] ?? '', ['403', '404'], true)
        ? report('PASS', "$path is blocked ({$m[1]})")
        : report('FAIL', "$path is reachable over the web (" . ($m[1] ?? '?') . ')', 'Make sure Apache honours .htaccess (AllowOverride All) or block it in the vhost.');
}
$home = @get_headers(BASE_URL, true, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]));
if ($home) {
    $home = array_change_key_case($home, CASE_LOWER);   // HTTP/2 hosts send header names in lower case
    $csp = $home['content-security-policy'] ?? null;
    $csp ? report('PASS', 'Content-Security-Policy header sent') : report('FAIL', 'Content-Security-Policy header missing');
    if (IS_HTTPS) {
        isset($home['strict-transport-security']) ? report('PASS', 'HSTS header sent') : report('FAIL', 'HSTS header missing');
    }
}

printf("\nSummary: %d pass, %d warning, %d fail\n", $results['PASS'], $results['WARN'], $results['FAIL']);
echo $results['FAIL'] ? "NOT ready for production.\n" : ($results['WARN'] ? "Ready, with warnings to review.\n" : "Ready for production.\n");
exit($results['FAIL'] ? 1 : 0);
