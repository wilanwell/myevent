<?php
/**
 * MyEvent - Choose a new password using a one-time reset link.
 */
require_once __DIR__ . '/../../config.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$reset = null;
if (preg_match('/^[0-9a-f]{64}$/', $token)) {
    $reset = db_all($conn,
        'SELECT pr.reset_id, pr.user_id, u.email, u.name, u.locale
         FROM password_resets pr JOIN users u ON u.user_id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()',
        's', [hash('sha256', $token)])[0] ?? null;
}

if (!$reset) {
    flash('error', __('This reset link is invalid or has expired. Request a new one below.'));
    redirect('auth/forgot_password.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $error = __('Use at least 8 characters, including a letter and a number.');
    } elseif (!hash_equals($password, $confirm)) {
        $error = __('The two passwords do not match.');
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            $stmt->bind_param('si', $hash, $reset['user_id']);
            $stmt->execute();
            $stmt->close();

            // Burn this token and any other outstanding ones for the account.
            $stmt = $conn->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
            $stmt->bind_param('i', $reset['user_id']);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
        } catch (Throwable $ex) {
            $conn->rollback();
            throw $ex;
        }

        login_clear_failures($conn, $reset['email']);
        audit($conn, 'password.reset', 'user', (int) $reset['user_id'], 'Password reset via emailed link for ' . $reset['email']);
        with_locale($reset['locale'] ?: current_locale(), static function () use ($reset) {
            $vars = ['name' => $reset['name'], 'app' => APP_NAME];
            send_mail($reset['email'], __('Your {app} password was changed', $vars),
                __('Hi {name},', $vars) . "\n\n" . __('The password for your {app} account was just changed.', $vars) . "\n\n"
                . __('If this was not you, reset it again straight away and contact an administrator:') . "\n"
                . BASE_URL . "auth/forgot_password.php\n\n- " . APP_NAME,
                email_html(__('Your password was changed'),
                    __('Hi {name},', $vars) . "\n\n" . __('The password for your {app} account was just changed.', $vars)
                        . ' ' . __('If this was you, there is nothing else to do.'),
                    __('Not you? Reset it now'), BASE_URL . 'auth/forgot_password.php', [],
                    __('If you did not change your password, reset it straight away and tell an administrator.')));
        });

        flash('success', __('Your password has been changed. Log in with your new password.'));
        $_SESSION['old_email'] = $reset['email'];
        redirect('auth/login.php');
    }
}

$page_title  = __('Choose a new password');
$nav_current = 'login';
include ROOT_PATH . '/include/layout/public_start.php';
?>

<div class="auth">
    <section class="auth-aside">
        <h1><?= t('Almost there.') ?></h1>
        <p><?= t('Choose a new password for {email}. You will use it the next time you log in.', ['email' => $reset['email']]) ?></p>
    </section>

    <section class="auth-main">
        <div class="auth-card">
            <h2><?= t('Choose a new password') ?></h2>
            <p><?= t('At least 8 characters, including a letter and a number.') ?></p>

            <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="POST" class="form-grid form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="field">
                    <label for="pw"><?= t('New password') ?></label>
                    <input type="password" id="pw" name="password" minlength="8" autocomplete="new-password" required autofocus>
                </div>
                <div class="field">
                    <label for="pw2"><?= t('Confirm new password') ?></label>
                    <input type="password" id="pw2" name="password_confirm" minlength="8" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg"><?= t('Save new password') ?></button>
            </form>
        </div>
    </section>
</div>

<?php include ROOT_PATH . '/include/layout/public_end.php'; ?>
