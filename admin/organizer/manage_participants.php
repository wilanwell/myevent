<?php
// FILE: admin/organizer/manage_participants.php

// 1. Include Config
require_once __DIR__ . '/../../config.php'; 

require_admin();

// 3. GET EVENT ID
if (empty($_GET['event_id'])) {
    redirect('admin/index.php');
}
$event_id = intval($_GET['event_id']);

// 4. FETCH EVENT DETAILS
$evt_sql = "SELECT event_name FROM events WHERE event_id = ?";
$evt_stmt = $conn->prepare($evt_sql);
$evt_stmt->bind_param("i", $event_id);
$evt_stmt->execute();
$event_name = $evt_stmt->get_result()->fetch_assoc()['event_name'] ?? "Unknown_Event";

// 5. HANDLE CSV DOWNLOAD (Using attendance_status)
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $filename = safe_filename('Participants_' . $event_name);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Reg ID', 'Name', 'Email', 'Category', 'Status', 'Attendance']);
    
    $query = $conn->prepare("SELECT r.reg_id, u.name, u.email, u.category, r.approval_status, r.attendance_status FROM registrations r JOIN users u ON r.user_id = u.user_id WHERE r.event_id = ?");
    $query->bind_param("i", $event_id);
    $query->execute();
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) { fputcsv($output, csv_row($row)); }
    fclose($output);
    exit;
}

// 6. HANDLE ACTIONS (Approve / Reject / Attendance)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $registration_id = (int) ($_POST['participant_id'] ?? 0);
    $action = (string) $_POST['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE registrations SET approval_status = 'Approved' WHERE reg_id = ? AND event_id = ?");
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE registrations SET approval_status = 'Rejected' WHERE reg_id = ? AND event_id = ?");
    } elseif ($action === 'mark_present') {
        $stmt = $conn->prepare("UPDATE registrations SET attendance_status = 'Present' WHERE reg_id = ? AND event_id = ?");
    } elseif ($action === 'mark_absent') {
        $stmt = $conn->prepare("UPDATE registrations SET attendance_status = 'Absent' WHERE reg_id = ? AND event_id = ?");
    }
    
    if (isset($stmt)) {
        $stmt->bind_param("ii", $registration_id, $event_id);
        $stmt->execute();
    }
    redirect('admin/organizer/manage_participants.php?event_id=' . $event_id);
}

// 7. FETCH PARTICIPANTS
$sql = "SELECT r.reg_id, r.approval_status, r.attendance_status, u.name, u.email, u.category 
        FROM registrations r 
        JOIN users u ON r.user_id = u.user_id 
        WHERE r.event_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$participants = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Participants | NexEvent</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --bg: #f8fafc; --sidebar-bg: #1e293b; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); display: flex; min-height: 100vh; }
        
        /* MYEVENT SIDEBAR STYLE */
        .sidebar { width: 250px; background: var(--sidebar-bg); color: white; display: flex; flex-direction: column; position: fixed; height: 100%; }
        .sidebar-header h2 { color: #ffffff !important; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.3); padding: 20px; margin: 0; text-align: center; border-bottom: 1px solid #334155; font-size: 1.5rem; }
        .nav-links { list-style: none; padding: 0; margin-top: 20px; }
        .nav-links li a { display: flex; align-items: center; gap: 12px; padding: 15px 25px; color: #94a3b8; text-decoration: none; transition: 0.3s; font-weight: 500; }
        .nav-links li a:hover, .nav-links li a.active { background-color: var(--primary); color: white; }
        .nav-links li a i { width: 20px; text-align: center; }

        .main-content { margin-left: 250px; flex: 1; padding: 40px; box-sizing: border-box; }
        
        /* Table and UI Styling */
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: #64748b; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; padding: 16px 24px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; }
        
        .btn-download { background: #ffffff; color: #334155; border: 1px solid #e2e8f0; padding: 10px 0; width: 130px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; cursor: pointer; }
        .btn-download:hover { background: #f8fafc; border-color: #cbd5e1; }

        .text-center { text-align: center !important; }
        .action-flex-container { display: flex; gap: 8px; justify-content: center; align-items: center; }

        .badge { padding: 6px 12px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-Approved { background: #dcfce7; color: #15803d; }
        .badge-Pending { background: #fef3c7; color: #92400e; }
        .badge-Rejected { background: #fee2e2; color: #991b1b; }
        .badge-Present { background: #e0e7ff; color: #4338ca; }
        .badge-Absent { background: #f1f5f9; color: #64748b; }
        
        .btn-action { border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; color: white; font-weight: 600; font-size: 0.8rem; transition: 0.2s; }
        
        @media print { .sidebar, .btn-action, .download-section, form, .back-btn { display: none !important; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header"><h2>MyEvent</h2></div>
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
            <li><a href="../users/manage.php"><i class="fa-solid fa-users"></i> Manage Users</a></li>
            <li><a href="../events/manage.php" class="active"><i class="fa-solid fa-calendar-days"></i> Events</a></li>
            <li><a href="../../auth/logout.php" style="color:#fb7185; margin-top: auto; border-top: 1px solid #334155;"><i class="fa-solid fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
    <?php include ROOT_PATH . '/include/flash.php'; ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 style="margin: 0; font-size: 1.8rem;">Participant Management</h1>
                <p style="color: #64748b; margin-top: 5px;">Event: <strong><?= e($event_name); ?></strong></p>
            </div>
            <div class="download-section" style="display: flex; gap: 12px;">
                <a href="?event_id=<?= e($event_id) ?>&download=csv" class="btn-download">
                    <i class="fas fa-file-csv" style="color: #059669;"></i> Export CSV
                </a>
                <button onclick="window.print();" class="btn-download">
                    <i class="fas fa-file-pdf" style="color: #e11d48;"></i> Export PDF
                </button>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 30%;">Participant</th>
                        <th class="text-center">Approval Status</th>
                        <th class="text-center">Attendance</th>
                        <th class="text-center download-section">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($participants->num_rows > 0): ?>
                        <?php while($row = $participants->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #1e293b;"><?= e($row['name']); ?></div>
                                    <div style="font-size: 0.85rem; color: #64748b;"><?= e($row['email']); ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-<?= e($row['approval_status']) ?>"><?= e($row['approval_status']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-<?= $row['attendance_status'] ?: 'Absent'; ?>">
                                        <?= $row['attendance_status'] ?: 'Absent'; ?>
                                    </span>
                                </td>
                                <td class="download-section">
                                    <div class="action-flex-container">
                                        <form method="POST" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="participant_id" value="<?= e($row['reg_id']) ?>">
                                            <?php if($row['attendance_status'] !== 'Present'): ?>
                                                <button type="submit" name="action" value="mark_present" class="btn-action" style="background: #4f46e5;">Present</button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="mark_absent" class="btn-action" style="background: #94a3b8;">Absent</button>
                                            <?php endif; ?>
                                        </form>

                                        <form method="POST" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="participant_id" value="<?= e($row['reg_id']) ?>">
                                            <?php if($row['approval_status'] !== 'Approved'): ?>
                                                <button type="submit" name="action" value="approve" class="btn-action" style="background: #10b981;">Approve</button>
                                            <?php endif; ?>
                                            <?php if($row['approval_status'] !== 'Rejected'): ?>
                                                <button type="submit" name="action" value="reject" class="btn-action" style="background: #ef4444;" onclick="return confirm('Reject registration?')">Reject</button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 50px; color: #94a3b8;">
                                <i class="fas fa-users-slash" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                                No participants found for this event.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>