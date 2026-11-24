<?php
/**
 * MyEvent - Organiser: manage participants of the organiser's OWN events.
 */
require_once __DIR__ . '/../config.php';

require_login();

$user_id  = current_user_id();
$event_id = (int) ($_GET['event_id'] ?? 0);

// Ownership check: organisers may only see/manage their own events.
if ($event_id) {
    $own = $conn->prepare('SELECT 1 FROM events WHERE event_id = ? AND created_by = ?');
    $own->bind_param('ii', $event_id, $user_id);
    $own->execute();
    $isOwner = (bool) $own->get_result()->fetch_row();
    $own->close();
    if (!$isOwner) {
        flash('error', 'You can only manage participants of events you organise.');
        redirect('users/manage_participants.php');
    }
}

// 2. HANDLE ACTIONS (POST Logic)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ACTION: UPDATE STATUS (Toggles Attendance or Approval)
    if (isset($_POST['action_type']) && isset($_POST['reg_id'])) {
        $reg_id = (int) $_POST['reg_id'];
        $action = (string) $_POST['action_type'];
        $sql = "";
        $notif_message = "";

        // Fetch Student ID and Event Name for the notification
        $info_query = $conn->prepare("SELECT r.user_id, e.event_name FROM registrations r JOIN events e ON r.event_id = e.event_id WHERE r.reg_id = ? AND r.event_id = ? AND e.created_by = ?");
        $info_query->bind_param("iii", $reg_id, $event_id, $user_id);
        $info_query->execute();
        $info = $info_query->get_result()->fetch_assoc();
        $target_student = $info['user_id'] ?? null;
        $event_name = $info['event_name'] ?? '';

        if ($action == 'approve') {
            $sql = "UPDATE registrations SET approval_status='Approved' WHERE reg_id=?";
            $notif_message = "✅ Your registration for <strong>$event_name</strong> has been Approved!";
        } elseif ($action == 'reject') {
            $sql = "UPDATE registrations SET approval_status='Rejected' WHERE reg_id=?";
            $notif_message = "❌ Your registration for <strong>$event_name</strong> was Rejected.";
        } elseif ($action == 'present') {
            $sql = "UPDATE registrations SET attendance_status='Present' WHERE reg_id=?";
        } elseif ($action == 'absent') {
            $sql = "UPDATE registrations SET attendance_status='Absent' WHERE reg_id=?";
        }

        if ($sql != "" && $info) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $reg_id);
            if ($stmt->execute() && !empty($notif_message)) {
                $ins_notif = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
                $ins_notif->bind_param("is", $target_student, $notif_message);
                $ins_notif->execute();
            }
            $stmt->close();
        }
    }

    // ACTION: EXPORT CSV
    if (isset($_POST['export_csv']) && $event_id) {
        ob_clean(); 
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="participants_list.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Name', 'Email', 'Registration Date', 'Approval Status', 'Attendance'));
        
        $sql = "SELECT u.name, u.email, r.registration_date, r.approval_status, r.attendance_status 
                FROM registrations r
                JOIN users u ON r.user_id = u.user_id
                WHERE r.event_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $rows = $stmt->get_result();
        
        while ($row = $rows->fetch_assoc()) {
            fputcsv($output, csv_row($row));
        }
        fclose($output);
        exit();
    }

    // Post/Redirect/Get
    redirect('users/manage_participants.php' . ($event_id ? '?event_id=' . $event_id : ''));
}

// 3. FETCH ORGANIZED EVENTS (For Dropdown)
$my_events = $conn->prepare("SELECT event_id, event_name FROM events WHERE created_by = ?");
$my_events->bind_param("i", $user_id);
$my_events->execute();
$events_list = $my_events->get_result();

