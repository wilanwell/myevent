<?php

require_once '../config.php';

// Access Control
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'];
$sql = "SELECT name, email, phone, role, organization, bio, profile_pic, category, recommend_events FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User profile not found.");
}

// Determine profile picture path
$profile_img = !empty($user['profile_pic']) ? "../assets/uploads/profiles/" . $user['profile_pic'] : null;

$user_interests = !empty($user['recommend_events']) ? explode(', ', $user['recommend_events']) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_PATH_CSS; ?>admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .sidebar-header h2 { color: #ffffff !important; font-size: 1.6rem; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.3); margin: 0; }
        .form-group label { display:block; margin-bottom:8px; font-weight:600; color: #334155; }
        .form-input { width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; box-sizing: border-box; }
        .btn-update:hover { background: #4338ca !important; }
        
        /* Read-only field styling */
        .form-input-readonly { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }

        /* Profile Picture Interaction */
        .avatar-container { position: relative; width: 120px; height: 120px; margin-bottom: 15px; cursor: pointer; transition: transform 0.2s; }
        .avatar-container:hover { transform: scale(1.05); }
        .profile-avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid #4f46e5; background: #fff; }
        .default-avatar { width: 100%; height: 100%; background: #4f46e5; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; }
        .camera-overlay { position: absolute; bottom: 5px; right: 5px; background: #4f46e5; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; font-size: 0.85rem; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        
        .checkbox-group label { display: inline-block; margin-right: 15px; margin-bottom: 5px; font-weight: normal; cursor: pointer; }
    </style>
</head>
<body>

<div class="admin-container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header" style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
            <h2><?php echo APP_NAME; ?></h2>
            <button class="toggle-btn" id="toggle-btn" style="background: none; border: none; color: white; cursor: pointer;">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <nav class="menu">
            <a href="dashboard.php" class="menu-item"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a>
            <a href="profile.php" class="menu-item active"><i class="fas fa-user-circle"></i> <span>My Profile</span></a>
            <a href="my_registrations.php" class="menu-item"><i class="fas fa-ticket-alt"></i> <span>My Registrations</span></a>
            <a href="../index.php" class="menu-item"><i class="fas fa-home"></i> <span>Back to Site</span></a>
            <a href="../auth/logout.php" class="menu-item" style="color: #fb7185;"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="dashboard-header" style="padding: 20px; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin:0;">Profile Management</h2>
            <span class="badge" style="background: #4f46e5; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem;"><?php echo $user['role']; ?></span>
        </header>

        <div style="padding: 30px;">
            <?php if (isset($_GET['success'])): ?>
                <div style="padding: 15px; background: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                    <i class="fas fa-check-circle"></i> Profile updated successfully!
                </div>
            <?php endif; ?>

            <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 800px;">
                
                <form action="profile_action.php" method="POST" enctype="multipart/form-data">
                    
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #f1f5f9;">
                        <div class="avatar-container" onclick="document.getElementById('profilePicInput').click();">
                            <?php if ($profile_img && file_exists($profile_img)): ?>
                                <img src="<?php echo $profile_img; ?>" class="profile-avatar" id="avatarPreview" alt="Profile Picture">
                            <?php else: ?>
                                <div class="default-avatar" id="avatarIcon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <img src="" class="profile-avatar" id="avatarPreview" style="display:none;">
                            <?php endif; ?>
                            <div class="camera-overlay"><i class="fas fa-camera"></i></div>
                        </div>
                        <div>
                            <h3 style="margin: 0;"><?php echo htmlspecialchars($user['name']); ?></h3>
                            <p style="color: #64748b; margin: 5px 0 0; font-size: 0.9rem;">Tap the icon to upload a new photo.</p>
                        </div>
                    </div>

                    <input type="file" name="profile_pic" id="profilePicInput" style="display: none;" accept="image/*">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['name']); ?>" readonly class="form-input form-input-readonly">
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly class="form-input form-input-readonly">
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
                            <label>Organization/Faculty</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['organization'] ?? ''); ?>" readonly class="form-input form-input-readonly">
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

                    <div class="form-group" style="margin-bottom: 25px;">
                        <label>Bio / Description</label>
                        <textarea name="bio" rows="3" class="form-input" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>

                    <h3 style="margin-bottom: 15px; padding-top: 15px; border-top: 1px solid #f1f5f9;">Security</h3>
                    <div class="form-group" style="margin-bottom: 25px;">
                        <label>New Password (Leave blank to keep current)</label>
                        <input type="password" name="new_password" placeholder="••••••••" class="form-input">
                    </div>

                    <button type="submit" class="btn-update" style="background: #4f46e5; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s;">
                        Save Profile Changes
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
    const btn = document.getElementById('toggle-btn');
    const sidebar = document.getElementById('sidebar');
    btn.addEventListener('click', () => { sidebar.classList.toggle('collapsed'); });

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