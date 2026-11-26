<?php
/**
 * MyEvent Admin - Delete user (POST only).
 */
require_once __DIR__ . '/../../../config.php';

require_admin();
require_post();

$id = (int) ($_POST['id'] ?? 0);

if ($id === current_user_id()) {
    flash('error', __('You cannot delete your own account.'));
    redirect('admin/users/manage.php');
}

$stmt = $conn->prepare('SELECT name, email, profile_pic FROM users WHERE user_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    flash('error', __('User not found.'));
    redirect('admin/users/manage.php');
}

$affectedEvents = array_column(db_all($conn,
    "SELECT DISTINCT event_id FROM registrations WHERE user_id = ? AND approval_status <> 'Rejected'", 'i', [$id]), 'event_id');

$stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

delete_upload($user['profile_pic'], AVATAR_DIR);
foreach ($affectedEvents as $eventId) {
    promote_waitlist($conn, (int) $eventId);
}
audit($conn, 'user.delete', 'user', $id, $user['name'] . ' <' . $user['email'] . '>');

flash('success', __('User deleted.'));
redirect('admin/users/manage.php');
