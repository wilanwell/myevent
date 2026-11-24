<?php
/**
 * MyEvent Admin - Delete user (POST only).
 */
require_once __DIR__ . '/../../config.php';

require_admin();
require_post();

$id = (int) ($_POST['id'] ?? 0);

if ($id === current_user_id()) {
    flash('error', 'You cannot delete your own account.');
    redirect('admin/users/manage.php');
}

$stmt = $conn->prepare('SELECT name, email, profile_pic FROM users WHERE user_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    flash('error', 'User not found.');
    redirect('admin/users/manage.php');
}

$stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

delete_upload($user['profile_pic'], AVATAR_DIR);
audit($conn, 'user.delete', 'user', $id, $user['name'] . ' <' . $user['email'] . '>');

flash('success', 'User deleted.');
redirect('admin/users/manage.php');
