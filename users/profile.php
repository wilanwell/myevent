<?php
/**
 * MyEvent - Participant profile
 */
require_once __DIR__ . '/../config.php';

require_login();

$user = db_all($conn,
    'SELECT name, email, phone, organization, bio, profile_pic, category, recommend_events FROM users WHERE user_id = ?',
    'i', [current_user_id()])[0] ?? null;
if (!$user) {
    force_logout('Your account could not be found. Please log in again.');
}

$page_title = 'My profile';
$nav_active = 'profile';
$nav_area = 'user';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header card-narrow">
    <div>
        <h1>My profile</h1>
        <p>Keep your contact details up to date so organisers can reach you.</p>
    </div>
</div>

<?php
$form_action = 'profile_action.php';
$edit_identity = false;
include ROOT_PATH . '/include/partials/profile_form.php';
include ROOT_PATH . '/include/layout/app_end.php';
