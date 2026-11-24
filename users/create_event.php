<?php
/**
 * MyEvent - Create an event (any signed-in user can organise).
 */
require_once __DIR__ . '/../config.php';

require_login();

$editor_event    = null;
$editor_redirect = 'users/my_events.php';
$editor_cancel   = 'my_events.php';
$page_title      = 'Create event';
$nav_active      = 'create_event';
include ROOT_PATH . '/include/event_editor.php';
