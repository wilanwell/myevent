<?php
/**
 * MyEvent - Demo mode (APP_DEMO=true in .env), for a public showcase.
 *
 * - The login page offers one-click sign-in as the seeded participant, organiser and admin.
 * - Signed-in pages show a thin "demo" band.
 * - The demo accounts cannot be locked out: no password changes, and admins cannot edit or delete them.
 * - tools/demo_reset.php restores the demo data (run it nightly from cron).
 *
 * With APP_DEMO=false (the default) none of this is visible and nothing behaves differently.
 */

define('DEMO_MODE', filter_var(env('APP_DEMO', 'false'), FILTER_VALIDATE_BOOLEAN));

/** Demo role => seeded account email (see database/seed.sql). */
const DEMO_ACCOUNTS = [
    'participant' => 'student@myevent.test',
    'organiser'   => 'organiser@myevent.test',
    'admin'       => 'admin@myevent.test',
];

/** Is $email one of the protected demo accounts (only while demo mode is on)? */
function is_demo_account(?string $email): bool
{
    return DEMO_MODE && $email !== null && in_array(strtolower(trim($email)), DEMO_ACCOUNTS, true);
}

/** Is user $userId one of the protected demo accounts (only while demo mode is on)? */
function is_demo_user(mysqli $conn, ?int $userId): bool
{
    if (!DEMO_MODE || !$userId) {
        return false;
    }
    return is_demo_account((string) db_value($conn, 'SELECT email FROM users WHERE user_id = ?', 'i', [$userId]));
}

/**
 * Restore the demo: empty every table, load seed.sql + demo.sql, and delete the uploaded posters and
 * profile photos THIS database used (files of another install sharing the folder are left alone).
 * DESTRUCTIVE. Refuses unless demo mode is on. Needs only DELETE/INSERT rights (tables are kept).
 * $uploadsRoot is the folder holding uploads/ (tests pass a temporary one).
 * Returns ['tables' => n, 'events' => n, 'users' => n, 'registrations' => n, 'uploads' => n].
 */
function demo_reset(mysqli $conn, string $uploadsRoot = PUBLIC_PATH): array
{
    if (!DEMO_MODE) {
        throw new RuntimeException('Refusing to reset: APP_DEMO is not true.');
    }

    // Files this database points at; collected before the rows go.
    $used = [
        POSTER_DIR => array_column(db_all($conn, 'SELECT poster_path FROM events WHERE poster_path IS NOT NULL'), 'poster_path'),
        AVATAR_DIR => array_column(db_all($conn, 'SELECT profile_pic FROM users WHERE profile_pic IS NOT NULL'), 'profile_pic'),
    ];

    $tables = array_map(static fn(array $r) => $r[0], $conn->query('SHOW TABLES')->fetch_all());
    $conn->query('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $conn->query('DELETE FROM `' . str_replace('`', '``', $table) . '`');
    }
    $conn->query('SET FOREIGN_KEY_CHECKS = 1');

    foreach (['seed.sql', 'demo.sql'] as $file) {
        $conn->multi_query((string) file_get_contents(ROOT_PATH . '/database/' . $file));
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
    }

    $uploads = 0;
    foreach ($used as $dir => $names) {
        foreach (array_unique($names) as $name) {
            $base = basename((string) $name);   // never leave the folder, never touch .gitkeep / .htaccess
            $file = $uploadsRoot . '/' . $dir . '/' . $base;
            if ($base !== '' && $base[0] !== '.' && is_file($file) && @unlink($file)) {
                $uploads++;
            }
        }
    }

    return [
        'tables'        => count($tables),
        'events'        => (int) db_value($conn, 'SELECT COUNT(*) FROM events'),
        'users'         => (int) db_value($conn, 'SELECT COUNT(*) FROM users'),
        'registrations' => (int) db_value($conn, 'SELECT COUNT(*) FROM registrations'),
        'uploads'       => $uploads,
    ];
}

/** Labels for the login page buttons, in display order. */
function demo_role_labels(): array
{
    return [
        'participant' => [__('Participant'), 'fa-user',            __('Browse, register and get tickets')],
        'organiser'   => [__('Organiser'),   'fa-calendar-check',  __('Approve, check in and see reports')],
        'admin'       => [__('Admin'),       'fa-shield-halved',   __('Manage every event and account')],
    ];
}
