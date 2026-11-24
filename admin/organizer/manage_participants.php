<?php
/**
 * MyEvent Admin - Review and check in participants of any event.
 */
require_once __DIR__ . '/../../config.php';

require_admin();

$event_id = (int) ($_GET['event_id'] ?? 0);
$pm_event = db_all($conn, 'SELECT event_id, event_name, event_date, max_participants FROM events WHERE event_id = ?', 'i', [$event_id])[0] ?? null;
if (!$pm_event) {
    flash('error', 'Event not found.');
    redirect('admin/events/manage.php');
}

$pm_switcher = [];
$pm_self_url = 'admin/organizer/manage_participants.php';
$page_title  = 'Participants';
$nav_active  = 'admin_events';
$nav_area    = 'admin';
include ROOT_PATH . '/include/participants_manager.php';
