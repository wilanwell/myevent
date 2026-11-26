<?php
/**
 * MyEvent - One-click sign-in as a demo account (POST, demo mode only).
 */
require_once __DIR__ . '/../../config.php';

if (!DEMO_MODE) {
    http_response_code(404);
    render_error_page(__('Page not found'), __('The page you are looking for does not exist.'));
}

require_post();

$role  = (string) ($_POST['role'] ?? '');
$email = DEMO_ACCOUNTS[$role] ?? null;
$user  = $email === null ? null
    : (db_all($conn, 'SELECT user_id, name, email, role, locale FROM users WHERE email = ? LIMIT 1', 's', [$email])[0] ?? null);

if (!$user) {
    flash('error', __('That demo account is not available right now. The demo data is restored every night.'));
    redirect('auth/login.php');
}

redirect(login_start_session($conn, $user));
