<?php
/**
 * MyEvent - Restore the public demo to its starting state (command line only).
 *
 *   php tools/demo_reset.php --yes
 *
 * ERASES EVERY ROW in the configured database and the posters and profile photos those rows used,
 * then loads database/seed.sql and database/demo.sql. It refuses to run unless APP_DEMO=true,
 * so it can never wipe a real site. Run it nightly from cron on the demo server (DEPLOY.md).
 * Needs only SELECT/INSERT/DELETE rights: the tables themselves are kept.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config.php';

if (!DEMO_MODE) {
    fwrite(STDERR, "Refusing to reset: APP_DEMO is not true in .env. This tool erases all data.\n");
    exit(1);
}
if (!in_array('--yes', $argv, true)) {
    fwrite(STDERR, "This erases ALL data in database '" . env('DB_DATABASE', 'myevent') . "' and the uploads it uses.\n"
        . "Run again with --yes to continue.\n");
    exit(1);
}

$result = demo_reset($conn);

echo 'Tables emptied:   ' . $result['tables'] . "\n";
echo 'Demo data loaded: ' . $result['events'] . ' events, ' . $result['users'] . ' users, '
    . $result['registrations'] . " registrations\n";
echo 'Uploads removed:  ' . $result['uploads'] . "\n";
