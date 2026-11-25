<?php
/**
 * MyEvent - Deliver queued emails (command line only).
 *
 *   php tools/send_mail.php    queue reminders for events starting within 24 hours,
 *                              then send what is due (up to 50) and exit
 *
 * Schedule every 5 minutes (see DEPLOY.md). Safe to run concurrently: rows are claimed
 * before sending, so an email is never delivered twice.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config.php';

printf("Reminders queued: %d\n", queue_event_reminders($conn));

$stats = process_mail_queue($conn);
printf("Sent: %d  Retrying later: %d  Failed: %d\n", $stats['sent'], $stats['retry'], $stats['failed']);

$stuck = (int) db_value($conn, "SELECT COUNT(*) FROM mail_queue WHERE status = 'failed' AND created_at > NOW() - INTERVAL 1 DAY");
if ($stuck) {
    fwrite(STDERR, "$stuck email(s) failed in the last 24 hours. See mail_queue.last_error.\n");
}
exit($stats['failed'] ? 1 : 0);
