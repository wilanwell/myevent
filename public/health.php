<?php
/**
 * MyEvent - Health check for uptime monitors (UptimeRobot, Better Stack, a load balancer...).
 * Returns 200 "ok" when PHP and the database respond, 503 otherwise. Reveals nothing else.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

try {
    require __DIR__ . '/../config.php';
    $ok = (int) db_value($conn, 'SELECT 1') === 1;
} catch (Throwable $ex) {
    $ok = false;
}

http_response_code($ok ? 200 : 503);
echo $ok ? "ok\n" : "unavailable\n";
