<?php
/**
 * MyEvent - Account registration handler
 */
require_once __DIR__ . '/../../config.php';

require_post();

$allowedCategories = ['Student', 'Staff', 'Public'];
$allowedInterests  = ['workshop', 'seminar', 'competition', 'festival', 'sport', 'course'];

$category     = (string) ($_POST['category'] ?? '');
$name         = trim((string) ($_POST['name'] ?? ''));
$email        = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone        = trim((string) ($_POST['phone'] ?? ''));
$organization = trim((string) ($_POST['organization'] ?? ''));
$password     = (string) ($_POST['password'] ?? '');
$interests    = array_values(array_intersect((array) ($_POST['event'] ?? []), $allowedInterests));

// Keep input (never the password) so the form can be refilled on error.
$_SESSION['old_input'] = compact('category', 'name', 'email', 'phone', 'organization') + ['event' => $interests];

$errors = [];
if ($name === '' || mb_strlen($name) > 255) {
    $errors[] = __('Please enter your full name.');
}
if (!in_array($category, $allowedCategories, true)) {
    $errors[] = __('Please choose a valid category.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    $errors[] = __('Please enter a valid email address.');
}
if ($phone !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $phone)) {
    $errors[] = __('Please enter a valid phone number.');
}
if ($organization === '' || mb_strlen($organization) > 255) {
    $errors[] = __('Please enter your organisation or faculty.');
}
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    $errors[] = __('Password must be at least 8 characters and include a letter and a number.');
}
if (!$interests) {
    $errors[] = __('Please choose at least one kind of event you are interested in.');
}

if ($errors) {
    foreach ($errors as $msg) {
        flash('error', $msg);
    }
    redirect('auth/register.php');
}

$stmt = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$exists = (bool) $stmt->get_result()->fetch_row();
$stmt->close();

if ($exists) {
    flash('error', __('This email is already registered. Please log in instead.'));
    redirect('auth/register.php');
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$recommendations = implode(', ', $interests);

$stmt = $conn->prepare(
    "INSERT INTO users (category, name, email, phone, organization, recommend_events, password, role, locale)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'User', ?)"
);
$locale = current_locale();
$stmt->bind_param('ssssssss', $category, $name, $email, $phone, $organization, $recommendations, $hash, $locale);
$stmt->execute();
$stmt->close();

unset($_SESSION['old_input']);
flash('success', __('Registration successful! Please log in to {app}.', ['app' => APP_NAME]));
redirect('auth/login.php');
