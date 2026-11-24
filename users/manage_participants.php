<?php
/**
 * MyEvent - Organiser: review and check in participants of your own events.
 */
require_once __DIR__ . '/../config.php';

require_login();

$user_id = current_user_id();
$mine = db_all($conn, 'SELECT event_id, event_name FROM events WHERE created_by = ? ORDER BY event_date DESC', 'i', [$user_id]);

if (!$mine) {
    flash('info', 'Create an event first. Its participants will appear on the Participants page.');
    redirect('users/my_events.php');
}

$pm_switcher = array_column($mine, 'event_name', 'event_id');
$event_id = (int) ($_GET['event_id'] ?? array_key_first($pm_switcher));

// Ownership check: organisers may only see and manage their own events.
if (!isset($pm_switcher[$event_id])) {
    flash('error', 'You can only manage participants of events you organise.');
    redirect('users/manage_participants.php');
}

$pm_event    = db_all($conn, 'SELECT event_id, event_name, event_date, max_participants FROM events WHERE event_id = ?', 'i', [$event_id])[0];
$pm_self_url = 'users/manage_participants.php';
$page_title  = 'Participants';
$nav_active  = 'participants';
include ROOT_PATH . '/include/participants_manager.php';
