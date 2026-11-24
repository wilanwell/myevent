<?php 
require_once __DIR__ . '/../../config.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();

// Security Check
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../auth/login.php");
    exit;
}
?> 

<!DOCTYPE html> 
<html lang="en"> 
<head> 
  <meta charset="UTF-8" /> 
  <meta name="viewport" content="width=device-width, initial-scale=1.0" /> 
  <title>Create Event | MyEvent Admin</title> 
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
      :root { --primary: #0d6efd; --danger: #dc3545; --sidebar-bg: #1e293b; --sidebar-text: #cbd5e1; --bg-body: #f1f5f9; }
      body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-body); display: flex; height: 100vh; overflow: hidden; }
      .sidebar { width: 250px; background: var(--sidebar-bg); display: flex; flex-direction: column; flex-shrink: 0; }
      .menu { display: flex; flex-direction: column; height: 100%; padding:0; margin:0; }
      .menu::before { content: 'MyEvent Admin'; display: block; padding: 20px; font-size: 1.1rem; font-weight: 700; color: #fff; background: #0f172a; }
      .menu-item, .submenu-toggle { padding: 12px 20px; color: var(--sidebar-text); display: flex; align-items: center; gap: 10px; font-size: 0.95rem; border: none; background: none; width: 100%; text-align: left; cursor: pointer; text-decoration: none; box-sizing: border-box; }
      .menu-item:hover { background: #334155; color: #fff; }
      .submenu-content { background: #0f172a; }
      .submenu-content a { display: block; padding: 10px 20px 10px 45px; color: var(--sidebar-text); font-size: 0.9rem; text-decoration: none; }
      .submenu-content a.active { color: #fff; background: var(--primary); }
      .menu-item.delete { margin-top: auto; background: var(--danger); color: white; }
      .main-content { flex: 1; padding: 30px; overflow-y: auto; }
      .form-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 900px; margin: 0 auto; }
      h2 { margin-top: 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-size: 1.25rem; }
      .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
      .form-group { margin-bottom: 15px; }
      label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; color: #334155; }
      input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.95rem; box-sizing: border-box; }
      .required::after { content: " *"; color: var(--danger); }
      .btn-submit { background: var(--primary); color: white; border: none; padding: 12px; border-radius: 4px; font-weight: 600; width: 100%; cursor: pointer; margin-top: 10px; }
  </style>
</head> 
<body> 
  <aside class="sidebar">
    <nav class="menu">
      <a href="../index.php" class="menu-item"><i class="fas fa-chart-pie"></i> Dashboard</a>
      <a href="../users/manage.php" class="menu-item"><i class="fas fa-users"></i> Users</a>
      <div class="submenu">
        <button class="submenu-toggle"><i class="fas fa-calendar-alt"></i> Events ▾</button>
        <div class="submenu-content">
          <a href="manage.php">Manage Events</a>
          <a href="create.php" class="active">Create Event</a>
        </div>
      </div>
      <a href="../../auth/logout.php" class="menu-item delete"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="main-content">
      <div class="form-card">
          <h2>Create New Event</h2>
          <form action="create_action.php" method="POST" enctype="multipart/form-data"> 
              <div class="form-grid">
                  <div class="form-group"> 
                      <label class="required">Event Name</label> 
                      <input type="text" name="event_name" required /> 
                  </div> 
                  <div class="form-group"> 
                      <label class="required">Type (Category)</label> 
                      <select name="category_id" required> 
                          <option value="">-- Select Type --</option> 
                          <option value="1">Workshop</option> 
                          <option value="2">Seminar</option> 
                          <option value="3">Competition</option> 
                          <option value="4">Festival</option> 
                          <option value="5">Sport</option> 
                          <option value="6">Course</option> 
                      </select> 
                  </div> 
              </div>

              <div class="form-group"> 
                  <label class="required">Description</label> 
                  <textarea name="description" rows="3" required></textarea> 
              </div> 

              <div class="form-grid">
                  <div class="form-group"> 
                      <label class="required">Venue</label> 
                      <input type="text" name="venue" required /> 
                  </div> 
                  <div class="form-group"> 
                      <label>Contact No</label> 
                      <input type="text" name="contact_no" placeholder="e.g. 012-3456789" /> 
                  </div> 
              </div>

              <div class="form-grid">
                  <div class="form-group"> 
                      <label class="required">Date</label> 
                      <input type="date" name="event_date" required /> 
                  </div> 
                  <div class="form-group"> 
                      <label class="required">Time</label> 
                      <input type="time" name="event_time" required /> 
                  </div> 
              </div>

              <div class="form-grid">
                  <div class="form-group"> 
                      <label class="required">Registration Close Date</label> 
                      <input type="date" name="reg_close_date" required /> 
                  </div> 
                  <div class="form-group"> 
                      <label class="required">Mode</label> 
                      <select name="mode" required> 
                          <option value="Physical">Physical</option> 
                          <option value="Online">Online</option> 
                          <option value="Hybrid">Hybrid</option> 
                      </select> 
                  </div> 
              </div>

              <div class="form-grid">
                  <div class="form-group"> 
                      <label class="required">Fee (RM)</label> 
                      <input type="number" step="0.01" name="fee" value="0.00" required /> 
                  </div> 
                  <div class="form-group"> 
                      <label>Maximum Participants</label> 
                      <input type="number" name="max_participants" /> 
                  </div> 
              </div>

              <div class="form-group"> 
                  <label>Event Poster</label> 
                  <input type="file" name="poster" accept="image/*" /> 
              </div> 

              <div class="form-group"> 
                  <label>Remarks / Notes</label> 
                  <textarea name="remarks" rows="2"></textarea> 
              </div> 

              <button type="submit" class="btn-submit">Publish Event</button> 
          </form> 
      </div>
  </main> 
</body> 
</html>