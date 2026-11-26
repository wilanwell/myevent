<?php
/**
 * MyEvent - User profile update handler
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../include/profile_update.php';

handle_profile_update($conn, 'users/profile.php');
