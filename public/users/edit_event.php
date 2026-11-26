<?php
/**
 * MyEvent - Edit an event the current user organises.
 */
require_once __DIR__ . '/../../config.php';

require_login();

$event_id = (int) ($_GET['id'] ?? 0);
$editor_event = db_all($conn, 'SELECT * FROM events WHERE event_id = ? AND created_by = ?', 'ii', [$event_id, current_user_id()])[0] ?? null;
if (!$editor_event) {
    flash('error', __('You can only edit events you organise.'));
    redirect('users/my_events.php');
}

$editor_redirect = 'users/my_events.php';
$editor_cancel   = 'my_events.php';
$page_title      = __('Edit event');
$nav_active      = 'my_events';
include ROOT_PATH . '/include/event_editor.php';
