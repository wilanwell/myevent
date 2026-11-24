<?php
/**
 * MyEvent - Full Beautiful Login Page
 * ------------------------------------
 */

// 1. Include configuration
require_once '../config.php';

// 2. Redirect if already logged in
if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'index.php');
}

$flashes = get_flashes();
$old_email = $_SESSION['old_email'] ?? '';
unset($_SESSION['old_email']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_PATH_CSS; ?>style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* Specific styling for the beautiful login card overlay */
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            margin: 30px auto 0;
            color: #1e293b;
            text-align: left;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .login-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 25px;
            text-align: center;
            color: #1e293b;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #334155;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
    </style>
</head>
<body>

    <?php include '../include/topNav.php'; ?>

    <header class="hero">
        <div class="overlay"></div>
        
        <div class="hero-content">
            <img src="<?php echo BASE_URL; ?>assets/img/logo.png" alt="MyEvent Logo" class="logo">
            
            <h1 style="margin-bottom: 10px; font-weight: 800;">Welcome to <?php echo APP_NAME; ?></h1>
            <p style="margin-bottom: 0; color: #e2e8f0;">Digitalizing campus event handling from announcement to registration.</p>

            <div class="login-card">
                <h3>Account Login</h3>

                <?php foreach ($flashes as $f): ?>
                    <div role="alert" class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
                <?php endforeach; ?>

                <form action="login_action.php" method="POST">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="name@example.com" value="<?= e($old_email) ?>" autocomplete="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                    </div>

                    <button type="submit" class="nav-btn active" style="width: 100%; border: none; padding: 14px; cursor: pointer; font-size: 1rem; font-weight: 700; margin: 10px 0; border-radius: 10px;">
                        Sign In
                    </button>
                </form>

                <p style="text-align: center; margin-top: 25px; font-size: 0.9rem; color: #64748b;">
                    Don't have an account? <a href="register.php" style="color: #4f46e5; font-weight: 700; text-decoration: none;">Register Now</a>
                </p>
            </div>
        </div>
    </header>

    <?php include '../include/footer.php'; ?>

</body>
</html>