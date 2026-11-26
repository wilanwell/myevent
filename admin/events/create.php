<?php
/**
 * MyEvent Admin - Create an event.
 */
require_once __DIR__ . '/../../config.php';

require_admin();

$editor_event    = null;
$editor_redirect = 'admin/events/manage.php';
$editor_cancel   = 'manage.php';
$page_title      = __('Create event');
$nav_active      = 'admin_create';
$nav_area        = 'admin';
include ROOT_PATH . '/include/event_editor.php';
