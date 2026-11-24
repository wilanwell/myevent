<?php
/**
 * MyEvent - Log in
 */
require_once __DIR__ . '/../config.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'users/dashboard.php');
}

$old_email = $_SESSION['old_email'] ?? '';
unset($_SESSION['old_email']);

$page_title  = 'Log in';
$nav_current = 'login';
include ROOT_PATH . '/include/layout/public_start.php';
?>

<div class="auth">
    <section class="auth-aside">
        <h1>Welcome back.</h1>
        <p>Log in to see your tickets, follow up on registrations and manage the events you organise.</p>
        <ul>
            <li><i class="fa-solid fa-ticket" aria-hidden="true"></i> Tickets for every approved registration</li>
            <li><i class="fa-regular fa-bell" aria-hidden="true"></i> Notifications when organisers respond</li>
            <li><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Approvals and check-in for your own events</li>
        </ul>
    </section>

    <section class="auth-main">
        <div class="auth-card">
            <h2>Log in</h2>
            <p>Use the email you registered with.</p>

            <?php include ROOT_PATH . '/include/flash.php'; ?>

            <form action="login_action.php" method="POST" class="form-grid form-stack">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($old_email) ?>" autocomplete="email" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Log in</button>
            </form>

            <p class="auth-switch">New to <?= e(APP_NAME) ?>? <a href="register.php">Create an account</a></p>
        </div>
    </section>
</div>

<?php include ROOT_PATH . '/include/layout/public_end.php'; ?>
