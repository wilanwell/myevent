<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config.php'; 

// 1. Security Check
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'Admin') { 
    die("Unauthorized access."); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2. Collect and Sanitize Data
    $event_id    = intval($_POST['event_id']);
    $event_name  = trim($_POST['event_name']);
    $description = trim($_POST['description']);
    $category_id = intval($_POST['category_id']); 
    $contact_no  = trim($_POST['contact_no'] ?? '');
    $venue       = trim($_POST['venue']);
    $event_date  = $_POST['date'];
    $event_time  = $_POST['time']; 
    $mode        = $_POST['mode']; 
    $reg_close   = $_POST['reg_close_date'];
    $fee         = floatval($_POST['fee']);
    $max_part    = intval($_POST['max_participants']);
    $remarks     = trim($_POST['remarks'] ?? '');

    // 3. Handle Poster Upload Logic
    $sql_old = "SELECT poster_path FROM events WHERE event_id = ?";
    $stmt_old = $conn->prepare($sql_old);
    $stmt_old->bind_param("i", $event_id);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $poster_path = $old_data['poster_path'] ?? '';

    if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = ROOT_PATH . '/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_name = time() . "_" . basename($_FILES["poster"]["name"]);
        
        if (move_uploaded_file($_FILES["poster"]["tmp_name"], $upload_dir . $file_name)) {
            if (!empty($poster_path) && file_exists($upload_dir . $poster_path)) {
                unlink($upload_dir . $poster_path);
            }
            $poster_path = $file_name;
        }
    }

    // 4. Update Database with ALL 13 fields (12 for SET + 1 for WHERE)
    $sql = "UPDATE events SET 
            event_name=?, description=?, category_id=?, contact_no=?, 
            venue=?, event_date=?, event_time=?, mode=?, reg_close_date=?, 
            fee=?, max_participants=?, remarks=?, poster_path=? 
            WHERE event_id=?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssissssssidssi",
        $event_name,
        $description,
        $category_id,
        $contact_no,
        $venue,
        $event_date,
        $event_time,
        $mode,
        $reg_close,
        $fee,
        $max_part,
        $remarks,
        $poster_path,
        $event_id
    );

    if ($stmt->execute()) {
        echo "<script>alert('✅ Event updated successfully.'); window.location.href='manage.php';</script>";
    } else {
        echo "<script>alert('❌ Error updating database: " . $stmt->error . "'); window.location.href='manage.php';</script>";
    }
    
    $stmt->close();
    $conn->close();
}
?>