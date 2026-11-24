<?php
require_once '../config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('users/dashboard.php');
}

$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);
$oldCategory = $old['category'] ?? 'Student';
$oldEvents = $old['event'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_PATH_CSS; ?>style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        .register-card {
            background: rgba(255, 255, 255, 0.98);
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 550px;
            margin: 30px auto 50px;
            color: #1e293b;
            text-align: left;
        }
        .register-card h3 { text-align: center; margin-bottom: 20px; font-weight: 700; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; }
        .form-group input, .form-group select {
            width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box;
        }

        /* Category Radio Styles */
        .radio-group { display: flex; gap: 15px; margin-top: 5px; }
        .radio-item { display: flex; align-items: center; gap: 5px; font-size: 0.9rem; cursor: pointer; }
        .radio-item input { width: 18px; height: 18px; cursor: pointer; }

        /* Recommendation Checkbox Styles */
        .checkbox-group { 
            background: #f8fafc; 
            padding: 15px; 
            border-radius: 8px; 
            border: 1px solid #e2e8f0; 
        }
        .checkbox-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 400;
            font-size: 0.9rem;
            cursor: pointer;
            margin-bottom: 0;
        }
        .checkbox-group input { width: 16px; height: 16px; cursor: pointer; }
    </style>
</head>
<body>

    <?php include '../include/topNav.php'; ?>

    <header class="hero" style="height: auto; min-height: 100vh; padding: 100px 20px;">
        <div class="overlay"></div>
        
        <div class="hero-content">
            <img src="<?php echo BASE_URL; ?>assets/img/logo.png" alt="MyEvent Logo" class="logo">
            <h1 style="margin-bottom: 10px;">Join <?php echo APP_NAME; ?></h1>
            <p style="color: #e2e8f0; margin-bottom: 20px;">Create an account to manage your campus events.</p>

            <div class="register-card">
                <h3>Create New Account</h3>

                <?php include '../include/flash.php'; ?>

                <form action="register_action.php" method="POST">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" placeholder="John Doe" value="<?= e($old['name'] ?? '') ?>" maxlength="255" autocomplete="name" required>
                    </div>

                    <div class="form-group">
                        <label>Category *</label>
                        <div class="radio-group">
                            <label class="radio-item"><input type="radio" name="category" value="Student"<?= $oldCategory === 'Student' ? ' checked' : '' ?> required> Student</label>
                            <label class="radio-item"><input type="radio" name="category" value="Staff"<?= $oldCategory === 'Staff' ? ' checked' : '' ?>> Staff</label>
                            <label class="radio-item"><input type="radio" name="category" value="Public"<?= $oldCategory === 'Public' ? ' checked' : '' ?>> Public</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" placeholder="email@campus.edu" value="<?= e($old['email'] ?? '') ?>" maxlength="255" autocomplete="email" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" placeholder="012-3456789" value="<?= e($old['phone'] ?? '') ?>" maxlength="20" pattern="[0-9+\- ]{7,20}" autocomplete="tel">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Organization/Faculty *</label>
                        <input type="text" name="organization" placeholder="e.g. Faculty of Computing" value="<?= e($old['organization'] ?? '') ?>" maxlength="255" required>
                    </div>

                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" placeholder="At least 8 characters" minlength="8" autocomplete="new-password" required>
                        <small style="color:#64748b;">Minimum 8 characters, including a letter and a number.</small>
                    </div>

                    <div class="form-group checkbox-group">
                        <label style="font-weight: 600; display:block; margin-bottom:10px;">Recommend event about:</label>
                        <div class="checkbox-grid">
                            <label><input type="checkbox" name="event[]" value="workshop"<?= in_array('workshop', $oldEvents, true) ? ' checked' : '' ?>> Workshop</label>
                            <label><input type="checkbox" name="event[]" value="seminar"<?= in_array('seminar', $oldEvents, true) ? ' checked' : '' ?>> Seminar</label>
                            <label><input type="checkbox" name="event[]" value="competition"<?= in_array('competition', $oldEvents, true) ? ' checked' : '' ?>> Competition</label>
                            <label><input type="checkbox" name="event[]" value="festival"<?= in_array('festival', $oldEvents, true) ? ' checked' : '' ?>> Festival</label>
                            <label><input type="checkbox" name="event[]" value="sport"<?= in_array('sport', $oldEvents, true) ? ' checked' : '' ?>> Sport</label>
                            <label><input type="checkbox" name="event[]" value="course"<?= in_array('course', $oldEvents, true) ? ' checked' : '' ?>> Course</label>
                        </div>
                    </div>

                    <button type="submit" class="nav-btn active" style="width: 100%; border: none; padding: 14px; cursor: pointer; font-size: 1rem; font-weight: 700; border-radius: 10px; margin-top: 10px;">
                        Register Account
                    </button>
                </form>

                <p style="text-align: center; margin-top: 20px; font-size: 0.9rem; color: #64748b;">
                    Already have an account? <a href="login.php" style="color: #4f46e5; font-weight: 700; text-decoration: none;">Login here</a>
                </p>
            </div>
        </div>
    </header>

    <?php include '../include/footer.php'; ?>

    <script>
        // Toggle submenu
        document.querySelectorAll('.submenu-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.nextElementSibling.classList.toggle('show');
            });
        });
    </script>
</body>
</html>