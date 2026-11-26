<?php
/**
 * MyEvent - Download an event as an .ics file (Outlook, Apple Calendar, Google import).
 * Same visibility as the event page: anyone with the link.
 */
require_once __DIR__ . '/../config.php';

$event = db_all($conn,
    'SELECT event_id, event_name, description, venue, event_date, event_time, end_time FROM events WHERE event_id = ?',
    'i', [(int) ($_GET['id'] ?? 0)])[0] ?? null;

if (!$event) {
    http_response_code(404);
    render_error_page(__('Event not found'), __('This event does not exist or has been removed.'));
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . safe_filename($event['event_name'], 'ics') . '"');
header('Cache-Control: no-cache');
echo event_ics($event);
