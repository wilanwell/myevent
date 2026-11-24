<?php
require_once '../config.php';

require_login();

$user_id = current_user_id();

// Handle "Clear All" Action
if (isset($_POST['clear_all'])) {
    $clear_sql = "DELETE FROM notifications WHERE user_id = ?";
    $stmt_clear = $conn->prepare($clear_sql);
    $stmt_clear->bind_param("i", $user_id);
    $stmt_clear->execute();
    flash('success', 'All notifications cleared.');
    redirect('users/notifications.php');
}

// 1. Mark all as read when page is opened
$mark = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
$mark->bind_param('i', $user_id);
$mark->execute();
$mark->close();

// 2. Fetch Notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* MyEvent Core Styles */
        :root { --primary: #4f46e5; --sidebar-bg: #1e293b; --bg-body: #f1f5f9; --danger: #ef4444; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); display: flex; min-height: 100vh; }
        
        /* Sidebar Navigation */
        .sidebar { width: 250px; background: var(--sidebar-bg); color: white; display: flex; flex-direction: column; position: fixed; height: 100%; }
        .sidebar-header h2 { color: #ffffff !important; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.3); padding: 20px; margin: 0; text-align: center; border-bottom: 1px solid #334155; }
        
        .nav-links { list-style: none; padding: 0; margin-top: 20px; }
        .nav-links li a { display: block; padding: 15px 25px; color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-links li a:hover, .nav-links li a.active { background-color: var(--primary); color: white; }
        
        /* Content Area */
        .main-content { margin-left: 250px; padding: 30px; width: 100%; box-sizing: border-box; }
        .notif-card { background: white; padding: 20px; border-radius: 12px; margin-bottom: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-left: 5px solid var(--primary); }
        .time { font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px; display: block; }
        .message { color: #334155; line-height: 1.5; font-size: 1rem; }

        /* Clear All Button */
        .btn-clear { background: none; border: 1px solid var(--danger); color: var(--danger); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: 0.2s; }
        .btn-clear:hover { background: var(--danger); color: white; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header"><h2>MyEvent</h2></div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
            <li><a href="my_registrations.php"><i class="fa-solid fa-ticket"></i> My Joins</a></li>
            <li><a href="notifications.php" class="active"><i class="fa-solid fa-bell"></i> Notifications</a></li>
            <li><a href="../auth/logout.php" style="color:#fb7185;"><i class="fa-solid fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <div class="main-content">
        <?php include ROOT_PATH . '/include/flash.php'; ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h2 style="margin:0;">🔔 Notifications</h2>
            
            <div style="display: flex; gap: 15px; align-items: center;">
                <?php if ($result->num_rows > 0): ?>
                    <form method="POST" onsubmit="return confirm('Clear all notifications permanently?');">
                        <?= csrf_field() ?>
                        <button type="submit" name="clear_all" class="btn-clear">
                            <i class="fa-solid fa-trash-can"></i> Clear All
                        </button>
                    </form>
                <?php endif; ?>
                <a href="dashboard.php" style="text-decoration:none; color:var(--primary); font-weight:600;">Back to Dashboard</a>
            </div>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="notif-card">
                    <span class="time"><i class="fa-regular fa-clock"></i> <?= date('M d, Y • h:i A', strtotime($row['created_at'])); ?></span>
                    <div class="message"><?= render_message($row['message']) ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; margin-top: 100px; color: #94a3b8;">
                <i class="fa-regular fa-bell-slash" style="font-size: 4rem; opacity: 0.3; margin-bottom: 15px;"></i>
                <p style="font-size: 1.2rem;">You have no notifications yet.</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>