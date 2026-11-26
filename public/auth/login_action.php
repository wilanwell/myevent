<?php
/**
 * MyEvent - Login handler
 * Verifies credentials with throttling, then starts a fresh session.
 */
require_once __DIR__ . '/../../config.php';

require_post();

$email    = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

// Generic message: never reveal whether the email exists.
$invalid = __('Invalid email or password.');

if ($email === '' || $password === '') {
    flash('error', __('Please enter both email and password.'));
    redirect('auth/login.php');
}

$_SESSION['old_email'] = $email;

if (login_is_throttled($conn, $email)) {
    flash('error', __('Too many failed login attempts. Please wait {n} minutes and try again.', ['n' => LOGIN_WINDOW_MINUTES]));
    redirect('auth/login.php');
}

$stmt = $conn->prepare('SELECT user_id, name, password, role, locale FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password'])) {
    login_record_failure($conn, $email);
    flash('error', $invalid);
    redirect('auth/login.php');
}

// Upgrade the hash transparently if the algorithm/cost changed.
if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $up = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
    $up->bind_param('si', $newHash, $user['user_id']);
    $up->execute();
    $up->close();
}

login_clear_failures($conn, $email);

// Prevent session fixation: new session ID, fresh session data.
session_regenerate_id(true);
$_SESSION = [
    'loggedin'      => true,
    'user_id'       => (int) $user['user_id'],
    'id'            => (int) $user['user_id'], // legacy key still read by some pages
    'email'         => $email,
    'name'          => $user['name'],
    'role'          => trim($user['role']),
    'last_activity' => time(),
    // The account's language wins; an account without one adopts the language in use now.
    'locale'        => isset(LOCALES[$user['locale'] ?? '']) ? $user['locale'] : current_locale(),
];
if (($user['locale'] ?? null) !== $_SESSION['locale']) {
    $up = $conn->prepare('UPDATE users SET locale = ? WHERE user_id = ?');
    $up->bind_param('si', $_SESSION['locale'], $_SESSION['user_id']);
    $up->execute();
    $up->close();
}

redirect($_SESSION['role'] === 'Admin' ? 'admin/index.php' : 'index.php');
