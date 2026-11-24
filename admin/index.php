<?php 
// 1. Start Session & Include Config
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php'; 

// 2. SECURITY CHECK
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../users/dashboard.php");
    exit;
}

$admin_id = $_SESSION['user_id'] ?? $_SESSION['id'];

/**
 * 3. HANDLE NOTIFICATIONS & STATUS UPDATES
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action_type']) && isset($_POST['reg_id'])) {
        $reg_id = intval($_POST['reg_id']);
        $action = $_POST['action_type'];
        $sql = "";
        $notif_message = "";

        $info_query = $conn->prepare("SELECT r.user_id, e.event_name FROM registrations r JOIN events e ON r.event_id = e.event_id WHERE r.reg_id = ?");
        $info_query->bind_param("i", $reg_id);
        $info_query->execute();
        $info = $info_query->get_result()->fetch_assoc();
        $target_student = $info['user_id'];
        $event_name = $info['event_name'];

        if ($action == 'approve') {
            $sql = "UPDATE registrations SET approval_status='Approved' WHERE reg_id=?";
            $notif_message = "✅ Your registration for <strong>$event_name</strong> has been Approved!";
        } elseif ($action == 'reject') {
            $sql = "UPDATE registrations SET approval_status='Rejected' WHERE reg_id=?";
            $notif_message = "❌ Your registration for <strong>$event_name</strong> was Rejected.";
        }

        if ($sql != "") {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $reg_id);
            if ($stmt->execute() && !empty($notif_message)) {
                $ins_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $ins_notif->bind_param("is", $target_student, $notif_message);
                $ins_notif->execute();
            }
        }
    }

    if (isset($_POST['delete_event_id'])) {
        $delete_id = intval($_POST['delete_event_id']);
        $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ? AND created_by = ?");
        $stmt->bind_param("ii", $delete_id, $admin_id);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * 4. FETCH STATISTICS
 */
$total_students = $conn->query("SELECT COUNT(*) as total FROM users WHERE category = 'Student'")->fetch_assoc()['total'];
$total_staff = $conn->query("SELECT COUNT(*) as total FROM users WHERE category = 'Staff'")->fetch_assoc()['total'];
$total_events = $conn->query("SELECT COUNT(*) as total FROM events")->fetch_assoc()['total'];
$total_participants = $conn->query("SELECT COUNT(*) as total FROM registrations")->fetch_assoc()['total'];

/**
 * 5. FETCH EVENTS ASSIGNED TO ADMIN
 */
$sql_my_events = "SELECT e.event_id, e.event_name, e.event_date, e.venue, e.poster_path, e.mode, e.max_participants, c.categoryName,
                  (SELECT COUNT(*) FROM registrations WHERE event_id = e.event_id AND approval_status = 'Approved') as current_reg 
                  FROM events e 
                  LEFT JOIN event_category c ON e.category_id = c.category_id 
                  WHERE e.created_by = ?";
$stmt = $conn->prepare($sql_my_events);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$my_events = $stmt->get_result();
?> 

