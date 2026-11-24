<?php
/**
 * MyEvent Admin - Profile update handler
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../include/profile_update.php';

require_admin();
handle_profile_update($conn, 'admin/profile.php');
