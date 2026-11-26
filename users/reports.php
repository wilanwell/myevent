<?php
/**
 * MyEvent - Organiser: reports for the events you organise.
 */
require_once __DIR__ . '/../config.php';

require_login();

$rf = report_filter($_GET, current_user_id()); // always limited to your own events

if (($_GET['export'] ?? '') === 'csv') {
    report_export_csv(report_events($conn, $rf), $rf, 'MyEvent_my_events_report');
}

$rp_organisers = [];
$rp_events_url = 'manage_participants.php?event_id=';
$rp_intro      = __('Registrations, attendance and ratings for the events you organise.');

$page_title = __('Reports');
$nav_active = 'reports';
include ROOT_PATH . '/include/layout/app_start.php';
include ROOT_PATH . '/include/partials/report_view.php';
include ROOT_PATH . '/include/layout/app_end.php';
