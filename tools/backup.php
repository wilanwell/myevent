<?php
/**
 * MyEvent - Database and uploads backup (command line only).
 *
 *   php tools/backup.php            database dump + uploads archive
 *   php tools/backup.php --db-only  database dump only
 *
 * Writes storage/backups/myevent_<timestamp>.sql.gz (and _uploads.tar.gz) and keeps the
 * newest BACKUP_KEEP sets (default 14). Settings come from .env:
 *   MYSQLDUMP_PATH  path to mysqldump (default "mysqldump"; XAMPP: C:/xampp/mysql/bin/mysqldump.exe)
 *   BACKUP_KEEP     how many backups to keep
 * Schedule it daily with cron or Windows Task Scheduler (see DEPLOY.md).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config.php';

$dir = ROOT_PATH . '/storage/backups';
if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
    fwrite(STDERR, "Cannot create $dir\n");
    exit(1);
}

$stamp = date('Ymd_His');
$dbFile = "$dir/myevent_{$stamp}.sql.gz";

/* ---------- Database ---------- */
$cmd = [
    env('MYSQLDUMP_PATH', 'mysqldump'),
    '--host=' . env('DB_HOST', 'localhost'),
    '--port=' . env('DB_PORT', '3306'),
    '--user=' . env('DB_USERNAME', 'root'),
    '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces',
    '--default-character-set=utf8mb4',
    env('DB_DATABASE', 'myevent'),
];
// The password goes through the environment, not the command line, so other users
// on the server cannot read it from the process list.
$envVars = getenv();
$envVars['MYSQL_PWD'] = (string) env('DB_PASSWORD', '');

$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $envVars);
if (!is_resource($proc)) {
    fwrite(STDERR, "Could not start mysqldump. Set MYSQLDUMP_PATH in .env.\n");
    exit(1);
}
$gz = gzopen($dbFile, 'wb6');
$bytes = 0;
while (!feof($pipes[1])) {
    $chunk = fread($pipes[1], 1 << 16);
    if ($chunk === false) {
        break;
    }
    $bytes += strlen($chunk);
    gzwrite($gz, $chunk);
}
gzclose($gz);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($proc);

if ($status !== 0 || $bytes === 0) {
    @unlink($dbFile);
    fwrite(STDERR, "mysqldump failed (exit $status): " . trim($stderr) . "\n");
    exit(1);
}
printf("Database: %s (%s KB uncompressed)\n", basename($dbFile), number_format($bytes / 1024));

/* ---------- Uploads ---------- */
if (!in_array('--db-only', $argv, true)) {
    $tar = "$dir/myevent_{$stamp}_uploads.tar";
    try {
        $archive = new PharData($tar);
        $archive->buildFromDirectory(ROOT_PATH . '/uploads', '/\.(jpe?g|png|gif|webp)$/i');
        $archive->compress(Phar::GZ);
        unset($archive);
        @unlink($tar);
        printf("Uploads:  %s\n", basename($tar) . '.gz');
    } catch (Throwable $ex) {
        @unlink($tar);
        fwrite(STDERR, 'Uploads archive skipped: ' . $ex->getMessage() . "\n");
    }
}

/* ---------- Rotation ---------- */
$keep = max(1, (int) env('BACKUP_KEEP', 14));
$sets = glob("$dir/myevent_*.sql.gz") ?: [];
rsort($sets);
foreach (array_slice($sets, $keep) as $old) {
    $prefix = substr($old, 0, -strlen('.sql.gz'));
    @unlink($old);
    @unlink($prefix . '_uploads.tar.gz');
    echo 'Removed old backup: ' . basename($old) . "\n";
}
echo "Done.\n";
