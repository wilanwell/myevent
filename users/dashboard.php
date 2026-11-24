<?php
/**
 * MyEvent - User Dashboard
 */

require_once '../config.php';

require_login();

// FIXED: Using 'user_id' for consistency with database and session
$user_id = (int) current_user_id();
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];
$user_email = $_SESSION['email'] ?? 'Not Set';

// Data Retrieval for Dashboard Stats
$total_events = $conn->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count'];
$my_registrations = $conn->query("SELECT COUNT(*) as count FROM registrations WHERE user_id = " . $user_id)->fetch_assoc()['count'];
$my_created_events = $conn->query("SELECT COUNT(*) as count FROM events WHERE created_by = " . $user_id)->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_PATH_CSS; ?>admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .sidebar-header h2 {
            color: #ffffff !important;
            font-size: 1.6rem;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            margin: 0;
            padding: 20px;
            text-align: center;
        }
        .stat-card {
            background: white; 
            padding: 25px; 
            border-radius: 12px; 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); 
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .alert-success {
            background: #dcfce7;
            color: #15803d;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #16a34a;
        }
    </style>
</head>
<body>

    <div class="admin-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>MyEvent</h2>
            </div>
            <nav class="menu">
                <a href="dashboard.php" class="menu-item active"><i class="fas fa-chart-line"></i> Dashboard</a>
                
                <a href="../browse_events.php" class="menu-item"><i class="fas fa-search"></i> Join New Events</a>
                
                <a href="profile.php" class="menu-item"><i class="fas fa-user-circle"></i> My Profile</a>
                <?php if ($user_role !== 'Public'): ?>
                    <a href="my_events.php" class="menu-item"><i class="fas fa-calendar-check"></i> Manage My Events</a>
                    <a href="create_event.php" class="menu-item"><i class="fas fa-plus-circle"></i> Create New Event</a>
                <?php endif; ?>
                <a href="my_registrations.php" class="menu-item"><i class="fas fa-ticket-alt"></i> My Registrations</a>
                <a href="../index.php" class="menu-item"><i class="fas fa-home"></i> Back to Site</a>
                <a href="../auth/logout.php" class="menu-item" style="color: #fb7185;"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
    <?php include ROOT_PATH . '/include/flash.php'; ?>
            <header style="padding: 20px; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin:0;">Welcome back, <?php echo e($user_name); ?>!</h2>
                <span style="background: #4f46e5; color: white; padding: 5px 15px; border-radius: 20px; font-weight: 600; font-size: 0.8rem;"><?= e($user_role) ?></span>
            </header>

            <div style="padding: 30px;">
                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i> Success! Your registration has been submitted for approval.
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-bottom: 40px;">
                    <div class="stat-card" style="border-left: 5px solid #4f46e5;">
                        <p style="color: #64748b; font-weight: 600; margin: 0;">Total System Events</p>
                        <h3 style="font-size: 2.5rem; margin: 10px 0; color: #1e293b;"><?= e($total_events) ?></h3>
                        <small style="color: #94a3b8;">Active events on site</small>
                    </div>
                    <div class="stat-card" style="border-left: 5px solid #10b981;">
                        <p style="color: #64748b; font-weight: 600; margin: 0;">My Registrations</p>
                        <h3 style="font-size: 2.5rem; margin: 10px 0; color: #1e293b;"><?= e($my_registrations) ?></h3>
                        <small style="color: #94a3b8;">Events you've joined</small>
                    </div>
                    <?php if ($user_role !== 'Public'): ?>
                    <div class="stat-card" style="border-left: 5px solid #f59e0b;">
                        <p style="color: #64748b; font-weight: 600; margin: 0;">Events I Organize</p>
                        <h3 style="font-size: 2.5rem; margin: 10px 0; color: #1e293b;"><?= e($my_created_events) ?></h3>
                        <small style="color: #94a3b8;">Managed by you</small>
                    </div>
                    <?php endif; ?>
                </div>

                <section style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <h3 style="margin-top: 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">Your Profile Overview</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding-top: 10px;">
                        <div>
                            <p style="color: #64748b; margin-bottom: 5px;">Account Category</p>
                            <p style="font-weight: 700; color: #1e293b;"><?= e($user_role) ?></p>
                        </div>
                        <div>
                            <p style="color: #64748b; margin-bottom: 5px;">Login Email</p>
                            <p style="font-weight: 700; color: #1e293b;"><?php echo e($user_email); ?></p>
                        </div>
                    </div>
                    <a href="profile.php" style="display: inline-block; margin-top: 20px; color: #4f46e5; text-decoration: none; font-weight: 700; border-bottom: 2px solid #4f46e5;">Edit Profile Details →</a>
                </section>
            </div>
        </main>
    </div>

</body>
</html>