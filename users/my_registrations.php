<?php

require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Access Control
if (!isset($_SESSION['loggedin'])) { header("location: ../auth/login.php"); exit; }

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'];
$user_role = $_SESSION['role'] ?? 'User';

// Fetch joined events with specific details: date, venue, mode, and status
$sql = "SELECT e.event_name, e.event_date, e.venue, e.mode, r.approval_status, r.event_id 
        FROM registrations r 
        JOIN events e ON r.event_id = e.event_id 
        WHERE r.user_id = ? 
        ORDER BY e.event_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Registrations | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_PATH_CSS; ?>admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; margin: 0; display: flex; }
        
        /* Sidebar Styling for high-contrast branding */
        .sidebar { width: 250px; background: #1e293b; color: white; min-height: 100vh; position: fixed; }
        .sidebar-header h2 { color: #ffffff !important; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        
        .main-content { margin-left: 250px; flex-grow: 1; padding: 40px; width: 100%; }
        
        /* Registration Card Layout */
        .reg-card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .event-info-grid { display: flex; gap: 20px; color: #64748b; font-size: 0.9rem; margin-top: 8px; }
        
        /* Status Badges */
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .status-Pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .status-Approved { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .mode-tag { background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.8rem; }
        
        /* Cancel Action */
        .btn-cancel { color: #ef4444; background: #fff; border: 1px solid #fecaca; padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: 0.3s; font-weight: 600; }
        .btn-cancel:hover { background: #fee2e2; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header" style="padding: 20px;">
            <h2><?php echo APP_NAME; ?></h2>
        </div>

        <nav class="menu">
            <a href="dashboard.php" class="menu-item"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a href="profile.php" class="menu-item"><i class="fas fa-user-circle"></i> My Profile</a>
            
            <?php if ($user_role !== 'Public'): ?>
                <a href="my_events.php" class="menu-item"><i class="fas fa-calendar-check"></i> Manage My Events</a>
                <a href="create_event.php" class="menu-item"><i class="fas fa-plus-circle"></i> Create New Event</a>
            <?php endif; ?>
            
            <a href="my_registrations.php" class="menu-item active"><i class="fas fa-ticket-alt"></i> My Registrations</a>
            
            <a href="../index.php" class="menu-item"><i class="fas fa-home"></i> Back to Site</a>
            <a href="../auth/logout.php" class="menu-item" style="color: #fb7185;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <div class="main-content">
        <h2 style="color: #1e293b;">My Registered Events</h2>
        <p style="color: #64748b; margin-bottom: 30px;">Track your status and manage your attendance list.</p>

        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="reg-card">
                    <div style="flex: 1;">
                        <span class="mode-tag"><i class="fas fa-vr-cardboard"></i> <?= $row['mode'] ?></span>
                        <h3 style="margin: 10px 0 5px 0; color: #1e293b;"><?= htmlspecialchars($row['event_name']) ?></h3>
                        
                        <div class="event-info-grid">
                            <span><i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($row['event_date'])) ?></span>
                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($row['venue']) ?></span>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 25px;">
                        <span class="status-badge status-<?= $row['approval_status'] ?>">
                            <?= $row['approval_status'] ?>
                        </span>
                        
                        <form action="my_events.php" method="POST" onsubmit="return confirm('Cancel this registration? This spot will be released.');">
                            <input type="hidden" name="event_id" value="<?= $row['event_id'] ?>">
                            <button type="submit" name="unregister_event" class="btn-cancel">
                                <i class="fas fa-times-circle"></i> Cancel Spot
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 60px; background: white; border-radius: 12px; border: 2px dashed #e2e8f0;">
                <i class="fas fa-calendar-times fa-3x" style="color: #cbd5e1; margin-bottom: 15px;"></i>
                <p style="color: #64748b; font-size: 1.1rem;">You haven't joined any events yet.</p>
                <a href="../browse_events.php" style="color: #4f46e5; font-weight: bold; text-decoration: none; border-bottom: 2px solid #4f46e5;">Explore Events Now →</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>