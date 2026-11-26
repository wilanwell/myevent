<?php
/**
 * MyEvent Admin - Reports across every event, optionally for one organiser.
 */
require_once __DIR__ . '/../config.php';

require_admin();

$rp_organisers = array_column(db_all($conn,
    'SELECT DISTINCT u.user_id, u.name FROM users u JOIN events e ON e.created_by = u.user_id ORDER BY u.name'), 'name', 'user_id');
$organiser = (int) ($_GET['organiser'] ?? 0);
$rf = report_filter($_GET, isset($rp_organisers[$organiser]) ? $organiser : null);

if (($_GET['export'] ?? '') === 'csv') {
    audit($conn, 'report.export', null, null, $rf['label']);
    report_export_csv(report_events($conn, $rf), $rf, 'MyEvent_report');
}

$rp_events_url = 'organizer/manage_participants.php?event_id=';
$rp_intro      = 'Registrations, attendance and ratings across ' . ($rf['organiser'] ? 'one organiser\'s events' : 'every event') . '.';

$page_title = 'Reports';
$nav_active = 'admin_reports';
$nav_area   = 'admin';
include ROOT_PATH . '/include/layout/app_start.php';
include ROOT_PATH . '/include/partials/report_view.php';
include ROOT_PATH . '/include/layout/app_end.php';
