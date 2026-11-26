<?php
/**
 * MyEvent - Housekeeping (command line only). Safe to run daily.
 *
 *   php tools/maintenance.php                    clean up and report
 *   php tools/maintenance.php --delete-orphans   also delete uploaded files no record uses
 *
 * - Removes login attempts older than a day and spent/expired password-reset tokens.
 * - Promotes waitlists whose events have free places (e.g. after manual DB edits).
 * - Rotates storage/logs/php-error.log when it grows past 5 MB.
 * - Deletes development mails in storage/mail older than 30 days.
 * - Reports (or with --delete-orphans removes) uploaded images that no event or profile uses.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config.php';

$affected = static function (string $sql) use ($conn): int {
    $conn->query($sql);
    return $conn->affected_rows;
};

echo "Login attempts removed:   " . $affected('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY') . "\n";
echo "Reset tokens removed:     " . $affected('DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 7 DAY OR used_at < NOW() - INTERVAL 7 DAY') . "\n";

$promoted = 0;
foreach (array_column(db_all($conn, 'SELECT DISTINCT event_id FROM waitlist'), 'event_id') as $eventId) {
    $promoted += promote_waitlist($conn, (int) $eventId);
}
echo "Waitlist promotions:      $promoted\n";

$log = ROOT_PATH . '/storage/logs/php-error.log';
if (is_file($log) && filesize($log) > 5 * 1024 * 1024) {
    rename($log, $log . '.' . date('Ymd_His'));
    touch($log);
    $rotated = glob($log . '.*') ?: [];
    rsort($rotated);
    foreach (array_slice($rotated, 5) as $old) {
        @unlink($old);
    }
    echo "Error log rotated.\n";
}

$mails = 0;
foreach (glob(ROOT_PATH . '/storage/mail/*.{eml,txt}', GLOB_BRACE) ?: [] as $file) {
    if (filemtime($file) < time() - 30 * 86400 && @unlink($file)) {
        $mails++;
    }
}
echo "Old development mails:    $mails\n";

// Orphaned uploads: files older than a day that no row references.
$used = array_flip(array_merge(
    array_column(db_all($conn, 'SELECT poster_path FROM events WHERE poster_path IS NOT NULL'), 'poster_path'),
    array_column(db_all($conn, 'SELECT profile_pic FROM users WHERE profile_pic IS NOT NULL'), 'profile_pic')
));
$delete = in_array('--delete-orphans', $argv, true);
$orphans = 0;
foreach ([POSTER_DIR, AVATAR_DIR] as $sub) {
    foreach (glob(PUBLIC_PATH . '/' . $sub . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $file) {
        if (!isset($used[basename($file)]) && filemtime($file) < time() - 86400) {
            $orphans++;
            echo ($delete ? 'Deleted orphan: ' : 'Orphan (not deleted): ') . $sub . '/' . basename($file) . "\n";
            if ($delete) {
                @unlink($file);
            }
        }
    }
}
echo "Orphaned uploads:         $orphans" . ($orphans && !$delete ? ' (run with --delete-orphans to remove)' : '') . "\n";