<!DOCTYPE html> 
<html lang="en"> 
<head> 
  <meta charset="UTF-8" /> 
  <meta name="viewport" content="width=device-width, initial-scale=1.0" /> 
  <title>Admin Dashboard | <?php echo APP_NAME; ?></title> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
      :root { --primary: #4f46e5; --sidebar-bg: #1e293b; --bg-body: #f1f5f9; }
      body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); display: flex; min-height: 100vh; }
      .sidebar { width: 250px; background: var(--sidebar-bg); color: white; display: flex; flex-direction: column; position: fixed; height: 100%; }
      .sidebar-header h2 { color: #ffffff !important; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.3); padding: 20px; margin: 0; text-align: center; border-bottom: 1px solid #334155; }
      .nav-links { list-style: none; padding: 0; margin-top: 20px; }
      .nav-links li a { display: block; padding: 15px 25px; color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; cursor: pointer; }
      .nav-links li a:hover, .nav-links li a.active { background-color: var(--primary); color: white; }
      .submenu-content { display: none; background: rgba(0,0,0,0.2); list-style: none; padding: 0; }
      .submenu.open .submenu-content { display: block; }
      .submenu-content a { padding: 10px 25px 10px 50px !important; font-size: 0.9rem; }
      .main-content { margin-left: 250px; padding: 30px; width: 100%; box-sizing: border-box; }
      .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
      .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
      .number { font-size: 2rem; font-weight: 800; color: #1e293b; }
      .table-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow-x: auto; }
      table { width: 100%; border-collapse: collapse; }
      th, td { padding: 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
      th { background: #f8fafc; color: #64748b; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
      .btn-manage { background: var(--primary); color: white; padding: 8px 16px; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 0.85rem; }
      .btn-delete { background: #dc2626; color: white; padding: 8px 16px; border-radius: 6px; font-weight: 600; border: none; font-size: 0.85rem; cursor: pointer; }
      .poster-img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; }
  </style>
</head> 
<body> 

  <nav class="sidebar">
    <div class="sidebar-header"><h2>MyEvent</h2></div>
    <ul class="nav-links">
      <li><a href="index.php" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
      
      <li><a href= "profile.php"><i class="fa-solid fa-user-circle"></i> My Profile</a></li>
      
      <li><a href="users/manage.php"><i class="fa-solid fa-users"></i> Manage Users</a></li>
      <li class="submenu">
        <a onclick="toggleMenu(this)"><i class="fa-solid fa-calendar-days"></i> Events <i class="fa-solid fa-chevron-down" style="font-size:0.7rem; margin-left:auto;"></i></a>
        <ul class="submenu-content">
          <li><a href="events/manage.php">All Events</a></li>
          <li><a href="events/create.php">Create New</a></li>
        </ul>
      </li>
      <li><a href="../auth/logout.php" style="color:#fb7185; margin-top:20px; border-top:1px solid #334155;"><i class="fa-solid fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </nav>

  <main class="main-content">
      <div class="header">
          <h1>Admin Dashboard</h1>
          <p style="color: #64748b;">Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Administrator') ?></p>
      </div>

      <div class="stats-grid">
          <div class="card">
              <div><p style="color:#64748b; margin:0;">Total Students</p><div class="number"><?= $total_students ?></div></div>
              <i class="fas fa-user-graduate fa-2x" style="color:#4f46e5; opacity:0.2;"></i>
          </div>
          <div class="card">
              <div><p style="color:#64748b; margin:0;">Total Staff</p><div class="number"><?= $total_staff ?></div></div>
              <i class="fas fa-user-tie fa-2x" style="color:#6366f1; opacity:0.2;"></i>
          </div>
          <div class="card">
              <div><p style="color:#64748b; margin:0;">Active Events</p><div class="number"><?= $total_events ?></div></div>
              <i class="fas fa-calendar-check fa-2x" style="color:#10b981; opacity:0.2;"></i>
          </div>
          <div class="card">
              <div><p style="color:#64748b; margin:0;">Total Registrations</p><div class="number"><?= $total_participants ?></div></div>
              <i class="fas fa-users fa-2x" style="color:#f59e0b; opacity:0.2;"></i>
          </div>
      </div>

      <div class="table-card">
          <h3 style="margin-top:0; margin-bottom:20px;">My Assigned Events</h3>
          <?php if($my_events->num_rows > 0): ?>
              <table>
                  <thead>
                      <tr>
                        <th>ID</th>
                        <th>Poster</th>
                        <th>Event Name</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Mode</th>
                        <th>Capacity</th>
                        <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php while($row = $my_events->fetch_assoc()): ?>
                          <tr>
                              <td>#<?= $row['event_id']; ?></td>
                              <td>
                                <?php if(!empty($row['poster_path']) && file_exists("../uploads/" . $row['poster_path'])): ?>
                                    <img src="../uploads/<?= $row['poster_path']; ?>" class="poster-img">
                                <?php else: ?>
                                    <div style="width:50px; height:50px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; border-radius:4px;"><i class="fa-solid fa-image text-muted"></i></div>
                                <?php endif; ?>
                              </td>
                              <td><strong><?= htmlspecialchars($row['event_name']); ?></strong></td>
                              <td><?= htmlspecialchars($row['categoryName'] ?? 'Uncategorized'); ?></td>
                              <td><?= date('M d, Y', strtotime($row['event_date'])); ?></td>
                              <td><?= htmlspecialchars($row['venue']); ?></td>
                              <td><span class="badge" style="background: #e0e7ff; color: #4338ca; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;"><?= htmlspecialchars($row['mode']); ?></span></td>
                              
                              <td><?= $row['current_reg']; ?> / <?= ($row['max_participants'] > 0) ? $row['max_participants'] : '∞'; ?></td>
                              
                              <td style="display: flex; gap: 8px;">
                                  <a href="organizer/manage_participants.php?event_id=<?= $row['event_id']; ?>" class="btn-manage">Manage</a>
                                  
                                  <form method="POST" onsubmit="return confirm('Permanently delete this event?');" style="display:inline;">
                                      <input type="hidden" name="delete_event_id" value="<?= $row['event_id']; ?>">
                                      <button type="submit" class="btn-delete">Delete</button>
                                  </form>
                              </td>
                          </tr>
                      <?php endwhile; ?>
                  </tbody>
              </table>
          <?php else: ?>
              <p style="text-align:center; color:#94a3b8; padding:20px;">No events created by you yet.</p>
          <?php endif; ?>
      </div>
  </main>

  <script>
      function toggleMenu(link) {
          link.parentElement.classList.toggle('open');
      }
  </script>

</body> 
</html>