// 4. FETCH PARTICIPANTS
$participants = null;
if ($event_id) {
    // JOIN with events table to get event_name for each row
    $sql = "SELECT r.reg_id, u.name, u.email, e.event_name, r.registration_date, r.approval_status, r.attendance_status 
            FROM registrations r 
            JOIN users u ON r.user_id = u.user_id 
            JOIN events e ON r.event_id = e.event_id
            WHERE r.event_id = ? ORDER BY r.registration_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $participants = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Participants | <?php echo APP_NAME; ?></title>
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
        .table-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow-x: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .checklist-btn { background: none; border: none; cursor: pointer; padding: 0; font-size: 1.6rem; transition: 0.2s; }
        .fa-check-square { color: #22c55e; }
        .fa-square { color: #cbd5e1; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-approved { background: #dcfce7; color: #15803d; }
        .badge-pending { background: #fff7ed; color: #c2410c; }
        .badge-rejected { background: #fee2e2; color: #b91c1c; }
        .btn-export { background: #10b981; color: white; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header"><h2>MyEvent</h2></div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
            <li><a href="my_events.php"><i class="fa-solid fa-calendar-check"></i> My Events</a></li>
            <li><a href="manage_participants.php" class="active"><i class="fa-solid fa-users-gear"></i> Participants</a></li>
            <li><a href="../auth/logout.php" style="color:#fb7185;"><i class="fa-solid fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <div class="main-content">
        <?php include ROOT_PATH . '/include/flash.php'; ?>
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>Manage Participants</h2>
            <form method="GET">
                <select name="event_id" onchange="this.form.submit()" style="padding:10px; border-radius:6px; border:1px solid #cbd5e1;">
                    <option value="">-- Select Event --</option>
                    <?php while($e = $events_list->fetch_assoc()): ?>
                        <option value="<?= e($e['event_id']) ?>" <?= ($event_id == $e['event_id']) ? 'selected' : '' ?>><?= e($e['event_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>

        <?php if ($participants): ?>
        <div class="table-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:10px; border-bottom:1px solid #f1f5f9;">
                <h3 style="margin:0;"><i class="fa-solid fa-users"></i> Registered (<?= $participants->num_rows ?>)</h3>
                
                <form method="POST">
                    <?= csrf_field() ?>
                    <button type="submit" name="export_csv" class="btn-export">
                        <i class="fa-solid fa-file-excel"></i> Export CSV
                    </button>
                </form>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Event Name</th>
                        <th>Status</th>
                        <th style="text-align:center;">Attendance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $participants->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['name']) ?></strong><br>
                            <small style="color: #64748b;"><?= e($row['email']) ?></small>
                        </td>

                        <td><small><?= e($row['event_name']) ?></small></td>

                        <td>
                            <?php 
                                $status_class = 'badge-pending';
                                if($row['approval_status'] == 'Approved') $status_class = 'badge-approved';
                                if($row['approval_status'] == 'Rejected') $status_class = 'badge-rejected';
                            ?>
                            <span class="badge <?= e($status_class) ?>"><?= e($row['approval_status']) ?></span>
                        </td>

                        <td style="text-align:center;">
                            <?php if($row['approval_status'] == 'Approved'): ?>
                                <form method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reg_id" value="<?= e($row['reg_id']) ?>">
                                    <button type="submit" name="action_type" value="<?= ($row['attendance_status']=='Present')?'absent':'present' ?>" class="checklist-btn">
                                        <i class="<?= ($row['attendance_status']=='Present')?'fas fa-check-square':'far fa-square' ?>"></i>
                                    </button>
                                </form>
                            <?php else: ?> 
                                <i class="far fa-square" style="color:#e2e8f0; font-size:1.6rem;"></i> 
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if($row['approval_status'] == 'Pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reg_id" value="<?= e($row['reg_id']) ?>">
                                    <button type="submit" name="action_type" value="approve" style="color:#15803d; border:none; background:none; cursor:pointer; font-size:1.2rem;" title="Approve"><i class="fa-solid fa-circle-check"></i></button>
                                    <button type="submit" name="action_type" value="reject" style="color:#b91c1c; border:none; background:none; cursor:pointer; font-size:1.2rem;" title="Reject"><i class="fa-solid fa-circle-xmark"></i></button>
                                </form>
                            <?php else: ?> 
                                <small style="color:#94a3b8;">Decision Made</small> 
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>