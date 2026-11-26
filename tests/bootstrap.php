<?php
/**
 * PHPUnit bootstrap.
 *
 * Tests never touch your real data: they run against a separate database
 * (DB_DATABASE, default "myevent_test") that is dropped and rebuilt from
 * database/schema.sql at the start of every run. Its name must end in "_test".
 *
 * Settings come from real environment variables first (CI), then .env
 * (DB_HOST / DB_USERNAME / DB_PASSWORD are reused from it), then these defaults.
 */

$defaults = [
    'APP_URL'      => 'http://localhost/myevent',
    'APP_DEBUG'    => 'true',
    'APP_TIMEZONE' => 'Asia/Kuala_Lumpur',
    'APP_KEY'      => str_repeat('0123456789abcdef', 4), // fixed test key, never used outside tests
    'DB_DATABASE'  => 'myevent_test',
    'MAIL_DRIVER'  => 'log',
    'MAIL_FROM'    => 'no-reply@myevent.example',
    'MAIL_LOG_DIR' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'myevent-test-mail',
];
foreach ($defaults as $key => $value) {
    if (getenv($key) === false) {
        putenv("$key=$value");
    }
}

// The settings that must not leak in from a developer's .env. Demo mode is on so its guards and
// demo_reset() are tested; the reset only ever sees this throwaway _test database.
foreach (['MAIL_DRIVER' => 'log', 'SMTP_HOST' => '', 'APP_DEMO' => 'true'] as $key => $value) {
    putenv("$key=$value");
}

$dbName = (string) getenv('DB_DATABASE');
if (!preg_match('/^[A-Za-z0-9_]+_test$/', $dbName)) {
    fwrite(STDERR, "Refusing to run: DB_DATABASE must end in _test (got '$dbName').\n");
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Support/TestDatabase.php';

// Build a clean test database before config.php connects to it.
MyEvent\Tests\Support\TestDatabase::rebuild($dbName);

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];

require_once __DIR__ . '/../config.php';

// PHPUnit includes this file inside a method, so publish the connection explicitly.
$GLOBALS['conn'] = $conn;

// Leave no test emails behind in the temp folder.
register_shutdown_function([MyEvent\Tests\Support\MailSpy::class, 'clear']);

// Expected errors raised by tests (failed SMTP sends etc.) go to a temp file, not storage/logs.
ini_set('error_log', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'myevent-test-php-error.log');
