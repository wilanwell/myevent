<?php
/**
 * MyEvent - Participant profile
 */
require_once __DIR__ . '/../config.php';

require_login();

$user = db_all($conn,
    'SELECT name, email, phone, organization, bio, profile_pic, category, recommend_events, email_participant, email_organiser FROM users WHERE user_id = ?',
    'i', [current_user_id()])[0] ?? null;
if (!$user) {
    force_logout(__('Your account could not be found. Please log in again.'));
}

$page_title = __('My profile');
$nav_active = 'profile';
$nav_area = 'user';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header card-narrow">
    <div>
        <h1><?= t('My profile') ?></h1>
        <p><?= t('Keep your contact details up to date so organisers can reach you.') ?></p>
    </div>
</div>

<?php
$form_action = 'profile_action.php';
$edit_identity = false;
include ROOT_PATH . '/include/partials/profile_form.php';
include ROOT_PATH . '/include/layout/app_end.php';
