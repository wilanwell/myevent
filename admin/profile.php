<?php
/**
 * MyEvent - Admin Profile Page
 */
require_once '../config.php';

// 1. Access Control (Admin Only)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'Admin') {
    header("location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'];

// 2. Fetch User Data
$sql = "SELECT name, email, phone, role, organization, bio, profile_pic, category, recommend_events FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User profile not found.");
}

// Correct path to images
$profile_img = !empty($user['profile_pic']) ? "../assets/uploads/profiles/" . $user['profile_pic'] : null;
$user_interests = !empty($user['recommend_events']) ? explode(', ', $user['recommend_events']) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --sidebar-bg: #1e293b; --bg-body: #f1f5f9; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); display: flex; min-height: 100vh; }
        
        .sidebar { width: 250px; background: var(--sidebar-bg); color: white; display: flex; flex-direction: column; position: fixed; height: 100%; }
        .sidebar-header h2 { color: #ffffff !important; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.3); padding: 20px; margin: 0; text-align: center; border-bottom: 1px solid #334155; }
        .nav-links { list-style: none; padding: 0; margin-top: 20px; }
        .nav-links li a { display: block; padding: 15px 25px; color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-links li a:hover, .nav-links li a.active { background-color: var(--primary); color: white; }

        .main-content { margin-left: 250px; padding: 40px; width: 100%; box-sizing: border-box; }
        .profile-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 800px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display:block; margin-bottom:8px; font-weight:600; color: #334155; }
        .form-input { width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; box-sizing: border-box; }
        .form-input-readonly { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }
        
        .checkbox-group label { display: inline-block; margin-right: 15px; margin-bottom: 5px; font-weight: normal; cursor: pointer; }

        .avatar-container { position: relative; width: 120px; height: 120px; margin-bottom: 15px; cursor: pointer; }
        .profile-avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid #4f46e5; }
        .default-avatar { width: 100%; height: 100%; background: #4f46e5; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; }
        .camera-overlay { position: absolute; bottom: 5px; right: 5px; background: #4f46e5; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; }
        
        .alert-success { padding: 15px; background: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>

<nav class="sidebar">
    <div class="sidebar-header"><h2>MyEvent</h2></div>
    <ul class="nav-links">
        <li><a href="index.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
        <li><a href="profile.php" class="active"><i class="fa-solid fa-user-circle"></i> My Profile</a></li>
        <li><a href="users/manage.php"><i class="fa-solid fa-users"></i> Manage Users</a></li>
        <li><a href="../auth/logout.php" style="color:#fb7185; margin-top:20px; border-top:1px solid #334155;"><i class="fa-solid fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</nav>

<main class="main-content">
    <h1>Profile Settings</h1>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> Profile updated successfully!
        </div>
    <?php endif; ?>

    <div class="profile-card">
        <form action="profile_action.php" method="POST" enctype="multipart/form-data">
            
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px;">
                <div class="avatar-container" onclick="document.getElementById('profilePicInput').click();">
                    <?php if ($profile_img && file_exists($profile_img)): ?>
                        <img src="<?php echo $profile_img; ?>" class="profile-avatar" id="avatarPreview">
                    <?php else: ?>
                        <div class="default-avatar" id="avatarIcon"><i class="fas fa-user"></i></div>
                        <img src="" class="profile-avatar" id="avatarPreview" style="display:none;">
                    <?php endif; ?>
                    <div class="camera-overlay"><i class="fas fa-camera"></i></div>
                </div>
                <div>
                    <h3 style="margin: 0;"><?php echo htmlspecialchars($user['name']); ?></h3>
                    <p style="color: #64748b; margin: 5px 0 0; font-size: 0.9rem;">Administrator Account</p>
                </div>
            </div>

            <input type="file" name="profile_pic" id="profilePicInput" style="display: none;" accept="image/*">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="form-input">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" readonly class="form-input form-input-readonly">
                </div>

                <fieldset style="grid-column: span 2; margin-bottom: 10px; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; background: #f8fafc;">
                    <legend style="padding: 0 5px; font-weight:600; color: #64748b;">Category (Locked)</legend>
                    <label style="margin-right: 15px; color: #94a3b8; cursor: not-allowed;">
                        <input type="radio" disabled <?php echo (strtolower($user['category']) == 'staff') ? 'checked' : ''; ?>> Staff
                    </label>
                    <label style="margin-right: 15px; color: #94a3b8; cursor: not-allowed;">
                        <input type="radio" disabled <?php echo (strtolower($user['category']) == 'student') ? 'checked' : ''; ?>> Student
                    </label>
                    <label style="color: #94a3b8; cursor: not-allowed;">
                        <input type="radio" disabled <?php echo (strtolower($user['category']) == 'public') ? 'checked' : ''; ?>> Public
                    </label>
                </fieldset>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="form-input" required>
                </div>

                <div class="form-group">
                    <label>Organization</label>
                    <input type="text" name="organization" value="<?php echo htmlspecialchars($user['organization']); ?>" class="form-input">
                </div>
            </div>

            <div class="form-group checkbox-group" style="margin-bottom: 25px; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px;">
                <label style="font-weight: 600; display:block; margin-bottom:10px;">Recommend event about:</label>
                <?php 
                $options = ['workshop', 'seminar', 'competition', 'festival', 'sport', 'course'];
                foreach($options as $opt): ?>
                    <label>
                        <input type="checkbox" name="event[]" value="<?php echo $opt; ?>" 
                        <?php echo in_array($opt, $user_interests) ? 'checked' : ''; ?>> <?php echo ucfirst($opt); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio" rows="3" class="form-input"><?php echo htmlspecialchars($user['bio']); ?></textarea>
            </div>

            <div class="form-group" style="margin-top: 25px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                <label>New Password (Leave blank to keep current)</label>
                <input type="password" name="new_password" placeholder="••••••••" class="form-input">
            </div>

            <button type="submit" style="background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 10px;">
                Save Changes
            </button>
        </form>
    </div>
</main>

<script>
    document.getElementById('profilePicInput').onchange = function (evt) {
        const [file] = this.files;
        if (file) {
            const preview = document.getElementById('avatarPreview');
            const icon = document.getElementById('avatarIcon');
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
            if(icon) icon.style.display = 'none';
        }
    };
</script>
</body>
</html>