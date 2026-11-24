<?php
/**
 * MyEvent - Request a password reset link.
 *
 * Always shows the same confirmation, whether or not the email exists,
 * so the form cannot be used to discover registered addresses.
 */
require_once __DIR__ . '/../config.php';

if (is_logged_in()) {
    redirect('users/profile.php');
}

const RESET_TTL_MINUTES = 60;
const RESET_MAX_PER_HOUR = 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Enter the email address you registered with.');
        redirect('auth/forgot_password.php');
    }

    $user = db_all($conn, 'SELECT user_id, name, email FROM users WHERE email = ? LIMIT 1', 's', [$email])[0] ?? null;

    if ($user) {
        $recent = (int) db_value($conn,
            'SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)',
            'i', [$user['user_id']]);

        if ($recent < RESET_MAX_PER_HOUR) {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            $ip    = client_ip();
            $stmt = $conn->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address)
                                    VALUES (?, ?, NOW() + INTERVAL ' . RESET_TTL_MINUTES . ' MINUTE, ?)');
            $stmt->bind_param('iss', $user['user_id'], $hash, $ip);
            $stmt->execute();
            $stmt->close();

            $link = BASE_URL . 'auth/reset_password.php?token=' . $token;
            $body = "Hi {$user['name']},\n\n"
                  . "We received a request to reset the password for your " . APP_NAME . " account.\n\n"
                  . "Choose a new password here (the link works once and expires in " . RESET_TTL_MINUTES . " minutes):\n"
                  . "$link\n\n"
                  . "If you did not ask for this, you can ignore this email. Your password will not change.\n\n"
                  . "- " . APP_NAME;
            send_mail($user['email'], 'Reset your ' . APP_NAME . ' password', $body);

            // Development convenience: with the log mail driver there is no inbox, so show where the email went.
            if (DEBUG_MODE && env('MAIL_DRIVER', 'log') === 'log') {
                flash('info', 'Development mode: the email was saved to storage/mail/. Reset link: ' . $link);
            }
        }
    }

    flash('success', 'If an account exists for ' . $email . ', we have sent a link to reset the password. Check your inbox and spam folder.');
    redirect('auth/login.php');
}

$page_title  = 'Reset your password';
$nav_current = 'login';
include ROOT_PATH . '/include/layout/public_start.php';
?>

<div class="auth">
    <section class="auth-aside">
        <h1>Locked out?</h1>
        <p>Enter your email and we will send you a link to choose a new password. The link works once and expires in an hour.</p>
    </section>

    <section class="auth-main">
        <div class="auth-card">
            <h2>Reset your password</h2>
            <p>We will email you a reset link.</p>

            <?php include ROOT_PATH . '/include/flash.php'; ?>

            <form method="POST" class="form-grid form-stack">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" autocomplete="email" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Send reset link</button>
            </form>

            <p class="auth-switch">Remembered it? <a href="login.php">Back to log in</a></p>
        </div>
    </section>
</div>

<?php include ROOT_PATH . '/include/layout/public_end.php'; ?>
