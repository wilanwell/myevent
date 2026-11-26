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

$page_title  = __('Log in');
$nav_current = 'login';
include ROOT_PATH . '/include/layout/public_start.php';
?>

<div class="auth">
    <section class="auth-aside">
        <h1><?= t('Welcome back.') ?></h1>
        <p><?= t('Log in to see your tickets, follow up on registrations and manage the events you organise.') ?></p>
        <ul>
            <li><i class="fa-solid fa-ticket" aria-hidden="true"></i> <?= t('Tickets for every approved registration') ?></li>
            <li><i class="fa-regular fa-bell" aria-hidden="true"></i> <?= t('Notifications when organisers respond') ?></li>
            <li><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> <?= t('Approvals and check-in for your own events') ?></li>
        </ul>
    </section>

    <section class="auth-main">
        <div class="auth-card">
            <h2><?= t('Log in') ?></h2>
            <p><?= t('Use the email you registered with.') ?></p>

            <?php include ROOT_PATH . '/include/flash.php'; ?>

            <form action="login_action.php" method="POST" class="form-grid form-stack">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email"><?= t('Email') ?></label>
                    <input type="email" id="email" name="email" value="<?= e($old_email) ?>" autocomplete="email" required autofocus>
                </div>
                <div class="field">
                    <label for="password"><?= t('Password') ?></label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                    <a class="hint" href="forgot_password.php"><?= t('Forgot your password?') ?></a>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg"><?= t('Log in') ?></button>
            </form>

            <p class="auth-switch"><?= t('New to {app}?', ['app' => APP_NAME]) ?> <a href="register.php"><?= t('Create an account') ?></a></p>
        </div>
    </section>
</div>

<?php include ROOT_PATH . '/include/layout/public_end.php'; ?>
