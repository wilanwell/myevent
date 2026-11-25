<?php
/**
 * MyEvent - Shared profile update logic (users and admins).
 *
 * Fields that a form does not submit (e.g. read-only name) keep their
 * current value. Changing the password requires the current password.
 */

function handle_profile_update(mysqli $conn, string $backPath): void
{
    require_login();
    require_post();

    $user_id = current_user_id();

    $stmt = $conn->prepare('SELECT name, organization, password, profile_pic FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$current) {
        force_logout('Your account could not be found. Please log in again.');
    }

    $allowedInterests = ['workshop', 'seminar', 'competition', 'festival', 'sport', 'course'];

    $name         = array_key_exists('name', $_POST) ? trim((string) $_POST['name']) : $current['name'];
    $organization = array_key_exists('organization', $_POST) ? trim((string) $_POST['organization']) : $current['organization'];
    $phone        = trim((string) ($_POST['phone'] ?? ''));
    $bio          = trim((string) ($_POST['bio'] ?? ''));
    $interests    = array_values(array_intersect((array) ($_POST['event'] ?? []), $allowedInterests));
    $newPassword  = (string) ($_POST['new_password'] ?? '');
    $currentPwd   = (string) ($_POST['current_password'] ?? '');

    $errors = [];
    if ($name === '' || mb_strlen($name) > 255) {
        $errors[] = 'Name is required.';
    }
    if (!preg_match('/^[0-9+\- ]{7,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (mb_strlen($bio) > 1000) {
        $errors[] = 'Bio must be 1000 characters or fewer.';
    }
    if ($newPassword !== '') {
        if (!password_verify($currentPwd, $current['password'])) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
            $errors[] = 'New password must be at least 8 characters and include a letter and a number.';
        }
    }

    if ($errors) {
        foreach ($errors as $msg) {
            flash('error', $msg);
        }
        redirect($backPath);
    }

    [$newPic, $uploadError] = upload_image($_FILES['profile_pic'] ?? null, AVATAR_DIR, 'profile', 2 * 1024 * 1024);
    if ($uploadError) {
        flash('error', $uploadError);
        redirect($backPath);
    }

    $sql    = 'UPDATE users SET name = ?, phone = ?, organization = ?, bio = ?, recommend_events = ?';
    $types  = 'sssss';
    $params = [$name, $phone, $organization, $bio, implode(', ', $interests)];

    if (isset($_POST['email_settings'])) {
        $sql .= ', email_participant = ?, email_organiser = ?';
        $types .= 'ii';
        array_push($params, isset($_POST['email_participant']) ? 1 : 0, isset($_POST['email_organiser']) ? 1 : 0);
    }

    if ($newPic) {
        $sql .= ', profile_pic = ?';
        $types .= 's';
        $params[] = $newPic;
    }
    if ($newPassword !== '') {
        $sql .= ', password = ?';
        $types .= 's';
        $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    $sql .= ' WHERE user_id = ?';
    $types .= 'i';
    $params[] = $user_id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    if ($newPic) {
        delete_upload($current['profile_pic'], AVATAR_DIR);
        $_SESSION['profile_pic'] = $newPic;
    }
    if ($newPassword !== '') {
        session_regenerate_id(true);
    }
    $_SESSION['name'] = $name;

    flash('success', 'Profile updated successfully!');
    redirect($backPath);
}
