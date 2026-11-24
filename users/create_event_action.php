<?php
/**
 * MyEvent - Create Event Action Logic
 * ----------------------------------
 */

// 1. Include global configuration
require_once '../config.php';

// 2. Access Control: Ensure only Staff/Student can perform this action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['loggedin'])) {
    
    // 3. Capture and Sanitize Mandatory Fields 
    $event_name     = trim($_POST['event_name']);
    $description    = trim($_POST['description']);
    $category_id    = intval($_POST['category_id']);
    $venue          = trim($_POST['venue']);
    $event_date     = $_POST['event_date'];
    $event_time     = $_POST['event_time'];
    $mode           = $_POST['mode']; // Physical, online, hybrid
    $reg_close_date = $_POST['reg_close_date'];
    $fee            = trim($_POST['fee']);
    $created_by     = $_SESSION['id'];

    // Optional Fields 
    $max_participants = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : NULL;
    $remarks          = trim($_POST['remarks'] ?? '');

    /**
     * 4. Handle Event Poster Upload.
     */
    $poster_name = ""; 
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] === 0) {
        $target_dir = "../uploads/";
        
        // Generate a unique file name to avoid overwriting
        $file_ext = pathinfo($_FILES["poster"]["name"], PATHINFO_EXTENSION);
        $poster_name = time() . "_" . rand(1000, 9999) . "." . $file_ext;
        $target_file = $target_dir . $poster_name;

        // Validation for image upload
        if (!move_uploaded_file($_FILES["poster"]["tmp_name"], $target_file)) {
            echo "<script>alert('Error: Failed to upload poster image.'); window.history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('Error: Event poster is required.'); window.history.back();</script>";
        exit;
    }

    /**
     * 5. Database Integration. 
     * Insert the data into the 'events' table including new required fields.
     */
    $sql = "INSERT INTO events (event_name, description, category_id, venue, event_date, event_time, mode, reg_close_date, max_participants, remarks, poster_path, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssisssssisss", 
            $event_name, $description, $category_id, $venue, $event_date, $event_time,
            $mode, $reg_close_date, $max_participants, $remarks, $poster_name, $created_by
        );

        if ($stmt->execute()) {
            // Success notification and redirect to event list
            echo "<script>alert('Success: Event created successfully!'); window.location.href='my_events.php';</script>";
        } else {
            echo "Database Error: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    header("location: create_event.php");
    exit;
}
$conn->close();
?>