<?php 

// Include config to access BASE_URL and APP_NAME
include_once __DIR__ . '/../config.php'; 
?>
<nav class="navbar">
    <a href="<?php echo BASE_URL; ?>index.php" class="brand">
        <i class="fa-solid fa-calendar-star"></i> <?php echo APP_NAME; ?>
    </a>

    <div class="menu-icon" id="menu-icon">
        <i class="fas fa-bars"></i>
    </div>

    <div class="nav-links" id="nav-links" style="display: flex; align-items: center;">
        
        <?php 
        // 1. Session Control: Check if user is logged in
        if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): 
            $u_id = $_SESSION['user_id'] ?? $_SESSION['id'];

            // 2. FETCH USER PROFILE PIC
            $u_sql = "SELECT profile_pic FROM users WHERE user_id = ?";
            $u_stmt = $conn->prepare($u_sql);
            $u_stmt->bind_param("i", $u_id);
            $u_stmt->execute();
            $u_data = $u_stmt->get_result()->fetch_assoc();
            $p_pic = $u_data['profile_pic'] ?? '';
        ?>
            <div style="position: relative; display: inline-block; margin-right: 25px; align-self: center;">
                <a href="<?php echo BASE_URL; ?>users/notifications.php" style="color: inherit; text-decoration: none;">
                    <i class="fa-solid fa-bell" style="font-size: 1.3rem;"></i>
                    <?php
                    // Count unread notifications
                    $notif_res = $conn->query("SELECT COUNT(*) as unread FROM notifications WHERE user_id = $u_id AND is_read = 0");
                    $unread = ($notif_res) ? $notif_res->fetch_assoc()['unread'] : 0;
                    
                    if ($unread > 0): ?>
                        <span style="position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 50%; font-weight: 800; border: 2px solid #fff;">
                            <?= $unread ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>

            <div style="margin-right: 20px;">
                <a href="<?php echo BASE_URL; ?>users/profile.php">
                    <?php if(!empty($p_pic) && file_exists(__DIR__ . '/../assets/uploads/profiles/' . $p_pic)): ?>
                        <img src="<?php echo BASE_URL; ?>assets/uploads/profiles/<?php echo $p_pic; ?>" 
                             style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid #4f46e5;">
                    <?php else: ?>
                        <div style="width: 35px; height: 35px; background: #4f46e5; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem;">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </a>
            </div>

            <a href="<?php echo BASE_URL; ?>index.php" class="nav-btn">Home</a>
            <a href="<?php echo BASE_URL; ?>users/dashboard.php" class="nav-btn">Dashboard</a>
            <a href="<?php echo BASE_URL; ?>users/my_registrations.php" class="nav-btn">My Joins</a>

            <?php if(isset($_SESSION['role']) && $_SESSION['role'] !== 'Public'): ?>
                <a href="<?php echo BASE_URL; ?>users/my_events.php" class="nav-btn">My Events</a>
            <?php endif; ?>

            <a href="<?php echo BASE_URL; ?>auth/logout.php" class="nav-btn" style="color: #f87171;">Logout</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>index.php" class="nav-btn">Home</a>
            <a href="<?php echo BASE_URL; ?>auth/register.php" class="nav-btn">Register</a>
            <a href="<?php echo BASE_URL; ?>auth/login.php" class="nav-btn active">Login</a>
        <?php endif; ?>
    </div>
</nav>