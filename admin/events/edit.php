<?php
/**
 * MyEvent Admin - Edit any event.
 */
require_once __DIR__ . '/../../config.php';

require_admin();

$event_id = (int) ($_GET['id'] ?? 0);
$editor_event = db_all($conn, 'SELECT * FROM events WHERE event_id = ?', 'i', [$event_id])[0] ?? null;
if (!$editor_event) {
    flash('error', 'Event not found.');
    redirect('admin/events/manage.php');
}

$editor_redirect = 'admin/events/manage.php';
$editor_cancel   = 'manage.php';
$page_title      = 'Edit event';
$nav_active      = 'admin_events';
$nav_area        = 'admin';
include ROOT_PATH . '/include/event_editor.php';
