<?php

// 1. Include global configuration (Includes session_start() and $conn)
require_once '../config.php';

// 2. Access Control: Ensure only authenticated users can trigger registration 
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo "<script>alert('Please login to register for this event.'); window.location.href='../auth/login.php';</script>";
    exit;
}

// 3. Input Validation: Ensure the event_id is received via POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['event_id'])) {
    $user_id = $_SESSION['user_id'] ?? $_SESSION['id']; // Support both session naming conventions
    $event_id = intval($_POST['event_id']); 

    /**
     * 4. Duplicate Check
     * Prevents double-booking in the 'registrations' table.
     */
    $check_sql = "SELECT reg_id FROM registrations WHERE user_id = ? AND event_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        echo "<script>alert('You are already registered for this event!'); window.location.href='../index.php';</script>";
        exit;
    }
    $stmt->close();

    /**
     * 5. Data Insertion & Organizer Notification
     */
    $insert_sql = "INSERT INTO registrations (user_id, event_id, approval_status) VALUES (?, ?, 'Pending')";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ii", $user_id, $event_id);

    if ($insert_stmt->execute()) {
        
        // --- START NOTIFICATION LOGIC ---
        
        // A. Fetch Organizer ID and Event Name to construct the message
        $ev_query = $conn->prepare("SELECT created_by, event_name FROM events WHERE event_id = ?");
        $ev_query->bind_param("i", $event_id);
        $ev_query->execute();
        $ev_data = $ev_query->get_result()->fetch_assoc();
        
        $organizer_id = $ev_data['created_by'];
        $event_name = $ev_data['event_name'];
        $student_name = $_SESSION['name'] ?? "A student";

        // B. Prepare and Insert Notification for the Organizer
        $msg = "📩 <strong>$student_name</strong> has registered for: <strong>$event_name</strong>.";
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
        $notif_stmt->bind_param("is", $organizer_id, $msg);
        $notif_stmt->execute();
        
        // --- END NOTIFICATION LOGIC ---

        echo "<script>
                alert('Success! Registration submitted for approval.');
                window.location.href = 'dashboard.php';
              </script>";
    } else {
        echo "<script>alert('Registration failed. Please try again.'); window.location.href='../index.php';</script>";
    }
    $insert_stmt->close();

} else {
    // Redirect if accessed directly without POST
    header("location: ../index.php");
    exit;
}

$conn->close();
?>