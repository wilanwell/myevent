<?php
require_once __DIR__ . '/../../config.php'; 

if (session_status() === PHP_SESSION_NONE) session_start();

// Security Check
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'Admin') {
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {    
    // 1. Collect form data
    $event_name     = $_POST['event_name'];
    $description    = $_POST['description'];
    $category_id    = intval($_POST['category_id']);
    $contact_no     = $_POST['contact_no'] ?? ''; 
    $venue          = $_POST['venue'];
    $event_date     = $_POST['event_date'];
    $event_time     = $_POST['event_time'];           
    $mode           = $_POST['mode'];
    $reg_close      = $_POST['reg_close_date'];       
    $max_part       = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : 0; 
    $fee            = floatval($_POST['fee']);        
    $remarks        = $_POST['remarks'] ?? '';        
    
    // Check for user_id to prevent "Undefined array key" warning
    $user_id        = $_SESSION['user_id'] ?? NULL;      

    // 2. Handle file upload
    $filename = "";
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] == 0) {
        $upload_dir = ROOT_PATH . '/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $filename = time() . '_' . basename($_FILES["poster"]["name"]); 
        move_uploaded_file($_FILES["poster"]["tmp_name"], $upload_dir . $filename);
    }

    // 3. Insert into database based on your actual table columns
    $sql = "INSERT INTO events (
        event_name, description, category_id, contact_no, 
        venue, event_date, event_time, mode, reg_close_date, 
        max_participants, fee, remarks, poster_path, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssissssssidssi", 
        $event_name, $description, $category_id, $contact_no,
        $venue, $event_date, $event_time, $mode, $reg_close,
        $max_part, $fee, $remarks, $filename, $user_id
    );

    $message = "";
    if ($stmt->execute()) {
        $message = "✅ Event created successfully.";
    } else {
        $message = "❌ Error creating event: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Action Status | MyEvent Admin</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
  <style>
      body { font-family: 'Inter', sans-serif; background: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
      .status-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
      .btn-home { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; }
  </style>
</head>
<body>
<div class="status-card">
    <h2>Processing Result</h2>
    <p><?php echo $message; ?></p>
    <a href="manage.php" class="btn-home">Return to Manage Events</a>
</div>
</body>
</html>