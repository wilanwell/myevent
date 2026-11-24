<?php
require_once __DIR__ . '/../config.php';

require_login();

$user_id = current_user_id();
$role = $_SESSION['role'] ?? 'User';

// --- HANDLE ACTIONS (POST, CSRF-checked in config.php) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_id = (int) ($_POST['event_id'] ?? 0);

    // Delete an event the user owns (registrations cascade).
    if (isset($_POST['delete_event'])) {
        $stmt = $conn->prepare('SELECT poster_path FROM events WHERE event_id = ? AND created_by = ?');
        $stmt->bind_param('ii', $target_id, $user_id);
        $stmt->execute();
        $owned = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($owned) {
            $stmt = $conn->prepare('DELETE FROM events WHERE event_id = ? AND created_by = ?');
            $stmt->bind_param('ii', $target_id, $user_id);
            $stmt->execute();
            $stmt->close();
            delete_upload($owned['poster_path'], POSTER_DIR);
            flash('success', 'Event deleted.');
        } else {
            flash('error', 'You can only delete events you organise.');
        }
    }

    // Leave an event the user registered for.
    if (isset($_POST['unregister_event'])) {
        $stmt = $conn->prepare('DELETE FROM registrations WHERE event_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $target_id, $user_id);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Your registration has been cancelled.');
    }

    redirect('users/my_events.php');
}

// --- FETCH DATA ---

// 5. Organized Events
$result_created = null;
if ($role !== 'Public') {
    $sql_created = "SELECT e.*, c.categoryName,
                    (SELECT COUNT(*) FROM registrations WHERE event_id = e.event_id) as current_reg 
                    FROM events e 
                    LEFT JOIN event_category c ON e.category_id = c.category_id
                    WHERE e.created_by = ? 
                    ORDER BY e.event_date DESC";
    $stmt = $conn->prepare($sql_created);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_created = $stmt->get_result();
    $stmt->close();
}

// 6. Joined Events
$sql_joined = "SELECT e.*, r.registration_date, r.approval_status, u.name as organizer_name, c.categoryName 
               FROM registrations r 
               JOIN events e ON r.event_id = e.event_id 
               LEFT JOIN users u ON e.created_by = u.user_id
               LEFT JOIN event_category c ON e.category_id = c.category_id
               WHERE r.user_id = ?  
               ORDER BY e.event_date DESC";
$stmt = $conn->prepare($sql_joined);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_joined = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Events | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --sidebar-bg: #1e293b; --bg-body: #f1f5f9; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: var(--sidebar-bg); color: white; display: flex; flex-direction: column; position: fixed; height: 100%; }
        .sidebar-header h2 { color: #ffffff !important; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.3); padding: 20px; margin: 0; text-align: center; border-bottom: 1px solid #334155; }
        .nav-links { list-style: none; padding: 0; margin-top: 20px; }
        .nav-links li a { display: block; padding: 15px 25px; color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-links li a:hover, .nav-links li a.active { background-color: var(--primary); color: white; }
        .main-content { margin-left: 250px; padding: 30px; width: 100%; box-sizing: border-box; }
        .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .table-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow-x: auto; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .poster-thumb { width: 45px; height: 45px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-approved { background: #dcfce7; color: #15803d; }
        .badge-pending { background: #fff7ed; color: #c2410c; }
        .btn-create { background: var(--primary); color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        
        /* Flexbox for Actions Column */
        .action-btns { display: flex; gap: 15px; align-items: center; white-space: nowrap; }
        .action-btns form { margin: 0; display: inline-flex; align-items: center; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header"><h2>MyEvent</h2></div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
            <li><a href="my_events.php" class="active"><i class="fa-solid fa-calendar-check"></i> My Events</a></li>
            <li><a href="../auth/logout.php" style="color:#fb7185;"><i class="fa-solid fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <div class="main-content">
        <?php include ROOT_PATH . '/include/flash.php'; ?>
        <div class="header-row">
            <h2>My Events Dashboard</h2>
            <?php if($role !== 'Public'): ?>
                <a href="create_event.php" class="btn-create">+ Create Event</a>
            <?php endif; ?>
        </div>
        

        <h3><i class="fa-solid fa-pen-ruler"></i> Events I Organized</h3>
        <div class="table-card">
            <?php if ($result_created && $result_created->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Poster</th><th>Event Name</th><th>Category</th><th>Visibility</th><th>Capacity</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result_created->fetch_assoc()): ?>
                            <tr>
                                <td><?= e($row['event_id']) ?></td>
                                <td>
                                    <img src="<?= e(poster_url($row['poster_path'])) ?>" class="poster-thumb" alt="<?= e($row['event_name'] ?? 'Event poster') ?>" loading="lazy">
                                </td>
                                <td><strong><?= e($row['event_name']); ?></strong><br><small><?= date('M d, Y', strtotime($row['event_date'])); ?></small></td>
                                <td><?= e($row['categoryName'] ?? 'General'); ?></td>
                                <td><?= e($row['visibility']) ?></td>
                                <td><?= e($row['current_reg']) ?> / <?= ($row['max_participants'] > 0) ? $row['max_participants'] : '∞'; ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="edit_event.php?id=<?= e($row['event_id']) ?>" style="color:#2563eb;" title="Edit Event">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="manage_participants.php?event_id=<?= e($row['event_id']) ?>" style="color:#059669;" title="Manage Participants">
                                            <i class="fa-solid fa-users-viewfinder"></i>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Permanently delete this event?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="event_id" value="<?= e($row['event_id']) ?>">
                                            <button type="submit" name="delete_event" style="border:none; background:none; color:#dc2626; cursor:pointer; padding:0; font-size: 1rem;">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No organized events found.</p>
            <?php endif; ?>
        </div>

        <h3><i class="fa-solid fa-ticket"></i> Events I Joined</h3>
        <div class="table-card">
            <?php if ($result_joined && $result_joined->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Poster</th><th>Event Name</th><th>Organizer</th><th>Category</th><th>Date</th><th>Status</th><th>Ticket</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result_joined->fetch_assoc()): ?>
                            <tr>
                                <td><?= e($row['event_id']) ?></td>
                                <td>
                                    <img src="<?= e(poster_url($row['poster_path'])) ?>" class="poster-thumb" alt="<?= e($row['event_name'] ?? 'Event poster') ?>" loading="lazy">
                                </td>
                                <td><strong><?= e($row['event_name']); ?></strong></td>
                                <td><?= e($row['organizer_name'] ?? 'Admin'); ?></td>
                                <td><?= e($row['categoryName'] ?? 'General'); ?></td>
                                <td><?= date('M d, Y', strtotime($row['event_date'])); ?></td>
                                <td>
                                    <?php 
                                        $s = $row['approval_status'];
                                        echo "<span class='badge ".($s=='Approved'?'badge-approved':'badge-pending')."'>$s</span>";
                                    ?>
                                </td>
                                <td>
                                    <?php if($row['approval_status'] == 'Approved'): ?>
                                        <a href="generate_ticket.php?event_id=<?= e($row['event_id']) ?>" target="_blank" style="color: #4f46e5; text-decoration:none; font-weight:bold;">
                                            <i class="fa-solid fa-file-pdf"></i> Get Ticket
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #cbd5e1;"><i class="fa-solid fa-lock"></i> Locked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Unregister?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="event_id" value="<?= e($row['event_id']) ?>">
                                        <button type="submit" name="unregister_event" style="border:none; background:none; color:#dc2626; cursor:pointer; font-weight:600; padding:0;">Unregister</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No joined events found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>